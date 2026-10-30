<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use DatabaseTransactions;

    public function test_us_02_fr_prod_1_fr_prod_5_br_01_create_normalizes_product_and_creates_zero_inventory(): void
    {
        $user = User::factory()->create(['role' => 'SUPERVISOR']);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/products', [
            'sku' => '  abc-001  ',
            'name' => '  Mineral Water 600 ml  ',
        ])->assertCreated()
            ->assertJsonPath('data.sku', 'ABC-001')
            ->assertJsonPath('data.name', 'Mineral Water 600 ml')
            ->assertJsonStructure([
                'data' => ['id', 'sku', 'name', 'created_at', 'updated_at'],
            ]);

        $product = Product::query()->where('sku', 'ABC-001')->firstOrFail();
        $this->assertSame($user->id, $product->created_by);
        $this->assertDatabaseHas('inventory', [
            'product_id' => $product->id,
            'quantity_on_hand' => 0,
        ]);
        $this->assertSame(1, Inventory::query()->where('product_id', $product->id)->count());
    }

    public function test_us_02_fr_prod_2_duplicate_sku_in_any_case_is_rejected_without_database_changes(): void
    {
        $user = User::factory()->create(['role' => 'SUPERVISOR']);
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/products', [
            'sku' => 'case-duplicate',
            'name' => 'Original product',
        ])->assertCreated();

        $productsBefore = Product::query()->count();
        $inventoryBefore = Inventory::query()->count();

        $this->postJson('/api/v1/products', [
            'sku' => ' CASE-DUPLICATE ',
            'name' => 'Duplicate product',
        ])->assertUnprocessable()
            ->assertJsonPath('error.code', 'SKU_ALREADY_EXISTS')
            ->assertJsonPath('error.details.fields.sku.0', 'The sku has already been taken.');

        $this->assertSame($productsBefore, Product::query()->count());
        $this->assertSame($inventoryBefore, Inventory::query()->count());
    }

    public function test_us_02_fr_prod_3_invalid_sku_and_blank_name_return_field_validation_errors(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'SUPERVISOR']));
        $productsBefore = Product::query()->count();
        $inventoryBefore = Inventory::query()->count();

        $this->postJson('/api/v1/products', [
            'sku' => '-invalid-',
            'name' => '   ',
        ])->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure([
                'error' => ['details' => ['fields' => ['sku', 'name']]],
            ]);

        $this->assertSame($productsBefore, Product::query()->count());
        $this->assertSame($inventoryBefore, Inventory::query()->count());
    }

    public function test_br_18_operator_is_forbidden_before_invalid_product_input_is_validated(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'OPERATOR']));
        $productsBefore = Product::query()->count();
        $inventoryBefore = Inventory::query()->count();

        $this->postJson('/api/v1/products', [
            'sku' => '-invalid-',
            'name' => '',
        ])->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->assertSame($productsBefore, Product::query()->count());
        $this->assertSame($inventoryBefore, Inventory::query()->count());
    }

    public function test_us_03_fr_prod_3_search_is_case_insensitive_across_sku_and_name_and_paginated_by_sku(): void
    {
        $supervisor = User::factory()->create(['role' => 'SUPERVISOR']);
        Sanctum::actingAs($supervisor);

        $term = 'T3'.strtoupper(Str::random(8));
        $this->createProduct($term.'-B', 'Unrelated name');
        $this->createProduct('Z-SKU-'.strtoupper(Str::random(8)), 'Product '.$term.' name');
        $this->createProduct($term.'-A', 'Another unrelated name');
        $this->createProduct('OTHER-'.strtoupper(Str::random(8)), 'No match');

        $operator = User::factory()->create(['role' => 'OPERATOR']);
        Sanctum::actingAs($operator);

        $this->getJson('/api/v1/products?search='.strtolower($term).'&per_page=1&page=1')
            ->assertOk()
            ->assertJsonPath('meta.page', 1)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('data.0.sku', $term.'-A');

        $this->getJson('/api/v1/products?search='.strtolower($term).'&per_page=1&page=2')
            ->assertOk()
            ->assertJsonPath('meta.page', 2)
            ->assertJsonPath('data.0.sku', $term.'-B');
    }

    public function test_us_03_fr_prod_3_rejects_out_of_range_pagination_and_search_parameters(): void
    {
        Sanctum::actingAs(User::factory()->create());

        foreach ([
            '/api/v1/products?page=0',
            '/api/v1/products?per_page=101',
            '/api/v1/products?search='.str_repeat('a', 101),
        ] as $url) {
            $this->getJson($url)
                ->assertUnprocessable()
                ->assertJsonPath('error.code', 'VALIDATION_ERROR');
        }
    }

    public function test_fr_prod_4_authenticated_user_can_view_a_product_and_missing_product_is_not_found(): void
    {
        $user = User::factory()->create(['role' => 'OPERATOR']);
        Sanctum::actingAs($user);
        $product = Product::query()->create([
            'sku' => 'T3-SHOW-'.strtoupper(Str::random(8)),
            'name' => 'Product details',
            'created_by' => $user->id,
        ]);

        $this->getJson("/api/v1/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.sku', $product->sku)
            ->assertJsonPath('data.name', $product->name);

        $this->getJson('/api/v1/products/999999999')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    private function createProduct(string $sku, string $name): void
    {
        $this->postJson('/api/v1/products', [
            'sku' => $sku,
            'name' => $name,
        ])->assertCreated();
    }
}
