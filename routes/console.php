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
    PersonalAccessToken::where('created_at', '<', now()->subMinutes(config('sanctum.expiration')))
        ->delete();
})->hourly();
