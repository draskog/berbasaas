<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Harvester;
use App\Models\HarvesterAssignment;
use App\Models\HarvestImportSettings;
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

        $settings = HarvestImportSettings::where('company_id', $company->id)->first();
        $delimiter = $settings?->csv_delimiter ?? ';';

        $harvesters = [];
        if (($handle = fopen($csvPath, 'rb')) !== false) {
            fgetcsv($handle, 1000, $delimiter);

            while (($data = fgetcsv($handle, 1000, $delimiter)) !== false) {
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

            for ($year = 2025; $year <= 2025; $year++) {
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
