<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class TransactionController extends Controller
{
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
        $user = $req->user();

        // 3. Start DB transaction (atomic)
        try {
            DB::transaction(function () use ($user, $req, &$transaction) {
                // 4. Record balance before
                $balanceBefore = $user->balance;

                // 5. Update user's balance
                $user->balance += $req->amount;
                $user->save();

                // 6. Create transaction record
                $transaction = Transaction::create([
                    'user_id' => $user->id,
                    'type' => 'deposit',
                    'direction' => 'credit',
                    'amount' => $req->amount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $user->balance,
                ]);
            });
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
            'new_balance' => $user->balance,
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
        $validation = Validator::make($req->all(), [
            'account_number' => ['required', 'string', 'size:12'],
            'account_name' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:100', 'max:10000000'],
            'pin' => ['required', 'string', 'size:4'],
        ], [
            'amount.min' => 'Minimum transfer is ₦100.',
            'amount.max' => 'Maximum transfer is ₦10,000,000.',
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

        // Verify recipient exists and name matches
        $recipient = User::where('account_number', $req->account_number)->first();

        if (!$recipient) {
            return response()->json([
                'status' => '404',
                'msg' => 'Recipient account not found.'
            ]);
        }

        if (strtolower($recipient->name) !== strtolower($req->account_name)) {
            return response()->json([
                'status' => '400',
                'msg' => 'Account name does not match the account holder.'
            ]);
        }

        // Check sufficient balance
        if ($sender->balance < $req->amount) {
            return response()->json([
                'status' => '400',
                'msg' => 'Insufficient balance.'
            ]);
        }

        // Validate transaction PIN
        if (!$sender->transaction_pin) {
            return response()->json([
                'status' => '403',
                'msg' => 'Please set your transaction PIN before making transfers.'
            ]);
        }

        if (!Hash::check($req->pin, $sender->transaction_pin)) {
            return response()->json([
                'status' => '401',
                'msg' => 'Invalid transaction PIN.'
            ]);
        }

        try {
            DB::transaction(function () use ($sender, $recipient, $req, &$senderTransaction) {
                // Debit sender
                $senderBalanceBefore = $sender->balance;
                $sender->balance -= $req->amount;
                $sender->save();

                // Credit recipient
                $recipientBalanceBefore = $recipient->balance;
                $recipient->balance += $req->amount;
                $recipient->save();

                // Log sender's transaction (outgoing)
                $senderTransaction = Transaction::create([
                    'user_id' => $sender->id,
                    'type' => 'transfer',
                    'direction' => 'debit',
                    'amount' => $req->amount,
                    'balance_before' => $senderBalanceBefore,
                    'balance_after' => $sender->balance,
                ]);

                // Log recipient's transaction (incoming)
                Transaction::create([
                    'user_id' => $recipient->id,
                    'type' => 'transfer',
                    'direction' => 'credit',
                    'amount' => $req->amount,
                    'balance_before' => $recipientBalanceBefore,
                    'balance_after' => $recipient->balance,
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'status' => '500',
                'msg' => 'Transfer failed. Please try again.'
            ]);
        }

        return response()->json([
            'status' => '200',
            'msg' => 'Transfer successful!',
            'transaction' => $senderTransaction,
            'new_balance' => $sender->balance,
        ]);
    }

    public function history(Request $req)
    {
        $user = $req->user();

        $transactions = Transaction::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'status' => '200',
            'transactions' => $transactions,
        ]);
    }
}
