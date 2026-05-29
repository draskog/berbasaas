<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\HarvesterAssignment;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class HarvesterAssignmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $company = Company::first();

        if (!$company) {
            return;
        }

        $harvesters = [
            2 => 'Harvester 2',
            4 => 'Harvester 4',
            6 => 'Harvester 6',
            7 => 'Harvester 7',
            8 => 'Harvester 8',
            13 => 'Harvester 13',
            17 => 'Harvester 17',
            19 => 'Harvester 19',
            27 => 'Harvester 27',
            29 => 'Harvester 29',
        ];

        $year = now()->year;

        foreach ($harvesters as $number => $name) {
            HarvesterAssignment::create([
                'company_id' => $company->id,
                'year' => $year,
                'number' => $number,
                'name' => $name,
            ]);
        }
    }
}
