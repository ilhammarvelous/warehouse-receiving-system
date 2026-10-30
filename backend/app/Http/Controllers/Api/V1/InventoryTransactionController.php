<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListInventoryTransactionsRequest;
use App\Http\Resources\InventoryTransactionResource;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;

class InventoryTransactionController extends Controller
{
    public function index(
        ListInventoryTransactionsRequest $request,
        InventoryService $inventoryService,
    ): JsonResponse {
        $transactions = $inventoryService->listTransactions($request->validated());

        return response()->json([
            'data' => InventoryTransactionResource::collection($transactions->getCollection())->resolve($request),
            'meta' => [
                'page' => $transactions->currentPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'last_page' => $transactions->lastPage(),
            ],
        ]);
    }
}
