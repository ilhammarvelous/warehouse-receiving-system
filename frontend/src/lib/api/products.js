import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiRequest } from "@/lib/api/client";
import { queryKeys } from "@/lib/api/query-keys";
import { queryString } from "@/lib/api/query-string";

export function useProducts(filters = {}) {
  return useQuery({
    queryKey: queryKeys.productList(filters),
    queryFn: () => apiRequest(`/products${queryString(filters)}`),
  });
}

export function useCreateProduct() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (product) =>
      apiRequest("/products", { method: "POST", body: product }),
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: queryKeys.products }),
        queryClient.invalidateQueries({ queryKey: queryKeys.inventory }),
      ]);
    },
  });
}
