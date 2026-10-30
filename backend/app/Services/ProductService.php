<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class ProductService
{
    public function create(string $name, string $sku, User $user): Product
    {
        try {
            return DB::transaction(function () use ($name, $sku, $user): Product {
                if (Product::query()->where('sku', $sku)->exists()) {
                    throw $this->duplicateSkuException();
                }

                $product = Product::query()->create([
                    'sku' => $sku,
                    'name' => $name,
                    'created_by' => $user->id,
                ]);

                // FR-PROD-5: The product and its initial inventory must be committed together.
                Inventory::query()->create([
                    'product_id' => $product->id,
                    'quantity_on_hand' => 0,
                ]);

                return $product;
            }, attempts: 3);
        } catch (UniqueConstraintViolationException $exception) {
            // BR-01: The unique index is the final guard when concurrent requests pass the pre-check.
            if (! str_contains($exception->getMessage(), 'products_sku_unique')) {
                throw $exception;
            }

            throw $this->duplicateSkuException($exception);
        }
    }

    /**
     * @param  array{page?: int|string, per_page?: int|string, search?: ?string}  $filters
     * @return LengthAwarePaginator<Product>
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 20);
        $search = $filters['search'] ?? null;

        return Product::query()
            ->when($search !== null && $search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('sku', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%");
                });
            })
            ->orderBy('sku')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function find(int $id): Product
    {
        return Product::query()->findOrFail($id);
    }

    private function duplicateSkuException(?UniqueConstraintViolationException $previous = null): ApiException
    {
        return new ApiException(
            ErrorCode::SkuAlreadyExists,
            422,
            'A product with this SKU already exists.',
            ['fields' => ['sku' => ['The sku has already been taken.']]],
            $previous,
        );
    }
}
