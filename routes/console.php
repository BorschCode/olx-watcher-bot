<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('olx:refresh-proxies')->everyFifteenMinutes();
Schedule::command('olx:sync')->everyFifteenMinutes();
