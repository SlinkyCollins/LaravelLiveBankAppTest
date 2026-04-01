<?php

namespace App\Http\Controllers;

use App\Models\Beneficiary;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class TransactionController extends Controller
{
    private const TX_RETRY_ATTEMPTS = 5;

    public function deposit(Request $req)
    {
        // 1. Validate the request
        $validation = Validator::make($req->all(), [
            'amount' => ['required', 'numeric', 'min:100', 'max:10000000'],
        ], [
            'amount.min' => 'Minimum deposit is ₦100.',
            'amount.max' => 'Maximum deposit is ₦10,000,000.',
        ]);

        if ($validation->fails()) {
            return response()->json([
                'status' => '422',
                'msg' => $validation->errors()
            ]);
        }

        // 2. Get the authenticated user
        $userId = $req->user()->id;
        $amount = $req->input('amount');

        // 3. Start DB transaction (atomic)
        $transaction = null;
        $newBalance = null;
        try {
            DB::transaction(function () use ($userId, $amount, &$transaction, &$newBalance) {
                // 4. Lock the user row for a safe read-modify-write cycle
                $user = User::whereKey($userId)->lockForUpdate()->firstOrFail();

                // 5. Record balance before
                $balanceBefore = $user->balance;

                // 6. Update user's balance atomically in DB
                $user->increment('balance', $amount);
                $user->refresh();
                $newBalance = $user->balance;

                // 7. Create transaction record
                $transaction = Transaction::create([
                    'user_id' => $user->id,
                    'type' => 'deposit',
                    'direction' => 'credit',
                    'amount' => $amount,
                    'sender_account_name' => 'Vaultly Funding',
                    'sender_account_number' => 'SYSTEM',
                    'recipient_account_name' => $user->name,
                    'recipient_account_number' => $user->account_number,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $newBalance,
                ]);
            }, self::TX_RETRY_ATTEMPTS);
        } catch (\Exception $e) {
            return response()->json([
                'status' => '500',
                'msg' => 'Deposit failed. Please try again.'
            ]);
        }

        // 7. Return JSON response
        return response()->json([
            'status' => '200',
            'msg' => 'Deposit successful!',
            'transaction' => $transaction,
            'new_balance' => $newBalance,
        ]);
    }

    public function verifyAccount(Request $req)
    {
        $validation = Validator::make($req->all(), [
            'account_number' => ['required', 'string', 'size:12'],
        ]);

        if ($validation->fails()) {
            return response()->json([
                'status' => '422',
                'msg' => $validation->errors()
            ]);
        }

        $sender = $req->user();

        // Prevent sending to yourself
        if ($sender->account_number === $req->account_number) {
            return response()->json([
                'status' => '400',
                'msg' => 'You cannot transfer to your own account.'
            ]);
        }

        $recipient = User::where('account_number', $req->account_number)->first();

        if (!$recipient) {
            return response()->json([
                'status' => '404',
                'msg' => 'Account not found.'
            ]);
        }

        return response()->json([
            'status' => '200',
            'msg' => 'Account verified.',
            'account_name' => $recipient->name,
        ]);
    }

    public function transfer(Request $req)
    {
        $sender = $req->user();

        // Ensure users without a configured transaction PIN get a consistent 403 response.
        if (!$sender->transaction_pin) {
            return response()->json([
                'status' => '403',
                'msg' => 'Please set your transaction PIN before making transfers.'
            ]);
        }

        $validation = Validator::make($req->all(), [
            'beneficiary_id' => ['nullable', 'integer'],
            'account_number' => ['required_without:beneficiary_id', 'string', 'size:12'],
            'amount' => ['required', 'numeric', 'min:100', 'max:10000000'],
            'pin' => ['required', 'string', 'size:4', 'regex:/^\d{4}$/'],
            'save_beneficiary' => ['nullable', 'boolean'],
        ], [
            'amount.min' => 'Minimum transfer is ₦100.',
            'amount.max' => 'Maximum transfer is ₦10,000,000.',
            'pin.regex' => 'PIN must be exactly 4 digits.',
        ]);

        if ($validation->fails()) {
            return response()->json([
                'status' => '422',
                'msg' => $validation->errors()
            ]);
        }

        $selectedBeneficiary = null;
        $targetAccountNumber = $req->account_number;

        if ($req->filled('beneficiary_id')) {
            $selectedBeneficiary = Beneficiary::where('user_id', $sender->id)
                ->where('id', $req->beneficiary_id)
                ->first();

            if (!$selectedBeneficiary) {
                return response()->json([
                    'status' => '404',
                    'msg' => 'Saved beneficiary not found.'
                ]);
            }

            $targetAccountNumber = $selectedBeneficiary->account_number;
        }

        // Prevent sending to yourself
        if ($sender->account_number === $targetAccountNumber) {
            return response()->json([
                'status' => '400',
                'msg' => 'You cannot transfer to your own account.'
            ]);
        }

        // Verify recipient exists using account number (source of truth)
        $recipient = User::where('account_number', $targetAccountNumber)->first();

        if (!$recipient) {
            return response()->json([
                'status' => '404',
                'msg' => 'Recipient account not found.'
            ]);
        }

        if (!Hash::check($req->pin, $sender->transaction_pin)) {
            return response()->json([
                'status' => '401',
                'msg' => 'Invalid transaction PIN.'
            ]);
        }

        $amount = $req->input('amount');
        $savedBeneficiary = null;
        $senderTransaction = null;
        $newBalance = null;
        $errorResponse = null;

        try {
            DB::transaction(function () use ($sender, $recipient, $req, $amount, $selectedBeneficiary, &$savedBeneficiary, &$senderTransaction, &$newBalance, &$errorResponse) {
                // Lock both users in stable order to avoid deadlocks.
                $idsToLock = [$sender->id, $recipient->id];
                sort($idsToLock);

                $lockedUsers = User::whereIn('id', $idsToLock)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $lockedSender = $lockedUsers->get($sender->id);
                $lockedRecipient = $lockedUsers->get($recipient->id);

                if (!$lockedSender || !$lockedRecipient) {
                    $errorResponse = response()->json([
                        'status' => '404',
                        'msg' => 'Account not found during transfer processing.'
                    ]);
                    return;
                }

                // Check sufficient balance on locked row to prevent race conditions.
                if ($lockedSender->balance < $amount) {
                    $errorResponse = response()->json([
                        'status' => '400',
                        'msg' => 'Insufficient balance.'
                    ]);
                    return;
                }

                // Debit sender atomically.
                $senderBalanceBefore = $lockedSender->balance;
                $lockedSender->decrement('balance', $amount);
                $lockedSender->refresh();

                // Credit recipient atomically.
                $recipientBalanceBefore = $lockedRecipient->balance;
                $lockedRecipient->increment('balance', $amount);
                $lockedRecipient->refresh();

                if (!$selectedBeneficiary && $req->boolean('save_beneficiary')) {
                    $savedBeneficiary = Beneficiary::firstOrCreate(
                        [
                            'user_id' => $lockedSender->id,
                            'account_number' => $lockedRecipient->account_number,
                            'bank_code' => '999001',
                        ],
                        [
                            'account_name' => $lockedRecipient->name,
                            'bank_name' => 'Vaultly Bank',
                        ]
                    );
                }

                // Log sender's transaction (outgoing)
                $senderTransaction = Transaction::create([
                    'user_id' => $lockedSender->id,
                    'beneficiary_id' => $selectedBeneficiary?->id ?? $savedBeneficiary?->id,
                    'type' => 'transfer',
                    'direction' => 'debit',
                    'amount' => $amount,
                    'sender_account_name' => $lockedSender->name,
                    'sender_account_number' => $lockedSender->account_number,
                    'recipient_account_name' => $lockedRecipient->name,
                    'recipient_account_number' => $lockedRecipient->account_number,
                    'balance_before' => $senderBalanceBefore,
                    'balance_after' => $lockedSender->balance,
                ]);

                // Log recipient's transaction (incoming)
                Transaction::create([
                    'user_id' => $lockedRecipient->id,
                    'type' => 'transfer',
                    'direction' => 'credit',
                    'amount' => $amount,
                    'sender_account_name' => $lockedSender->name,
                    'sender_account_number' => $lockedSender->account_number,
                    'recipient_account_name' => $lockedRecipient->name,
                    'recipient_account_number' => $lockedRecipient->account_number,
                    'balance_before' => $recipientBalanceBefore,
                    'balance_after' => $lockedRecipient->balance,
                ]);

                $newBalance = $lockedSender->balance;
            }, self::TX_RETRY_ATTEMPTS);
        } catch (\Exception $e) {
            return response()->json([
                'status' => '500',
                'msg' => 'Transfer failed. Please try again.'
            ]);
        }

        if ($errorResponse) {
            return $errorResponse;
        }

        return response()->json([
            'status' => '200',
            'msg' => 'Transfer successful!',
            'transaction' => $senderTransaction,
            'new_balance' => $newBalance,
            'beneficiary_saved' => !is_null($savedBeneficiary),
            'beneficiary' => $savedBeneficiary,
        ]);
    }

    public function history(Request $req)
    {
        $user = $req->user();

        $transactions = Transaction::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->through(function (Transaction $transaction) {
                return [
                    // Keep internal ID for system use.
                    'id' => $transaction->id,
                    'transaction_id' => $transaction->id,
                    // Public-facing, non-guessable identifier.
                    'transaction_reference' => $transaction->transaction_reference,
                    'user_id' => $transaction->user_id,
                    'beneficiary_id' => $transaction->beneficiary_id,
                    'type' => $transaction->type,
                    'direction' => $transaction->direction,
                    'amount' => $transaction->amount,
                    'sender_account_name' => $transaction->sender_account_name,
                    'sender_account_number' => $transaction->sender_account_number,
                    'recipient_account_name' => $transaction->recipient_account_name,
                    'recipient_account_number' => $transaction->recipient_account_number,
                    'balance_before' => $transaction->balance_before,
                    'balance_after' => $transaction->balance_after,
                    'created_at' => $transaction->created_at,
                    'updated_at' => $transaction->updated_at,
                ];
            });

        return response()->json([
            'status' => '200',
            'transactions' => $transactions,
        ]);
    }

    public function withdraw(Request $req)
    {
        $user = $req->user();

        // Ensure users without a configured transaction PIN get a consistent 403 response.
        if (!$user->transaction_pin) {
            return response()->json([
                'status' => '403',
                'msg' => 'Please set your transaction PIN before making withdrawals.'
            ]);
        }

        $validation = Validator::make($req->all(), [
            'amount' => ['required', 'numeric', 'min:100', 'max:10000000'],
            'pin' => ['required', 'string', 'size:4', 'regex:/^\d{4}$/'],
        ], [
            'amount.min' => 'Minimum withdrawal is ₦100.',
            'amount.max' => 'Maximum withdrawal is ₦10,000,000.',
            'pin.regex' => 'PIN must be exactly 4 digits.',
        ]);

        if ($validation->fails()) {
            return response()->json([
                'status' => '422',
                'msg' => $validation->errors()
            ]);
        }

        if (!Hash::check($req->pin, $user->transaction_pin)) {
            return response()->json([
                'status' => '401',
                'msg' => 'Invalid transaction PIN.'
            ]);
        }

        $amount = $req->input('amount');
        $transaction = null;
        $newBalance = null;
        $errorResponse = null;

        try {
            DB::transaction(function () use ($user, $amount, &$transaction, &$newBalance, &$errorResponse) {
                $lockedUser = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

                if ($lockedUser->balance < $amount) {
                    $errorResponse = response()->json([
                        'status' => '400',
                        'msg' => 'Insufficient balance.'
                    ]);
                    return;
                }

                $balanceBefore = $lockedUser->balance;

                $lockedUser->decrement('balance', $amount);
                $lockedUser->refresh();
                $newBalance = $lockedUser->balance;

                $transaction = Transaction::create([
                    'user_id' => $lockedUser->id,
                    'type' => 'withdraw',
                    'direction' => 'debit',
                    'amount' => $amount,
                    'sender_account_name' => $lockedUser->name,
                    'sender_account_number' => $lockedUser->account_number,
                    'recipient_account_name' => 'Cash Withdrawal',
                    'recipient_account_number' => 'CASH',
                    'balance_before' => $balanceBefore,
                    'balance_after' => $newBalance,
                ]);
            }, self::TX_RETRY_ATTEMPTS);
        } catch (\Exception $e) {
            return response()->json([
                'status' => '500',
                'msg' => 'Withdrawal failed. Please try again.'
            ]);
        }

        if ($errorResponse) {
            return $errorResponse;
        }

        return response()->json([
            'status' => '200',
            'msg' => 'Withdrawal successful!',
            'transaction' => $transaction,
            'new_balance' => $newBalance,
        ]);
    }
}
