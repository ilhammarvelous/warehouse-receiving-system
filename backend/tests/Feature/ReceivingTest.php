<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\ReceivingDocument;
use App\Models\ReceivingItem;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReceivingTest extends TestCase
{
    use DatabaseTransactions;

    private User $supervisor;

    private User $operator;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supervisor = User::factory()->create(['role' => 'SUPERVISOR']);
        $this->operator = User::factory()->create(['role' => 'OPERATOR']);
        $this->product = $this->makeProduct('T6-BASE-'.strtoupper(Str::random(6)));
        Sanctum::actingAs($this->operator);
    }

    public function test_us_09_br_10_br_13_br_21_partial_then_full_receipts_update_item_inventory_and_ledger(): void
    {
        [$document, $item] = $this->makeOpenDocument($this->product, 10);
        $documentStatusBefore = $document->status;

        $firstResponse = $this->postReceipt($document, $item, 4, 'partial-receipt-key-1')
            ->assertCreated()
            ->assertJsonPath('data.receipt.item_id', $item->id)
            ->assertJsonPath('data.receipt.quantity', 4)
            ->assertJsonPath('data.receipt.received_by.id', $this->operator->id)
            ->assertJsonPath('data.item.expected_quantity', 10)
            ->assertJsonPath('data.item.received_quantity', 4)
            ->assertJsonPath('data.item.remaining_quantity', 6)
            ->assertJsonPath('data.document.status', 'PARTIALLY_RECEIVED')
            ->assertJsonPath('data.document.all_items_received', false)
            ->assertJsonPath('data.inventory.product_id', $this->product->id)
            ->assertJsonPath('data.inventory.quantity_on_hand', 4)
            ->assertJsonPath('data.transaction.type', 'RECEIVE')
            ->assertJsonPath('data.transaction.quantity', 4)
            ->assertJsonPath('data.transaction.balance_after', 4)
            ->assertJsonStructure([
                'data' => [
                    'receipt' => ['id', 'item_id', 'quantity', 'received_by' => ['id', 'name'], 'created_at'],
                    'item' => ['id', 'product' => ['id', 'sku', 'name'], 'expected_quantity', 'received_quantity', 'remaining_quantity'],
                    'document' => ['id', 'document_number', 'status', 'all_items_received'],
                    'inventory' => ['product_id', 'quantity_on_hand'],
                    'transaction' => ['id', 'type', 'quantity', 'balance_after'],
                ],
            ]);

        $document->refresh();
        $item->refresh();
        $this->assertSame(DocumentStatus::PartiallyReceived, $document->status);
        $this->assertSame($this->operator->id, $document->status_changed_by);
        $this->assertNotNull($document->status_changed_at);
        $this->assertNotSame($documentStatusBefore, $document->status);
        $this->assertSame(4, $item->received_quantity);
        $this->assertDatabaseHas('inventory', ['product_id' => $this->product->id, 'quantity_on_hand' => 4]);
        $this->assertDatabaseHas('receipts', [
            'id' => $firstResponse->json('data.receipt.id'),
            'receiving_item_id' => $item->id,
            'quantity' => 4,
            'received_by' => $this->operator->id,
        ]);
        $this->assertDatabaseHas('inventory_transactions', [
            'id' => $firstResponse->json('data.transaction.id'),
            'product_id' => $this->product->id,
            'type' => 'RECEIVE',
            'quantity' => 4,
            'balance_after' => 4,
            'receipt_id' => $firstResponse->json('data.receipt.id'),
            'receiving_item_id' => $item->id,
            'receiving_document_id' => $document->id,
            'created_by' => $this->operator->id,
        ]);

        $secondResponse = $this->postReceipt($document, $item, 6, 'full-receipt-key-2')
            ->assertCreated()
            ->assertJsonPath('data.item.received_quantity', 10)
            ->assertJsonPath('data.item.remaining_quantity', 0)
            ->assertJsonPath('data.document.status', 'PARTIALLY_RECEIVED')
            ->assertJsonPath('data.document.all_items_received', true)
            ->assertJsonPath('data.inventory.quantity_on_hand', 10)
            ->assertJsonPath('data.transaction.balance_after', 10);

        $this->assertSame(10, $item->refresh()->received_quantity);
        $this->assertSame(DocumentStatus::PartiallyReceived, $document->refresh()->status);
        $this->assertSame(10, Inventory::query()->findOrFail($this->product->id)->quantity_on_hand);
        $this->assertSame(2, Receipt::query()->count());
        $this->assertSame(2, InventoryTransaction::query()->count());
        $this->assertSame(10, (int) DB::table('inventory_transactions')->max('balance_after'));
        $this->assertSame($firstResponse->json('data.transaction.id') + 1, $secondResponse->json('data.transaction.id'));
    }

    public function test_br_18_supervisor_can_record_a_receipt(): void
    {
        [$document, $item] = $this->makeOpenDocument($this->product, 5);
        Sanctum::actingAs($this->supervisor);

        $this->postReceipt($document, $item, 2, 'supervisor-receipt-key')
            ->assertCreated()
            ->assertJsonPath('data.receipt.received_by.id', $this->supervisor->id)
            ->assertJsonPath('data.document.status', 'PARTIALLY_RECEIVED');

        $this->assertSame(2, $item->refresh()->received_quantity);
        $this->assertSame(2, Inventory::query()->findOrFail($this->product->id)->quantity_on_hand);
        $this->assertDatabaseHas('idempotency_keys', [
            'user_id' => $this->supervisor->id,
            'endpoint' => 'receipts.store',
            'idempotency_key' => 'supervisor-receipt-key',
        ]);
    }

    public function test_br_07_br_14_quantity_above_remaining_returns_422_without_any_database_changes(): void
    {
        [$document, $item] = $this->makeOpenDocument($this->product, 10);
        $this->postReceipt($document, $item, 4, 'remaining-receipt-key')
            ->assertCreated();
        $before = $this->receiptState($document, $item, 'over-receipt-key');

        $this->postReceipt($document, $item, 7, 'over-receipt-key')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'QUANTITY_EXCEEDS_REMAINING')
            ->assertJsonPath('error.message', 'Received quantity exceeds the remaining expected quantity.')
            ->assertJsonPath('error.details.remaining_quantity', 6)
            ->assertJsonPath('error.details.requested_quantity', 7);

        $this->assertSame($before, $this->receiptState($document, $item, 'over-receipt-key'));
    }

    #[DataProvider('invalidReceiptQuantities')]
    public function test_us_09_fr_rcv_2_and_3_invalid_quantities_are_rejected_without_writes(mixed $quantity): void
    {
        [$document, $item] = $this->makeOpenDocument($this->product, 10);
        $before = $this->receiptState($document, $item, 'invalid-quantity-key');

        $this->postJson($this->receiptPath($document, $item), ['quantity' => $quantity], [
            'Idempotency-Key' => 'invalid-quantity-key',
        ])->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['error' => ['details' => ['fields' => ['quantity']]]]);

        $this->assertSame($before, $this->receiptState($document, $item, 'invalid-quantity-key'));
    }

    public static function invalidReceiptQuantities(): array
    {
        return [
            'zero' => [0],
            'negative' => [-1],
            'decimal' => [1.5],
            'numeric decimal string' => ['1.5'],
            'non-numeric' => ['not-a-number'],
        ];
    }

    public function test_us_09_fr_rcv_6_missing_idempotency_key_is_rejected_without_writes(): void
    {
        [$document, $item] = $this->makeOpenDocument($this->product, 10);
        $before = $this->receiptState($document, $item, null);

        $this->postJson($this->receiptPath($document, $item), ['quantity' => 2])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['error' => ['details' => ['fields' => ['Idempotency-Key']]]]);

        $this->assertSame($before, $this->receiptState($document, $item, null));
    }

    public function test_us_09_fr_rcv_6_idempotency_key_length_is_validated(): void
    {
        [$document, $item] = $this->makeOpenDocument($this->product, 10);
        $before = $this->receiptState($document, $item, null);

        foreach (['short', str_repeat('k', 129)] as $key) {
            $this->postJson($this->receiptPath($document, $item), ['quantity' => 2], [
                'Idempotency-Key' => $key,
            ])->assertUnprocessable()
                ->assertJsonPath('error.code', 'VALIDATION_ERROR')
                ->assertJsonStructure(['error' => ['details' => ['fields' => ['Idempotency-Key']]]]);
        }

        $this->assertSame($before, $this->receiptState($document, $item, null));
    }

    public function test_us_09_fr_rcv_6_item_must_belong_to_the_receiving_document(): void
    {
        [$document, $item] = $this->makeOpenDocument($this->product, 10);
        [$otherDocument, $otherItem] = $this->makeOpenDocument($this->makeProduct('T6-OTHER-'.strtoupper(Str::random(6))), 8);
        $before = $this->receiptState($document, $item, 'wrong-item-key');

        $this->postReceipt($document, $otherItem, 2, 'wrong-item-key')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');

        $this->assertSame($before, $this->receiptState($document, $item, 'wrong-item-key'));
        $this->assertSame(0, $otherItem->refresh()->received_quantity);
        $this->assertSame(DocumentStatus::Open, $otherDocument->refresh()->status);
    }

    #[DataProvider('nonReceivableStatuses')]
    public function test_br_06_fr_rcv_8_non_receivable_document_statuses_reject_without_writes(
        DocumentStatus $status,
    ): void {
        [$document, $item] = $this->makeOpenDocument($this->product, 10, $status);
        $before = $this->receiptState($document, $item, 'blocked-status-key');

        $this->postReceipt($document, $item, 2, 'blocked-status-key')
            ->assertConflict()
            ->assertJsonPath('error.code', 'DOCUMENT_NOT_RECEIVABLE')
            ->assertJsonPath('error.message', 'Goods can only be received on documents in OPEN or PARTIALLY_RECEIVED status.')
            ->assertJsonPath('error.details.status', $status->value);

        $this->assertSame($before, $this->receiptState($document, $item, 'blocked-status-key'));
    }

    public static function nonReceivableStatuses(): array
    {
        return [
            'draft' => [DocumentStatus::Draft],
            'completed' => [DocumentStatus::Completed],
            'cancelled' => [DocumentStatus::Cancelled],
        ];
    }

    public function test_us_10_fr_rcv_6_same_key_and_request_replays_the_original_response_once(): void
    {
        [$document, $item] = $this->makeOpenDocument($this->product, 10);
        $key = 'same-request-idempotency-key';

        $firstResponse = $this->postReceipt($document, $item, 4, $key)->assertCreated();
        $stateAfterFirst = $this->receiptState($document, $item, $key);
        $secondResponse = $this->postReceipt($document, $item, 4, $key)
            ->assertCreated()
            ->assertHeader('Idempotent-Replayed', 'true');

        $this->assertSame(
            $this->canonicalizeJsonValue($firstResponse->json()),
            $this->canonicalizeJsonValue($secondResponse->json()),
        );
        $this->assertSame($stateAfterFirst, $this->receiptState($document, $item, $key));
        $this->assertDatabaseHas('idempotency_keys', [
            'user_id' => $this->operator->id,
            'endpoint' => 'receipts.store',
            'idempotency_key' => $key,
            'request_hash' => hash('sha256', json_encode([
                'document_id' => $document->id,
                'item_id' => $item->id,
                'quantity' => 4,
            ], JSON_THROW_ON_ERROR)),
            'receipt_id' => $firstResponse->json('data.receipt.id'),
            'response_status' => 201,
        ]);
        $this->assertSame(1, Receipt::query()->count());
        $this->assertSame(1, InventoryTransaction::query()->count());
    }

    public function test_us_10_br_15_same_key_with_different_quantity_is_rejected_without_writes(): void
    {
        [$document, $item] = $this->makeOpenDocument($this->product, 10);
        $key = 'different-quantity-key';
        $this->postReceipt($document, $item, 2, $key)->assertCreated();
        $before = $this->receiptState($document, $item, $key);

        $this->postReceipt($document, $item, 3, $key)
            ->assertConflict()
            ->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_REUSED')
            ->assertJsonPath('error.message', 'This Idempotency-Key was already used with a different request.');

        $this->assertSame($before, $this->receiptState($document, $item, $key));
    }

    public function test_us_10_br_15_same_key_with_different_item_is_rejected_without_writes(): void
    {
        [$document, $item] = $this->makeOpenDocument($this->product, 10);
        $otherProduct = $this->makeProduct('T6-SECOND-'.strtoupper(Str::random(6)));
        $otherItem = $this->createItem($document, $otherProduct, 10);
        $key = 'different-item-key';

        $this->postReceipt($document, $item, 2, $key)->assertCreated();
        $before = $this->receiptState($document, $otherItem, $key);

        $this->postReceipt($document, $otherItem, 2, $key)
            ->assertConflict()
            ->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_REUSED');

        $this->assertSame($before, $this->receiptState($document, $otherItem, $key));
        $this->assertSame(0, $otherItem->refresh()->received_quantity);
        $this->assertSame(0, Inventory::query()->findOrFail($otherProduct->id)->quantity_on_hand);
    }

    public function test_us_10_rejected_attempt_does_not_store_key_and_a_corrected_request_succeeds(): void
    {
        [$document, $item] = $this->makeOpenDocument($this->product, 10);

        $this->postReceipt($document, $item, 11, 'rejected-attempt-key')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'QUANTITY_EXCEEDS_REMAINING');
        $this->assertDatabaseMissing('idempotency_keys', [
            'user_id' => $this->operator->id,
            'endpoint' => 'receipts.store',
            'idempotency_key' => 'rejected-attempt-key',
        ]);

        $this->postReceipt($document, $item, 10, 'corrected-attempt-key')
            ->assertCreated()
            ->assertJsonPath('data.item.received_quantity', 10);

        $this->assertSame(10, $item->refresh()->received_quantity);
        $this->assertSame(10, Inventory::query()->findOrFail($this->product->id)->quantity_on_hand);
        $this->assertSame(1, Receipt::query()->count());
        $this->assertSame(1, InventoryTransaction::query()->count());
    }

    public function test_us_10_replay_returns_original_success_after_document_is_completed(): void
    {
        [$document, $item] = $this->makeOpenDocument($this->product, 3);
        $key = 'replay-after-completion-key';

        $original = $this->postReceipt($document, $item, 3, $key)->assertCreated();
        Sanctum::actingAs($this->supervisor);
        $this->postJson("/api/v1/receiving-documents/{$document->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'COMPLETED');

        Sanctum::actingAs($this->operator);
        $replay = $this->postReceipt($document, $item, 3, $key)
            ->assertCreated()
            ->assertHeader('Idempotent-Replayed', 'true');

        $this->assertSame(
            $this->canonicalizeJsonValue($original->json()),
            $this->canonicalizeJsonValue($replay->json()),
        );
        $this->assertSame(DocumentStatus::Completed, $document->refresh()->status);
        $this->assertSame(1, Receipt::query()->count());
        $this->assertSame(1, InventoryTransaction::query()->count());
        $this->assertSame(3, Inventory::query()->findOrFail($this->product->id)->quantity_on_hand);
    }

    public function test_us_14_inventory_list_searches_sorts_paginates_and_includes_zero_stock(): void
    {
        $alpha = $this->makeProduct('T6-INV-ALPHA');
        $beta = $this->makeProduct('T6-INV-BETA');
        $gamma = $this->makeProduct('T6-INV-GAMMA');
        Inventory::query()->where('product_id', $gamma->id)->update(['quantity_on_hand' => 0]);

        $response = $this->getJson('/api/v1/inventory?search=t6-inv&per_page=2&page=1')
            ->assertOk()
            ->assertJsonPath('meta.page', 1)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.product_id', $alpha->id)
            ->assertJsonPath('data.0.sku', $alpha->sku)
            ->assertJsonPath('data.1.product_id', $beta->id);

        $this->assertSame(0, $response->json('data.0.quantity_on_hand'));
        $this->assertSame(0, $response->json('data.1.quantity_on_hand'));

        $this->getJson('/api/v1/inventory?search=T6-INV&per_page=2&page=2')
            ->assertOk()
            ->assertJsonPath('meta.page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.product_id', $gamma->id)
            ->assertJsonPath('data.0.quantity_on_hand', 0);
    }

    public function test_us_15_inventory_transaction_history_filters_orders_and_matches_the_contract(): void
    {
        [$firstDocument, $firstItem] = $this->makeOpenDocument($this->product, 10);
        $secondProduct = $this->makeProduct('T6-HISTORY-'.strtoupper(Str::random(6)));
        [$secondDocument, $secondItem] = $this->makeOpenDocument($secondProduct, 10);

        $first = $this->postReceipt($firstDocument, $firstItem, 2, 'history-first-key')->assertCreated();
        $second = $this->postReceipt($secondDocument, $secondItem, 3, 'history-second-key')->assertCreated();

        $this->getJson('/api/v1/inventory-transactions?product_id='.$this->product->id)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $first->json('data.transaction.id'))
            ->assertJsonPath('data.0.product.id', $this->product->id)
            ->assertJsonPath('data.0.type', 'RECEIVE')
            ->assertJsonPath('data.0.quantity', 2)
            ->assertJsonPath('data.0.balance_after', 2)
            ->assertJsonPath('data.0.receiving_document.id', $firstDocument->id)
            ->assertJsonPath('data.0.receiving_item_id', $firstItem->id)
            ->assertJsonPath('data.0.receipt_id', $first->json('data.receipt.id'))
            ->assertJsonPath('data.0.created_by.id', $this->operator->id)
            ->assertJsonStructure(['data' => [['id', 'type', 'quantity', 'balance_after', 'product', 'receiving_document', 'receiving_item_id', 'receipt_id', 'created_by', 'created_at']]]);

        $this->getJson('/api/v1/inventory-transactions?receiving_document_id='.$secondDocument->id)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $second->json('data.transaction.id'))
            ->assertJsonPath('data.0.receiving_document.id', $secondDocument->id);

        $this->getJson('/api/v1/inventory-transactions?per_page=1&page=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.id', $second->json('data.transaction.id'));

        $this->deleteJson('/api/v1/inventory-transactions/'.$first->json('data.transaction.id'))
            ->assertNotFound();
        $this->assertSame(2, InventoryTransaction::query()->count());
    }

    public function test_nfr_4_mixed_receipts_preserve_all_database_integrity_queries(): void
    {
        [$document, $item] = $this->makeOpenDocument($this->product, 10);
        $successful = $this->postReceipt($document, $item, 4, 'integrity-success-key')->assertCreated();

        $this->postReceipt($document, $item, 7, 'integrity-rejected-key')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'QUANTITY_EXCEEDS_REMAINING');
        $this->postReceipt($document, $item, 4, 'integrity-success-key')
            ->assertCreated()
            ->assertHeader('Idempotent-Replayed', 'true')
            ->assertJson($successful->json());
        $this->postReceipt($document, $item, 6, 'integrity-final-key')
            ->assertCreated()
            ->assertJsonPath('data.item.received_quantity', 10);

        $integrityQueries = [
            'SELECT i.product_id FROM inventory i LEFT JOIN inventory_transactions t ON t.product_id = i.product_id GROUP BY i.product_id, i.quantity_on_hand HAVING i.quantity_on_hand <> COALESCE(SUM(t.quantity), 0)',
            'SELECT ri.id FROM receiving_items ri LEFT JOIN receipts r ON r.receiving_item_id = ri.id GROUP BY ri.id, ri.received_quantity HAVING ri.received_quantity <> COALESCE(SUM(r.quantity), 0)',
            'SELECT id FROM receiving_items WHERE received_quantity > expected_quantity',
            'SELECT r.id FROM receipts r LEFT JOIN inventory_transactions t ON t.receipt_id = r.id WHERE t.id IS NULL',
            'SELECT id FROM (SELECT id, product_id, balance_after, SUM(quantity) OVER (PARTITION BY product_id ORDER BY id) AS expected_balance FROM inventory_transactions) x WHERE balance_after <> expected_balance',
            "SELECT d.id FROM receiving_documents d JOIN receiving_items ri ON ri.receiving_document_id = d.id WHERE d.status = 'COMPLETED' AND ri.received_quantity < ri.expected_quantity",
        ];

        foreach ($integrityQueries as $query) {
            $this->assertSame([], DB::select($query));
        }
    }

    private function postReceipt(
        ReceivingDocument $document,
        ReceivingItem $item,
        int $quantity,
        string $idempotencyKey,
    ): TestResponse {
        return $this->postJson($this->receiptPath($document, $item), ['quantity' => $quantity], [
            'Idempotency-Key' => $idempotencyKey,
        ]);
    }

    private function receiptPath(ReceivingDocument $document, ReceivingItem $item): string
    {
        return "/api/v1/receiving-documents/{$document->id}/items/{$item->id}/receipts";
    }

    private function canonicalizeJsonValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalizeJsonValue($item), $value);
        }

        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalizeJsonValue($item);
        }

        return $value;
    }

    /**
     * @return array{0: ReceivingDocument, 1: ReceivingItem}
     */
    private function makeOpenDocument(
        Product $product,
        int $expectedQuantity,
        DocumentStatus $status = DocumentStatus::Open,
    ): array {
        $statusChangedAt = $status === DocumentStatus::Draft ? null : now('UTC')->subMinute();
        $document = ReceivingDocument::factory()->create([
            'status' => $status,
            'created_by' => $this->supervisor->id,
            'status_changed_by' => $statusChangedAt === null ? null : $this->supervisor->id,
            'status_changed_at' => $statusChangedAt,
        ]);

        return [$document, $this->createItem($document, $product, $expectedQuantity)];
    }

    private function createItem(
        ReceivingDocument $document,
        Product $product,
        int $expectedQuantity,
    ): ReceivingItem {
        return $document->items()->create([
            'product_id' => $product->id,
            'expected_quantity' => $expectedQuantity,
            'received_quantity' => 0,
        ]);
    }

    private function makeProduct(string $sku): Product
    {
        $product = Product::query()->create([
            'sku' => $sku,
            'name' => 'T6 Product '.$sku,
            'created_by' => $this->supervisor->id,
        ]);

        Inventory::query()->create([
            'product_id' => $product->id,
            'quantity_on_hand' => 0,
        ]);

        return $product;
    }

    /**
     * @return array<string, mixed>
     */
    private function receiptState(
        ReceivingDocument $document,
        ReceivingItem $item,
        ?string $idempotencyKey,
    ): array {
        $idempotencyQuery = DB::table('idempotency_keys')
            ->where('user_id', $this->operator->id)
            ->where('endpoint', 'receipts.store');

        if ($idempotencyKey === null) {
            $idempotencyQuery->whereRaw('1 = 0');
        } else {
            $idempotencyQuery->where('idempotency_key', $idempotencyKey);
        }

        return [
            'document' => (array) DB::table('receiving_documents')->where('id', $document->id)->first(),
            'item' => (array) DB::table('receiving_items')->where('id', $item->id)->first(),
            'inventory' => (array) DB::table('inventory')->where('product_id', $item->product_id)->first(),
            'receipts' => DB::table('receipts')
                ->where('receiving_item_id', $item->id)
                ->orderBy('id')
                ->get()
                ->map(fn (object $row): array => (array) $row)
                ->all(),
            'transactions' => DB::table('inventory_transactions')
                ->where('receiving_item_id', $item->id)
                ->orderBy('id')
                ->get()
                ->map(fn (object $row): array => (array) $row)
                ->all(),
            'idempotency' => $idempotencyQuery
                ->orderBy('id')
                ->get()
                ->map(fn (object $row): array => (array) $row)
                ->all(),
        ];
    }
}
