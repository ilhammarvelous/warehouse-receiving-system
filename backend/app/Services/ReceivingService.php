<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\ErrorCode;
use App\Enums\TransactionType;
use App\Exceptions\ApiException;
use App\Models\IdempotencyKey;
use App\Models\InventoryTransaction;
use App\Models\Receipt;
use App\Models\ReceivingDocument;
use App\Models\ReceivingItem;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReceivingService
{
    private const ENDPOINT = 'receipts.store';

    /**
     * @return array{body: array<string, mixed>, status: int, replayed: bool}
     */
    public function recordReceipt(
        int $documentId,
        int $itemId,
        int $quantity,
        string $idempotencyKey,
        User $user,
    ): array {
        $requestHash = hash('sha256', json_encode([
            'document_id' => $documentId,
            'item_id' => $itemId,
            'quantity' => $quantity,
        ], JSON_THROW_ON_ERROR));

        try {
            return DB::transaction(function () use (
                $documentId,
                $itemId,
                $quantity,
                $idempotencyKey,
                $user,
                $requestHash,
            ): array {
                $document = ReceivingDocument::query()
                    ->whereKey($documentId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $existingKey = IdempotencyKey::query()
                    ->where('user_id', $user->id)
                    ->where('endpoint', self::ENDPOINT)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existingKey !== null) {
                    if (! hash_equals($existingKey->request_hash, $requestHash)) {
                        throw new ApiException(
                            ErrorCode::IdempotencyKeyReused,
                            409,
                            'This Idempotency-Key was already used with a different request.',
                        );
                    }

                    return [
                        'body' => $existingKey->response_body,
                        'status' => $existingKey->response_status,
                        'replayed' => true,
                    ];
                }

                if (! in_array($document->status, [DocumentStatus::Open, DocumentStatus::PartiallyReceived], true)) {
                    throw new ApiException(
                        ErrorCode::DocumentNotReceivable,
                        409,
                        'Goods can only be received on documents in OPEN or PARTIALLY_RECEIVED status.',
                        ['status' => $document->status->value],
                    );
                }

                $item = ReceivingItem::query()
                    ->whereKey($itemId)
                    ->where('receiving_document_id', $documentId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $remainingQuantity = $item->expected_quantity - $item->received_quantity;

                if ($quantity > $remainingQuantity) {
                    throw new ApiException(
                        ErrorCode::QuantityExceedsRemaining,
                        422,
                        'Received quantity exceeds the remaining expected quantity.',
                        [
                            'remaining_quantity' => $remainingQuantity,
                            'requested_quantity' => $quantity,
                        ],
                    );
                }

                $item->increment('received_quantity', $quantity);

                $receipt = Receipt::query()->create([
                    'receiving_item_id' => $item->id,
                    'quantity' => $quantity,
                    'received_by' => $user->id,
                ]);

                $inventory = DB::selectOne(
                    'UPDATE inventory
                     SET quantity_on_hand = quantity_on_hand + ?, updated_at = CURRENT_TIMESTAMP
                     WHERE product_id = ?
                     RETURNING quantity_on_hand',
                    [$quantity, $item->product_id],
                );

                if ($inventory === null) {
                    throw new RuntimeException('Inventory row is missing for the received product.');
                }

                $balanceAfter = (int) $inventory->quantity_on_hand;

                $transaction = InventoryTransaction::query()->create([
                    'product_id' => $item->product_id,
                    'type' => TransactionType::Receive,
                    'quantity' => $quantity,
                    'balance_after' => $balanceAfter,
                    'receipt_id' => $receipt->id,
                    'receiving_item_id' => $item->id,
                    'receiving_document_id' => $document->id,
                    'created_by' => $user->id,
                ]);

                if ($document->status === DocumentStatus::Open) {
                    $document->fill([
                        'status' => DocumentStatus::PartiallyReceived,
                        'status_changed_by' => $user->id,
                        'status_changed_at' => now('UTC'),
                    ])->save();
                }

                $receipt->refresh()->load('receiver');
                $item->refresh()->load('product');
                $document->refresh();
                $transaction->refresh();

                $body = [
                    'receipt' => [
                        'id' => $receipt->id,
                        'item_id' => $receipt->receiving_item_id,
                        'quantity' => $receipt->quantity,
                        'received_by' => [
                            'id' => $receipt->receiver->id,
                            'name' => $receipt->receiver->name,
                        ],
                        'created_at' => $receipt->created_at->toIso8601ZuluString(),
                    ],
                    'item' => [
                        'id' => $item->id,
                        'product' => [
                            'id' => $item->product->id,
                            'sku' => $item->product->sku,
                            'name' => $item->product->name,
                        ],
                        'expected_quantity' => $item->expected_quantity,
                        'received_quantity' => $item->received_quantity,
                        'remaining_quantity' => $item->expected_quantity - $item->received_quantity,
                    ],
                    'document' => [
                        'id' => $document->id,
                        'document_number' => $document->document_number,
                        'status' => $document->status->value,
                        'all_items_received' => ! $document->items()
                            ->whereColumn('received_quantity', '<', 'expected_quantity')
                            ->exists(),
                    ],
                    'inventory' => [
                        'product_id' => $item->product_id,
                        'quantity_on_hand' => $balanceAfter,
                    ],
                    'transaction' => [
                        'id' => $transaction->id,
                        'type' => $transaction->type->value,
                        'quantity' => $transaction->quantity,
                        'balance_after' => $transaction->balance_after,
                    ],
                ];

                IdempotencyKey::query()->create([
                    'user_id' => $user->id,
                    'endpoint' => self::ENDPOINT,
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => $requestHash,
                    'receipt_id' => $receipt->id,
                    'response_status' => 201,
                    'response_body' => $body,
                ]);

                return [
                    'body' => $body,
                    'status' => 201,
                    'replayed' => false,
                ];
            }, attempts: 3);
        } catch (UniqueConstraintViolationException $exception) {
            if (! str_contains($exception->getMessage(), 'idempotency_keys_scope_unique')) {
                throw $exception;
            }

            throw new ApiException(
                ErrorCode::IdempotencyKeyReused,
                409,
                'This Idempotency-Key was already used with a different request.',
                previous: $exception,
            );
        }
    }
}
