"use client";

import { useState } from "react";
import { ProductForm } from "@/components/product-form";
import { EmptyState, LoadingState } from "@/components/feedback-states";
import { PaginationControls } from "@/components/pagination-controls";
import { ErrorAlert } from "@/components/error-alert";
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
import { useCreateProduct, useProducts } from "@/lib/api/products";
import { useAuth } from "@/lib/auth/AuthProvider";

const PAGE_SIZE = 20;

export default function ProductsPage() {
  const { user } = useAuth();
  const [searchText, setSearchText] = useState("");
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const productsQuery = useProducts({
    page,
    per_page: PAGE_SIZE,
    search,
  });
  const createProduct = useCreateProduct();

  function applySearch(event) {
    event.preventDefault();
    setPage(1);
    setSearch(searchText.trim());
  }

  return (
    <section className="mx-auto w-full max-w-6xl space-y-6">
      <header>
        <h1 className="font-heading text-2xl font-semibold">Products</h1>
        <p className="text-sm text-muted-foreground">
          Search the product catalog used in receiving documents.
        </p>
      </header>

      {user?.role === "SUPERVISOR" && (
        <ProductForm
          onSubmit={(product) => createProduct.mutateAsync(product)}
        />
      )}

      <Card>
        <CardHeader>
          <CardTitle>Product catalog</CardTitle>
          <CardDescription>
            Search by SKU or product name. Results are ordered by SKU.
          </CardDescription>
          <form
            className="flex flex-col gap-3 sm:flex-row sm:items-end"
            onSubmit={applySearch}
          >
            <Field className="flex-1">
              <FieldLabel htmlFor="product-search">Search products</FieldLabel>
              <Input
                id="product-search"
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
          {productsQuery.isPending && <LoadingState label="Loading products..." />}
          {productsQuery.isError && <ErrorAlert error={productsQuery.error} />}
          {productsQuery.isSuccess && productsQuery.data.data.length === 0 && (
            <EmptyState
              title="No products found"
              description={
                search
                  ? "Try another search term."
                  : "Products will appear here after they are created."
              }
            />
          )}
          {productsQuery.isSuccess && productsQuery.data.data.length > 0 && (
            <>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead scope="col">SKU</TableHead>
                    <TableHead scope="col">Product name</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {productsQuery.data.data.map((product) => (
                    <TableRow key={product.id}>
                      <TableCell className="font-medium">{product.sku}</TableCell>
                      <TableCell>{product.name}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
              <PaginationControls
                page={productsQuery.data.meta.page}
                lastPage={productsQuery.data.meta.last_page}
                onPageChange={setPage}
                label="Product pages"
              />
            </>
          )}
        </CardContent>
      </Card>
    </section>
  );
}
