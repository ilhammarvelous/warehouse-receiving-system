<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecordReceiptRequest;
use App\Http\Resources\ReceiptResultResource;
use App\Models\User;
use App\Services\ReceivingService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;

class ReceiptController extends Controller
{
    public function store(
        int $documentId,
        int $itemId,
        RecordReceiptRequest $request,
        ReceivingService $receivingService,
    ): JsonResponse {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        $result = $receivingService->recordReceipt(
            $documentId,
            $itemId,
            (int) $request->validated('quantity'),
            $request->validated('Idempotency-Key'),
            $user,
        );

        $response = ReceiptResultResource::make($result['body'])
            ->response()
            ->setStatusCode($result['status']);

        if ($result['replayed']) {
            $response->headers->set('Idempotent-Replayed', 'true');
        }

        return $response;
    }
}
