<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use App\Services\HarvestImportService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;

class HarvestRecordSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csvPath = resource_path('samples/full/record.csv');

        if (! file_exists($csvPath)) {
            $this->command->warn("CSV file not found at {$csvPath}");

            return;
        }

        $user = User::first();
        $defaultProduct = Product::first();

        $file = new UploadedFile(
            $csvPath,
            'record.csv',
            'text/csv',
            null,
            true
        );

        $service = new HarvestImportService;
        $result = $service->parse(
            $file,
            $user->company_id,
            $defaultProduct->id,
            $user->id
        );

        $upload = $result['upload'];
        $validCount = $upload->harvestRecords()->count();
        $invalidCount = $upload->stagingRecords()->count();

        $this->command->info("Successfully loaded {$upload->record_count} harvest records from CSV");
        $this->command->info("Valid records: {$validCount}");
        $this->command->info("Invalid records (staged for review): {$invalidCount}");
    }
}
