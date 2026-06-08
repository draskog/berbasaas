<?php

use App\Models\Company;
use App\Services\WeatherService;
use Illuminate\Support\Facades\Http;

test('weather service geocodes address to coordinates', function () {
    Http::fake([
        '*photon.komoot.io*' => Http::response([
            'features' => [
                [
                    'geometry' => ['coordinates' => [20.4489, 44.7866]],
                    'properties' => [
                        'name' => 'Beograd',
                        'city' => 'Beograd',
                        'country' => 'Srbija',
                    ],
                ],
            ],
        ]),
    ]);

    $result = (new WeatherService)->geocodeAddress('Beograd');

    expect($result)->not->toBeNull()
        ->and($result['lat'])->toBe(44.7866)
        ->and($result['lon'])->toBe(20.4489);
});

test('weather service returns null for empty geocode results', function () {
    Http::fake([
        '*photon.komoot.io*' => Http::response(['features' => []]),
    ]);

    $result = (new WeatherService)->geocodeAddress('Nonexistent');

    expect($result)->toBeNull();
});

test('weather service returns company coordinates as fallback when geocoding fails', function () {
    Http::fake([
        '*photon.komoot.io*' => Http::response(['features' => []]),
    ]);

    $company = Company::factory()->create([
        'latitude' => 44.7866,
        'longitude' => 20.4489,
    ]);

    $result = (new WeatherService)->geocodeAddress('Nonexistent', $company);

    expect($result)->not->toBeNull()
        ->and($result['lat'])->toBe(44.7866)
        ->and($result['lon'])->toBe(20.4489)
        ->and($result['is_fallback'])->toBeTrue();
});
