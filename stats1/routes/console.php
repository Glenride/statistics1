<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Polling Data Sync Schedule
|--------------------------------------------------------------------------
|
| These scheduled tasks keep your polling data current by:
| 1. Syncing 538 polling data hourly (backbone tracker)
| 2. Checking press release monitors every 15 minutes (first mover alerts)
|
*/

// Hourly: Sync FiveThirtyEight and other backbone trackers
Schedule::command('polls:sync --source=all')
    ->hourly()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/polls-sync.log'));

// Every 15 minutes: Check press release monitors and PDF hunters for breaking news
Schedule::command('monitors:check --type=all')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/monitors.log'));
