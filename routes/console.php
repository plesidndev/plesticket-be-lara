<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Release ticket quota held by orders that were never paid. Runs often because
// every minute a lapsed order sits unswept is a minute its seats cannot be sold.
// withoutOverlapping guards against a slow run colliding with the next tick.
Schedule::command('orders:expire')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();
