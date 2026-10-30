<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_number_sequences', function (Blueprint $table): void {
            $table->date('sequence_date')->primary();
            $table->integer('last_number');
        });

        DB::statement('ALTER TABLE document_number_sequences ADD CONSTRAINT document_number_sequences_positive_check CHECK (last_number >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('document_number_sequences');
    }
};
