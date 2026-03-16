<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Laravel\Sanctum\PersonalAccessToken;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Prune expired Sanctum tokens every hour
Schedule::call(function () {
    $cutoff = now()->subMinutes(config('sanctum.expiration'));

    PersonalAccessToken::where('created_at', '<', $cutoff)
        ->select('id')
        ->chunkById(1000, function ($tokens) {
            PersonalAccessToken::whereIn('id', $tokens->pluck('id'))->delete();
        });
})->hourly();