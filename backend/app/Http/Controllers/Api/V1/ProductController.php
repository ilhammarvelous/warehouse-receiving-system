<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListProductsRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\User;
use App\Services\ProductService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function index(ListProductsRequest $request, ProductService $productService): JsonResponse
    {
        $products = $productService->list($request->validated());

        return response()->json([
            'data' => ProductResource::collection($products->getCollection())->resolve($request),
            'meta' => [
                'page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'last_page' => $products->lastPage(),
            ],
        ]);
    }

    public function show(int $productId, ProductService $productService): JsonResponse
    {
        return ProductResource::make($productService->find($productId))->response();
    }

    public function store(StoreProductRequest $request, ProductService $productService): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        $product = $productService->create(
            $request->validated('name'),
            $request->validated('sku'),
            $user,
        );

        return ProductResource::make($product)->response()->setStatusCode(201);
    }
}
