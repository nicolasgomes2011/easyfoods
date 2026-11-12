<?php

use App\Jobs\ExpireUnconfirmedOrders;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Cancels orders left in pending_confirmation past the restaurant's window.
// Needs the scheduler running (`php artisan schedule:work` in dev, cron in production).
Schedule::job(new ExpireUnconfirmedOrders)->everyMinute()->withoutOverlapping();
