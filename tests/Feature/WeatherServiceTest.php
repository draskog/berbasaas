<?php

use App\Services\WeatherService;
use Illuminate\Support\Facades\Http;

test('weather service geocodes multiple addresses', function () {
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
                [
                    'geometry' => ['coordinates' => [20.0, 44.0]],
                    'properties' => [
                        'name' => 'Beograd - drugi rezultat',
                        'city' => 'Beograd',
                        'country' => 'Srbija',
                    ],
                ],
            ],
        ]),
    ]);

    $results = (new WeatherService)->geocodeAddresses('Beograd');

    expect($results)->toHaveCount(2)
        ->and($results[0]['lat'])->toBe(44.7866)
        ->and($results[0]['lon'])->toBe(20.4489)
        ->and((float) $results[1]['lat'])->toBe(44.0)
        ->and((float) $results[1]['lon'])->toBe(20.0);
});

test('weather service returns empty array for no geocode results', function () {
    Http::fake([
        '*photon.komoot.io*' => Http::response(['features' => []]),
    ]);

    $results = (new WeatherService)->geocodeAddresses('Nonexistent');

    expect($results)->toBeEmpty();
});
