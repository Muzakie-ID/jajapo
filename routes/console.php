<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('jajapo:expire-orders')->everyMinute()->withoutOverlapping();
Schedule::command('jajapo:release-held')->hourly()->withoutOverlapping();
