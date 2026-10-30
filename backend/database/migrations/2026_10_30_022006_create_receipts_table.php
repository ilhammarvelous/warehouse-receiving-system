<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('receiving_item_id')->constrained('receiving_items')->restrictOnDelete();
            $table->integer('quantity');
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
        });

        DB::statement('ALTER TABLE receipts ADD CONSTRAINT receipts_quantity_positive_check CHECK (quantity > 0)');
        DB::statement('CREATE INDEX receipts_item_idx ON receipts (receiving_item_id, id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
