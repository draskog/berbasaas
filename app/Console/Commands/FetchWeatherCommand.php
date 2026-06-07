<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\WeatherService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('weather:fetch')]
#[Description('Fetch weather forecast data for all companies with coordinates')]
class FetchWeatherCommand extends Command
{
    public function handle(WeatherService $weatherService): int
    {
        $companies = Company::whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();

        if ($companies->isEmpty()) {
            $this->info('No companies with coordinates found.');

            return self::SUCCESS;
        }

        $this->info("Fetching weather for {$companies->count()} compan".($companies->count() === 1 ? 'y' : 'ies').'...');

        foreach ($companies as $company) {
            $this->info("Fetching for: {$company->name}");
            $weatherService->fetchForCompany($company);
        }

        $this->info('Weather data fetched successfully.');

        return self::SUCCESS;
    }
}
