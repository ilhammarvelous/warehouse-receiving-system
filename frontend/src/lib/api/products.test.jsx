import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { act, renderHook } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { apiRequest } from "@/lib/api/client";
import { queryKeys } from "@/lib/api/query-keys";
import { useCreateProduct } from "@/lib/api/products";
import { toast } from "@/components/ui/toast";

vi.mock("@/lib/api/client", () => ({
  apiRequest: vi.fn(),
}));

vi.mock("@/components/ui/toast", () => ({
  toast: {
    success: vi.fn(),
  },
}));

describe("useCreateProduct", () => {
  let queryClient;

  beforeEach(() => {
    queryClient = new QueryClient({
      defaultOptions: { queries: { retry: false } },
    });
    apiRequest.mockResolvedValue({ data: { id: 12 } });
  });

  afterEach(() => {
    queryClient.clear();
    vi.clearAllMocks();
  });

  it("test_us_02_fr_ui_2_notifies_and_invalidates_after_product_creation", async () => {
    queryClient.setQueryData(queryKeys.products, { data: [] });
    queryClient.setQueryData(queryKeys.inventory, { data: [] });
    const wrapper = ({ children }) => (
      <QueryClientProvider client={queryClient}>
        {children}
      </QueryClientProvider>
    );
    const { result } = renderHook(() => useCreateProduct(), { wrapper });

    await act(async () => {
      await result.current.mutateAsync({ sku: "SKU-12", name: "Product 12" });
    });

    expect(toast.success).toHaveBeenCalledWith(
      "Product created successfully."
    );
    expect(queryClient.getQueryState(queryKeys.products).isInvalidated)
      .toBe(true);
    expect(queryClient.getQueryState(queryKeys.inventory).isInvalidated)
      .toBe(true);
  });
});
