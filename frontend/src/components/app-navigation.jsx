"use client";

import {
  Boxes,
  ClipboardList,
  FilePlus2,
  Warehouse,
} from "lucide-react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from "@/components/ui/sidebar";

const commonLinks = [
  { href: "/products", label: "Products", icon: Boxes },
  {
    href: "/receiving-documents",
    label: "Receiving documents",
    icon: ClipboardList,
  },
  { href: "/inventory", label: "Inventory", icon: Warehouse },
  {
    href: "/inventory/transactions",
    label: "Transactions",
    icon: ClipboardList,
  },
];

const supervisorLinks = [
  {
    href: "/receiving-documents/new",
    label: "New receiving document",
    icon: FilePlus2,
  },
];

export function getNavigationLinks(role) {
  return role === "SUPERVISOR"
    ? [...commonLinks, ...supervisorLinks]
    : commonLinks;
}

export function AppNavigation({ role }) {
  const pathname = usePathname();
  const links = getNavigationLinks(role);

  return (
    <Sidebar collapsible="icon" variant="inset">
      <SidebarHeader>
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton asChild size="lg">
              <Link href="/receiving-documents">
                <Warehouse aria-hidden="true" />
                <span>Warehouse receiving</span>
              </Link>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarHeader>
      <SidebarContent>
        <SidebarGroup>
          <SidebarGroupLabel>Workspace</SidebarGroupLabel>
          <SidebarGroupContent>
            <SidebarMenu>
              {links.map(({ href, label, icon: Icon }) => {
                const isActive =
                  pathname === href || pathname?.startsWith(`${href}/`);

                return (
                  <SidebarMenuItem key={label}>
                    <SidebarMenuButton asChild isActive={isActive}>
                      <Link href={href}>
                        <Icon aria-hidden="true" />
                        <span>{label}</span>
                      </Link>
                    </SidebarMenuButton>
                  </SidebarMenuItem>
                );
              })}
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>
      </SidebarContent>
      <SidebarFooter />
    </Sidebar>
  );
}
