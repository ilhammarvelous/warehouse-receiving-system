<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use PDOException;
use RuntimeException;
use Tests\TestCase;

class ApiErrorHandlingTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/api/v1/__tests/unexpected-error', function () {
            throw new RuntimeException('internal detail must not be exposed');
        });

        Route::get('/api/v1/__tests/sku-constraint', function () {
            throw new QueryException(
                'pgsql',
                'insert into products (sku) values (?)',
                ['T2-DUPLICATE'],
                new PDOException('SQLSTATE[23505]: duplicate key violates unique constraint "products_sku_unique"'),
            );
        });
    }

    public function test_nfr_11_unknown_api_route_returns_the_standard_not_found_shape_without_accept_header(): void
    {
        $this->get('/api/v1/unknown')
            ->assertNotFound()
            ->assertHeader('content-type', 'application/json')
            ->assertExactJson([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Resource not found.',
                ],
            ]);
    }

    public function test_nfr_11_validation_failure_returns_field_details_in_the_standard_shape(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.message', 'The given data was invalid.')
            ->assertJsonStructure(['error' => ['code', 'message', 'details' => ['fields' => ['email', 'password']]]]);
    }

    public function test_nfr_7_unexpected_api_exception_is_generic_and_does_not_expose_internal_details(): void
    {
        $this->getJson('/api/v1/__tests/unexpected-error')
            ->assertInternalServerError()
            ->assertExactJson([
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'An unexpected error occurred.',
                ],
            ])
            ->assertDontSee('internal detail must not be exposed');
    }

    public function test_technical_spec_4_4_known_database_constraint_maps_to_its_stable_error_code(): void
    {
        $this->getJson('/api/v1/__tests/sku-constraint')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'SKU_ALREADY_EXISTS')
            ->assertJsonPath('error.message', 'A product with this SKU already exists.');
    }
}
