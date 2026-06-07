<?php

use App\Models\Company;
use App\Models\WeatherRecord;
use Illuminate\Support\Facades\Http;

test('fetch weather command works and creates records', function () {
    // Build hourly times for 7 days
    $hourlyTimes = [];
    $hourlyPrecip = [];
    for ($day = 8; $day <= 14; $day++) {
        for ($hour = 0; $hour <= 23; $hour++) {
            $hourlyTimes[] = '2026-06-'.str_pad($day, 2, '0', STR_PAD_LEFT).'T'.str_pad($hour, 2, '0', STR_PAD_LEFT).':00';
            $hourlyPrecip[] = 0.1;
        }
    }

    Http::fake([
        'https://api.open-meteo.com/v1/forecast' => Http::response([
            'daily' => [
                'time' => ['2026-06-08', '2026-06-09', '2026-06-10', '2026-06-11', '2026-06-12', '2026-06-13', '2026-06-14'],
                'weather_code' => [0, 1, 2, 3, 45, 51, 61],
                'temperature_2m_max' => [25.5, 26.2, 24.8, 23.1, 22.5, 21.0, 20.5],
                'temperature_2m_min' => [15.2, 16.1, 15.8, 14.5, 13.2, 12.8, 11.5],
                'precipitation_sum' => [0.0, 0.5, 2.3, 5.1, 3.2, 1.5, 0.8],
                'wind_speed_10m_max' => [10.5, 12.1, 8.5, 15.2, 9.8, 11.2, 13.5],
            ],
            'hourly' => [
                'time' => $hourlyTimes,
                'precipitation' => $hourlyPrecip,
            ],
        ]),
    ]);

    $company = Company::factory()->create([
        'latitude' => 44.7866,
        'longitude' => 20.4489,
    ]);

    $this->artisan('weather:fetch')
        ->assertSuccessful();

    expect(WeatherRecord::where('company_id', $company->id)->count())->toBeGreaterThan(0);
});

test('fetch weather command skips companies without coordinates', function () {
    $company = Company::factory()->create([
        'latitude' => null,
        'longitude' => null,
    ]);

    $this->artisan('weather:fetch')
        ->assertSuccessful();

    expect(WeatherRecord::where('company_id', $company->id)->count())->toBe(0);
});
