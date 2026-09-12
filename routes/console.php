<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Poll DataSika every minute for order status updates
Schedule::command('orders:sync-statuses')->everyMinute();

// Expire unpaid deposits every 5 minutes
Schedule::command('deposits:expire')->everyFiveMinutes();
