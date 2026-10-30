<?php

namespace App\Http\Middleware;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! in_array($user->role->value, $roles, true)) {
            throw new ApiException(
                ErrorCode::Forbidden,
                403,
                'Your role is not allowed to perform this action.',
            );
        }

        return $next($request);
    }
}
