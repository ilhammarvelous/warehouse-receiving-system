"use client";

import { usePathname, useSearchParams } from "next/navigation";
import { ErrorAlert } from "@/components/error-alert";

export function RouteNotice() {
  const pathname = usePathname();
  const searchParams = useSearchParams();

  if (
    pathname !== "/receiving-documents" ||
    searchParams.get("notice") !== "supervisor-required"
  ) {
    return null;
  }

  return (
    <ErrorAlert
      title="Access restricted"
      message="Only Supervisors can create or change receiving documents."
    />
  );
}
