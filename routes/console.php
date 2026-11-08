<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('jajapo:expire-orders')->everyMinute()->withoutOverlapping();
Schedule::command('jajapo:release-held')->hourly()->withoutOverlapping();
Schedule::command('jajapo:remind-pickups')->everyFifteenMinutes()->withoutOverlapping();
