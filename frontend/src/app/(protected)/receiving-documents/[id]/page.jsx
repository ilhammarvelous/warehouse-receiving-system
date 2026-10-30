"use client";

import { useParams } from "next/navigation";
import { DocumentActionToolbar } from "@/components/document-action-toolbar";
import { EmptyState, LoadingState } from "@/components/feedback-states";
import { ErrorAlert } from "@/components/error-alert";
import { ReceiptForm } from "@/components/receipt-form";
import { StatusBadge } from "@/components/status-badge";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import {
  useRecordReceipt,
  useReceivingDocument,
  useTransitionReceivingDocument,
} from "@/lib/api/receiving-documents";
import { useAuth } from "@/lib/auth/AuthProvider";
import { formatDate, formatDateTime } from "@/lib/formatters";
import { useState } from "react";

export default function ReceivingDocumentDetailPage() {
  const { id } = useParams();
  const { user } = useAuth();
  const [actionError, setActionError] = useState(null);
  const documentQuery = useReceivingDocument(id);
  const transition = useTransitionReceivingDocument();
  const receipt = useRecordReceipt();

  async function handleAction(action) {
    setActionError(null);
    try {
      await transition.mutateAsync({ id, action });
    } catch (error) {
      setActionError(error);
    }
  }

  if (documentQuery.isPending) {
    return <LoadingState label="Loading receiving document..." />;
  }

  if (documentQuery.isError) {
    return <ErrorAlert error={documentQuery.error} />;
  }

  const document = documentQuery.data.data;
  const canReceive =
    document.status === "OPEN" || document.status === "PARTIALLY_RECEIVED";
  const itemsById = new Map(
    document.items.map((item) => [String(item.id), item])
  );

  return (
    <section className="mx-auto w-full max-w-6xl space-y-6">
      <header className="flex flex-wrap items-start justify-between gap-4">
        <div className="space-y-1">
          <div className="flex flex-wrap items-center gap-3">
            <h1 className="font-heading text-2xl font-semibold">
              {document.document_number}
            </h1>
            <StatusBadge status={document.status} />
          </div>
          <p className="text-sm text-muted-foreground">
            Supplier: {document.supplier_name}
          </p>
        </div>
        <DocumentActionToolbar
          document={document}
          role={user.role}
          onAction={handleAction}
          isPending={transition.isPending}
        />
      </header>

      {actionError && <ErrorAlert error={actionError} />}

      <Card>
        <CardHeader>
          <CardTitle>Document details</CardTitle>
          <CardDescription>
            Created by {document.created_by.name} on{" "}
            {formatDateTime(document.created_at)}.
          </CardDescription>
        </CardHeader>
        <CardContent className="grid gap-4 sm:grid-cols-2">
          <div>
            <p className="text-sm text-muted-foreground">Expected arrival</p>
            <p>{formatDate(document.expected_date)}</p>
          </div>
          <div>
            <p className="text-sm text-muted-foreground">Items</p>
            <p>{document.item_count}</p>
          </div>
          <div>
            <p className="text-sm text-muted-foreground">Notes</p>
            <p>{document.notes || "No notes"}</p>
          </div>
          <div>
            <p className="text-sm text-muted-foreground">Last status change</p>
            <p>
              {document.status_changed_by
                ? `${document.status_changed_by.name} on ${formatDateTime(
                    document.status_changed_at
                  )}`
                : "No status changes"}
            </p>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Expected items</CardTitle>
          <CardDescription>
            Quantities are shown as returned by the receiving API.
          </CardDescription>
        </CardHeader>
        <CardContent>
          {document.items.length === 0 ? (
            <EmptyState title="No items on this document" />
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead scope="col">Product</TableHead>
                  <TableHead scope="col">Expected</TableHead>
                  <TableHead scope="col">Received</TableHead>
                  <TableHead scope="col">Remaining</TableHead>
                  {canReceive && <TableHead scope="col">Record receipt</TableHead>}
                </TableRow>
              </TableHeader>
              <TableBody>
                {document.items.map((item) => (
                  <TableRow key={item.id}>
                    <TableCell>
                      <p className="font-medium">{item.product.name}</p>
                      <p className="text-sm text-muted-foreground">
                        {item.product.sku}
                      </p>
                    </TableCell>
                    <TableCell>{item.expected_quantity}</TableCell>
                    <TableCell>{item.received_quantity}</TableCell>
                    <TableCell>{item.remaining_quantity}</TableCell>
                    {canReceive && (
                      <TableCell className="min-w-64">
                        {item.remaining_quantity > 0 ? (
                          <ReceiptForm
                            documentId={document.id}
                            item={item}
                            onRecord={(request) =>
                              receipt.mutateAsync(request)
                            }
                            onRefresh={() => void documentQuery.refetch()}
                          />
                        ) : (
                          <span className="text-sm text-muted-foreground">
                            Fully received
                          </span>
                        )}
                      </TableCell>
                    )}
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Receipt history</CardTitle>
          <CardDescription>
            Receipts are listed newest first and cannot be changed.
          </CardDescription>
        </CardHeader>
        <CardContent>
          {document.receipts.length === 0 ? (
            <EmptyState
              title="No receipts yet"
              description="Recorded receipts will appear in this history."
            />
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead scope="col">Product</TableHead>
                  <TableHead scope="col">Quantity</TableHead>
                  <TableHead scope="col">Received by</TableHead>
                  <TableHead scope="col">Received at (UTC)</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {document.receipts.map((entry) => {
                  const item = itemsById.get(String(entry.item_id));

                  return (
                    <TableRow key={entry.id}>
                      <TableCell>
                        {item
                          ? `${item.product.sku} — ${item.product.name}`
                          : `Item ${entry.item_id}`}
                      </TableCell>
                      <TableCell>{entry.quantity}</TableCell>
                      <TableCell>{entry.received_by.name}</TableCell>
                      <TableCell>{formatDateTime(entry.created_at)}</TableCell>
                    </TableRow>
                  );
                })}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>
    </section>
  );
}
