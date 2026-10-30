import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, describe, expect, it, vi } from "vitest";
import { ReceiptForm } from "@/components/receipt-form";
import { ApiError } from "@/lib/api/errors";

const item = {
  id: 31,
  product: { id: 12, sku: "ABC-001", name: "Product one" },
  expected_quantity: 10,
  received_quantity: 2,
  remaining_quantity: 8,
};

function renderReceiptForm(onRecord, props = {}) {
  return render(
    <ReceiptForm
      documentId={7}
      item={item}
      onRecord={onRecord}
      {...props}
    />
  );
}

describe("ReceiptForm", () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it("test_us_10_fr_ui_9_double_click_sends_one_receipt_request", async () => {
    const user = userEvent.setup();
    let resolveRequest;
    const onRecord = vi.fn(
      () =>
        new Promise((resolve) => {
          resolveRequest = resolve;
        })
    );
    renderReceiptForm(onRecord);
    await user.type(screen.getByLabelText("Quantity to receive"), "3");

    const submit = screen.getByRole("button", { name: "Record receipt" });
    await user.dblClick(submit);

    await waitFor(() => expect(onRecord).toHaveBeenCalledTimes(1));
    expect(onRecord.mock.calls[0][0]).toMatchObject({
      documentId: 7,
      itemId: 31,
      quantity: 3,
    });
    expect(onRecord.mock.calls[0][0].idempotencyKey).toMatch(
      /^[0-9a-f-]{36}$/i
    );
    resolveRequest({});
    await waitFor(() =>
      expect(screen.getByLabelText("Quantity to receive")).toHaveValue(null)
    );
  });

  it("test_us_10_fr_ui_9_reuses_the_key_after_a_network_failure", async () => {
    const user = userEvent.setup();
    const onRecord = vi
      .fn()
      .mockRejectedValueOnce(new Error("Network unavailable"))
      .mockResolvedValueOnce({});
    renderReceiptForm(onRecord);
    const quantity = screen.getByLabelText("Quantity to receive");
    await user.type(quantity, "3");
    await user.click(screen.getByRole("button", { name: "Record receipt" }));
    await screen.findByText("Network unavailable");
    await user.click(screen.getByRole("button", { name: "Record receipt" }));

    await waitFor(() => expect(onRecord).toHaveBeenCalledTimes(2));
    expect(onRecord.mock.calls[1][0].idempotencyKey).toBe(
      onRecord.mock.calls[0][0].idempotencyKey
    );
  });

  it("test_us_10_fr_ui_9_reuses_the_key_after_a_5xx_response", async () => {
    const user = userEvent.setup();
    const randomUuid = vi
      .spyOn(globalThis.crypto, "randomUUID")
      .mockReturnValueOnce("server-error-attempt-key");
    const onRecord = vi
      .fn()
      .mockRejectedValueOnce(
        new ApiError(503, "INTERNAL_ERROR", "The server is unavailable.")
      )
      .mockResolvedValueOnce({});
    renderReceiptForm(onRecord);
    await user.type(screen.getByLabelText("Quantity to receive"), "3");
    await user.click(screen.getByRole("button", { name: "Record receipt" }));
    await screen.findByText("The server is unavailable.");
    await user.click(screen.getByRole("button", { name: "Record receipt" }));

    await waitFor(() => expect(onRecord).toHaveBeenCalledTimes(2));
    expect(randomUuid).toHaveBeenCalledOnce();
    expect(onRecord.mock.calls[1][0].idempotencyKey).toBe(
      onRecord.mock.calls[0][0].idempotencyKey
    );
  });

  it("test_us_10_fr_ui_9_replaces_the_key_after_a_422_response", async () => {
    const user = userEvent.setup();
    const randomUuid = vi
      .spyOn(globalThis.crypto, "randomUUID")
      .mockReturnValueOnce("first-attempt-key")
      .mockReturnValueOnce("second-attempt-key");
    const onRecord = vi
      .fn()
      .mockRejectedValueOnce(
        new ApiError(422, "VALIDATION_ERROR", "Invalid quantity.")
      )
      .mockResolvedValueOnce({});
    renderReceiptForm(onRecord);
    await user.type(screen.getByLabelText("Quantity to receive"), "3");
    await user.click(screen.getByRole("button", { name: "Record receipt" }));
    await screen.findByText("Invalid quantity.");
    await user.click(screen.getByRole("button", { name: "Record receipt" }));

    await waitFor(() => expect(onRecord).toHaveBeenCalledTimes(2));
    expect(randomUuid).toHaveBeenCalledTimes(2);
    expect(onRecord.mock.calls[0][0].idempotencyKey).toBe("first-attempt-key");
    expect(onRecord.mock.calls[1][0].idempotencyKey).toBe("second-attempt-key");
  });

  it("test_us_09_fr_ui_5_shows_remaining_quantity_and_refetches_on_over_receipt", async () => {
    const user = userEvent.setup();
    const onRefresh = vi.fn();
    const onRecord = vi.fn().mockRejectedValue(
      new ApiError(
        422,
        "QUANTITY_EXCEEDS_REMAINING",
        "Received quantity exceeds the remaining expected quantity.",
        { remaining_quantity: 4, requested_quantity: 5 }
      )
    );
    renderReceiptForm(onRecord, { onRefresh });
    await user.type(screen.getByLabelText("Quantity to receive"), "5");
    await user.click(screen.getByRole("button", { name: "Record receipt" }));

    expect(
      await screen.findByText(/Remaining quantity: 4\./)
    ).toBeInTheDocument();
    expect(onRefresh).toHaveBeenCalledOnce();
  });
});
