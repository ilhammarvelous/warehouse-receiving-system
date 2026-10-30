<?php

namespace Tests\Feature;

use App\Models\IdempotencyKey;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\ReceivingDocument;
use App\Models\ReceivingItem;
use App\Models\User;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseProtectionTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private User $otherUser;

    private Product $product;

    private ReceivingDocument $document;

    private ReceivingItem $item;

    private Receipt $receipt;

    private InventoryTransaction $inventoryTransaction;

    private IdempotencyKey $idempotencyKey;

    private int $documentNumber = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();

        $this->product = Product::query()->create([
            'sku' => 'T1-BASE',
            'name' => 'Base product',
            'created_by' => $this->user->id,
        ]);

        Inventory::query()->create([
            'product_id' => $this->product->id,
            'quantity_on_hand' => 1,
        ]);

        $this->document = ReceivingDocument::query()->create([
            'document_number' => $this->nextDocumentNumber(),
            'supplier_name' => 'Fixture supplier',
            'expected_date' => now()->toDateString(),
            'created_by' => $this->user->id,
        ]);

        $this->item = ReceivingItem::query()->create([
            'receiving_document_id' => $this->document->id,
            'product_id' => $this->product->id,
            'expected_quantity' => 5,
            'received_quantity' => 1,
        ]);

        $this->receipt = Receipt::query()->create([
            'receiving_item_id' => $this->item->id,
            'quantity' => 1,
            'received_by' => $this->user->id,
        ]);

        $this->inventoryTransaction = InventoryTransaction::query()->create([
            'product_id' => $this->product->id,
            'type' => 'RECEIVE',
            'quantity' => 1,
            'balance_after' => 1,
            'receipt_id' => $this->receipt->id,
            'receiving_item_id' => $this->item->id,
            'receiving_document_id' => $this->document->id,
            'created_by' => $this->user->id,
        ]);

        $this->idempotencyKey = IdempotencyKey::query()->create([
            'user_id' => $this->user->id,
            'endpoint' => '/receipts',
            'idempotency_key' => 'fixture-key-01',
            'request_hash' => str_repeat('a', 64),
            'receipt_id' => $this->receipt->id,
            'response_status' => 201,
            'response_body' => ['data' => ['receipt_id' => $this->receipt->id]],
        ]);
    }

    public function test_br01_database_rejects_invalid_and_duplicate_product_skus(): void
    {
        $this->assertDatabaseError('23514', 'products_sku_format_check', fn () => DB::table('products')->insert([
            'sku' => 'lowercase',
            'name' => 'Invalid product',
            'created_by' => $this->user->id,
        ]));

        $this->assertDatabaseError('23505', 'products_sku_unique', fn () => DB::table('products')->insert([
            'sku' => $this->product->sku,
            'name' => 'Duplicate product',
            'created_by' => $this->user->id,
        ]));
    }

    public function test_br02_database_rejects_duplicate_product_lines_in_a_document(): void
    {
        $this->assertDatabaseError('23505', 'receiving_items_document_product_unique', fn () => DB::table('receiving_items')->insert([
            'receiving_document_id' => $this->document->id,
            'product_id' => $this->product->id,
            'expected_quantity' => 2,
            'received_quantity' => 0,
        ]));
    }

    public function test_br03_database_rejects_invalid_quantity_ranges(): void
    {
        foreach ([0, 1_000_001] as $quantity) {
            $documentId = $this->insertDocument();

            $this->assertDatabaseError('23514', 'receiving_items_expected_range_check', fn () => DB::table('receiving_items')->insert([
                'receiving_document_id' => $documentId,
                'product_id' => $this->product->id,
                'expected_quantity' => $quantity,
                'received_quantity' => 0,
            ]));
        }

        $this->assertDatabaseError('23514', 'receiving_items_received_non_negative_check', fn () => DB::table('receiving_items')
            ->where('id', $this->item->id)
            ->update(['received_quantity' => -1]));

        $this->assertDatabaseError('23514', 'receipts_quantity_positive_check', fn () => DB::table('receipts')->insert([
            'receiving_item_id' => $this->item->id,
            'quantity' => 0,
            'received_by' => $this->user->id,
        ]));

        $this->assertDatabaseError('23514', 'inventory_quantity_non_negative_check', fn () => DB::table('inventory')
            ->where('product_id', $this->product->id)
            ->update(['quantity_on_hand' => -1]));
    }

    public function test_br07_database_rejects_over_received_items(): void
    {
        $this->assertDatabaseError('23514', 'receiving_items_not_over_received_check', fn () => DB::table('receiving_items')
            ->where('id', $this->item->id)
            ->update(['received_quantity' => 6]));
    }

    public function test_br12_database_rejects_unsupported_document_statuses(): void
    {
        $documentId = $this->insertDocument();

        $this->assertDatabaseError('23514', 'receiving_documents_status_check', fn () => DB::table('receiving_documents')
            ->where('id', $documentId)
            ->update(['status' => 'UNKNOWN']));
    }

    public function test_br13_database_allows_only_one_transaction_per_receipt(): void
    {
        $this->assertDatabaseError('23505', 'inventory_transactions_receipt_unique', fn () => DB::table('inventory_transactions')->insert([
            'product_id' => $this->product->id,
            'type' => 'RECEIVE',
            'quantity' => 1,
            'balance_after' => 2,
            'receipt_id' => $this->receipt->id,
            'receiving_item_id' => $this->item->id,
            'receiving_document_id' => $this->document->id,
            'created_by' => $this->user->id,
        ]));
    }

    public function test_br15_database_rejects_reused_idempotency_scope(): void
    {
        $this->assertDatabaseError('23505', 'idempotency_keys_scope_unique', fn () => DB::table('idempotency_keys')->insert([
            'user_id' => $this->user->id,
            'endpoint' => '/receipts',
            'idempotency_key' => 'fixture-key-01',
            'request_hash' => str_repeat('b', 64),
            'receipt_id' => $this->receipt->id,
            'response_status' => 201,
            'response_body' => '{}',
        ]));
    }

    public function test_br16_receipts_and_inventory_transactions_are_append_only(): void
    {
        foreach ([
            ['UPDATE receipts SET quantity = 2 WHERE id = ?', [$this->receipt->id]],
            ['DELETE FROM receipts WHERE id = ?', [$this->receipt->id]],
            ['UPDATE inventory_transactions SET balance_after = 2 WHERE id = ?', [$this->inventoryTransaction->id]],
            ['DELETE FROM inventory_transactions WHERE id = ?', [$this->inventoryTransaction->id]],
        ] as [$sql, $bindings]) {
            $this->assertDatabaseError('23000', 'append-only', fn () => DB::statement($sql, $bindings));
        }
    }

    public function test_br19_database_rejects_duplicate_document_numbers(): void
    {
        $this->assertDatabaseError('23505', 'receiving_documents_number_unique', fn () => DB::table('receiving_documents')->insert([
            'document_number' => $this->document->document_number,
            'supplier_name' => 'Duplicate supplier',
            'expected_date' => now()->toDateString(),
            'created_by' => $this->user->id,
        ]));
    }

    public function test_br23_database_restricts_deleting_products_in_use(): void
    {
        $this->assertDatabaseError('23503', null, fn () => DB::table('products')
            ->where('id', $this->product->id)
            ->delete());
    }

    public function test_nfr4_database_enforces_remaining_check_constraints(): void
    {
        $documentId = $this->insertDocument();

        $checks = [
            ['users_role_check', fn () => DB::table('users')->insert([
                'name' => 'Invalid role',
                'email' => 'invalid-role@example.test',
                'password' => 'hashed-password',
                'role' => 'ADMIN',
            ])],
            ['products_name_not_blank_check', fn () => DB::table('products')->insert([
                'sku' => 'T1-BLANK',
                'name' => '   ',
                'created_by' => $this->user->id,
            ])],
            ['document_number_sequences_positive_check', fn () => DB::table('document_number_sequences')->insert([
                'sequence_date' => '2099-12-31',
                'last_number' => 0,
            ])],
            ['receiving_documents_number_format_check', fn () => DB::table('receiving_documents')
                ->where('id', $documentId)
                ->update(['document_number' => 'INVALID'])],
            ['receiving_documents_supplier_not_blank_check', fn () => DB::table('receiving_documents')
                ->where('id', $documentId)
                ->update(['supplier_name' => '   '])],
            ['receiving_documents_status_actor_pair_check', fn () => DB::table('receiving_documents')
                ->where('id', $documentId)
                ->update(['status_changed_by' => $this->user->id])],
            ['inventory_transactions_type_check', fn () => $this->insertInventoryTransaction(['type' => 'ISSUE'])],
            ['inventory_transactions_quantity_check', fn () => $this->insertInventoryTransaction(['quantity' => 0])],
            ['inventory_transactions_balance_check', fn () => $this->insertInventoryTransaction([
                'quantity' => 2,
                'balance_after' => 1,
            ])],
            ['idempotency_keys_key_length_check', fn () => DB::table('idempotency_keys')->insert([
                'user_id' => $this->otherUser->id,
                'endpoint' => '/receipts',
                'idempotency_key' => 'short',
                'request_hash' => str_repeat('c', 64),
                'receipt_id' => null,
                'response_status' => 201,
                'response_body' => '{}',
            ])],
        ];

        foreach ($checks as [$constraint, $operation]) {
            $this->assertDatabaseError('23514', $constraint, $operation);
        }
    }

    public function test_nfr4_database_enforces_remaining_unique_constraints(): void
    {
        DB::table('document_number_sequences')->insert([
            'sequence_date' => '2099-12-30',
            'last_number' => 1,
        ]);

        $token = [
            'tokenable_type' => User::class,
            'tokenable_id' => $this->user->id,
            'name' => 'test-token',
            'token' => str_repeat('d', 64),
            'abilities' => '["*"]',
        ];
        DB::table('personal_access_tokens')->insert($token);

        $checks = [
            ['users_email_unique', fn () => DB::table('users')->insert([
                'name' => 'Duplicate email',
                'email' => strtoupper($this->user->email),
                'password' => 'hashed-password',
                'role' => 'OPERATOR',
            ])],
            ['inventory_pkey', fn () => DB::table('inventory')->insert([
                'product_id' => $this->product->id,
                'quantity_on_hand' => 0,
            ])],
            ['document_number_sequences_pkey', fn () => DB::table('document_number_sequences')->insert([
                'sequence_date' => '2099-12-30',
                'last_number' => 2,
            ])],
            ['personal_access_tokens_token_unique', fn () => DB::table('personal_access_tokens')->insert($token)],
        ];

        foreach ($checks as [$constraint, $operation]) {
            $this->assertDatabaseError('23505', $constraint, $operation);
        }
    }

    public function test_nfr4_database_rejects_invalid_foreign_keys(): void
    {
        $checks = [
            ['products_created_by_foreign', fn () => DB::table('products')
                ->where('id', $this->product->id)
                ->update(['created_by' => 0])],
            ['inventory_product_id_foreign', fn () => DB::table('inventory')
                ->where('product_id', $this->product->id)
                ->update(['product_id' => 0])],
            ['receiving_documents_created_by_foreign', fn () => DB::table('receiving_documents')
                ->where('id', $this->document->id)
                ->update(['created_by' => 0])],
            ['receiving_documents_status_changed_by_foreign', fn () => DB::table('receiving_documents')
                ->where('id', $this->document->id)
                ->update([
                    'status_changed_by' => 0,
                    'status_changed_at' => now(),
                ])],
            ['receiving_items_receiving_document_id_foreign', fn () => DB::table('receiving_items')
                ->where('id', $this->item->id)
                ->update(['receiving_document_id' => 0])],
            ['receiving_items_product_id_foreign', fn () => DB::table('receiving_items')
                ->where('id', $this->item->id)
                ->update(['product_id' => 0])],
            ['receipts_receiving_item_id_foreign', fn () => DB::table('receipts')->insert([
                'receiving_item_id' => 0,
                'quantity' => 1,
                'received_by' => $this->user->id,
            ])],
            ['receipts_received_by_foreign', fn () => DB::table('receipts')->insert([
                'receiving_item_id' => $this->item->id,
                'quantity' => 1,
                'received_by' => 0,
            ])],
            ['idempotency_keys_user_id_foreign', fn () => DB::table('idempotency_keys')
                ->where('id', $this->idempotencyKey->id)
                ->update(['user_id' => 0])],
            ['idempotency_keys_receipt_id_foreign', fn () => DB::table('idempotency_keys')
                ->where('id', $this->idempotencyKey->id)
                ->update(['receipt_id' => 0])],
        ];

        foreach ($checks as [$constraint, $operation]) {
            $this->assertDatabaseError('23503', $constraint, $operation);
        }
    }

    public function test_nfr4_inventory_transactions_reject_invalid_foreign_keys(): void
    {
        $foreignKeys = [
            'inventory_transactions_product_id_foreign' => ['product_id' => 0],
            'inventory_transactions_receipt_id_foreign' => ['receipt_id' => 0],
            'inventory_transactions_receiving_item_id_foreign' => ['receiving_item_id' => 0],
            'inventory_transactions_receiving_document_id_foreign' => ['receiving_document_id' => 0],
            'inventory_transactions_created_by_foreign' => ['created_by' => 0],
        ];

        foreach ($foreignKeys as $constraint => $invalidValue) {
            $this->assertDatabaseError('23503', $constraint, fn () => $this->insertInventoryTransaction(
                $invalidValue,
                $this->insertReceipt(),
            ));
        }
    }

    private function assertDatabaseError(string $sqlState, ?string $constraint, Closure $operation): void
    {
        try {
            DB::transaction($operation);
        } catch (QueryException $exception) {
            $previous = $exception->getPrevious();
            $this->assertSame($sqlState, $previous?->getCode());

            if ($constraint !== null) {
                $this->assertStringContainsString($constraint, $previous?->getMessage() ?? $exception->getMessage());
            }

            return;
        }

        $this->fail('Expected PostgreSQL to reject an invalid database operation.');
    }

    private function insertDocument(array $overrides = []): int
    {
        $document = array_replace([
            'document_number' => $this->nextDocumentNumber(),
            'supplier_name' => 'Constraint test supplier',
            'expected_date' => now()->toDateString(),
            'created_by' => $this->user->id,
        ], $overrides);

        return DB::table('receiving_documents')->insertGetId($document);
    }

    private function insertInventoryTransaction(array $overrides = [], ?int $receiptId = null): bool
    {
        $transaction = array_replace([
            'product_id' => $this->product->id,
            'type' => 'RECEIVE',
            'quantity' => 1,
            'balance_after' => 1,
            'receipt_id' => $receiptId ?? $this->insertReceipt(),
            'receiving_item_id' => $this->item->id,
            'receiving_document_id' => $this->document->id,
            'created_by' => $this->user->id,
        ], $overrides);

        return DB::table('inventory_transactions')->insert($transaction);
    }

    private function insertReceipt(): int
    {
        return DB::table('receipts')->insertGetId([
            'receiving_item_id' => $this->item->id,
            'quantity' => 1,
            'received_by' => $this->otherUser->id,
        ]);
    }

    private function nextDocumentNumber(): string
    {
        return sprintf('RCV-%s-%04d', now()->format('Ymd'), $this->documentNumber++);
    }
}
