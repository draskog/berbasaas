<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('holidays:import')->yearlyOn(1, 9);
Schedule::command('weather:fetch')
    ->cron('0 6,8,9,10,11,12,13,14,15,16,17,18,19,20 * * *')
    ->when(fn () => now()->month >= 3 && now()->month <= 8);
