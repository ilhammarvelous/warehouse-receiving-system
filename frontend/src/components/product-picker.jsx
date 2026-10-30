"use client";

import { useState } from "react";
import { Search } from "lucide-react";
import { Field, FieldError, FieldLabel } from "@/components/ui/field";
import { Button } from "@/components/ui/button";
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
import { useProducts } from "@/lib/api/products";

const PAGE_SIZE = 20;

export function ProductPicker({
  id,
  label,
  value,
  onChange,
  error,
  selectedProduct,
  allowAll = false,
  disabled = false,
}) {
  const [searchText, setSearchText] = useState("");
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [lastSelectedProduct, setLastSelectedProduct] = useState(null);
  const productsQuery = useProducts({
    page,
    per_page: PAGE_SIZE,
    search,
  });
  const products = productsQuery.data?.data ?? [];
  const meta = productsQuery.data?.meta;
  const selectedId = value === undefined || value === null ? "" : String(value);
  const selectedIsListed = products.some(
    (product) => String(product.id) === selectedId
  );
  const displayProduct =
    products.find((product) => String(product.id) === selectedId) ??
    (String(selectedProduct?.id) === selectedId ? selectedProduct : null) ??
    (String(lastSelectedProduct?.id) === selectedId
      ? lastSelectedProduct
      : null);

  function applySearch() {
    setPage(1);
    setSearch(searchText.trim());
  }

  return (
    <Field data-invalid={Boolean(error)}>
      <FieldLabel htmlFor={`${id}-search`}>Find product</FieldLabel>
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
          placeholder="Search SKU or name"
          disabled={disabled}
        />
        <Button
          type="button"
          variant="outline"
          aria-label="Search products"
          onClick={applySearch}
          disabled={disabled}
        >
          <Search aria-hidden="true" />
        </Button>
      </div>
      {productsQuery.isError && (
        <FieldError>
          Unable to load products: {productsQuery.error.message}
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

          const nextProduct = products.find(
            (product) => String(product.id) === nextValue
          );
          if (nextProduct) {
            setLastSelectedProduct(nextProduct);
          }
          onChange(nextValue);
        }}
        disabled={disabled || productsQuery.isError}
      >
        <SelectTrigger id={id} aria-invalid={Boolean(error)}>
          <SelectValue
            placeholder={
              productsQuery.isLoading ? "Loading products..." : "Select a product"
            }
          />
        </SelectTrigger>
        <SelectContent>
          {allowAll && <SelectItem value="all">All products</SelectItem>}
          {displayProduct && !selectedIsListed && (
            <SelectItem value={String(displayProduct.id)}>
              {displayProduct.sku} — {displayProduct.name}
            </SelectItem>
          )}
          {products.map((product) => (
            <SelectItem key={product.id} value={String(product.id)}>
              {product.sku} — {product.name}
            </SelectItem>
          ))}
          {!productsQuery.isLoading && products.length === 0 && (
            <SelectItem value="no-products" disabled>
              No products found
            </SelectItem>
          )}
        </SelectContent>
      </Select>
      {productsQuery.isFetching && (
        <span className="flex items-center gap-2 text-sm text-muted-foreground">
          <Spinner /> Loading product results...
        </span>
      )}
      <FieldError errors={[error]} />
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
