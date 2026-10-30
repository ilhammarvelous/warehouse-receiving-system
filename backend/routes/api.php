<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\InventoryTransactionController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ReceiptController;
use App\Http\Controllers\Api\V1\ReceivingDocumentController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{productId}', [ProductController::class, 'show'])->whereNumber('productId');
    Route::post('/products', [ProductController::class, 'store'])
        ->middleware('role:SUPERVISOR');

    Route::get('/receiving-documents', [ReceivingDocumentController::class, 'index']);
    Route::post('/receiving-documents', [ReceivingDocumentController::class, 'store'])
        ->middleware('role:SUPERVISOR');
    Route::get('/receiving-documents/{documentId}', [ReceivingDocumentController::class, 'show'])
        ->whereNumber('documentId');
    Route::put('/receiving-documents/{documentId}', [ReceivingDocumentController::class, 'update'])
        ->whereNumber('documentId')
        ->middleware('role:SUPERVISOR');
    Route::post('/receiving-documents/{documentId}/confirm', [ReceivingDocumentController::class, 'confirm'])
        ->whereNumber('documentId')
        ->middleware('role:SUPERVISOR');
    Route::post('/receiving-documents/{documentId}/complete', [ReceivingDocumentController::class, 'complete'])
        ->whereNumber('documentId')
        ->middleware('role:SUPERVISOR');
    Route::post('/receiving-documents/{documentId}/cancel', [ReceivingDocumentController::class, 'cancel'])
        ->whereNumber('documentId')
        ->middleware('role:SUPERVISOR');
    Route::post(
        '/receiving-documents/{documentId}/items/{itemId}/receipts',
        [ReceiptController::class, 'store'],
    )
        ->whereNumber('documentId')
        ->whereNumber('itemId')
        ->middleware('role:OPERATOR,SUPERVISOR');

    Route::get('/inventory', [InventoryController::class, 'index']);
    Route::get('/inventory-transactions', [InventoryTransactionController::class, 'index']);
});

Route::get('/health', function () {
    try {
        DB::select('SELECT 1');
    } catch (PDOException $exception) {
        report($exception);

        return response()->json([
            'status' => 'error',
            'database' => 'unreachable',
        ], 503);
    }

    return response()->json([
        'status' => 'ok',
        'database' => 'ok',
    ]);
});
