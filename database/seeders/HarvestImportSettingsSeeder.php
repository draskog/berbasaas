<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\HarvestImportSettings;
use Illuminate\Database\Seeder;

class HarvestImportSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Company::all()->each(function (Company $company) {
            HarvestImportSettings::firstOrCreate(
                ['company_id' => $company->id],
                [
                    'tare_min' => 0.100,
                    'tare_max' => 0.800,
                ]
            );
        });
    }
}
