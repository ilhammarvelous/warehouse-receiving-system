<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListReceivingDocumentsRequest;
use App\Http\Requests\ReceivingDocumentRequest;
use App\Http\Resources\ReceivingDocumentDetailResource;
use App\Http\Resources\ReceivingDocumentSummaryResource;
use App\Models\User;
use App\Services\ReceivingDocumentService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;

class ReceivingDocumentController extends Controller
{
    public function index(
        ListReceivingDocumentsRequest $request,
        ReceivingDocumentService $documentService,
    ): JsonResponse {
        $documents = $documentService->list($request->validated());

        return response()->json([
            'data' => ReceivingDocumentSummaryResource::collection($documents->getCollection())->resolve($request),
            'meta' => [
                'page' => $documents->currentPage(),
                'per_page' => $documents->perPage(),
                'total' => $documents->total(),
                'last_page' => $documents->lastPage(),
            ],
        ]);
    }

    public function store(
        ReceivingDocumentRequest $request,
        ReceivingDocumentService $documentService,
    ): JsonResponse {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        $document = $documentService->create($request->validated(), $user);

        return ReceivingDocumentDetailResource::make($document)->response()->setStatusCode(201);
    }

    public function show(int $documentId, ReceivingDocumentService $documentService): ReceivingDocumentDetailResource
    {
        return ReceivingDocumentDetailResource::make($documentService->find($documentId));
    }

    public function update(
        int $documentId,
        ReceivingDocumentRequest $request,
        ReceivingDocumentService $documentService,
    ): ReceivingDocumentDetailResource {
        return ReceivingDocumentDetailResource::make(
            $documentService->update($documentId, $request->validated()),
        );
    }
}
