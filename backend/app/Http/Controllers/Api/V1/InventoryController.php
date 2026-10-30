<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListInventoryRequest;
use App\Http\Resources\InventoryItemResource;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;

class InventoryController extends Controller
{
    public function index(ListInventoryRequest $request, InventoryService $inventoryService): JsonResponse
    {
        $inventory = $inventoryService->listInventory($request->validated());

        return response()->json([
            'data' => InventoryItemResource::collection($inventory->getCollection())->resolve($request),
            'meta' => [
                'page' => $inventory->currentPage(),
                'per_page' => $inventory->perPage(),
                'total' => $inventory->total(),
                'last_page' => $inventory->lastPage(),
            ],
        ]);
    }
}
