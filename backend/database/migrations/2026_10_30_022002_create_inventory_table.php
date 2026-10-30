<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory', function (Blueprint $table): void {
            $table->foreignId('product_id')->primary()->constrained('products')->restrictOnDelete();
            $table->bigInteger('quantity_on_hand')->default(0);
            $table->timestampTz('updated_at')->useCurrent();
        });

        DB::statement('ALTER TABLE inventory ADD CONSTRAINT inventory_quantity_non_negative_check CHECK (quantity_on_hand >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory');
    }
};
