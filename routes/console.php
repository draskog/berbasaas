<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('holidays:import')->yearlyOn(1, 9);
Schedule::command('weather:fetch')
    ->dailyAt('06:00')
    ->when(fn () => now()->month >= 3 && now()->month <= 8);
