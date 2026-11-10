<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Schedule::command('jajapo:expire-orders')->everyMinute()->withoutOverlapping();
Schedule::command('jajapo:mark-not-picked-up')->hourly()->withoutOverlapping();
Schedule::command('jajapo:remind-pickups')->everyFifteenMinutes()->withoutOverlapping();

// Detak scheduler untuk jajapo:doctor: kalau cron mati, detaknya berhenti.
Schedule::call(fn () => Cache::put('jajapo:scheduler-heartbeat', now()->toIso8601String(), now()->addHour()))->everyMinute()->name('jajapo:heartbeat');
