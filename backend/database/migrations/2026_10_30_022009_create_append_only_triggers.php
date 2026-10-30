<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_row_mutation() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION '% on % is not allowed: table is append-only', TG_OP, TG_TABLE_NAME
                    USING ERRCODE = 'integrity_constraint_violation';
            END;
            $$ LANGUAGE plpgsql;
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER receipts_append_only
                BEFORE UPDATE OR DELETE ON receipts
                FOR EACH ROW EXECUTE FUNCTION prevent_row_mutation();
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER inventory_transactions_append_only
                BEFORE UPDATE OR DELETE ON inventory_transactions
                FOR EACH ROW EXECUTE FUNCTION prevent_row_mutation();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS inventory_transactions_append_only ON inventory_transactions');
        DB::unprepared('DROP TRIGGER IF EXISTS receipts_append_only ON receipts');
        DB::unprepared('DROP FUNCTION IF EXISTS prevent_row_mutation()');
    }
};
