import { useQuery } from "@tanstack/react-query";
import { apiRequest } from "@/lib/api/client";
import { queryKeys } from "@/lib/api/query-keys";
import { queryString } from "@/lib/api/query-string";

export function useInventory(filters = {}) {
  return useQuery({
    queryKey: queryKeys.inventoryList(filters),
    queryFn: () => apiRequest(`/inventory${queryString(filters)}`),
  });
}

export function useInventoryTransactions(filters = {}) {
  return useQuery({
    queryKey: queryKeys.transactionList(filters),
    queryFn: () =>
      apiRequest(`/inventory-transactions${queryString(filters)}`),
  });
}
