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

        $upload = HarvestUpload::create([
            'company_id' => $user->company->id,
            'product_id' => 1,
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
        $productCache = [];
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

            $productId = trim($data['Product'] ?? '');
            $weight = (float) trim($data['weight'] ?? 0);
            $tare = (float) trim($data['tare'] ?? 0);
            $gross = (float) trim($data['Gross'] ?? 0);
            $date = trim($data['date'] ?? '');
            $time = trim($data['time'] ?? '');

            if (!$productId || !$weight) {
                continue;
            }

            if (!isset($productCache[$productId])) {
                $product = Product::firstOrCreate(
                    ['name' => "Product {$productId}"],
                    [
                        'company_id' => $user->company->id,
                        'slug' => "product-{$productId}",
                        'active' => true,
                    ]
                );
                $productCache[$productId] = $product->id;
            }

            if ($date) {
                $weighedAt = Carbon::createFromFormat('Y-m-d H:i:s', "{$date} {$time}", 'Europe/Belgrade');
                $minDate = $minDate ? $minDate->min($weighedAt) : $weighedAt;
                $maxDate = $maxDate ? $maxDate->max($weighedAt) : $weighedAt;
            } else {
                $weighedAt = now();
            }

            $batch[] = [
                'company_id' => $user->company->id,
                'upload_id' => $upload->id,
                'product_id' => $productCache[$productId],
                'harvester_number' => 0,
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
