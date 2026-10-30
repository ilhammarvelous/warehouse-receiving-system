<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function login(LoginRequest $request, AuthService $authService): JsonResponse
    {
        $result = $authService->login(
            $request->validated('email'),
            $request->validated('password'),
        );

        return response()->json([
            'data' => [
                'token' => $result['token'],
                'token_type' => 'Bearer',
                'user' => UserResource::make($result['user'])->resolve($request),
            ],
        ]);
    }

    public function logout(Request $request, AuthService $authService): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        $authService->logout($user);

        return response()->noContent();
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return UserResource::make($user)->response();
    }
}
