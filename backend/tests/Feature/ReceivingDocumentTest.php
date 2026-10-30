<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ReceivingDocument;
use App\Models\User;
use App\Services\DocumentNumberGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReceivingDocumentTest extends TestCase
{
    use DatabaseTransactions;

    private User $supervisor;

    private User $operator;

    private Product $firstProduct;

    private Product $secondProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supervisor = User::factory()->create(['role' => 'SUPERVISOR']);
        $this->operator = User::factory()->create(['role' => 'OPERATOR']);
        $this->firstProduct = $this->makeProduct();
        $this->secondProduct = $this->makeProduct();
    }

    public function test_us_04_fr_doc_1_to_5_document_creation_generates_draft_number_and_items(): void
    {
        Sanctum::actingAs($this->supervisor);
        $documentsBefore = ReceivingDocument::query()->count();
        $itemsBefore = DB::table('receiving_items')->count();
        $sequenceDate = CarbonImmutable::now('UTC')->toDateString();
        $sequenceBefore = DB::table('document_number_sequences')
            ->where('sequence_date', $sequenceDate)
            ->value('last_number');

        $response = $this->postJson('/api/v1/receiving-documents', [
            'supplier_name' => '  Example Supplier  ',
            'expected_date' => '2026-11-15',
            'notes' => 'Dock 2 delivery',
            'items' => [
                ['product_id' => $this->firstProduct->id, 'expected_quantity' => 5],
                ['product_id' => $this->secondProduct->id, 'expected_quantity' => 7],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.supplier_name', 'Example Supplier')
            ->assertJsonPath('data.expected_date', '2026-11-15')
            ->assertJsonPath('data.status', 'DRAFT')
            ->assertJsonPath('data.item_count', 2)
            ->assertJsonPath('data.total_expected_quantity', 12)
            ->assertJsonPath('data.total_received_quantity', 0)
            ->assertJsonPath('data.all_items_received', false)
            ->assertJsonPath('data.created_by.id', $this->supervisor->id)
            ->assertJsonPath('data.notes', 'Dock 2 delivery')
            ->assertJsonCount(2, 'data.items')
            ->assertJsonCount(0, 'data.receipts');

        $document = ReceivingDocument::query()->where('document_number', $response->json('data.document_number'))->firstOrFail();
        $this->assertMatchesRegularExpression(
            '/^RCV-'.CarbonImmutable::now('UTC')->format('Ymd').'-[0-9]{4,}$/',
            $document->document_number,
        );
        $this->assertSame($this->supervisor->id, $document->created_by);
        $this->assertSame(DocumentStatus::Draft, $document->status);
        $this->assertSame($documentsBefore + 1, ReceivingDocument::query()->count());
        $this->assertSame($itemsBefore + 2, DB::table('receiving_items')->count());
        $this->assertSame((int) $sequenceBefore + 1, (int) DB::table('document_number_sequences')
            ->where('sequence_date', $sequenceDate)
            ->value('last_number'));
        $this->assertDatabaseHas('receiving_items', [
            'receiving_document_id' => $document->id,
            'product_id' => $this->firstProduct->id,
            'expected_quantity' => 5,
            'received_quantity' => 0,
        ]);
    }

    public function test_us_04_fr_doc_2_document_numbers_increment_atomically_for_the_same_utc_date(): void
    {
        $generator = app(DocumentNumberGenerator::class);
        $date = CarbonImmutable::parse('2026-10-30 00:30:00+02:00');

        $firstNumber = $generator->next($date);
        $secondNumber = $generator->next($date);

        $this->assertSame('RCV-20261029-0001', $firstNumber);
        $this->assertSame('RCV-20261029-0002', $secondNumber);
        $this->assertDatabaseHas('document_number_sequences', [
            'sequence_date' => '2026-10-29',
            'last_number' => 2,
        ]);
    }

    public function test_us_04_fr_doc_3_duplicate_products_are_rejected_without_writing_anything(): void
    {
        Sanctum::actingAs($this->supervisor);
        $documentsBefore = ReceivingDocument::query()->count();
        $itemsBefore = DB::table('receiving_items')->count();
        $sequencesBefore = DB::table('document_number_sequences')->count();

        $this->postJson('/api/v1/receiving-documents', [
            'supplier_name' => 'Duplicate supplier',
            'expected_date' => '2026-11-15',
            'items' => [
                ['product_id' => $this->firstProduct->id, 'expected_quantity' => 5],
                ['product_id' => (string) $this->firstProduct->id, 'expected_quantity' => 6],
            ],
        ])->assertUnprocessable()
            ->assertJsonPath('error.code', 'DUPLICATE_PRODUCT_IN_DOCUMENT')
            ->assertJsonPath('error.message', 'A product can appear only once in a receiving document.')
            ->assertJsonPath('error.details.product_ids.0', $this->firstProduct->id);

        $this->assertSame($documentsBefore, ReceivingDocument::query()->count());
        $this->assertSame($itemsBefore, DB::table('receiving_items')->count());
        $this->assertSame($sequencesBefore, DB::table('document_number_sequences')->count());
    }

    public function test_us_04_fr_doc_1_to_5_invalid_document_fields_and_items_return_422_without_writes(): void
    {
        Sanctum::actingAs($this->supervisor);
        $valid = [
            'supplier_name' => 'Valid supplier',
            'expected_date' => '2026-11-15',
            'items' => [
                ['product_id' => $this->firstProduct->id, 'expected_quantity' => 5],
            ],
        ];
        $invalidRequests = [
            array_replace($valid, ['supplier_name' => '   ']),
            array_replace($valid, ['expected_date' => '2026-02-30']),
            array_replace($valid, ['notes' => str_repeat('n', 1001)]),
            array_replace($valid, ['items' => []]),
            array_replace($valid, ['items' => [['product_id' => 999999999, 'expected_quantity' => 5]]]),
            array_replace($valid, ['items' => [['product_id' => $this->firstProduct->id, 'expected_quantity' => 0]]]),
            array_replace($valid, ['items' => [['product_id' => $this->firstProduct->id, 'expected_quantity' => -1]]]),
            array_replace($valid, ['items' => [['product_id' => $this->firstProduct->id, 'expected_quantity' => 1.5]]]),
        ];
        $documentsBefore = ReceivingDocument::query()->count();
        $itemsBefore = DB::table('receiving_items')->count();
        $sequencesBefore = DB::table('document_number_sequences')->count();

        foreach ($invalidRequests as $invalidRequest) {
            $this->postJson('/api/v1/receiving-documents', $invalidRequest)
                ->assertUnprocessable()
                ->assertJsonPath('error.code', 'VALIDATION_ERROR');
        }

        $this->assertSame($documentsBefore, ReceivingDocument::query()->count());
        $this->assertSame($itemsBefore, DB::table('receiving_items')->count());
        $this->assertSame($sequencesBefore, DB::table('document_number_sequences')->count());
    }

    public function test_br_18_operator_cannot_create_or_edit_a_document_even_with_invalid_input(): void
    {
        Sanctum::actingAs($this->operator);
        $document = $this->createDocument([
            ['product_id' => $this->firstProduct->id, 'expected_quantity' => 5],
        ]);
        Sanctum::actingAs($this->operator);
        $documentsBefore = ReceivingDocument::query()->count();
        $itemsBefore = DB::table('receiving_items')->count();

        $this->postJson('/api/v1/receiving-documents', [])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');
        $this->putJson("/api/v1/receiving-documents/{$document['id']}", [])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->assertSame($documentsBefore, ReceivingDocument::query()->count());
        $this->assertSame($itemsBefore, DB::table('receiving_items')->count());
    }

    public function test_us_06_fr_doc_8_list_search_status_filter_aggregates_and_pagination_follow_contract(): void
    {
        Sanctum::actingAs($this->supervisor);
        $search = 'T4'.strtoupper(Str::random(8));

        $first = $this->createDocument([
            ['product_id' => $this->firstProduct->id, 'expected_quantity' => 2],
        ], ['supplier_name' => $search.' Alpha']);
        $this->createDocument([
            ['product_id' => $this->secondProduct->id, 'expected_quantity' => 7],
        ], ['supplier_name' => 'Unrelated supplier '.Str::random(8)]);
        $newest = $this->createDocument([
            ['product_id' => $this->firstProduct->id, 'expected_quantity' => 3],
            ['product_id' => $this->secondProduct->id, 'expected_quantity' => 4],
        ], ['supplier_name' => $search.' Beta']);
        ReceivingDocument::query()->findOrFail($newest['id'])->update(['status' => DocumentStatus::Open]);

        $this->getJson('/api/v1/receiving-documents?search='.strtolower($search).'&per_page=1&page=1')
            ->assertOk()
            ->assertJsonPath('meta.page', 1)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('data.0.id', $newest['id'])
            ->assertJsonPath('data.0.item_count', 2)
            ->assertJsonPath('data.0.total_expected_quantity', 7)
            ->assertJsonPath('data.0.total_received_quantity', 0);

        $this->getJson('/api/v1/receiving-documents?search='.strtolower($search).'&per_page=1&page=2')
            ->assertOk()
            ->assertJsonPath('data.0.id', $first['id']);

        $this->getJson('/api/v1/receiving-documents?search='.strtolower($search).'&status=DRAFT')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $first['id']);

        $this->getJson('/api/v1/receiving-documents?status=UNKNOWN')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_us_07_fr_doc_9_fr_doc_10_document_detail_includes_items_receipts_and_audit_fields(): void
    {
        Sanctum::actingAs($this->supervisor);
        $created = $this->createDocument([
            ['product_id' => $this->firstProduct->id, 'expected_quantity' => 3],
        ], ['notes' => 'History fixture']);
        $document = ReceivingDocument::query()->findOrFail($created['id']);
        $item = $document->items()->firstOrFail();
        $item->update(['received_quantity' => 3]);
        $statusChangedAt = CarbonImmutable::now('UTC');
        $document->update([
            'status' => DocumentStatus::PartiallyReceived,
            'status_changed_by' => $this->operator->id,
            'status_changed_at' => $statusChangedAt,
        ]);
        $firstReceivedAt = $statusChangedAt->subMinute();
        $latestReceivedAt = $statusChangedAt->subSeconds(30);
        DB::table('receipts')->insert([
            [
                'receiving_item_id' => $item->id,
                'quantity' => 1,
                'received_by' => $this->operator->id,
                'created_at' => $firstReceivedAt,
            ],
            [
                'receiving_item_id' => $item->id,
                'quantity' => 2,
                'received_by' => $this->supervisor->id,
                'created_at' => $latestReceivedAt,
            ],
        ]);

        Sanctum::actingAs($this->operator);
        $this->getJson("/api/v1/receiving-documents/{$document->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $document->id)
            ->assertJsonPath('data.created_by.id', $this->supervisor->id)
            ->assertJsonPath('data.status_changed_by.id', $this->operator->id)
            ->assertJsonPath('data.all_items_received', true)
            ->assertJsonPath('data.items.0.product.id', $this->firstProduct->id)
            ->assertJsonPath('data.items.0.expected_quantity', 3)
            ->assertJsonPath('data.items.0.received_quantity', 3)
            ->assertJsonPath('data.items.0.remaining_quantity', 0)
            ->assertJsonPath('data.receipts.0.quantity', 2)
            ->assertJsonPath('data.receipts.0.received_by.id', $this->supervisor->id)
            ->assertJsonPath('data.receipts.1.quantity', 1)
            ->assertJsonPath('data.receipts.1.received_by.id', $this->operator->id)
            ->assertJsonStructure([
                'data' => [
                    'document_number',
                    'supplier_name',
                    'expected_date',
                    'status',
                    'item_count',
                    'total_expected_quantity',
                    'total_received_quantity',
                    'notes',
                    'all_items_received',
                    'created_by' => ['id', 'name'],
                    'status_changed_by' => ['id', 'name'],
                    'status_changed_at',
                    'items' => [['id', 'product' => ['id', 'sku', 'name'], 'remaining_quantity']],
                    'receipts' => [['id', 'item_id', 'quantity', 'received_by' => ['id', 'name'], 'created_at']],
                ],
            ]);
    }

    public function test_us_05_fr_doc_6_draft_update_replaces_header_and_complete_item_list(): void
    {
        Sanctum::actingAs($this->supervisor);
        $created = $this->createDocument([
            ['product_id' => $this->firstProduct->id, 'expected_quantity' => 5],
        ]);
        $documentNumber = $created['document_number'];
        $oldItemId = $created['items'][0]['id'];

        $this->putJson("/api/v1/receiving-documents/{$created['id']}", [
            'supplier_name' => '  Updated supplier  ',
            'expected_date' => '2026-12-01',
            'notes' => null,
            'items' => [
                ['product_id' => $this->secondProduct->id, 'expected_quantity' => 9],
            ],
        ])->assertOk()
            ->assertJsonPath('data.document_number', $documentNumber)
            ->assertJsonPath('data.supplier_name', 'Updated supplier')
            ->assertJsonPath('data.expected_date', '2026-12-01')
            ->assertJsonPath('data.notes', null)
            ->assertJsonPath('data.status', 'DRAFT')
            ->assertJsonPath('data.item_count', 1)
            ->assertJsonPath('data.total_expected_quantity', 9)
            ->assertJsonPath('data.items.0.product.id', $this->secondProduct->id);

        $this->assertDatabaseMissing('receiving_items', ['id' => $oldItemId]);
        $this->assertDatabaseHas('receiving_items', [
            'receiving_document_id' => $created['id'],
            'product_id' => $this->secondProduct->id,
            'expected_quantity' => 9,
            'received_quantity' => 0,
        ]);
    }

    public function test_us_05_fr_doc_7_non_draft_document_cannot_be_edited_and_database_remains_unchanged(): void
    {
        Sanctum::actingAs($this->supervisor);
        $created = $this->createDocument([
            ['product_id' => $this->firstProduct->id, 'expected_quantity' => 5],
        ]);
        $document = ReceivingDocument::query()->findOrFail($created['id']);
        $document->update(['status' => DocumentStatus::Open]);
        $itemsBefore = DB::table('receiving_items')
            ->where('receiving_document_id', $document->id)
            ->get()
            ->toArray();
        $headerBefore = [$document->supplier_name, $document->expected_date->toDateString(), $document->status->value];

        $this->putJson("/api/v1/receiving-documents/{$document->id}", [
            'supplier_name' => 'Changed supplier',
            'expected_date' => '2026-12-01',
            'items' => [
                ['product_id' => $this->secondProduct->id, 'expected_quantity' => 10],
            ],
        ])->assertConflict()
            ->assertJsonPath('error.code', 'DOCUMENT_NOT_EDITABLE')
            ->assertJsonPath('error.message', 'Only documents in DRAFT status can be edited.')
            ->assertJsonPath('error.details.status', 'OPEN');

        $document->refresh();
        $this->assertSame($headerBefore, [
            $document->supplier_name,
            $document->expected_date->toDateString(),
            $document->status->value,
        ]);
        $this->assertEquals(
            $itemsBefore,
            DB::table('receiving_items')->where('receiving_document_id', $document->id)->get()->toArray(),
        );
    }

    public function test_us_07_fr_doc_9_missing_document_returns_not_found(): void
    {
        Sanctum::actingAs($this->operator);

        $this->getJson('/api/v1/receiving-documents/999999999')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    /**
     * @param  array<int, array{product_id: int, expected_quantity: int}>  $items
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function createDocument(array $items, array $overrides = []): array
    {
        Sanctum::actingAs($this->supervisor);

        return $this->postJson('/api/v1/receiving-documents', array_replace([
            'supplier_name' => 'Supplier '.Str::random(8),
            'expected_date' => '2026-11-15',
            'items' => $items,
        ], $overrides))->assertCreated()->json('data');
    }

    private function makeProduct(): Product
    {
        $product = Product::query()->create([
            'sku' => 'T4-'.strtoupper(Str::random(8)),
            'name' => 'T4 Product',
            'created_by' => $this->supervisor->id,
        ]);

        Inventory::query()->create([
            'product_id' => $product->id,
            'quantity_on_hand' => 0,
        ]);

        return $product;
    }
}
