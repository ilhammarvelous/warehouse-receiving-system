"use client";

import { LogOut, UserRound } from "lucide-react";
import { Suspense, useState } from "react";
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/components/ui/breadcrumb";
import { Button } from "@/components/ui/button";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { ErrorAlert } from "@/components/error-alert";
import { RouteNotice } from "@/components/route-notice";
import { AppNavigation } from "@/components/app-navigation";
import {
  SidebarInset,
  SidebarProvider,
  SidebarTrigger,
} from "@/components/ui/sidebar";
import { Separator } from "@/components/ui/separator";
import { useAuth } from "@/lib/auth/AuthProvider";
import { usePathname } from "next/navigation";

const breadcrumbLabels = {
  products: "Products",
  "receiving-documents": "Receiving documents",
  new: "New receiving document",
  edit: "Edit document",
  inventory: "Inventory",
  transactions: "Transactions",
};

function currentBreadcrumb(pathname) {
  const segments = pathname.split("/").filter(Boolean);

  if (segments.length === 0) {
    return { label: "Workspace", parent: null };
  }

  const lastSegment = segments.at(-1);
  const label =
    /^\d+$/.test(lastSegment)
      ? "Document details"
      : (breadcrumbLabels[lastSegment] ?? lastSegment);

  const parentSegment = segments.at(-2);
  const parent = parentSegment
    ? {
        href: `/${segments.slice(0, -1).join("/")}`,
        label: breadcrumbLabels[parentSegment] ?? parentSegment,
      }
    : null;

  return { label, parent };
}

export function AppShell({ user, children }) {
  const { logout } = useAuth();
  const pathname = usePathname();
  const [logoutError, setLogoutError] = useState(null);
  const breadcrumb = currentBreadcrumb(pathname ?? "/");

  async function handleLogout() {
    setLogoutError(null);
    try {
      await logout();
    } catch (error) {
      setLogoutError(error);
    }
  }

  return (
    <SidebarProvider>
      <AppNavigation role={user.role} />
      <SidebarInset>
        <header className="flex min-h-14 items-center gap-3 border-b px-4">
          <SidebarTrigger aria-label="Toggle navigation" />
          <Separator orientation="vertical" className="h-5" />
          <Breadcrumb>
            <BreadcrumbList>
              {breadcrumb.parent && (
                <>
                  <BreadcrumbItem>
                    <BreadcrumbLink href={breadcrumb.parent.href}>
                      {breadcrumb.parent.label}
                    </BreadcrumbLink>
                  </BreadcrumbItem>
                  <BreadcrumbSeparator />
                </>
              )}
              <BreadcrumbItem>
                <BreadcrumbPage>{breadcrumb.label}</BreadcrumbPage>
              </BreadcrumbItem>
            </BreadcrumbList>
          </Breadcrumb>
          <div className="ml-auto">
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="outline">
                  <UserRound aria-hidden="true" />
                  <span>{user.name}</span>
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end">
                <DropdownMenuItem disabled>
                  {user.role === "SUPERVISOR" ? "Supervisor" : "Operator"}
                </DropdownMenuItem>
                <DropdownMenuItem onSelect={() => void handleLogout()}>
                  <LogOut aria-hidden="true" />
                  Log out
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </header>
        <main className="flex-1 space-y-4 p-4 md:p-6">
          {logoutError && <ErrorAlert error={logoutError} />}
          <Suspense fallback={null}>
            <RouteNotice />
          </Suspense>
          {children}
        </main>
      </SidebarInset>
    </SidebarProvider>
  );
}
