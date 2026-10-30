<?php

namespace Tests\Feature;

use App\Enums\DocumentAction;
use App\Enums\DocumentStatus;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ReceivingDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReceivingDocumentLifecycleTest extends TestCase
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
        $this->product = Product::query()->create([
            'sku' => 'T5-'.strtoupper(Str::random(8)),
            'name' => 'Lifecycle product',
            'created_by' => $this->supervisor->id,
        ]);
        Inventory::query()->create([
            'product_id' => $this->product->id,
            'quantity_on_hand' => 0,
        ]);
    }

    #[DataProvider('statusActionMatrix')]
    public function test_us_08_us_12_us_13_and_br_12_every_status_action_pair_obeys_the_matrix(
        DocumentStatus $from,
        DocumentAction $action,
        ?DocumentStatus $to,
        ?string $errorCode,
        bool $fullyReceived,
    ): void {
        Sanctum::actingAs($this->supervisor);
        $document = $this->makeDocument($from, $fullyReceived);
        $documentBefore = DB::table('receiving_documents')->where('id', $document->id)->first();
        $itemsBefore = DB::table('receiving_items')
            ->where('receiving_document_id', $document->id)
            ->get()
            ->toArray();

        $response = $this->postJson("/api/v1/receiving-documents/{$document->id}/{$action->value}");

        if ($errorCode === null) {
            $response->assertOk()
                ->assertJsonPath('data.status', $to->value)
                ->assertJsonPath('data.status_changed_by.id', $this->supervisor->id)
                ->assertJsonPath('data.status_changed_at', fn ($value): bool => is_string($value));

            $document->refresh();
            $this->assertSame($to, $document->status);
            $this->assertSame($this->supervisor->id, $document->status_changed_by);
            $this->assertNotNull($document->status_changed_at);
            $this->assertDatabaseHas('receiving_documents', [
                'id' => $document->id,
                'status' => $to->value,
                'status_changed_by' => $this->supervisor->id,
            ]);
        } else {
            $response->assertConflict()
                ->assertJsonPath('error.code', $errorCode);

            if ($errorCode === 'DOCUMENT_HAS_RECEIPTS') {
                $response->assertJsonPath('error.message', 'A document that already has receipts cannot be cancelled.')
                    ->assertJsonPath('error.details.status', DocumentStatus::PartiallyReceived->value);
            } else {
                $response->assertJsonPath(
                    'error.message',
                    "A document in status {$from->value} cannot be {$action->pastParticiple()}.",
                )
                    ->assertJsonPath('error.details.from', $from->value)
                    ->assertJsonPath('error.details.action', $action->value);
            }

            $this->assertEquals($documentBefore, DB::table('receiving_documents')->where('id', $document->id)->first());
        }

        $this->assertEquals(
            $itemsBefore,
            DB::table('receiving_items')->where('receiving_document_id', $document->id)->get()->toArray(),
        );
    }

    public static function statusActionMatrix(): array
    {
        return [
            'DRAFT confirm' => [DocumentStatus::Draft, DocumentAction::Confirm, DocumentStatus::Open, null, false],
            'OPEN confirm' => [DocumentStatus::Open, DocumentAction::Confirm, null, 'INVALID_STATUS_TRANSITION', false],
            'PARTIALLY_RECEIVED confirm' => [DocumentStatus::PartiallyReceived, DocumentAction::Confirm, null, 'INVALID_STATUS_TRANSITION', false],
            'COMPLETED confirm' => [DocumentStatus::Completed, DocumentAction::Confirm, null, 'INVALID_STATUS_TRANSITION', true],
            'CANCELLED confirm' => [DocumentStatus::Cancelled, DocumentAction::Confirm, null, 'INVALID_STATUS_TRANSITION', false],
            'DRAFT complete' => [DocumentStatus::Draft, DocumentAction::Complete, null, 'INVALID_STATUS_TRANSITION', false],
            'OPEN complete' => [DocumentStatus::Open, DocumentAction::Complete, null, 'INVALID_STATUS_TRANSITION', false],
            'PARTIALLY_RECEIVED complete' => [DocumentStatus::PartiallyReceived, DocumentAction::Complete, DocumentStatus::Completed, null, true],
            'COMPLETED complete' => [DocumentStatus::Completed, DocumentAction::Complete, null, 'INVALID_STATUS_TRANSITION', true],
            'CANCELLED complete' => [DocumentStatus::Cancelled, DocumentAction::Complete, null, 'INVALID_STATUS_TRANSITION', false],
            'DRAFT cancel' => [DocumentStatus::Draft, DocumentAction::Cancel, DocumentStatus::Cancelled, null, false],
            'OPEN cancel' => [DocumentStatus::Open, DocumentAction::Cancel, DocumentStatus::Cancelled, null, false],
            'PARTIALLY_RECEIVED cancel' => [DocumentStatus::PartiallyReceived, DocumentAction::Cancel, null, 'DOCUMENT_HAS_RECEIPTS', false],
            'COMPLETED cancel' => [DocumentStatus::Completed, DocumentAction::Cancel, null, 'INVALID_STATUS_TRANSITION', true],
            'CANCELLED cancel' => [DocumentStatus::Cancelled, DocumentAction::Cancel, null, 'INVALID_STATUS_TRANSITION', false],
        ];
    }

    public function test_br_08_us_12_incomplete_items_block_completion_without_changing_the_database(): void
    {
        Sanctum::actingAs($this->supervisor);
        $document = $this->makeDocument(DocumentStatus::PartiallyReceived);
        $documentBefore = DB::table('receiving_documents')->where('id', $document->id)->first();
        $item = $document->items()->firstOrFail();

        $this->postJson("/api/v1/receiving-documents/{$document->id}/complete")
            ->assertConflict()
            ->assertJsonPath('error.code', 'DOCUMENT_NOT_FULLY_RECEIVED')
            ->assertJsonPath('error.message', 'All items must be fully received before the document can be completed.')
            ->assertJsonPath('error.details.incomplete_items.0.item_id', $item->id)
            ->assertJsonPath('error.details.incomplete_items.0.remaining_quantity', 3);

        $this->assertEquals($documentBefore, DB::table('receiving_documents')->where('id', $document->id)->first());
        $this->assertDatabaseHas('receiving_items', [
            'id' => $item->id,
            'expected_quantity' => 5,
            'received_quantity' => 2,
        ]);
    }

    public function test_br_18_operator_cannot_confirm_complete_or_cancel_a_document(): void
    {
        $document = $this->makeDocument(DocumentStatus::Draft);
        $documentBefore = DB::table('receiving_documents')->where('id', $document->id)->first();
        Sanctum::actingAs($this->operator);

        foreach (DocumentAction::cases() as $action) {
            $this->postJson("/api/v1/receiving-documents/{$document->id}/{$action->value}")
                ->assertForbidden()
                ->assertJsonPath('error.code', 'FORBIDDEN');
        }

        $this->assertEquals($documentBefore, DB::table('receiving_documents')->where('id', $document->id)->first());
    }

    public function test_us_08_us_12_us_13_missing_document_returns_not_found_for_each_action(): void
    {
        Sanctum::actingAs($this->supervisor);

        foreach (DocumentAction::cases() as $action) {
            $this->postJson("/api/v1/receiving-documents/999999999/{$action->value}")
                ->assertNotFound()
                ->assertJsonPath('error.code', 'NOT_FOUND');
        }
    }

    private function makeDocument(DocumentStatus $status, bool $fullyReceived = false): ReceivingDocument
    {
        $statusChangedAt = $status === DocumentStatus::Draft ? null : now('UTC')->subMinute();
        $document = ReceivingDocument::factory()->create([
            'status' => $status,
            'created_by' => $this->supervisor->id,
            'status_changed_by' => $statusChangedAt === null ? null : $this->supervisor->id,
            'status_changed_at' => $statusChangedAt,
        ]);

        // T6 has not implemented receipt creation; partial fixtures set received_quantity without a receipt row.
        $receivedQuantity = match ($status) {
            DocumentStatus::PartiallyReceived => $fullyReceived ? 5 : 2,
            DocumentStatus::Completed => 5,
            default => 0,
        };

        $document->items()->create([
            'product_id' => $this->product->id,
            'expected_quantity' => 5,
            'received_quantity' => $receivedQuantity,
        ]);

        return $document;
    }
}
