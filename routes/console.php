<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// `schedule:run` invokes this every minute in production. `withoutOverlapping`
// protects a single scheduler/cache environment; this application does not
// assume a multi-server shared cache, so `onOneServer()` is intentionally absent.
Schedule::command('public-content:release-due')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->description('Release due public editorial content');
