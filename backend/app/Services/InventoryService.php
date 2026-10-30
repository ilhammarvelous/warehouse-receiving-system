<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class InventoryService
{
    /**
     * @param  array{page?: int|string, per_page?: int|string, search?: ?string}  $filters
     * @return LengthAwarePaginator<Inventory>
     */
    public function listInventory(array $filters): LengthAwarePaginator
    {
        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 20);
        $search = $filters['search'] ?? null;

        return Inventory::query()
            ->select('inventory.*')
            ->join('products', 'products.id', '=', 'inventory.product_id')
            ->with('product')
            ->when($search !== null && $search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('products.sku', 'ilike', "%{$search}%")
                        ->orWhere('products.name', 'ilike', "%{$search}%");
                });
            })
            ->orderBy('products.sku')
            ->orderBy('inventory.product_id')
            ->paginate($perPage, ['inventory.*'], 'page', $page);
    }

    /**
     * @param  array{page?: int|string, per_page?: int|string, product_id?: int|string, receiving_document_id?: int|string}  $filters
     * @return LengthAwarePaginator<InventoryTransaction>
     */
    public function listTransactions(array $filters): LengthAwarePaginator
    {
        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 20);

        return InventoryTransaction::query()
            ->with(['product', 'document', 'creator'])
            ->when(isset($filters['product_id']), function (Builder $query) use ($filters): void {
                $query->where('product_id', (int) $filters['product_id']);
            })
            ->when(isset($filters['receiving_document_id']), function (Builder $query) use ($filters): void {
                $query->where('receiving_document_id', (int) $filters['receiving_document_id']);
            })
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }
}
