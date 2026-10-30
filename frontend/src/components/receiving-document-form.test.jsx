import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { ReceivingDocumentForm } from "@/components/receiving-document-form";
import { ApiError } from "@/lib/api/errors";

vi.mock("@/components/product-picker", () => ({
  ProductPicker({ id, label, value, onChange, error }) {
    return (
      <div>
        <label htmlFor={id}>{label}</label>
        <select
          id={id}
          value={value ?? ""}
          onChange={(event) => onChange(event.target.value)}
        >
          <option value="">Select a product</option>
          <option value="1">ABC-001 — Product one</option>
          <option value="2">ABC-002 — Product two</option>
        </select>
        {error?.message && <p role="alert">{error.message}</p>}
      </div>
    );
  },
}));

describe("ReceivingDocumentForm", () => {
  let user;

  beforeEach(() => {
    user = userEvent.setup();
  });

  async function fillDocumentForm() {
    await user.type(screen.getByLabelText("Supplier name"), "Example supplier");
    fireEvent.change(screen.getByLabelText("Expected arrival date"), {
      target: { value: "2026-11-02" },
    });
    await user.selectOptions(screen.getAllByLabelText("Product")[0], "1");
  }

  it("test_us_04_fr_ui_4_shows_duplicate_product_hint", async () => {
    const onSubmit = vi.fn();
    render(<ReceivingDocumentForm onSubmit={onSubmit} />);
    await fillDocumentForm();
    await user.click(screen.getByRole("button", { name: "Add item" }));
    await user.selectOptions(screen.getAllByLabelText("Product")[1], "1");
    await user.click(screen.getByRole("button", { name: "Save draft" }));

    expect(
      await screen.findByText(
        "A product can appear only once in a receiving document."
      )
    ).toBeInTheDocument();
    expect(onSubmit).not.toHaveBeenCalled();
  });

  it("test_us_04_fr_ui_8_displays_backend_field_errors", async () => {
    const onSubmit = vi.fn().mockRejectedValue(
      new ApiError(422, "VALIDATION_ERROR", "The given data was invalid.", {
        fields: { supplier_name: ["The supplier could not be found."] },
      })
    );
    render(<ReceivingDocumentForm onSubmit={onSubmit} />);
    await fillDocumentForm();
    await user.click(screen.getByRole("button", { name: "Save draft" }));

    expect(
      await screen.findByText("The supplier could not be found.")
    ).toBeInTheDocument();
    expect(screen.getByText("The given data was invalid.")).toBeInTheDocument();
  });
});
