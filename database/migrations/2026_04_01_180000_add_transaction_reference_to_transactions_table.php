<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('transaction_reference', 26)->nullable()->after('id');
        });

        DB::table('transactions')
            ->select('id')
            ->orderBy('id')
            ->chunkById(500, function ($transactions) {
                foreach ($transactions as $transaction) {
                    do {
                        $reference = (string) Str::ulid();
                    } while (DB::table('transactions')->where('transaction_reference', $reference)->exists());

                    DB::table('transactions')
                        ->where('id', $transaction->id)
                        ->update(['transaction_reference' => $reference]);
                }
            });

        DB::statement('ALTER TABLE transactions MODIFY transaction_reference VARCHAR(26) NOT NULL');

        Schema::table('transactions', function (Blueprint $table) {
            $table->unique('transaction_reference', 'transactions_transaction_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique('transactions_transaction_reference_unique');
            $table->dropColumn('transaction_reference');
        });
    }
};
