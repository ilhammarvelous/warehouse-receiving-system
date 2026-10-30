"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { LoadingState } from "@/components/feedback-states";
import { useAuth } from "@/lib/auth/AuthProvider";

export function RoleGuard({ allowedRoles, children }) {
  const { status, user } = useAuth();
  const router = useRouter();
  const isAllowed =
    status === "authenticated" && allowedRoles.includes(user?.role);

  useEffect(() => {
    if (status === "authenticated" && !isAllowed) {
      router.replace(
        "/receiving-documents?notice=supervisor-required"
      );
    }
  }, [isAllowed, router, status]);

  if (!isAllowed) {
    return <LoadingState label="Checking your permissions..." />;
  }

  return children;
}
