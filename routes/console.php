<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sends queued text messages. On the server one cron job runs
// "php artisan schedule:run" every minute (see docs/DEPLOY-CPANEL.md).
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping();

// Clears out old background-job records
Schedule::command('queue:prune-failed --hours=720')->daily();
