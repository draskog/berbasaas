<?php

namespace Database\Seeders;

use App\Models\HarvesterAssignment;
use App\Models\HarvestRecord;
use App\Models\HarvestRecordStaging;
use App\Models\HarvestUpload;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

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
        $company = $user->company;

        // Create a default blueberry product for the upload
        $defaultProduct = Product::firstOrCreate(
            ['name' => 'Blueberries'],
            [
                'company_id' => $company->id,
                'slug' => 'blueberries',
                'active' => true,
            ]
        );

        $handle = fopen($csvPath, 'r');
        $header = fgetcsv($handle);

        $recordCount = 0;
        $batchSize = 1000;
        $records = [];
        $minDate = null;
        $maxDate = null;

        while (($row = fgetcsv($handle)) !== false) {
            // Handle rows with mismatched column counts (pad or trim)
            if (count($row) < count($header)) {
                $row = array_pad($row, count($header), '');
            } elseif (count($row) > count($header)) {
                $row = array_slice($row, 0, count($header));
            }

            $data = array_combine($header, $row);

            // The 'Product' column contains the harvester number assigned for that year
            $harvesterNumber = (int) trim($data['Product'] ?? 0);
            $weight = (float) trim($data['weight'] ?? 0);
            $tare = (float) trim($data['tare'] ?? 0);
            $gross = (float) trim($data['Gross'] ?? 0);
            $date = trim($data['date'] ?? '');
            $time = trim($data['time'] ?? '');

            if (! $harvesterNumber || ! $weight) {
                continue;
            }

            if ($date) {
                $weighedAt = Carbon::createFromFormat('Y-m-d H:i:s', "{$date} {$time}", 'Europe/Belgrade');
                $minDate = $minDate ? $minDate->min($weighedAt) : $weighedAt;
                $maxDate = $maxDate ? $maxDate->max($weighedAt) : $weighedAt;
            } else {
                $weighedAt = now();
            }

            $records[] = [
                'company_id' => $company->id,
                'product_id' => $defaultProduct->id,
                'harvester_number' => $harvesterNumber,
                'weight' => $weight,
                'tare' => $tare,
                'gross' => $gross,
                'weighed_at' => $weighedAt,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $recordCount++;
        }

        fclose($handle);

        // Create upload record
        $upload = HarvestUpload::create([
            'company_id' => $company->id,
            'product_id' => $defaultProduct->id,
            'uploaded_by' => $user->id,
            'original_filename' => 'record.csv',
            'record_count' => $recordCount,
            'date_from' => $minDate ?? now(),
            'date_to' => $maxDate ?? now(),
        ]);

        // Process records: validate and stage
        $validRecords = [];
        $stagingRecords = [];

        foreach ($records as $record) {
            $record['upload_id'] = $upload->id;
            $weighedAt = $record['weighed_at'];
            $harvesterNumber = $record['harvester_number'];

            // Check if harvester exists for the year of weighed_at
            $harvesterExists = HarvesterAssignment::where('company_id', $company->id)
                ->where('year', $weighedAt->year)
                ->where('number', $harvesterNumber)
                ->exists();

            if ($harvesterExists) {
                // Valid: prepare for direct insert to harvest_records
                $validRecords[] = $record;
            } else {
                // Invalid: stage for user review
                $stagingRecord = $record + ['status' => 'invalid'];
                $stagingRecords[] = $stagingRecord;
            }
        }

        // Insert valid records directly into harvest_records
        foreach (array_chunk($validRecords, $batchSize) as $chunk) {
            HarvestRecord::insert($chunk);
            $this->command->line('Inserted '.count($chunk).' valid records...');
        }

        // Insert invalid records into staging
        foreach (array_chunk($stagingRecords, $batchSize) as $chunk) {
            HarvestRecordStaging::insert($chunk);
            $this->command->line('Staged '.count($chunk).' invalid records for review...');
        }

        $validCount = count($validRecords);
        $invalidCount = count($stagingRecords);

        $this->command->info("Successfully loaded {$recordCount} harvest records from CSV");
        $this->command->info("Valid records: {$validCount}");
        $this->command->info("Invalid records (staged for review): {$invalidCount}");
    }
}
