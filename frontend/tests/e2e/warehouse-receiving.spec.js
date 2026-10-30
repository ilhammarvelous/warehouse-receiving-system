import { randomUUID } from "node:crypto";
import { expect, test } from "@playwright/test";

const apiBaseUrl =
  process.env.E2E_API_URL ?? "http://localhost:8000/api/v1";
const supervisor = {
  email:
    process.env.E2E_SUPERVISOR_EMAIL ??
    process.env.SEED_SUPERVISOR_EMAIL ??
    "supervisor@example.com",
  password:
    process.env.E2E_SUPERVISOR_PASSWORD ??
    process.env.SEED_SUPERVISOR_PASSWORD ??
    "change-me-supervisor",
};
const operator = {
  email:
    process.env.E2E_OPERATOR_EMAIL ??
    process.env.SEED_OPERATOR_EMAIL ??
    "operator@example.com",
  password:
    process.env.E2E_OPERATOR_PASSWORD ??
    process.env.SEED_OPERATOR_PASSWORD ??
    "change-me-operator",
};
const runId = `${Date.now()}-${Math.floor(Math.random() * 1_000_000)}`;
const products = {
  first: {
    sku: `E2E-A-${runId}`,
    name: `E2E first product ${runId}`,
  },
  second: {
    sku: `E2E-B-${runId}`,
    name: `E2E second product ${runId}`,
  },
  doubleClick: {
    sku: `E2E-C-${runId}`,
    name: `E2E double-click product ${runId}`,
  },
};
const supplierName = `E2E supplier ${runId}`;
const doubleClickSupplierName = `E2E double-click supplier ${runId}`;

let documentId;
let documentNumber;
let firstItemId;
let secondItemId;
let doubleClickDocumentId;
let doubleClickItemId;

async function loginAs(page, user) {
  await page.goto("/login");
  await page.evaluate(() =>
    localStorage.removeItem("warehouse-receiving.access-token")
  );
  await page.goto("/login");
  await page.getByLabel("Email").fill(user.email);
  await page.getByLabel("Password").fill(user.password);
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/receiving-documents$/);
}

async function createProduct(page, product) {
  await page.goto("/products");
  await page.getByLabel("SKU").fill(product.sku);
  await page.getByLabel("Product name").fill(product.name);
  await page.getByRole("button", { name: "Create product" }).click();
  await expect(page.getByLabel("SKU")).toHaveValue("");
  await page.getByLabel("Search products").fill(product.sku);
  await page.getByRole("button", { name: "Search", exact: true }).click();
  await expect(
    page.getByRole("row").filter({ hasText: product.sku })
  ).toBeVisible();
}

async function selectProduct(page, itemIndex, sku) {
  await page.getByLabel("Find product").nth(itemIndex).fill(sku);
  await page.getByRole("button", { name: "Search products" }).nth(itemIndex).click();
  await page.getByRole("combobox").nth(itemIndex).click();
  await page.getByRole("option", { name: new RegExp(sku) }).click();
}

function nextExpectedDate() {
  const date = new Date();
  date.setUTCDate(date.getUTCDate() + 1);
  return date.toISOString().slice(0, 10);
}

async function createReceivingDocument(page, supplier, documentItems) {
  await page.goto("/receiving-documents/new");
  await page.getByLabel("Supplier name").fill(supplier);
  await page.getByLabel("Expected arrival date").fill(nextExpectedDate());

  for (let index = 0; index < documentItems.length; index += 1) {
    if (index > 0) {
      await page.getByRole("button", { name: "Add item" }).click();
    }

    await selectProduct(page, index, documentItems[index].product.sku);
    await page.getByLabel("Expected quantity").nth(index).fill(
      String(documentItems[index].quantity)
    );
  }

  const responsePromise = page.waitForResponse(
    (response) =>
      response.request().method() === "POST" &&
      response.url() === `${apiBaseUrl}/receiving-documents`
  );
  await page.getByRole("button", { name: "Save draft" }).click();
  const response = await responsePromise;
  expect(response.status()).toBe(201);
  const createdDocument = (await response.json()).data;
  await expect(page).toHaveURL(
    new RegExp(`/receiving-documents/${createdDocument.id}$`)
  );
  return createdDocument;
}

async function confirmDocument(page) {
  await page.getByRole("button", { name: "Confirm document" }).click();
  const dialog = page.getByRole("alertdialog");
  await dialog.getByRole("button", { name: "Confirm document" }).click();
  await expect(page.getByText("OPEN", { exact: true })).toBeVisible();
}

async function recordReceipt(page, sku, quantity, { doubleClick = false } = {}) {
  const row = page.getByRole("row").filter({ hasText: sku });
  await row.getByLabel("Quantity to receive").fill(String(quantity));
  const button = row.getByRole("button", { name: "Record receipt" });

  if (doubleClick) {
    await button.dblclick();
  } else {
    await button.click();
  }
}

async function bearerHeaders(page) {
  const token = await page.evaluate(() =>
    localStorage.getItem("warehouse-receiving.access-token")
  );
  expect(token).toBeTruthy();
  return { Authorization: `Bearer ${token}` };
}

test.describe.serial("Warehouse receiving end-to-end (US-02 to US-15)", () => {
  test("test_us_02_us_04_us_08_supervisor_creates_products_document_and_confirms", async ({
    page,
  }) => {
    await loginAs(page, supervisor);
    await createProduct(page, products.first);
    await createProduct(page, products.second);

    const createdDocument = await createReceivingDocument(page, supplierName, [
      { product: products.first, quantity: 5 },
      { product: products.second, quantity: 3 },
    ]);
    documentId = createdDocument.id;
    documentNumber = createdDocument.document_number;
    firstItemId = createdDocument.items[0].id;
    secondItemId = createdDocument.items[1].id;

    await expect(page.getByText("DRAFT", { exact: true })).toBeVisible();
    await confirmDocument(page);
  });

  test("test_us_09_operator_records_partial_receipt_and_sees_updated_document", async ({
    page,
  }) => {
    await loginAs(page, operator);
    await page.goto(`/receiving-documents/${documentId}`);
    await recordReceipt(page, products.first.sku, 2);

    const row = page.getByRole("row").filter({ hasText: products.first.sku });
    await expect(row.getByRole("cell").nth(2)).toHaveText("2");
    await expect(row.getByRole("cell").nth(3)).toHaveText("3");
    await expect(
      page.getByText("PARTIALLY_RECEIVED", { exact: true })
    ).toBeVisible();
  });

  test("test_br07_us_09_over_receipt_shows_error_without_changing_quantity", async ({
    page,
  }) => {
    await loginAs(page, operator);
    await page.goto(`/receiving-documents/${documentId}`);
    const row = page.getByRole("row").filter({ hasText: products.first.sku });
    await recordReceipt(page, products.first.sku, 4);
    await expect(row.getByRole("alert")).toContainText("3");

    const response = await page.request.post(
      `${apiBaseUrl}/receiving-documents/${documentId}/items/${firstItemId}/receipts`,
      {
        headers: {
          ...(await bearerHeaders(page)),
          "Idempotency-Key": randomUUID(),
        },
        data: { quantity: 4 },
      }
    );
    expect(response.status()).toBe(422);
    const error = (await response.json()).error;
    expect(error.code).toBe("QUANTITY_EXCEEDS_REMAINING");
    expect(error.details.remaining_quantity).toBe(3);

    await page.reload();
    const refreshedRow = page
      .getByRole("row")
      .filter({ hasText: products.first.sku });
    await expect(refreshedRow.getByRole("cell").nth(2)).toHaveText("2");
    await expect(refreshedRow.getByRole("cell").nth(3)).toHaveText("3");

    await page.goto("/inventory");
    await page.getByLabel("Search products").fill(products.first.sku);
    await page.getByRole("button", { name: "Search", exact: true }).click();
    const inventoryRow = page
      .getByRole("row")
      .filter({ hasText: products.first.sku });
    await expect(inventoryRow.getByRole("cell").nth(2)).toHaveText("2");
  });

  test("test_br08_us_12_completion_is_disabled_until_all_items_are_received_and_api_rejects_forced_attempt", async ({
    page,
  }) => {
    await loginAs(page, supervisor);
    await page.goto(`/receiving-documents/${documentId}`);
    await expect(
      page.getByRole("button", { name: "Complete document" })
    ).toBeDisabled();

    const response = await page.request.post(
      `${apiBaseUrl}/receiving-documents/${documentId}/complete`,
      { headers: await bearerHeaders(page) }
    );
    expect(response.status()).toBe(409);
    expect((await response.json()).error.code).toBe(
      "DOCUMENT_NOT_FULLY_RECEIVED"
    );
  });

  test("test_us_09_us_12_operator_finishes_receipts_then_supervisor_completes", async ({
    page,
  }) => {
    await loginAs(page, operator);
    await page.goto(`/receiving-documents/${documentId}`);
    await recordReceipt(page, products.first.sku, 3);
    await recordReceipt(page, products.second.sku, 3);
    await expect(page.getByText("Ready to complete")).toHaveCount(0);

    await loginAs(page, supervisor);
    await page.goto(`/receiving-documents/${documentId}`);
    await expect(page.getByText("Ready to complete")).toBeVisible();
    await page.getByRole("button", { name: "Complete document" }).click();
    const dialog = page.getByRole("alertdialog");
    await dialog.getByRole("button", { name: "Complete document" }).click();
    await expect(page.getByText("COMPLETED", { exact: true })).toBeVisible();
  });

  test("test_us_14_us_15_inventory_and_transactions_match_successful_receipts", async ({
    page,
  }) => {
    await loginAs(page, operator);
    await page.goto("/inventory");
    await page.getByLabel("Search products").fill(products.first.sku);
    await page.getByRole("button", { name: "Search", exact: true }).click();
    const firstInventoryRow = page
      .getByRole("row")
      .filter({ hasText: products.first.sku });
    await expect(firstInventoryRow.getByRole("cell").nth(2)).toHaveText("5");

    await page.getByLabel("Search products").fill(products.second.sku);
    await page.getByRole("button", { name: "Search", exact: true }).click();
    const secondInventoryRow = page
      .getByRole("row")
      .filter({ hasText: products.second.sku });
    await expect(secondInventoryRow.getByRole("cell").nth(2)).toHaveText("3");

    await page.goto("/inventory/transactions");
    await page.getByLabel("Find product").fill(products.first.sku);
    await page.getByRole("button", { name: "Search products" }).click();
    await page.getByRole("combobox").nth(0).click();
    await page.getByRole("option", { name: new RegExp(products.first.sku) }).click();
    await page.getByLabel("Find receiving document").fill(documentNumber);
    await page.getByRole("button", { name: "Search receiving documents" }).click();
    await page.getByRole("combobox").nth(1).click();
    await page.getByRole("option", { name: new RegExp(documentNumber) }).click();
    await page.getByRole("button", { name: "Apply filters" }).click();

    const firstTransactions = page
      .getByRole("row")
      .filter({ hasText: products.first.sku });
    await expect(firstTransactions).toHaveCount(2);
    await expect(firstTransactions.nth(0).getByRole("cell").nth(3)).toHaveText(
      "5"
    );
    await expect(firstTransactions.nth(1).getByRole("cell").nth(3)).toHaveText(
      "2"
    );
    await expect(
      page.getByRole("row").filter({ hasText: products.second.sku })
    ).toHaveCount(0);
  });

  test("test_fr_auth_5_operator_has_no_supervisor_controls_and_api_returns_403", async ({
    page,
  }) => {
    await loginAs(page, operator);
    await page.goto("/products");
    await expect(page.getByRole("heading", { name: "New product" })).toHaveCount(
      0
    );
    await page.goto(`/receiving-documents/${documentId}`);
    await expect(
      page.getByRole("button", { name: "Complete document" })
    ).toHaveCount(0);
    await expect(
      page.getByRole("button", { name: "Confirm document" })
    ).toHaveCount(0);

    const response = await page.request.post(`${apiBaseUrl}/products`, {
      headers: await bearerHeaders(page),
      data: {},
    });
    expect(response.status()).toBe(403);
    expect((await response.json()).error.code).toBe("FORBIDDEN");
  });

  test("test_us_10_fr_ui_9_double_click_records_exactly_one_receipt", async ({
    page,
  }) => {
    await loginAs(page, supervisor);
    await createProduct(page, products.doubleClick);
    const createdDocument = await createReceivingDocument(
      page,
      doubleClickSupplierName,
      [{ product: products.doubleClick, quantity: 2 }]
    );
    doubleClickDocumentId = createdDocument.id;
    doubleClickItemId = createdDocument.items[0].id;
    await confirmDocument(page);

    await loginAs(page, operator);
    await page.goto(`/receiving-documents/${doubleClickDocumentId}`);
    let receiptRequestCount = 0;
    page.on("request", (request) => {
      if (
        request.method() === "POST" &&
        request.url() ===
          `${apiBaseUrl}/receiving-documents/${doubleClickDocumentId}/items/${doubleClickItemId}/receipts`
      ) {
        receiptRequestCount += 1;
      }
    });
    await recordReceipt(page, products.doubleClick.sku, 2, {
      doubleClick: true,
    });
    await expect(
      page.getByRole("row").filter({ hasText: products.doubleClick.sku })
    ).toHaveCount(2);
    await expect.poll(() => receiptRequestCount).toBe(1);
    await page.reload();
    await expect(
      page.locator("table").nth(1).getByRole("row")
    ).toHaveCount(2);
    await expect(page.getByText("COMPLETED", { exact: true })).toHaveCount(0);

    await page.goto("/inventory");
    await page.getByLabel("Search products").fill(products.doubleClick.sku);
    await page.getByRole("button", { name: "Search", exact: true }).click();
    const inventoryRow = page
      .getByRole("row")
      .filter({ hasText: products.doubleClick.sku });
    await expect(inventoryRow.getByRole("cell").nth(2)).toHaveText("2");
  });
});
