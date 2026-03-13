<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->string('bank_code', 10)->nullable()->after('bank_name');
            $table->unique(['user_id', 'account_number', 'bank_code'], 'beneficiaries_user_account_bank_unique');
        });
    }

    public function down(): void
    {
        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->dropUnique('beneficiaries_user_account_bank_unique');
            $table->dropColumn('bank_code');
        });
    }
};