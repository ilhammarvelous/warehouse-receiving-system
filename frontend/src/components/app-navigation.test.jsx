import { render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { AppNavigation } from "@/components/app-navigation";
import { SidebarProvider } from "@/components/ui/sidebar";

vi.mock("next/navigation", () => ({
  usePathname: () => "/products",
}));

describe("AppNavigation", () => {
  beforeEach(() => {
    window.matchMedia = vi.fn().mockImplementation((query) => ({
      matches: false,
      media: query,
      onchange: null,
      addListener: vi.fn(),
      removeListener: vi.fn(),
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
      dispatchEvent: vi.fn(),
    }));
  });

  function renderNavigation(role) {
    return render(
      <SidebarProvider>
        <AppNavigation role={role} />
      </SidebarProvider>
    );
  }

  it("shows common navigation and hides Supervisor actions from Operators", () => {
    renderNavigation("OPERATOR");

    expect(screen.getByRole("link", { name: "Products" })).toBeInTheDocument();
    expect(
      screen.getByRole("link", { name: "Receiving documents" })
    ).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Inventory" })).toBeInTheDocument();
    expect(
      screen.getByRole("link", { name: "Transactions" })
    ).toBeInTheDocument();
    expect(
      screen.queryByRole("link", { name: "New receiving document" })
    ).not.toBeInTheDocument();
  });

  it("shows Supervisor document creation navigation only to Supervisors", () => {
    renderNavigation("SUPERVISOR");

    expect(
      screen.getByRole("link", { name: "New receiving document" })
    ).toHaveAttribute("href", "/receiving-documents/new");
  });
});
