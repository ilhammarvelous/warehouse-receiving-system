"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { ErrorAlert } from "@/components/error-alert";
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
import { applyServerFieldErrors } from "@/lib/forms/server-errors";
import { productSchema } from "@/lib/schemas/product";

export function ProductForm({ onSubmit }) {
  const [submissionError, setSubmissionError] = useState(null);
  const {
    formState: { errors, isSubmitting },
    handleSubmit,
    register,
    reset,
    setError,
  } = useForm({
    resolver: zodResolver(productSchema),
    defaultValues: { sku: "", name: "" },
  });

  async function submitProduct(product) {
    setSubmissionError(null);
    try {
      await onSubmit(product);
      reset();
    } catch (error) {
      applyServerFieldErrors(error, setError);
      setSubmissionError(error);
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>New product</CardTitle>
        <CardDescription>
          Add a product that can be included in receiving documents.
        </CardDescription>
      </CardHeader>
      <CardContent>
        <form
          noValidate
          className="space-y-4"
          onSubmit={handleSubmit(submitProduct)}
        >
          {submissionError && (
            <ErrorAlert error={submissionError} includeFieldErrors={false} />
          )}
          <FieldGroup className="grid gap-4 md:grid-cols-2">
            <Field data-invalid={Boolean(errors.sku)}>
              <FieldLabel htmlFor="product-sku">SKU</FieldLabel>
              <Input
                id="product-sku"
                autoComplete="off"
                maxLength={50}
                aria-invalid={Boolean(errors.sku)}
                {...register("sku")}
              />
              <FieldError errors={[errors.sku]} />
            </Field>
            <Field data-invalid={Boolean(errors.name)}>
              <FieldLabel htmlFor="product-name">Product name</FieldLabel>
              <Input
                id="product-name"
                maxLength={255}
                aria-invalid={Boolean(errors.name)}
                {...register("name")}
              />
              <FieldError errors={[errors.name]} />
            </Field>
          </FieldGroup>
          <Button type="submit" disabled={isSubmitting}>
            {isSubmitting ? "Saving..." : "Create product"}
          </Button>
        </form>
      </CardContent>
    </Card>
  );
}
