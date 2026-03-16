<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Backfill legacy NULL bank codes first.
        DB::table('beneficiaries')
            ->whereNull('bank_code')
            ->update(['bank_code' => '999001']);

        // Deduplicate legacy rows by user/account before adding stricter uniqueness.
        DB::statement("\n            DELETE b1 FROM beneficiaries b1\n            INNER JOIN beneficiaries b2\n                ON b1.user_id = b2.user_id\n                AND b1.account_number = b2.account_number\n                AND b1.id > b2.id\n        ");

        // MySQL may currently use the old unique index to satisfy the user_id FK.
        // Add a dedicated user_id index first so dropping the old unique index is allowed.
        if (!$this->indexExists('beneficiaries', 'beneficiaries_user_id_idx')) {
            Schema::table('beneficiaries', function (Blueprint $table) {
                $table->index('user_id', 'beneficiaries_user_id_idx');
            });
        }

        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->dropUnique('beneficiaries_user_account_bank_unique');
        });

        DB::statement("ALTER TABLE beneficiaries MODIFY bank_code VARCHAR(10) NOT NULL DEFAULT '999001'");

        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->unique(['user_id', 'account_number'], 'beneficiaries_user_account_unique');
        });
    }

    public function down(): void
    {
        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->dropUnique('beneficiaries_user_account_unique');
        });

        DB::statement("ALTER TABLE beneficiaries MODIFY bank_code VARCHAR(10) NULL DEFAULT NULL");

        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->unique(['user_id', 'account_number', 'bank_code'], 'beneficiaries_user_account_bank_unique');
        });
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $tableName)
            ->where('index_name', $indexName)
            ->exists();
    }
};
