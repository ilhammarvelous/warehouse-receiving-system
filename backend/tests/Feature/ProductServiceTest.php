<?php

namespace Tests\Feature;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\ProductService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use PDOException;
use Tests\TestCase;

class ProductServiceTest extends TestCase
{
    public function test_br_01_product_service_maps_a_unique_index_race_to_sku_already_exists(): void
    {
        DB::shouldReceive('transaction')
            ->once()
            ->andThrow(new UniqueConstraintViolationException(
                'pgsql',
                'insert into products (sku) values (?)',
                ['T3-RACE'],
                new PDOException('duplicate key violates unique constraint "products_sku_unique"'),
            ));

        $user = new User;
        $user->id = 1;

        try {
            app(ProductService::class)->create('Racing product', 'T3-RACE', $user);
            $this->fail('A unique SKU violation should be converted to an API exception.');
        } catch (ApiException $exception) {
            $this->assertSame(ErrorCode::SkuAlreadyExists, $exception->errorCode);
            $this->assertSame(422, $exception->status);
        }
    }
}
