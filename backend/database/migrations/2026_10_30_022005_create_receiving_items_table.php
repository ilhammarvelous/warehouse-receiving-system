<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receiving_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('receiving_document_id')->constrained('receiving_documents')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->integer('expected_quantity');
            $table->integer('received_quantity')->default(0);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->unique(['receiving_document_id', 'product_id'], 'receiving_items_document_product_unique');
        });

        DB::statement('ALTER TABLE receiving_items ADD CONSTRAINT receiving_items_expected_range_check CHECK (expected_quantity BETWEEN 1 AND 1000000)');
        DB::statement('ALTER TABLE receiving_items ADD CONSTRAINT receiving_items_received_non_negative_check CHECK (received_quantity >= 0)');
        DB::statement('ALTER TABLE receiving_items ADD CONSTRAINT receiving_items_not_over_received_check CHECK (received_quantity <= expected_quantity)');
        DB::statement('CREATE INDEX receiving_items_product_idx ON receiving_items (product_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('receiving_items');
    }
};
