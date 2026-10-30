import { render, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { AuthProvider } from "@/lib/auth/AuthProvider";
import ProtectedLayout from "@/app/(protected)/layout";

const { replace } = vi.hoisted(() => ({ replace: vi.fn() }));

vi.mock("next/navigation", () => ({
  useRouter: () => ({ replace }),
  usePathname: () => "/products",
}));

describe("ProtectedLayout", () => {
  beforeEach(() => {
    localStorage.clear();
    localStorage.setItem("warehouse-receiving.access-token", "expired-token");
    replace.mockReset();
    vi.stubGlobal(
      "fetch",
      vi.fn().mockResolvedValue({
        ok: false,
        status: 401,
        json: async () => ({
          error: {
            code: "UNAUTHENTICATED",
            message: "Unauthenticated.",
          },
        }),
      })
    );
  });

  it("redirects to login when the API rejects the stored token", async () => {
    render(
      <AuthProvider>
        <ProtectedLayout>
          <p>Protected content</p>
        </ProtectedLayout>
      </AuthProvider>
    );

    await waitFor(() => {
      expect(replace).toHaveBeenCalledWith("/login");
    });
    expect(localStorage.getItem("warehouse-receiving.access-token")).toBeNull();
  });
});
