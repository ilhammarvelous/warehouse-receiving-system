"use client";

import Link from "next/link";
import { useState } from "react";
import { EmptyState, LoadingState } from "@/components/feedback-states";
import { ErrorAlert } from "@/components/error-alert";
import { PaginationControls } from "@/components/pagination-controls";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Field, FieldLabel } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { useReceivingDocuments } from "@/lib/api/receiving-documents";
import { formatDate } from "@/lib/formatters";

const PAGE_SIZE = 20;
const statuses = [
  "DRAFT",
  "OPEN",
  "PARTIALLY_RECEIVED",
  "COMPLETED",
  "CANCELLED",
];

export default function ReceivingDocumentsPage() {
  const [searchText, setSearchText] = useState("");
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("all");
  const [page, setPage] = useState(1);
  const documentsQuery = useReceivingDocuments({
    page,
    per_page: PAGE_SIZE,
    search,
    ...(status === "all" ? {} : { status }),
  });

  function applySearch(event) {
    event.preventDefault();
    setPage(1);
    setSearch(searchText.trim());
  }

  function changeStatus(value) {
    setPage(1);
    setStatus(value);
  }

  return (
    <section className="mx-auto w-full max-w-6xl space-y-6">
      <header>
        <h1 className="font-heading text-2xl font-semibold">
          Receiving documents
        </h1>
        <p className="text-sm text-muted-foreground">
          Review incoming deliveries, their status, and item progress.
        </p>
      </header>

      <Card>
        <CardHeader>
          <CardTitle>Document register</CardTitle>
          <CardDescription>
            Search by document number or supplier and filter by status.
          </CardDescription>
          <form
            className="grid gap-3 sm:grid-cols-[minmax(12rem,1fr)_minmax(12rem,1fr)_auto] sm:items-end"
            onSubmit={applySearch}
          >
            <Field>
              <FieldLabel htmlFor="document-search">Search documents</FieldLabel>
              <Input
                id="document-search"
                value={searchText}
                maxLength={100}
                onChange={(event) => setSearchText(event.target.value)}
                placeholder="Document number or supplier"
              />
            </Field>
            <Field>
              <FieldLabel htmlFor="document-status">Status</FieldLabel>
              <Select value={status} onValueChange={changeStatus}>
                <SelectTrigger id="document-status">
                  <SelectValue placeholder="All statuses" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All statuses</SelectItem>
                  {statuses.map((value) => (
                    <SelectItem key={value} value={value}>
                      {value.replaceAll("_", " ")}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Field>
            <Button type="submit">Search</Button>
          </form>
        </CardHeader>
        <CardContent className="space-y-4">
          {documentsQuery.isPending && (
            <LoadingState label="Loading receiving documents..." />
          )}
          {documentsQuery.isError && <ErrorAlert error={documentsQuery.error} />}
          {documentsQuery.isSuccess && documentsQuery.data.data.length === 0 && (
            <EmptyState
              title="No receiving documents found"
              description={
                search || status !== "all"
                  ? "Change the search or status filter and try again."
                  : "New receiving documents will appear here."
              }
            />
          )}
          {documentsQuery.isSuccess && documentsQuery.data.data.length > 0 && (
            <>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead scope="col">Document</TableHead>
                    <TableHead scope="col">Supplier</TableHead>
                    <TableHead scope="col">Expected date</TableHead>
                    <TableHead scope="col">Status</TableHead>
                    <TableHead scope="col">Items</TableHead>
                    <TableHead scope="col">Quantity received</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {documentsQuery.data.data.map((document) => (
                    <TableRow key={document.id}>
                      <TableCell>
                        <Button variant="link" asChild>
                          <Link href={`/receiving-documents/${document.id}`}>
                            {document.document_number}
                          </Link>
                        </Button>
                      </TableCell>
                      <TableCell>{document.supplier_name}</TableCell>
                      <TableCell>{formatDate(document.expected_date)}</TableCell>
                      <TableCell>
                        <StatusBadge status={document.status} />
                      </TableCell>
                      <TableCell>{document.item_count}</TableCell>
                      <TableCell>
                        {document.total_received_quantity} /{" "}
                        {document.total_expected_quantity}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
              <PaginationControls
                page={documentsQuery.data.meta.page}
                lastPage={documentsQuery.data.meta.last_page}
                onPageChange={setPage}
                label="Receiving document pages"
              />
            </>
          )}
        </CardContent>
      </Card>
    </section>
  );
}
