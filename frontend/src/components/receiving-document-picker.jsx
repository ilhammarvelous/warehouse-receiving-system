"use client";

import { useState } from "react";
import { Search } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Field, FieldError, FieldLabel } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { PaginationControls } from "@/components/pagination-controls";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Spinner } from "@/components/ui/spinner";
import { useReceivingDocuments } from "@/lib/api/receiving-documents";

const PAGE_SIZE = 20;

export function ReceivingDocumentPicker({
  id,
  label,
  value,
  onChange,
  allowAll = false,
}) {
  const [searchText, setSearchText] = useState("");
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [lastSelectedDocument, setLastSelectedDocument] = useState(null);
  const documentsQuery = useReceivingDocuments({
    page,
    per_page: PAGE_SIZE,
    search,
  });
  const documents = documentsQuery.data?.data ?? [];
  const meta = documentsQuery.data?.meta;
  const selectedId = value === undefined || value === null ? "" : String(value);
  const selectedIsListed = documents.some(
    (document) => String(document.id) === selectedId
  );
  const displayDocument =
    documents.find((document) => String(document.id) === selectedId) ??
    (String(lastSelectedDocument?.id) === selectedId
      ? lastSelectedDocument
      : null);

  function applySearch() {
    setPage(1);
    setSearch(searchText.trim());
  }

  return (
    <Field>
      <FieldLabel htmlFor={`${id}-search`}>Find receiving document</FieldLabel>
      <div className="flex gap-2">
        <Input
          id={`${id}-search`}
          value={searchText}
          maxLength={100}
          onChange={(event) => setSearchText(event.target.value)}
          onKeyDown={(event) => {
            if (event.key === "Enter") {
              event.preventDefault();
              applySearch();
            }
          }}
          placeholder="Document number or supplier"
        />
        <Button
          type="button"
          variant="outline"
          aria-label="Search receiving documents"
          onClick={applySearch}
        >
          <Search aria-hidden="true" />
        </Button>
      </div>
      {documentsQuery.isError && (
        <FieldError>
          Unable to load receiving documents: {documentsQuery.error.message}
        </FieldError>
      )}
      <FieldLabel htmlFor={id}>{label}</FieldLabel>
      <Select
        value={selectedId}
        onValueChange={(nextValue) => {
          if (allowAll && nextValue === "all") {
            onChange("all");
            return;
          }

          const nextDocument = documents.find(
            (document) => String(document.id) === nextValue
          );
          if (nextDocument) {
            setLastSelectedDocument(nextDocument);
          }
          onChange(nextValue);
        }}
        disabled={documentsQuery.isError}
      >
        <SelectTrigger id={id}>
          <SelectValue
            placeholder={
              documentsQuery.isLoading
                ? "Loading documents..."
                : "Select a document"
            }
          />
        </SelectTrigger>
        <SelectContent>
          {allowAll && (
            <SelectItem value="all">All receiving documents</SelectItem>
          )}
          {displayDocument && !selectedIsListed && (
            <SelectItem value={String(displayDocument.id)}>
              {displayDocument.document_number} — {displayDocument.supplier_name}
            </SelectItem>
          )}
          {documents.map((document) => (
            <SelectItem key={document.id} value={String(document.id)}>
              {document.document_number} — {document.supplier_name}
            </SelectItem>
          ))}
          {!documentsQuery.isLoading && documents.length === 0 && (
            <SelectItem value="no-documents" disabled>
              No receiving documents found
            </SelectItem>
          )}
        </SelectContent>
      </Select>
      {documentsQuery.isFetching && (
        <span className="flex items-center gap-2 text-sm text-muted-foreground">
          <Spinner /> Loading document results...
        </span>
      )}
      {meta?.last_page > 1 && (
        <PaginationControls
          page={meta.page}
          lastPage={meta.last_page}
          onPageChange={setPage}
          label={`${label} search results`}
        />
      )}
    </Field>
  );
}
