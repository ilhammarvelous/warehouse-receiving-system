<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receiving_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('document_number', 30);
            $table->string('supplier_name');
            $table->date('expected_date');
            $table->string('notes', 1000)->nullable();
            $table->string('status', 30)->default('DRAFT');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('status_changed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('status_changed_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });

        DB::statement("ALTER TABLE receiving_documents ADD CONSTRAINT receiving_documents_status_check CHECK (status IN ('DRAFT', 'OPEN', 'PARTIALLY_RECEIVED', 'COMPLETED', 'CANCELLED'))");
        DB::statement("ALTER TABLE receiving_documents ADD CONSTRAINT receiving_documents_number_format_check CHECK (document_number ~ '^RCV-[0-9]{8}-[0-9]{4,}$')");
        DB::statement('ALTER TABLE receiving_documents ADD CONSTRAINT receiving_documents_supplier_not_blank_check CHECK (char_length(btrim(supplier_name)) >= 1)');
        DB::statement('ALTER TABLE receiving_documents ADD CONSTRAINT receiving_documents_status_actor_pair_check CHECK ((status_changed_by IS NULL) = (status_changed_at IS NULL))');
        DB::statement('CREATE UNIQUE INDEX receiving_documents_number_unique ON receiving_documents (document_number)');
        DB::statement('CREATE INDEX receiving_documents_status_created_idx ON receiving_documents (status, id DESC)');
        DB::statement('CREATE INDEX receiving_documents_created_idx ON receiving_documents (id DESC)');
    }

    public function down(): void
    {
        Schema::dropIfExists('receiving_documents');
    }
};
