"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { Plus, Trash2 } from "lucide-react";
import { useState } from "react";
import { Controller, useFieldArray, useForm, useWatch } from "react-hook-form";
import { ErrorAlert } from "@/components/error-alert";
import { ProductPicker } from "@/components/product-picker";
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
import { Textarea } from "@/components/ui/textarea";
import { applyServerFieldErrors } from "@/lib/forms/server-errors";
import { receivingDocumentSchema } from "@/lib/schemas/receiving-document";

function documentDefaults(document) {
  if (!document) {
    return {
      supplier_name: "",
      expected_date: "",
      notes: "",
      items: [{ product_id: "", expected_quantity: 1 }],
    };
  }

  return {
    supplier_name: document.supplier_name,
    expected_date: document.expected_date,
    notes: document.notes ?? "",
    items: document.items.map((item) => ({
      product_id: item.product.id,
      expected_quantity: item.expected_quantity,
    })),
  };
}

export function ReceivingDocumentForm({
  initialData,
  submitLabel = "Save draft",
  onSubmit,
}) {
  const [submissionError, setSubmissionError] = useState(null);
  const {
    control,
    formState: { errors, isSubmitting },
    handleSubmit,
    register,
    setError,
  } = useForm({
    resolver: zodResolver(receivingDocumentSchema),
    defaultValues: documentDefaults(initialData),
  });
  const { append, fields, remove } = useFieldArray({
    control,
    name: "items",
  });
  const watchedItems = useWatch({ control, name: "items" });

  async function submitDocument(document) {
    setSubmissionError(null);
    const payload = {
      ...document,
      notes: document.notes.trim() || null,
    };

    try {
      await onSubmit(payload);
    } catch (error) {
      applyServerFieldErrors(error, setError);
      setSubmissionError(error);
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>
          {initialData ? "Edit draft document" : "New receiving document"}
        </CardTitle>
        <CardDescription>
          Enter the supplier, expected arrival date, and products for this
          delivery.
        </CardDescription>
      </CardHeader>
      <CardContent>
        <form
          noValidate
          className="space-y-6"
          onSubmit={handleSubmit(submitDocument)}
        >
          {submissionError && (
            <ErrorAlert error={submissionError} includeFieldErrors={false} />
          )}
          <FieldGroup className="grid gap-4 md:grid-cols-2">
            <Field data-invalid={Boolean(errors.supplier_name)}>
              <FieldLabel htmlFor="supplier-name">Supplier name</FieldLabel>
              <Input
                id="supplier-name"
                maxLength={255}
                aria-invalid={Boolean(errors.supplier_name)}
                {...register("supplier_name")}
              />
              <FieldError errors={[errors.supplier_name]} />
            </Field>
            <Field data-invalid={Boolean(errors.expected_date)}>
              <FieldLabel htmlFor="expected-date">Expected arrival date</FieldLabel>
              <Input
                id="expected-date"
                type="date"
                aria-invalid={Boolean(errors.expected_date)}
                {...register("expected_date")}
              />
              <FieldError errors={[errors.expected_date]} />
            </Field>
            <Field className="md:col-span-2" data-invalid={Boolean(errors.notes)}>
              <FieldLabel htmlFor="document-notes">Notes (optional)</FieldLabel>
              <Textarea
                id="document-notes"
                maxLength={1000}
                aria-invalid={Boolean(errors.notes)}
                {...register("notes")}
              />
              <FieldError errors={[errors.notes]} />
            </Field>
          </FieldGroup>

          <section aria-labelledby="document-items-heading" className="space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div>
                <h2
                  id="document-items-heading"
                  className="font-heading text-lg font-semibold"
                >
                  Expected items
                </h2>
                <FieldError errors={[errors.items]} />
              </div>
              <Button
                type="button"
                variant="outline"
                onClick={() =>
                  append({ product_id: "", expected_quantity: 1 })
                }
              >
                <Plus aria-hidden="true" />
                Add item
              </Button>
            </div>

            {fields.map((field, index) => {
              const productId = watchedItems?.[index]?.product_id;
              const selectedProduct = initialData?.items.find(
                (item) => String(item.product.id) === String(productId)
              )?.product;
              const itemErrors = errors.items?.[index];

              return (
                <Card key={field.id}>
                  <CardContent className="grid gap-4 pt-6 md:grid-cols-[minmax(0,2fr)_minmax(10rem,1fr)_auto] md:items-end">
                    <Controller
                      control={control}
                      name={`items.${index}.product_id`}
                      render={({ field: productField }) => (
                        <ProductPicker
                          id={`document-product-${field.id}`}
                          label="Product"
                          value={productField.value}
                          onChange={productField.onChange}
                          error={itemErrors?.product_id}
                          selectedProduct={selectedProduct}
                        />
                      )}
                    />
                    <Field data-invalid={Boolean(itemErrors?.expected_quantity)}>
                      <FieldLabel htmlFor={`expected-quantity-${field.id}`}>
                        Expected quantity
                      </FieldLabel>
                      <Input
                        id={`expected-quantity-${field.id}`}
                        type="number"
                        min={1}
                        max={1_000_000}
                        step={1}
                        aria-invalid={Boolean(itemErrors?.expected_quantity)}
                        {...register(`items.${index}.expected_quantity`)}
                      />
                      <FieldError errors={[itemErrors?.expected_quantity]} />
                    </Field>
                    <Button
                      type="button"
                      variant="destructive"
                      size="icon"
                      aria-label={`Remove item ${index + 1}`}
                      onClick={() => remove(index)}
                    >
                      <Trash2 aria-hidden="true" />
                    </Button>
                  </CardContent>
                </Card>
              );
            })}
          </section>

          <Button type="submit" disabled={isSubmitting}>
            {isSubmitting ? "Saving..." : submitLabel}
          </Button>
        </form>
      </CardContent>
    </Card>
  );
}
