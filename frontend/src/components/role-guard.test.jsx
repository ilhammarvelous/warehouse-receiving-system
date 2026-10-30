import { render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { RoleGuard } from "@/components/role-guard";

const { replace, useAuth } = vi.hoisted(() => ({
  replace: vi.fn(),
  useAuth: vi.fn(),
}));

vi.mock("next/navigation", () => ({
  useRouter: () => ({ replace }),
}));

vi.mock("@/lib/auth/AuthProvider", () => ({ useAuth }));

describe("RoleGuard", () => {
  beforeEach(() => {
    replace.mockReset();
    useAuth.mockReset();
  });

  it("allows the required role to view a protected route", () => {
    useAuth.mockReturnValue({
      status: "authenticated",
      user: { role: "SUPERVISOR" },
    });

    render(
      <RoleGuard allowedRoles={["SUPERVISOR"]}>
        <p>Supervisor-only content</p>
      </RoleGuard>
    );

    expect(screen.getByText("Supervisor-only content")).toBeInTheDocument();
    expect(replace).not.toHaveBeenCalled();
  });

  it("redirects an authenticated Operator away from a Supervisor route", async () => {
    useAuth.mockReturnValue({
      status: "authenticated",
      user: { role: "OPERATOR" },
    });

    render(
      <RoleGuard allowedRoles={["SUPERVISOR"]}>
        <p>Supervisor-only content</p>
      </RoleGuard>
    );

    expect(
      screen.queryByText("Supervisor-only content")
    ).not.toBeInTheDocument();
    await waitFor(() => {
      expect(replace).toHaveBeenCalledWith(
        "/receiving-documents?notice=supervisor-required"
      );
    });
  });
});
