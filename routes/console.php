<?php

use App\Console\Commands\CleanExpiredSelfOrders;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Clean up expired self-order sessions and pending_payment transactions every minute
Schedule::command(CleanExpiredSelfOrders::class)->everyMinute();
