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

        $csvPath = resource_path('samples/full/Spisak_beraca_Ime_Prezime_Konacno.csv');

        if (! file_exists($csvPath)) {
            return;
        }

        $harvesters = [];
        if (($handle = fopen($csvPath, 'rb')) !== false) {
            fgetcsv($handle, 1000, ';');

            while (($data = fgetcsv($handle, 1000, ';')) !== false) {
                if (count($data) >= 2 && ! empty($data[1])) {
                    $number = (int) $data[0];
                    $name = trim($data[1]);
                    $prefix = isset($data[2]) ? trim($data[2]) : null;

                    $harvesters[$number] = [
                        'name' => $name,
                        'prefix' => $prefix ?: null,
                    ];
                }
            }
            fclose($handle);
        }

        foreach ($harvesters as $number => $harvesterData) {
            $harvester = Harvester::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'name' => $harvesterData['name'],
                ],
                [
                    'active' => true,
                    'prefix' => $harvesterData['prefix'],
                ]
            );

            for ($year = 2023; $year <= 2026; $year++) {
                HarvesterAssignment::updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'year' => $year,
                        'number' => $number,
                    ],
                    [
                        'harvester_id' => $harvester->id,
                    ]
                );
            }
        }
    }
}
