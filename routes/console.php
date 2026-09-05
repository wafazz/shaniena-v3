<?php

use App\Jobs\ExpireAbandonedCarts;
use App\Jobs\RefreshVisitorCounts;
use App\Jobs\SyncDeliveryStatus;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled work
|--------------------------------------------------------------------------
|
| Replaces the source's four standalone cron scripts, each of which opened its
| own database connection with credentials written into the file.
|
*/

Schedule::job(new ExpireAbandonedCarts)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->name('carts:expire');

Schedule::job(new RefreshVisitorCounts)
    ->everyMinute()
    ->withoutOverlapping()
    ->name('visitors:refresh');

Schedule::job(new SyncDeliveryStatus)
    ->hourly()
    ->withoutOverlapping()
    ->name('delivery:sync');
