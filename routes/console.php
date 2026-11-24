<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('olx:refresh-proxies')
    ->everyFifteenMinutes()
    ->withoutOverlapping(30)
    ->runInBackground();
Schedule::command('olx:sync')->everyMinute()->withoutOverlapping();
Schedule::command('olx:track-prices')->daily()->withoutOverlapping();
