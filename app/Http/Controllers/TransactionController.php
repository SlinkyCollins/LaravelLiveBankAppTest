<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
}
