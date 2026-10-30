"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { ErrorAlert } from "@/components/error-alert";
import { Button } from "@/components/ui/button";
import { Field, FieldError, FieldLabel } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { ApiError } from "@/lib/api/errors";
import {
  getIdempotencyAttempt,
  shouldDiscardIdempotencyKey,
} from "@/lib/api/idempotency";
import { applyServerFieldErrors } from "@/lib/forms/server-errors";
import { createReceiptSchema } from "@/lib/schemas/receipt";

export function ReceiptForm({
  documentId,
  item,
  onRecord,
  onRefresh,
}) {
  const [submissionError, setSubmissionError] = useState(null);
  const [requestPending, setRequestPending] = useState(false);
  const [attempt, setAttempt] = useState(null);
  const {
    formState: { errors, isSubmitting },
    handleSubmit,
    register,
    reset,
    setError,
  } = useForm({
    resolver: zodResolver(createReceiptSchema(item.remaining_quantity)),
    defaultValues: { quantity: "" },
  });

  async function submitReceipt(values) {
    if (requestPending) {
      return;
    }

    setRequestPending(true);
    setSubmissionError(null);
    const request = {
      documentId: Number(documentId),
      itemId: Number(item.id),
      quantity: Number(values.quantity),
    };

    try {
      const currentAttempt = getIdempotencyAttempt(attempt, request);
      setAttempt(currentAttempt);
      await onRecord({ ...request, idempotencyKey: currentAttempt.key });
      setAttempt(null);
      reset({ quantity: "" });
    } catch (error) {
      if (shouldDiscardIdempotencyKey(error)) {
        setAttempt(null);
      }

      applyServerFieldErrors(error, setError);
      setSubmissionError(error);

      if (error instanceof ApiError && error.code === "QUANTITY_EXCEEDS_REMAINING") {
        onRefresh?.();
      }
    } finally {
      setRequestPending(false);
    }
  }

  const remainingQuantity =
    submissionError instanceof ApiError &&
    submissionError.code === "QUANTITY_EXCEEDS_REMAINING" &&
    typeof submissionError.details?.remaining_quantity === "number"
      ? submissionError.details.remaining_quantity
      : null;
  const errorMessage =
    remainingQuantity === null
      ? undefined
      : `${submissionError.message} Remaining quantity: ${remainingQuantity}.`;

  return (
    <form
      noValidate
      className="flex flex-col gap-3 sm:flex-row sm:items-end"
      onSubmit={handleSubmit(submitReceipt)}
    >
      <Field className="min-w-32 flex-1" data-invalid={Boolean(errors.quantity)}>
        <FieldLabel htmlFor={`receipt-quantity-${item.id}`}>
          Quantity to receive
        </FieldLabel>
        <Input
          id={`receipt-quantity-${item.id}`}
          type="number"
          min={1}
          max={item.remaining_quantity}
          step={1}
          aria-invalid={Boolean(errors.quantity)}
          {...register("quantity")}
        />
        <FieldError errors={[errors.quantity]} />
      </Field>
      <Button type="submit" disabled={isSubmitting || requestPending}>
        {isSubmitting || requestPending ? "Recording..." : "Record receipt"}
      </Button>
      {submissionError && (
        <div className="basis-full">
          <ErrorAlert
            error={submissionError}
            message={errorMessage}
            includeFieldErrors={false}
          />
        </div>
      )}
    </form>
  );
}
