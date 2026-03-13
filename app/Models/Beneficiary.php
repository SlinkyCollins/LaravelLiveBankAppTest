<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Beneficiary extends Model
{
    protected $fillable = [
        'user_id',
        'account_name',
        'account_number',
        'bank_name',
        'bank_code',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}