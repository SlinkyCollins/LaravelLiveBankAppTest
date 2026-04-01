<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('sender_account_name', 100)->nullable()->after('transaction_reference');
            $table->string('sender_account_number', 32)->nullable()->after('sender_account_name');
            $table->string('recipient_account_name', 100)->nullable()->after('sender_account_number');
            $table->string('recipient_account_number', 32)->nullable()->after('recipient_account_name');
        });

        DB::table('transactions')
            ->select('id', 'user_id', 'beneficiary_id', 'type', 'direction', 'amount', 'created_at')
            ->orderBy('id')
            ->chunkById(500, function ($transactions) {
                foreach ($transactions as $transaction) {
                    $owner = DB::table('users')
                        ->select('name', 'account_number')
                        ->where('id', $transaction->user_id)
                        ->first();

                    $senderAccountName = 'N/A';
                    $senderAccountNumber = 'N/A';
                    $recipientAccountName = 'N/A';
                    $recipientAccountNumber = 'N/A';

                    if ($transaction->type === 'deposit') {
                        $senderAccountName = 'Vaultly Funding';
                        $senderAccountNumber = 'SYSTEM';
                        $recipientAccountName = $owner->name ?? 'N/A';
                        $recipientAccountNumber = $owner->account_number ?? 'N/A';
                    } elseif ($transaction->type === 'withdraw') {
                        $senderAccountName = $owner->name ?? 'N/A';
                        $senderAccountNumber = $owner->account_number ?? 'N/A';
                        $recipientAccountName = 'Cash Withdrawal';
                        $recipientAccountNumber = 'CASH';
                    } elseif ($transaction->type === 'transfer') {
                        if ($transaction->direction === 'debit') {
                            $senderAccountName = $owner->name ?? 'N/A';
                            $senderAccountNumber = $owner->account_number ?? 'N/A';

                            if ($transaction->beneficiary_id) {
                                $beneficiary = DB::table('beneficiaries')
                                    ->select('account_name', 'account_number')
                                    ->where('id', $transaction->beneficiary_id)
                                    ->first();

                                $recipientAccountName = $beneficiary->account_name ?? 'N/A';
                                $recipientAccountNumber = $beneficiary->account_number ?? 'N/A';
                            } else {
                                // Best-effort recipient lookup for legacy outgoing transfer rows
                                // where beneficiary_id was not stored.
                                $counterparty = DB::table('transactions')
                                    ->select('user_id')
                                    ->where('type', 'transfer')
                                    ->where('direction', 'credit')
                                    ->where('amount', $transaction->amount)
                                    ->where('created_at', $transaction->created_at)
                                    ->where('id', '!=', $transaction->id)
                                    ->first();

                                if ($counterparty) {
                                    $recipient = DB::table('users')
                                        ->select('name', 'account_number')
                                        ->where('id', $counterparty->user_id)
                                        ->first();

                                    $recipientAccountName = $recipient->name ?? 'Recipient Account';
                                    $recipientAccountNumber = $recipient->account_number ?? 'N/A';
                                } else {
                                    $recipientAccountName = 'Recipient Account';
                                    $recipientAccountNumber = 'N/A';
                                }
                            }
                        } else {
                            $recipientAccountName = $owner->name ?? 'N/A';
                            $recipientAccountNumber = $owner->account_number ?? 'N/A';

                            // Best-effort source lookup for legacy incoming transfer rows.
                            $counterparty = DB::table('transactions')
                                ->select('user_id')
                                ->where('type', 'transfer')
                                ->where('direction', 'debit')
                                ->where('amount', $transaction->amount)
                                ->where('created_at', $transaction->created_at)
                                ->where('id', '!=', $transaction->id)
                                ->first();

                            if ($counterparty) {
                                $sender = DB::table('users')
                                    ->select('name', 'account_number')
                                    ->where('id', $counterparty->user_id)
                                    ->first();

                                $senderAccountName = $sender->name ?? 'Transfer Sender';
                                $senderAccountNumber = $sender->account_number ?? 'N/A';
                            } else {
                                $senderAccountName = 'Transfer Sender';
                                $senderAccountNumber = 'N/A';
                            }
                        }
                    }

                    DB::table('transactions')
                        ->where('id', $transaction->id)
                        ->update([
                            'sender_account_name' => $senderAccountName,
                            'sender_account_number' => $senderAccountNumber,
                            'recipient_account_name' => $recipientAccountName,
                            'recipient_account_number' => $recipientAccountNumber,
                        ]);
                }
            });

        DB::table('transactions')->whereNull('sender_account_name')->update(['sender_account_name' => 'N/A']);
        DB::table('transactions')->whereNull('sender_account_number')->update(['sender_account_number' => 'N/A']);
        DB::table('transactions')->whereNull('recipient_account_name')->update(['recipient_account_name' => 'N/A']);
        DB::table('transactions')->whereNull('recipient_account_number')->update(['recipient_account_number' => 'N/A']);

        DB::statement("ALTER TABLE transactions MODIFY sender_account_name VARCHAR(100) NOT NULL DEFAULT 'N/A'");
        DB::statement("ALTER TABLE transactions MODIFY sender_account_number VARCHAR(32) NOT NULL DEFAULT 'N/A'");
        DB::statement("ALTER TABLE transactions MODIFY recipient_account_name VARCHAR(100) NOT NULL DEFAULT 'N/A'");
        DB::statement("ALTER TABLE transactions MODIFY recipient_account_number VARCHAR(32) NOT NULL DEFAULT 'N/A'");
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn([
                'sender_account_name',
                'sender_account_number',
                'recipient_account_name',
                'recipient_account_number',
            ]);
        });
    }
};
