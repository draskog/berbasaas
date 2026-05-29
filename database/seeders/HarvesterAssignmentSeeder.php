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
    public function run(): void
    {
        $company = Company::first();

        if (! $company) {
            return;
        }

        $harvesters = [
            2 => ['name' => 'Harvester 2', 'prefix' => null],
            4 => ['name' => 'Harvester 4', 'prefix' => null],
            6 => ['name' => 'Harvester 6', 'prefix' => null],
            7 => ['name' => 'Harvester 7', 'prefix' => null],
            8 => ['name' => 'Harvester 8', 'prefix' => null],
            13 => ['name' => 'Harvester 13', 'prefix' => null],
            17 => ['name' => 'Harvester 17', 'prefix' => null],
            19 => ['name' => 'Harvester 19', 'prefix' => null],
            27 => ['name' => 'Harvester 27', 'prefix' => null],
            29 => ['name' => 'Harvester 29', 'prefix' => null],
        ];

        $year = now()->year;

        foreach ($harvesters as $number => $data) {
            $harvester = Harvester::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'name' => $data['name'],
                ],
                [
                    'active' => true,
                    'prefix' => $data['prefix'],
                ]
            );

            HarvesterAssignment::create([
                'company_id' => $company->id,
                'harvester_id' => $harvester->id,
                'year' => $year,
                'number' => $number,
            ]);
        }
    }
}
