<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('olx:refresh-proxies')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('olx:sync')->everyMinute()->withoutOverlapping();
