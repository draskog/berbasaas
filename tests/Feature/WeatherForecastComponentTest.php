<?php

use App\Models\Company;
use App\Models\ReligiousHoliday;
use App\Models\User;
use App\Models\WeatherRecord;

test('weather forecast component displays weather records', function () {
    $company = Company::factory()->create([
        'latitude' => 44.7866,
        'longitude' => 20.4489,
    ]);

    $user = User::factory()->create(['company_id' => $company->id]);

    // Create weather records
    for ($i = 0; $i < 7; $i++) {
        WeatherRecord::create([
            'company_id' => $company->id,
            'date' => now()->addDays($i)->toDateString(),
            'temperature_min' => 15 + $i,
            'temperature_max' => 25 + $i,
            'precipitation_sum' => $i * 0.5,
            'wind_speed_max' => 10 + $i,
            'weather_code' => 0,
            'hourly_precipitation' => array_fill(0, 12, 0.1 * $i),
        ]);
    }

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertStatus(200)
        ->assertSee('Vremenska prognoza');
});

test('weather forecast grid stacks into a single column on mobile', function () {
    $company = Company::factory()->create([
        'latitude' => 44.7866,
        'longitude' => 20.4489,
    ]);

    $user = User::factory()->create(['company_id' => $company->id]);

    for ($i = 0; $i < 7; $i++) {
        WeatherRecord::create([
            'company_id' => $company->id,
            'date' => now()->addDays($i)->toDateString(),
            'temperature_min' => 15 + $i,
            'temperature_max' => 25 + $i,
            'precipitation_sum' => $i * 0.5,
            'wind_speed_max' => 10 + $i,
            'weather_code' => 0,
            'hourly_precipitation' => array_fill(0, 12, 0.1 * $i),
        ]);
    }

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertStatus(200)
        ->assertSee('grid-cols-1 sm:grid-cols-2 xl:grid-cols-3', false);
});

test('weather forecast component shows location prompt when location is not configured', function () {
    $company = Company::factory()->create([
        'latitude' => null,
        'longitude' => null,
    ]);

    $user = User::factory()->create(['company_id' => $company->id]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertStatus(200)
        ->assertSee('Lokacija nije konfigurisana')
        ->assertSee('Postavi lokaciju');
});

test('weather forecast component shows no data message when no weather records', function () {
    $company = Company::factory()->create([
        'latitude' => 44.7866,
        'longitude' => 20.4489,
    ]);

    $user = User::factory()->create(['company_id' => $company->id]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertStatus(200)
        ->assertSee('Meteorološki podaci još nisu dostupni');
});

test('weather forecast component displays religious holiday badge', function () {
    $company = Company::factory()->create([
        'latitude' => 44.7866,
        'longitude' => 20.4489,
    ]);

    $user = User::factory()->create(['company_id' => $company->id]);

    $tomorrow = now()->addDay()->toDateString();
    WeatherRecord::create([
        'company_id' => $company->id,
        'date' => $tomorrow,
        'temperature_min' => 15,
        'temperature_max' => 25,
        'precipitation_sum' => 0,
        'wind_speed_max' => 10,
        'weather_code' => 0,
        'hourly_precipitation' => array_fill(0, 12, 0),
    ]);

    ReligiousHoliday::create([
        'date' => $tomorrow,
        'year' => now()->year,
        'description' => 'Svetkovina',
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertStatus(200)
        ->assertSee('Svetkovina');
});
