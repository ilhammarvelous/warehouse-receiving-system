"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useEffect, useState } from "react";
import { useForm } from "react-hook-form";
import { useRouter } from "next/navigation";
import { ErrorAlert } from "@/components/error-alert";
import { LoadingState } from "@/components/feedback-states";
import { ApiError } from "@/lib/api/errors";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import {
  Field,
  FieldError,
  FieldGroup,
  FieldLabel,
} from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { useAuth } from "@/lib/auth/AuthProvider";
import { loginSchema } from "@/lib/schemas/login";

export default function LoginPage() {
  const { login, status, user, error: sessionError } = useAuth();
  const router = useRouter();
  const [submissionError, setSubmissionError] = useState(null);
  const {
    formState: { errors, isSubmitting },
    handleSubmit,
    register,
    setError,
  } = useForm({
    resolver: zodResolver(loginSchema),
    defaultValues: { email: "", password: "" },
  });

  useEffect(() => {
    if (status === "authenticated" && user) {
      router.replace("/receiving-documents");
    }
  }, [router, status, user]);

  async function onSubmit(credentials) {
    setSubmissionError(null);
    try {
      await login(credentials);
    } catch (error) {
      if (error instanceof ApiError && error.details?.fields) {
        for (const field of ["email", "password"]) {
          const messages = error.details.fields[field];
          if (Array.isArray(messages) && typeof messages[0] === "string") {
            setError(field, { type: "server", message: messages[0] });
          }
        }

        setSubmissionError(
          new ApiError(error.status, error.code, error.message)
        );
        return;
      }

      setSubmissionError(error);
    }
  }

  if (status === "loading") {
    return <LoadingState label="Checking your session..." />;
  }

  if (status === "authenticated") {
    return null;
  }

  return (
    <main className="flex min-h-screen items-center justify-center bg-muted/40 p-4">
      <Card className="w-full max-w-md">
        <CardHeader>
          <CardTitle>Sign in</CardTitle>
          <CardDescription>
            Sign in to manage warehouse receiving.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <form
            noValidate
            className="space-y-4"
            onSubmit={handleSubmit(onSubmit)}
          >
            {(submissionError || sessionError) && (
              <ErrorAlert error={submissionError ?? sessionError} />
            )}
            <FieldGroup>
              <Field data-invalid={Boolean(errors.email)}>
                <FieldLabel htmlFor="email">Email</FieldLabel>
                <Input
                  id="email"
                  type="email"
                  autoComplete="email"
                  aria-invalid={Boolean(errors.email)}
                  {...register("email")}
                />
                <FieldError errors={[errors.email]} />
              </Field>
              <Field data-invalid={Boolean(errors.password)}>
                <FieldLabel htmlFor="password">Password</FieldLabel>
                <Input
                  id="password"
                  type="password"
                  autoComplete="current-password"
                  aria-invalid={Boolean(errors.password)}
                  {...register("password")}
                />
                <FieldError errors={[errors.password]} />
              </Field>
            </FieldGroup>
            <Button className="w-full" type="submit" disabled={isSubmitting}>
              {isSubmitting ? "Signing in..." : "Sign in"}
            </Button>
          </form>
        </CardContent>
      </Card>
    </main>
  );
}
