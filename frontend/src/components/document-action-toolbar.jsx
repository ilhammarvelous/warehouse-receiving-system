"use client";

import Link from "next/link";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/confirm-dialog";

export function DocumentActionToolbar({
  document,
  role,
  onAction,
  isPending = false,
}) {
  if (role !== "SUPERVISOR") {
    return null;
  }

  const isDraft = document.status === "DRAFT";
  const canCancel = isDraft || document.status === "OPEN";
  const canComplete =
    document.status === "PARTIALLY_RECEIVED" &&
    document.all_items_received === true;
  const completeDisabled =
    document.status === "PARTIALLY_RECEIVED" && !document.all_items_received;

  if (!isDraft && !canCancel && document.status !== "PARTIALLY_RECEIVED") {
    return null;
  }

  return (
    <div className="flex flex-wrap items-center gap-2">
      {isDraft && (
        <Button variant="outline" asChild>
          <Link href={`/receiving-documents/${document.id}/edit`}>
            Edit draft
          </Link>
        </Button>
      )}
      {isDraft && (
        <ConfirmDialog
          trigger={
            <Button disabled={isPending}>
              Confirm document
            </Button>
          }
          title="Confirm this receiving document?"
          description="The document will be opened for receiving. Its supplier and expected items can no longer be edited."
          confirmLabel="Confirm document"
          confirmDisabled={isPending}
          onConfirm={() => onAction("confirm")}
        />
      )}
      {canCancel && (
        <ConfirmDialog
          trigger={
            <Button variant="destructive" disabled={isPending}>
              Cancel document
            </Button>
          }
          title="Cancel this receiving document?"
          description="This action cannot be undone. A document with receipts cannot be cancelled."
          confirmLabel="Cancel document"
          confirmDisabled={isPending}
          destructive
          onConfirm={() => onAction("cancel")}
        />
      )}
      {document.status === "PARTIALLY_RECEIVED" && (
        <>
          {canComplete && (
            <Alert className="w-auto">
              <AlertTitle>Ready to complete</AlertTitle>
              <AlertDescription>
                Every item has been fully received.
              </AlertDescription>
            </Alert>
          )}
          <ConfirmDialog
            trigger={
              <Button disabled={isPending || completeDisabled}>
                Complete document
              </Button>
            }
            title="Complete this receiving document?"
            description="The document will be marked completed and no further receipts will be allowed."
            confirmLabel="Complete document"
            confirmDisabled={isPending}
            onConfirm={() => onAction("complete")}
          />
        </>
      )}
    </div>
  );
}
