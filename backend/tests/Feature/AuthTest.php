<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use DatabaseTransactions;

    public function test_us_01_fr_auth_1_login_returns_a_token_and_the_users_role(): void
    {
        $user = User::factory()->create(['role' => Role::Supervisor]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => strtoupper($user->email),
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.name', $user->name)
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonPath('data.user.role', 'SUPERVISOR');

        $token = $response->json('data.token');
        $this->assertIsString($token);
        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.role', 'SUPERVISOR');
    }

    public function test_us_01_fr_auth_1_invalid_email_and_password_return_the_same_unauthenticated_error(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);

        $invalidPassword = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnauthorized()->json();

        $invalidEmail = $this->postJson('/api/v1/auth/login', [
            'email' => 'missing@example.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized()->json();

        $this->assertSame($invalidPassword, $invalidEmail);
        $this->assertSame('UNAUTHENTICATED', $invalidPassword['error']['code']);
        $this->assertSame('Invalid email or password.', $invalidPassword['error']['message']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_fr_auth_3_and_fr_auth_4_protected_auth_route_requires_a_token(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertExactJson([
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Authentication is required.',
                ],
            ]);
    }

    public function test_fr_auth_2_logout_revokes_only_the_current_token(): void
    {
        $user = User::factory()->create();
        $otherToken = $user->createToken('other-device')->plainTextToken;
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();
        $token = $login->json('data.token');

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 1);
        Auth::forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->withToken($otherToken)->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_technical_spec_4_5_sanctum_token_expiration_uses_the_configured_lifetime(): void
    {
        config(['sanctum.expiration' => 1]);

        $user = User::factory()->create();
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();
        $token = $login->json('data.token');
        $createdAt = DB::table('personal_access_tokens')->value('created_at');

        Carbon::setTestNow(Carbon::parse($createdAt)->addMinutes(2));

        try {
            $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
        } finally {
            Carbon::setTestNow();
        }
    }
}
