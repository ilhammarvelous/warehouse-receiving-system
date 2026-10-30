"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { AppShell } from "@/components/app-shell";
import { ErrorAlert } from "@/components/error-alert";
import { LoadingState } from "@/components/feedback-states";
import { Button } from "@/components/ui/button";
import { useAuth } from "@/lib/auth/AuthProvider";

export default function ProtectedLayout({ children }) {
  const { status, user, error, refreshUser } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (status === "unauthenticated") {
      router.replace("/login");
    }
  }, [router, status]);

  if (status === "loading") {
    return <LoadingState label="Checking your session..." />;
  }

  if (status === "error") {
    return (
      <main className="mx-auto flex min-h-screen w-full max-w-xl flex-col justify-center gap-4 p-6">
        <ErrorAlert title="Unable to verify your session" error={error} />
        <Button onClick={() => void refreshUser()}>Try again</Button>
      </main>
    );
  }

  if (status !== "authenticated" || !user) {
    return null;
  }

  return <AppShell user={user}>{children}</AppShell>;
}
