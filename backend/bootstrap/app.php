<?php

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Http\Middleware\EnsureRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = 500;
            $code = ErrorCode::InternalError;
            $message = 'An unexpected error occurred.';
            $details = null;

            if ($exception instanceof ApiException) {
                $status = $exception->status;
                $code = $exception->errorCode;
                $message = $exception->getMessage();
                $details = $exception->details;
            } elseif ($exception instanceof AuthenticationException) {
                $status = 401;
                $code = ErrorCode::Unauthenticated;
                $message = 'Authentication is required.';
            } elseif ($exception instanceof AuthorizationException) {
                $status = 403;
                $code = ErrorCode::Forbidden;
                $message = 'Your role is not allowed to perform this action.';
            } elseif ($exception instanceof ValidationException) {
                $status = 422;
                $code = ErrorCode::ValidationError;
                $message = 'The given data was invalid.';
                $details = ['fields' => $exception->errors()];
            } elseif ($exception instanceof ModelNotFoundException || $exception instanceof NotFoundHttpException) {
                $status = 404;
                $code = ErrorCode::NotFound;
                $message = 'Resource not found.';
            } elseif ($exception instanceof QueryException) {
                [$code, $status, $message] = match (true) {
                    str_contains($exception->getMessage(), 'products_sku_unique') => [
                        ErrorCode::SkuAlreadyExists,
                        422,
                        'A product with this SKU already exists.',
                    ],
                    str_contains($exception->getMessage(), 'receiving_items_document_product_unique') => [
                        ErrorCode::DuplicateProductInDocument,
                        422,
                        'A product can appear only once in a receiving document.',
                    ],
                    str_contains($exception->getMessage(), 'receiving_items_not_over_received_check') => [
                        ErrorCode::QuantityExceedsRemaining,
                        422,
                        'Received quantity exceeds the remaining expected quantity.',
                    ],
                    str_contains($exception->getMessage(), 'idempotency_keys_scope_unique') => [
                        ErrorCode::IdempotencyKeyReused,
                        409,
                        'This idempotency key has already been used for a different request.',
                    ],
                    default => [$code, $status, $message],
                };
            }

            $error = [
                'code' => $code->value,
                'message' => $message,
            ];

            if ($details !== null) {
                $error['details'] = $details;
            }

            return response()->json(['error' => $error], $status);
        });
    })->create();
