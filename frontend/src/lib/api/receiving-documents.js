import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiRequest } from "@/lib/api/client";
import { queryKeys } from "@/lib/api/query-keys";
import { queryString } from "@/lib/api/query-string";

export function useReceivingDocuments(filters = {}) {
  return useQuery({
    queryKey: queryKeys.documentList(filters),
    queryFn: () =>
      apiRequest(`/receiving-documents${queryString(filters)}`),
  });
}

export function useReceivingDocument(id, { enabled = true } = {}) {
  return useQuery({
    queryKey: queryKeys.document(id),
    queryFn: () => apiRequest(`/receiving-documents/${id}`),
    enabled: enabled && Boolean(id),
  });
}

export function useCreateReceivingDocument() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (document) =>
      apiRequest("/receiving-documents", {
        method: "POST",
        body: document,
      }),
    onSuccess: async (response) => {
      queryClient.setQueryData(
        queryKeys.document(response.data.id),
        response
      );
      await queryClient.invalidateQueries({
        queryKey: queryKeys.documents,
      });
    },
  });
}

export function useUpdateReceivingDocument() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, document }) =>
      apiRequest(`/receiving-documents/${id}`, {
        method: "PUT",
        body: document,
      }),
    onSuccess: async (response, variables) => {
      queryClient.setQueryData(
        queryKeys.document(variables.id),
        response
      );
      await queryClient.invalidateQueries({
        queryKey: queryKeys.documents,
      });
      await queryClient.invalidateQueries({
        queryKey: queryKeys.document(variables.id),
      });
    },
  });
}

export function useTransitionReceivingDocument() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, action }) =>
      apiRequest(`/receiving-documents/${id}/${action}`, {
        method: "POST",
      }),
    onSuccess: async (_response, variables) => {
      await Promise.all([
        queryClient.invalidateQueries({
          queryKey: queryKeys.documents,
        }),
        queryClient.invalidateQueries({
          queryKey: queryKeys.document(variables.id),
        }),
      ]);
    },
  });
}

export function useRecordReceipt() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ documentId, itemId, quantity, idempotencyKey }) =>
      apiRequest(
        `/receiving-documents/${documentId}/items/${itemId}/receipts`,
        {
          method: "POST",
          body: { quantity },
          headers: { "Idempotency-Key": idempotencyKey },
        }
      ),
    onSuccess: async (_response, variables) => {
      await Promise.all([
        queryClient.invalidateQueries({
          queryKey: queryKeys.document(variables.documentId),
        }),
        queryClient.invalidateQueries({
          queryKey: queryKeys.documents,
        }),
        queryClient.invalidateQueries({
          queryKey: queryKeys.inventory,
        }),
        queryClient.invalidateQueries({
          queryKey: queryKeys.transactions,
        }),
      ]);
    },
  });
}
