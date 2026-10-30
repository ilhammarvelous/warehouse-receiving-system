<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService
{
    /**
     * @return array{token: string, user: User}
     */
    public function login(string $email, string $password): array
    {
        $user = User::query()
            ->whereRaw('lower(email) = ?', [strtolower($email)])
            ->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw new ApiException(
                ErrorCode::Unauthenticated,
                401,
                'Invalid email or password.',
            );
        }

        return [
            'token' => $user->createToken('warehouse-receiving')->plainTextToken,
            'user' => $user,
        ];
    }

    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
    }
}
