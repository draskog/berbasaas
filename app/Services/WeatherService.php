<?php

namespace App\Services;

use App\Models\Company;
use App\Models\WeatherRecord;
use Illuminate\Support\Facades\Http;

class WeatherService
{
    private const OPEN_METEO_URL = 'https://api.open-meteo.com/v1/forecast';

    private const PHOTON_URL = 'https://photon.komoot.io/api';

    private const WORKING_HOURS = [9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20]; // 09:00-20:00

    public function fetchForCompany(Company $company): void
    {
        if (! $company->latitude || ! $company->longitude) {
            return;
        }

        try {
            $response = Http::get(self::OPEN_METEO_URL, [
                'latitude' => $company->latitude,
                'longitude' => $company->longitude,
                'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_sum,wind_speed_10m_max',
                'hourly' => 'precipitation',
                'timezone' => 'Europe/Belgrade',
                'forecast_days' => 7,
            ]);

            if (! $response->successful()) {
                return;
            }

            $data = $response->json();
            $dailyData = $data['daily'];
            $hourlyData = $data['hourly']['precipitation'];
            $hourlyTimes = $data['hourly']['time'];

            foreach ($dailyData['time'] as $dayIndex => $date) {
                $hourlyPrecipitation = $this->extractHourlyPrecipitation($hourlyTimes, $hourlyData, $date);

                WeatherRecord::updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'date' => $date,
                    ],
                    [
                        'temperature_min' => $dailyData['temperature_2m_min'][$dayIndex],
                        'temperature_max' => $dailyData['temperature_2m_max'][$dayIndex],
                        'precipitation_sum' => $dailyData['precipitation_sum'][$dayIndex],
                        'wind_speed_max' => $dailyData['wind_speed_10m_max'][$dayIndex],
                        'weather_code' => $dailyData['weather_code'][$dayIndex],
                        'hourly_precipitation' => $hourlyPrecipitation,
                        'fetched_at' => now(),
                    ]
                );
            }
        } catch (\Exception) {
            // Silently fail, logging can be added if needed
        }
    }

    public function geocodeAddress(string $query, ?Company $company = null): ?array
    {
        try {
            $response = Http::get(self::PHOTON_URL, [
                'q' => $query,
                'limit' => 5,
                'lang' => 'sr',
            ]);

            if (! $response->successful()) {
                return $this->getCompanyCoordinatesFallback($company);
            }

            $data = $response->json();

            if (empty($data['features'])) {
                return $this->getCompanyCoordinatesFallback($company);
            }

            $feature = $data['features'][0];
            $coords = $feature['geometry']['coordinates'];

            return [
                'lat' => $coords[1],
                'lon' => $coords[0],
                'label' => $this->buildLabel($feature['properties']),
            ];
        } catch (\Exception) {
            return $this->getCompanyCoordinatesFallback($company);
        }
    }

    private function getCompanyCoordinatesFallback(?Company $company): ?array
    {
        if ($company && $company->latitude && $company->longitude) {
            return [
                'lat' => $company->latitude,
                'lon' => $company->longitude,
                'label' => $this->buildLabel(['name' => __('Company location')]),
                'is_fallback' => true,
            ];
        }

        return null;
    }

    private function extractHourlyPrecipitation(array $times, array $precipitation, string $date): array
    {
        $result = [];

        foreach (self::WORKING_HOURS as $hour) {
            $timeStr = "{$date}T".str_pad($hour, 2, '0', STR_PAD_LEFT).':00';
            $index = array_search($timeStr, $times, true);

            if ($index !== false) {
                $result[] = $precipitation[$index] ?? 0;
            }
        }

        return $result;
    }

    private function buildLabel(array $properties): string
    {
        $parts = [];

        if (isset($properties['name'])) {
            $parts[] = $properties['name'];
        }

        if (isset($properties['city'])) {
            $parts[] = $properties['city'];
        }

        if (isset($properties['country'])) {
            $parts[] = $properties['country'];
        }

        return implode(', ', $parts);
    }
}
