<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Temporarily allow NULL so backfill is always safe.
        DB::statement("ALTER TABLE transactions MODIFY direction ENUM('credit','debit') NULL");

        // 2) Backfill missing direction values from historical transaction rows.
        DB::statement("\n            UPDATE transactions\n            SET direction = CASE\n                WHEN type = 'deposit' THEN 'credit'\n                WHEN type = 'withdraw' THEN 'debit'\n                WHEN type = 'transfer' AND balance_after >= balance_before THEN 'credit'\n                ELSE 'debit'\n            END\n            WHERE direction IS NULL\n        ");

        // 3) Re-enforce NOT NULL after backfill.
        DB::statement("ALTER TABLE transactions MODIFY direction ENUM('credit','debit') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE transactions MODIFY direction ENUM('credit','debit') NULL");
    }
};
