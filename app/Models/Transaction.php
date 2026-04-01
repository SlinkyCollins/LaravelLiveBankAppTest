<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Transaction extends Model
{
    protected $fillable = [
        'transaction_reference',
        'user_id',
        'beneficiary_id',
        'type',
        'direction',
        'amount',
        'sender_account_name',
        'sender_account_number',
        'recipient_account_name',
        'recipient_account_number',
        'balance_before',
        'balance_after',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $transaction) {
            if (!empty($transaction->transaction_reference)) {
                return;
            }

            do {
                $reference = (string) Str::ulid();
            } while (self::where('transaction_reference', $reference)->exists());

            $transaction->transaction_reference = $reference;
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
