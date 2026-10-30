"use client";

import Link from "next/link";
import { useState } from "react";
import { ProductPicker } from "@/components/product-picker";
import { ReceivingDocumentPicker } from "@/components/receiving-document-picker";
import { EmptyState, LoadingState } from "@/components/feedback-states";
import { ErrorAlert } from "@/components/error-alert";
import { PaginationControls } from "@/components/pagination-controls";
import { Button } from "@/components/ui/button";
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
import { useInventoryTransactions } from "@/lib/api/inventory";
import { formatDateTime } from "@/lib/formatters";

const PAGE_SIZE = 20;

export default function InventoryTransactionsPage() {
  const [selectedProductId, setSelectedProductId] = useState("all");
  const [selectedDocumentId, setSelectedDocumentId] = useState("all");
  const [filters, setFilters] = useState({
    product_id: "",
    receiving_document_id: "",
  });
  const [page, setPage] = useState(1);
  const transactionsQuery = useInventoryTransactions({
    page,
    per_page: PAGE_SIZE,
    ...(filters.product_id ? { product_id: filters.product_id } : {}),
    ...(filters.receiving_document_id
      ? { receiving_document_id: filters.receiving_document_id }
      : {}),
  });

  function applyFilters(event) {
    event.preventDefault();
    setPage(1);
    setFilters({
      product_id:
        selectedProductId === "all" ? "" : selectedProductId,
      receiving_document_id:
        selectedDocumentId === "all" ? "" : selectedDocumentId,
    });
  }

  return (
    <section className="mx-auto w-full max-w-6xl space-y-6">
      <header>
        <h1 className="font-heading text-2xl font-semibold">
          Inventory transactions
        </h1>
        <p className="text-sm text-muted-foreground">
          Read-only history of stock changes, newest first.
        </p>
      </header>

      <Card>
        <CardHeader>
          <CardTitle>Filter history</CardTitle>
          <CardDescription>
            Filter by product and/or receiving document ID.
          </CardDescription>
          <form
            className="grid gap-4 md:grid-cols-[minmax(16rem,1fr)_minmax(12rem,1fr)_auto] md:items-end"
            onSubmit={applyFilters}
          >
            <ProductPicker
              id="transaction-product-filter"
              label="Product"
              value={selectedProductId}
              onChange={setSelectedProductId}
              allowAll
            />
            <ReceivingDocumentPicker
              id="transaction-document-filter"
              label="Receiving document"
              value={selectedDocumentId}
              onChange={setSelectedDocumentId}
              allowAll
            />
            <Button type="submit">Apply filters</Button>
          </form>
        </CardHeader>
        <CardContent className="space-y-4">
          {transactionsQuery.isPending && (
            <LoadingState label="Loading transactions..." />
          )}
          {transactionsQuery.isError && (
            <ErrorAlert error={transactionsQuery.error} />
          )}
          {transactionsQuery.isSuccess &&
            transactionsQuery.data.data.length === 0 && (
              <EmptyState
                title="No transactions found"
                description="Stock transactions will appear after a receipt is recorded."
              />
            )}
          {transactionsQuery.isSuccess &&
            transactionsQuery.data.data.length > 0 && (
              <>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead scope="col">Type</TableHead>
                      <TableHead scope="col">Product</TableHead>
                      <TableHead scope="col">Quantity</TableHead>
                      <TableHead scope="col">Balance after</TableHead>
                      <TableHead scope="col">Document</TableHead>
                      <TableHead scope="col">Received by</TableHead>
                      <TableHead scope="col">Created at (UTC)</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {transactionsQuery.data.data.map((transaction) => (
                      <TableRow key={transaction.id}>
                        <TableCell>{transaction.type}</TableCell>
                        <TableCell>
                          {transaction.product.sku} — {transaction.product.name}
                        </TableCell>
                        <TableCell>{transaction.quantity}</TableCell>
                        <TableCell>{transaction.balance_after}</TableCell>
                        <TableCell>
                          <Button variant="link" asChild>
                            <Link
                              href={`/receiving-documents/${transaction.receiving_document.id}`}
                            >
                              {transaction.receiving_document.document_number}
                            </Link>
                          </Button>
                        </TableCell>
                        <TableCell>{transaction.created_by.name}</TableCell>
                        <TableCell>
                          {formatDateTime(transaction.created_at)}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
                <PaginationControls
                  page={transactionsQuery.data.meta.page}
                  lastPage={transactionsQuery.data.meta.last_page}
                  onPageChange={setPage}
                  label="Transaction pages"
                />
              </>
            )}
        </CardContent>
      </Card>
    </section>
  );
}
