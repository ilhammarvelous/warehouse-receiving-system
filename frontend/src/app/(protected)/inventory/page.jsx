"use client";

import { useState } from "react";
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
import { Field, FieldLabel } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { useInventory } from "@/lib/api/inventory";
import { formatDateTime } from "@/lib/formatters";

const PAGE_SIZE = 20;

export default function InventoryPage() {
  const [searchText, setSearchText] = useState("");
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const inventoryQuery = useInventory({
    page,
    per_page: PAGE_SIZE,
    search,
  });

  function applySearch(event) {
    event.preventDefault();
    setPage(1);
    setSearch(searchText.trim());
  }

  return (
    <section className="mx-auto w-full max-w-6xl space-y-6">
      <header>
        <h1 className="font-heading text-2xl font-semibold">Inventory</h1>
        <p className="text-sm text-muted-foreground">
          Current stock on hand by product.
        </p>
      </header>

      <Card>
        <CardHeader>
          <CardTitle>Stock on hand</CardTitle>
          <CardDescription>
            Search inventory by SKU or product name.
          </CardDescription>
          <form
            className="flex flex-col gap-3 sm:flex-row sm:items-end"
            onSubmit={applySearch}
          >
            <Field className="flex-1">
              <FieldLabel htmlFor="inventory-search">Search products</FieldLabel>
              <Input
                id="inventory-search"
                value={searchText}
                maxLength={100}
                onChange={(event) => setSearchText(event.target.value)}
                placeholder="SKU or name"
              />
            </Field>
            <Button type="submit">Search</Button>
          </form>
        </CardHeader>
        <CardContent className="space-y-4">
          {inventoryQuery.isPending && (
            <LoadingState label="Loading inventory..." />
          )}
          {inventoryQuery.isError && <ErrorAlert error={inventoryQuery.error} />}
          {inventoryQuery.isSuccess && inventoryQuery.data.data.length === 0 && (
            <EmptyState
              title="No inventory found"
              description={
                search
                  ? "Try another search term."
                  : "Inventory records will appear when products are created."
              }
            />
          )}
          {inventoryQuery.isSuccess && inventoryQuery.data.data.length > 0 && (
            <>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead scope="col">SKU</TableHead>
                    <TableHead scope="col">Product name</TableHead>
                    <TableHead scope="col">Quantity on hand</TableHead>
                    <TableHead scope="col">Last updated (UTC)</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {inventoryQuery.data.data.map((item) => (
                    <TableRow key={item.product_id}>
                      <TableCell className="font-medium">{item.sku}</TableCell>
                      <TableCell>{item.name}</TableCell>
                      <TableCell>{item.quantity_on_hand}</TableCell>
                      <TableCell>{formatDateTime(item.updated_at)}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
              <PaginationControls
                page={inventoryQuery.data.meta.page}
                lastPage={inventoryQuery.data.meta.last_page}
                onPageChange={setPage}
                label="Inventory pages"
              />
            </>
          )}
        </CardContent>
      </Card>
    </section>
  );
}
