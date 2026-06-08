<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('holidays:import')->yearlyOn(1, 9);
Schedule::command('weather:fetch')
    ->cron('0 5,8,11,14 * * *')
    ->when(fn () => now()->month >= 3 && now()->month <= 8);
