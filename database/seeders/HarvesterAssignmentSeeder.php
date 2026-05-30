<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Harvester;
use App\Models\HarvesterAssignment;
use Illuminate\Database\Seeder;

class HarvesterAssignmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run (): void
    {
        $company = Company::first();

        if (! $company) {
            return;
        }


        for ($number = 1; $number <= 150; $number++) {
            $harvester = Harvester::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'name' => 'Harvester '.$number,
                ],
                [
                    'active' => true,
                    'prefix' => fake()->boolean(5) ? fake()->firstName() : null,
                ]
            );
            for ($year = 2023; $year <= 2026; $year++) {
                HarvesterAssignment::create([
                    'company_id' => $company->id,
                    'harvester_id' => $harvester->id,
                    'year' => $year,
                    'number' => $number,
                ]);
            }
        }
    }
}
