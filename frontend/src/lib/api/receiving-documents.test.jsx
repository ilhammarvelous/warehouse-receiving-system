import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { act, renderHook } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { apiRequest } from "@/lib/api/client";
import { queryKeys } from "@/lib/api/query-keys";
import { useRecordReceipt } from "@/lib/api/receiving-documents";

vi.mock("@/lib/api/client", () => ({
  apiRequest: vi.fn(),
}));

describe("useRecordReceipt", () => {
  let queryClient;

  beforeEach(() => {
    queryClient = new QueryClient({
      defaultOptions: { queries: { retry: false } },
    });
    apiRequest.mockResolvedValue({ data: { receipt: { id: 88 } } });
  });

  afterEach(() => {
    queryClient.clear();
    vi.clearAllMocks();
  });

  it("test_us_09_fr_ui_5_sends_the_idempotency_key_and_invalidates_affected_resources", async () => {
    queryClient.setQueryData(queryKeys.document(7), { data: { id: 7 } });
    queryClient.setQueryData(queryKeys.documentList({ page: 1 }), {
      data: [],
    });
    queryClient.setQueryData(queryKeys.inventoryList({ page: 1 }), {
      data: [],
    });
    queryClient.setQueryData(queryKeys.transactionList({ page: 1 }), {
      data: [],
    });
    const wrapper = ({ children }) => (
      <QueryClientProvider client={queryClient}>
        {children}
      </QueryClientProvider>
    );
    const { result } = renderHook(() => useRecordReceipt(), { wrapper });

    await act(async () => {
      await result.current.mutateAsync({
        documentId: 7,
        itemId: 31,
        quantity: 4,
        idempotencyKey: "test-key-123456",
      });
    });

    expect(apiRequest).toHaveBeenCalledWith(
      "/receiving-documents/7/items/31/receipts",
      {
        method: "POST",
        body: { quantity: 4 },
        headers: { "Idempotency-Key": "test-key-123456" },
      }
    );
    expect(queryClient.getQueryState(queryKeys.document(7)).isInvalidated)
      .toBe(true);
    expect(
      queryClient.getQueryState(queryKeys.documentList({ page: 1 }))
        .isInvalidated
    ).toBe(true);
    expect(queryClient.getQueryState(queryKeys.inventoryList({ page: 1 })).isInvalidated)
      .toBe(true);
    expect(
      queryClient.getQueryState(queryKeys.transactionList({ page: 1 }))
        .isInvalidated
    ).toBe(true);
  });
});
