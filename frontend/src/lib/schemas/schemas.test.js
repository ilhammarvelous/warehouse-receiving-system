import { describe, expect, it } from "vitest";
import { loginSchema } from "@/lib/schemas/login";
import { productSchema } from "@/lib/schemas/product";
import { receivingDocumentSchema } from "@/lib/schemas/receiving-document";
import { createReceiptSchema } from "@/lib/schemas/receipt";

describe("Zod form schemas", () => {
  it("test_us_01_fr_auth_login_accepts_credentials_and_rejects_invalid_email_or_password", () => {
    expect(
      loginSchema.safeParse({
        email: "operator@example.com",
        password: "password",
      }).success
    ).toBe(true);
    expect(
      loginSchema.safeParse({ email: "not-an-email", password: "password" })
        .success
    ).toBe(false);
    expect(
      loginSchema.safeParse({ email: "operator@example.com", password: "" })
        .success
    ).toBe(false);
  });

  it("test_us_02_fr_prod_normalizes_sku_and_enforces_product_fields", () => {
    expect(
      productSchema.parse({ sku: " abc-001 ", name: "  Product name  " })
    ).toEqual({ sku: "ABC-001", name: "Product name" });
    expect(productSchema.safeParse({ sku: "AB--01", name: "Product" }).success)
      .toBe(true);
    expect(productSchema.safeParse({ sku: "-invalid", name: "Product" }).success)
      .toBe(false);
    expect(
      productSchema.safeParse({ sku: "VALID-1", name: " " }).success
    ).toBe(false);
    expect(
      productSchema.safeParse({
        sku: "A".repeat(51),
        name: "Product",
      }).success
    ).toBe(false);
  });

  it("test_us_04_fr_doc_accepts_valid_documents_and_rejects_invalid_items", () => {
    const validDocument = {
      supplier_name: " Example supplier ",
      expected_date: "2026-10-30",
      items: [{ product_id: 1, expected_quantity: 5 }],
    };

    expect(receivingDocumentSchema.safeParse(validDocument).success).toBe(true);
    expect(
      receivingDocumentSchema.safeParse({
        ...validDocument,
        supplier_name: " ",
      }).success
    ).toBe(false);
    expect(
      receivingDocumentSchema.safeParse({
        ...validDocument,
        expected_date: "2025-02-29",
      }).success
    ).toBe(false);
    expect(
      receivingDocumentSchema.safeParse({
        ...validDocument,
        items: [],
      }).success
    ).toBe(false);
    expect(
      receivingDocumentSchema.safeParse({
        ...validDocument,
        items: [
          { product_id: 1, expected_quantity: 5 },
          { product_id: 1, expected_quantity: 2 },
        ],
      }).success
    ).toBe(false);
    expect(
      receivingDocumentSchema.safeParse({
        ...validDocument,
        items: [{ product_id: 1, expected_quantity: 1.5 }],
      }).success
    ).toBe(false);
    expect(
      receivingDocumentSchema.safeParse({
        ...validDocument,
        items: Array.from({ length: 101 }, (_, index) => ({
          product_id: index + 1,
          expected_quantity: 1,
        })),
      }).success
    ).toBe(false);
  });

  it("test_us_09_fr_rcv_limits_receipt_quantity_to_the_api_remaining_quantity", () => {
    const receiptSchema = createReceiptSchema(4);

    expect(receiptSchema.safeParse({ quantity: 4 }).success).toBe(true);
    expect(receiptSchema.safeParse({ quantity: 5 }).success).toBe(false);
    expect(receiptSchema.safeParse({ quantity: 0 }).success).toBe(false);
    expect(receiptSchema.safeParse({ quantity: 1.5 }).success).toBe(false);
  });
});
