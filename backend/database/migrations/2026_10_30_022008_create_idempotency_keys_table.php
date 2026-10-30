<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('endpoint', 100);
            $table->string('idempotency_key', 128);
            $table->char('request_hash', 64);
            $table->foreignId('receipt_id')->nullable()->constrained('receipts')->restrictOnDelete();
            $table->smallInteger('response_status');
            $table->jsonb('response_body');
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['user_id', 'endpoint', 'idempotency_key'], 'idempotency_keys_scope_unique');
        });

        DB::statement('ALTER TABLE idempotency_keys ADD CONSTRAINT idempotency_keys_key_length_check CHECK (char_length(idempotency_key) BETWEEN 8 AND 128)');
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
