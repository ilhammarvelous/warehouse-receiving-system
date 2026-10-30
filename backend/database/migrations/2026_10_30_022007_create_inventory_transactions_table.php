<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('type', 20);
            $table->integer('quantity');
            $table->bigInteger('balance_after');
            $table->foreignId('receipt_id')->constrained('receipts')->restrictOnDelete();
            $table->foreignId('receiving_item_id')->constrained('receiving_items')->restrictOnDelete();
            $table->foreignId('receiving_document_id')->constrained('receiving_documents')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique('receipt_id', 'inventory_transactions_receipt_unique');
        });

        DB::statement("ALTER TABLE inventory_transactions ADD CONSTRAINT inventory_transactions_type_check CHECK (type IN ('RECEIVE'))");
        DB::statement('ALTER TABLE inventory_transactions ADD CONSTRAINT inventory_transactions_quantity_check CHECK (quantity > 0)');
        DB::statement('ALTER TABLE inventory_transactions ADD CONSTRAINT inventory_transactions_balance_check CHECK (balance_after >= quantity)');
        DB::statement('CREATE INDEX inventory_transactions_product_idx ON inventory_transactions (product_id, id DESC)');
        DB::statement('CREATE INDEX inventory_transactions_document_idx ON inventory_transactions (receiving_document_id, id DESC)');
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
    }
};
