<?php

namespace Database\Seeders;

use Artisan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(UserSeeder::class);
        $this->call(ProductSeeder::class);
        $this->call(HarvestImportSettingsSeeder::class);
        $this->call(HarvesterAssignmentSeeder::class);
        Artisan::call('holidays:import');
        Artisan::call('weather:fetch');
        // $this->call(HarvestRecordSeeder::class);
    }
}
