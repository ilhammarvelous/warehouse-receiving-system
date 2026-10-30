import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { ErrorAlert } from "@/components/error-alert";
import { ApiError } from "@/lib/api/errors";

describe("ErrorAlert", () => {
  it("shows unknown API messages and field errors without requiring a known code", () => {
    const error = new ApiError(422, "FUTURE_ERROR_CODE", "The request was rejected.", {
      fields: { supplier_name: ["The supplier name is required."] },
    });

    render(<ErrorAlert error={error} />);

    expect(screen.getByRole("alert")).toHaveTextContent(
      "The request was rejected."
    );
    expect(screen.getByRole("alert")).toHaveTextContent("FUTURE_ERROR_CODE");
    expect(screen.getByRole("alert")).toHaveTextContent(
      "supplier_name: The supplier name is required."
    );
  });
});
