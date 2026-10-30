<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('sku', 50);
            $table->string('name');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });

        DB::statement("ALTER TABLE products ADD CONSTRAINT products_sku_format_check CHECK (sku ~ '^[A-Z0-9]([A-Z0-9-]*[A-Z0-9])?$')");
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_name_not_blank_check CHECK (char_length(btrim(name)) >= 1)');
        DB::statement('CREATE UNIQUE INDEX products_sku_unique ON products (sku)');
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
