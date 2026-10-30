<?php

namespace App\Services;

use App\Enums\DocumentAction;
use App\Enums\DocumentStatus;
use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Models\ReceivingDocument;
use App\Models\ReceivingItem;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class ReceivingDocumentService
{
    public function __construct(private readonly DocumentNumberGenerator $numberGenerator) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $user): ReceivingDocument
    {
        try {
            $id = DB::transaction(function () use ($data, $user): int {
                $this->assertUniqueProducts($data['items']);

                $document = ReceivingDocument::query()->create([
                    'document_number' => $this->numberGenerator->next(now('UTC')->toImmutable()),
                    'supplier_name' => $data['supplier_name'],
                    'expected_date' => $data['expected_date'],
                    'notes' => $data['notes'] ?? null,
                    'status' => DocumentStatus::Draft,
                    'created_by' => $user->id,
                ]);

                $document->items()->createMany($this->prepareItems($data['items']));

                return $document->id;
            }, attempts: 3);
        } catch (UniqueConstraintViolationException $exception) {
            $this->throwIfDuplicateProductConstraint($exception);

            throw $exception;
        }

        return $this->find($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): ReceivingDocument
    {
        try {
            $documentId = DB::transaction(function () use ($id, $data): int {
                $document = ReceivingDocument::query()
                    ->whereKey($id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($document->status !== DocumentStatus::Draft) {
                    throw new ApiException(
                        ErrorCode::DocumentNotEditable,
                        409,
                        'Only documents in DRAFT status can be edited.',
                        ['status' => $document->status->value],
                    );
                }

                $this->assertUniqueProducts($data['items']);

                $document->fill([
                    'supplier_name' => $data['supplier_name'],
                    'expected_date' => $data['expected_date'],
                    'notes' => $data['notes'] ?? null,
                ])->save();

                $document->items()->delete();
                $document->items()->createMany($this->prepareItems($data['items']));

                return $document->id;
            }, attempts: 3);
        } catch (UniqueConstraintViolationException $exception) {
            $this->throwIfDuplicateProductConstraint($exception);

            throw $exception;
        }

        return $this->find($documentId);
    }

    public function confirm(int $id, User $user): ReceivingDocument
    {
        return $this->transition($id, DocumentAction::Confirm, $user);
    }

    public function complete(int $id, User $user): ReceivingDocument
    {
        return $this->transition($id, DocumentAction::Complete, $user);
    }

    public function cancel(int $id, User $user): ReceivingDocument
    {
        return $this->transition($id, DocumentAction::Cancel, $user);
    }

    /**
     * @param  array{page?: int|string, per_page?: int|string, search?: ?string, status?: string}  $filters
     * @return LengthAwarePaginator<ReceivingDocument>
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 20);
        $search = $filters['search'] ?? null;

        return ReceivingDocument::query()
            ->withCount('items')
            ->withSum('items as total_expected_quantity', 'expected_quantity')
            ->withSum('items as total_received_quantity', 'received_quantity')
            ->when(isset($filters['status']), function (Builder $query) use ($filters): void {
                $query->where('status', $filters['status']);
            })
            ->when($search !== null && $search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('document_number', 'ilike', "%{$search}%")
                        ->orWhere('supplier_name', 'ilike', "%{$search}%");
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function find(int $id): ReceivingDocument
    {
        return ReceivingDocument::query()
            ->with([
                'creator',
                'statusChangedBy',
                'items' => fn ($query) => $query->orderBy('id'),
                'items.product',
                'receipts' => fn ($query) => $query->orderByDesc('receipts.id'),
                'receipts.receiver',
            ])
            ->withCount('items')
            ->withSum('items as total_expected_quantity', 'expected_quantity')
            ->withSum('items as total_received_quantity', 'received_quantity')
            ->findOrFail($id);
    }

    private function transition(int $id, DocumentAction $action, User $user): ReceivingDocument
    {
        $documentId = DB::transaction(function () use ($id, $action, $user): int {
            $document = ReceivingDocument::query()
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($action === DocumentAction::Cancel && $document->status === DocumentStatus::PartiallyReceived) {
                throw new ApiException(
                    ErrorCode::DocumentHasReceipts,
                    409,
                    'A document that already has receipts cannot be cancelled.',
                    ['status' => $document->status->value],
                );
            }

            $nextStatus = $document->status->transitionTo($action);

            if ($nextStatus === null) {
                throw new ApiException(
                    ErrorCode::InvalidStatusTransition,
                    409,
                    "A document in status {$document->status->value} cannot be {$action->pastParticiple()}.",
                    [
                        'from' => $document->status->value,
                        'action' => $action->value,
                    ],
                );
            }

            if ($action === DocumentAction::Complete) {
                $incompleteItems = $document->items()
                    ->whereColumn('received_quantity', '<', 'expected_quantity')
                    ->orderBy('id')
                    ->get(['id', 'expected_quantity', 'received_quantity'])
                    ->map(fn (ReceivingItem $item): array => [
                        'item_id' => $item->id,
                        'remaining_quantity' => $item->expected_quantity - $item->received_quantity,
                    ])
                    ->all();

                if ($incompleteItems !== []) {
                    throw new ApiException(
                        ErrorCode::DocumentNotFullyReceived,
                        409,
                        'All items must be fully received before the document can be completed.',
                        ['incomplete_items' => $incompleteItems],
                    );
                }
            }

            $document->fill([
                'status' => $nextStatus,
                'status_changed_by' => $user->id,
                'status_changed_at' => now('UTC'),
            ])->save();

            return $document->id;
        }, attempts: 3);

        return $this->find($documentId);
    }

    /**
     * @param  array<int, array{product_id: int|string, expected_quantity: int|string}>  $items
     * @return array<int, array{product_id: int, expected_quantity: int, received_quantity: int}>
     */
    private function prepareItems(array $items): array
    {
        return array_map(
            fn (array $item): array => [
                'product_id' => (int) $item['product_id'],
                'expected_quantity' => (int) $item['expected_quantity'],
                'received_quantity' => 0,
            ],
            $items,
        );
    }

    /**
     * @param  array<int, array{product_id: int|string}>  $items
     */
    private function assertUniqueProducts(array $items): void
    {
        $productIds = array_map(fn (array $item): int => (int) $item['product_id'], $items);
        $duplicates = array_values(array_unique(array_filter(
            $productIds,
            fn (int $productId): bool => count(array_keys($productIds, $productId, true)) > 1,
        )));

        if ($duplicates !== []) {
            throw new ApiException(
                ErrorCode::DuplicateProductInDocument,
                422,
                'A product can appear only once in a receiving document.',
                ['product_ids' => $duplicates],
            );
        }
    }

    private function throwIfDuplicateProductConstraint(UniqueConstraintViolationException $exception): void
    {
        if (! str_contains($exception->getMessage(), 'receiving_items_document_product_unique')) {
            return;
        }

        throw new ApiException(
            ErrorCode::DuplicateProductInDocument,
            422,
            'A product can appear only once in a receiving document.',
            null,
            $exception,
        );
    }
}
