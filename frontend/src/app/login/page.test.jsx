import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import LoginPage from "@/app/login/page";
import { AuthProvider } from "@/lib/auth/AuthProvider";

const { replace } = vi.hoisted(() => ({ replace: vi.fn() }));

vi.mock("next/navigation", () => ({
  useRouter: () => ({ replace }),
}));

describe("LoginPage", () => {
  beforeEach(() => {
    localStorage.clear();
    replace.mockReset();
  });

  it.each(["OPERATOR", "SUPERVISOR"])(
    "signs in a %s and navigates to receiving documents",
    async (role) => {
      vi.stubGlobal(
        "fetch",
        vi.fn().mockResolvedValue({
          ok: true,
          status: 200,
          json: async () => ({
            data: {
              token: "session-token",
              token_type: "Bearer",
              user: {
                id: 1,
                name: `Warehouse ${role}`,
                email: `${role.toLowerCase()}@example.com`,
                role,
              },
            },
          }),
        })
      );

      const user = userEvent.setup();
      render(
        <AuthProvider>
          <LoginPage />
        </AuthProvider>
      );

      await user.type(
        await screen.findByLabelText("Email"),
        `${role.toLowerCase()}@example.com`
      );
      await user.type(screen.getByLabelText("Password"), "password");
      await user.click(screen.getByRole("button", { name: "Sign in" }));

      await waitFor(() => {
        expect(replace).toHaveBeenCalledWith("/receiving-documents");
      });
      expect(localStorage.getItem("warehouse-receiving.access-token")).toBe(
        "session-token"
      );
    }
  );

  it("shows field errors returned by the API", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn().mockResolvedValue({
        ok: false,
        status: 422,
        json: async () => ({
          error: {
            code: "VALIDATION_ERROR",
            message: "The given data was invalid.",
            details: {
              fields: { email: ["The email field is required."] },
            },
          },
        }),
      })
    );

    const user = userEvent.setup();
    render(
      <AuthProvider>
        <LoginPage />
      </AuthProvider>
    );

    await user.type(await screen.findByLabelText("Email"), "user@example.com");
    await user.type(screen.getByLabelText("Password"), "password");
    await user.click(screen.getByRole("button", { name: "Sign in" }));
    expect(
      await screen.findByText("The email field is required.")
    ).toBeInTheDocument();
  });
});
