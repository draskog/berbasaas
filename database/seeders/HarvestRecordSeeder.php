<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\HarvestRecord;
use App\Models\HarvestUpload;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class HarvestRecordSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csvPath = resource_path('samples/full/record.csv');

        if (!file_exists($csvPath)) {
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

        $upload = HarvestUpload::create([
            'company_id' => $company->id,
            'product_id' => $defaultProduct->id,
            'uploaded_by' => $user->id,
            'original_filename' => 'record.csv',
            'record_count' => 0,
            'date_from' => Carbon::parse('2023-06-26'),
            'date_to' => Carbon::parse('2023-06-26'),
        ]);

        $handle = fopen($csvPath, 'r');
        $header = fgetcsv($handle);

        $recordCount = 0;
        $batchSize = 1000;
        $batch = [];
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

            if (!$harvesterNumber || !$weight) {
                continue;
            }

            if ($date) {
                $weighedAt = Carbon::createFromFormat('Y-m-d H:i:s', "{$date} {$time}", 'Europe/Belgrade');
                $minDate = $minDate ? $minDate->min($weighedAt) : $weighedAt;
                $maxDate = $maxDate ? $maxDate->max($weighedAt) : $weighedAt;
            } else {
                $weighedAt = now();
            }

            $batch[] = [
                'company_id' => $company->id,
                'upload_id' => $upload->id,
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

            if (count($batch) >= $batchSize) {
                HarvestRecord::insert($batch);
                $batch = [];
                $this->command->line("Inserted {$recordCount} records...");
            }
        }

        if (count($batch) > 0) {
            HarvestRecord::insert($batch);
        }

        if ($minDate && $maxDate) {
            $upload->update([
                'record_count' => $recordCount,
                'date_from' => $minDate,
                'date_to' => $maxDate,
            ]);
        }

        fclose($handle);

        $this->command->info("Successfully loaded {$recordCount} harvest records from CSV");
    }
}
