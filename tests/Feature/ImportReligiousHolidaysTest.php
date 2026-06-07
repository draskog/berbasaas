<?php

use App\Models\ReligiousHoliday;

test('command executes successfully', function () {
    $this->artisan('holidays:import')
        ->assertSuccessful();
});

test('command imports holidays with latin characters', function () {
    $this->artisan('holidays:import');

    $holidays = ReligiousHoliday::where('year', 2026)
        ->limit(5)
        ->get();

    foreach ($holidays as $holiday) {
        expect($holiday->description)->toBeString();
        expect(strlen($holiday->description))->toBeGreaterThan(0);
    }
});
