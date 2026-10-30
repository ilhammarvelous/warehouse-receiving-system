import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import { DocumentActionToolbar } from "@/components/document-action-toolbar";

const document = (status, allItemsReceived = false) => ({
  id: 7,
  status,
  all_items_received: allItemsReceived,
});

describe("DocumentActionToolbar", () => {
  it("test_fr_ui_6_shows_draft_actions_only_to_supervisors", () => {
    const onAction = vi.fn();
    const { rerender } = render(
      <DocumentActionToolbar
        document={document("DRAFT")}
        role="OPERATOR"
        onAction={onAction}
      />
    );
    expect(screen.queryByRole("button", { name: "Confirm document" }))
      .not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Cancel document" }))
      .not.toBeInTheDocument();

    rerender(
      <DocumentActionToolbar
        document={document("DRAFT")}
        role="SUPERVISOR"
        onAction={onAction}
      />
    );
    expect(screen.getByRole("link", { name: "Edit draft" })).toHaveAttribute(
      "href",
      "/receiving-documents/7/edit"
    );
    expect(screen.getByRole("button", { name: "Confirm document" }))
      .toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Cancel document" }))
      .toBeInTheDocument();
  });

  it("test_us_08_fr_ui_6_requires_confirmation_before_confirming_a_document", async () => {
    const user = userEvent.setup();
    const onAction = vi.fn();
    render(
      <DocumentActionToolbar
        document={document("DRAFT")}
        role="SUPERVISOR"
        onAction={onAction}
      />
    );

    await user.click(screen.getByRole("button", { name: "Confirm document" }));
    expect(onAction).not.toHaveBeenCalled();
    await user.click(
      screen.getAllByRole("button", { name: "Confirm document" }).at(-1)
    );
    expect(onAction).toHaveBeenCalledWith("confirm");
  });

  it("test_fr_ui_6_shows_cancel_only_for_supervisor_on_open_documents", () => {
    render(
      <DocumentActionToolbar
        document={document("OPEN")}
        role="SUPERVISOR"
        onAction={vi.fn()}
      />
    );
    expect(screen.getByRole("button", { name: "Cancel document" }))
      .toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Confirm document" }))
      .not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Complete document" }))
      .not.toBeInTheDocument();
  });

  it("test_us_12_fr_ui_6_enables_completion_only_when_api_reports_all_items_received", () => {
    const { rerender } = render(
      <DocumentActionToolbar
        document={document("PARTIALLY_RECEIVED")}
        role="SUPERVISOR"
        onAction={vi.fn()}
      />
    );
    expect(
      screen.getByRole("button", { name: "Complete document" })
    ).toBeDisabled();
    expect(screen.queryByRole("button", { name: "Cancel document" }))
      .not.toBeInTheDocument();
    expect(screen.queryByText("Ready to complete")).not.toBeInTheDocument();

    rerender(
      <DocumentActionToolbar
        document={document("PARTIALLY_RECEIVED", true)}
        role="SUPERVISOR"
        onAction={vi.fn()}
      />
    );
    expect(
      screen.getByRole("button", { name: "Complete document" })
    ).toBeEnabled();
    expect(screen.getByText("Ready to complete")).toBeInTheDocument();
  });

  it.each(["COMPLETED", "CANCELLED"])(
    "test_fr_ui_6_hides_actions_for_final_status_%s",
    (status) => {
      const { container } = render(
        <DocumentActionToolbar
          document={document(status)}
          role="SUPERVISOR"
          onAction={vi.fn()}
        />
      );
      expect(container).toBeEmptyDOMElement();
    }
  );
});
