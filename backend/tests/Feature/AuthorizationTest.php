<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Route::post('/api/v1/__tests/supervisor-action/{userId}', function (Request $request, string $userId) {
            $request->validate(['name' => ['required', 'string']]);
            User::query()->findOrFail($userId);

            return response()->noContent();
        })->middleware(['auth:sanctum', 'role:SUPERVISOR']);

        Route::get('/api/v1/__tests/shared-action', fn () => response()->noContent())
            ->middleware(['auth:sanctum', 'role:OPERATOR,SUPERVISOR']);
    }

    public function test_fr_auth_5_operator_is_forbidden_before_validation_and_resource_lookup(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'OPERATOR']));

        $this->postJson('/api/v1/__tests/supervisor-action/999999', [])
            ->assertForbidden()
            ->assertExactJson([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Your role is not allowed to perform this action.',
                ],
            ]);
    }

    public function test_fr_auth_5_supervisor_passes_role_middleware_before_validation(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'SUPERVISOR']));

        $this->postJson('/api/v1/__tests/supervisor-action/999999', [])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_fr_auth_5_operator_and_supervisor_can_use_shared_role_middleware(): void
    {
        foreach (['OPERATOR', 'SUPERVISOR'] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role]));
            $this->getJson('/api/v1/__tests/shared-action')->assertNoContent();
        }
    }
}
