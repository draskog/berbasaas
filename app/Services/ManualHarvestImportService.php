<?php

namespace App\Services;

use App\Enums\ImportType;
use App\Models\HarvesterAssignment;
use App\Models\HarvestImportSettings;
use App\Models\HarvestRecord;
use App\Models\HarvestRecordStaging;
use App\Models\HarvestUpload;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;

class ManualHarvestImportService
{
    public function parse(
        UploadedFile $file,
        int $companyId,
        int $productId,
        int $userId,
        string $harvestDate,
        string $tare
    ): array {
        // Load import settings for CSV delimiter
        $settings = HarvestImportSettings::where('company_id', $companyId)->first();
        $delimiter = $settings?->csv_delimiter ?? ',';
        $tareValue = (float) $tare;

        $path = $file->getRealPath();
        $handle = fopen($path, 'rb');

        // Read header
        $header = fgetcsv($handle, null, $delimiter, '"');

        // Map column names to indices (case-insensitive)
        $columns = array_flip(array_map('strtolower', $header));
        $numberCol = $columns['berac_br'] ?? null;
        $grossCol = $columns['bruto_tezina'] ?? null;

        if ($numberCol === null || $grossCol === null) {
            fclose($handle);

            throw new \InvalidArgumentException('CSV must contain "berac_br" and "bruto_tezina" columns');
        }

        $records = [];
        $dateFrom = $harvestDate;
        $dateTo = $harvestDate;
        $rowCount = 0;
        $skippedEmpty = 0;
        $sequenceNumber = 0;

        $harvestCarbon = Carbon::createFromFormat('Y-m-d', $harvestDate);
        if ($harvestCarbon === false) {
            fclose($handle);

            throw new \InvalidArgumentException('Invalid harvest date format. Expected YYYY-MM-DD');
        }

        while (($row = fgetcsv($handle, null, $delimiter, '"')) !== false) {
            if (empty($row) || empty($row[$numberCol]) || empty($row[$grossCol])) {
                $skippedEmpty++;

                continue;
            }

            $sequenceNumber++;
            $harvesterNumber = (int) trim($row[$numberCol]);
            $gross = (float) trim($row[$grossCol]);
            $weight = $gross - $tareValue;

            // Generate weighed_at: harvest date + 09:00:00 + (sequence_number - 1) seconds
            $weighedAt = $harvestCarbon->clone()
                ->setTime(9, 0, 0)
                ->addMinutes($sequenceNumber - 1);

            $records[] = [
                'company_id' => $companyId,
                'upload_id' => null, // Set after upload created
                'product_id' => $productId,
                'harvester_number' => $harvesterNumber,
                'weight' => $weight,
                'tare' => $tareValue,
                'gross' => $gross,
                'weighed_at' => $weighedAt,
                'sequence_number' => $sequenceNumber,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $rowCount++;
        }

        fclose($handle);

        if ($rowCount === 0) {
            throw new \InvalidArgumentException('No valid records found in CSV');
        }

        // Total records from CSV
        $totalRecords = $rowCount + $skippedEmpty;

        // Create upload record
        $upload = HarvestUpload::create([
            'company_id' => $companyId,
            'product_id' => $productId,
            'uploaded_by' => $userId,
            'original_filename' => $file->getClientOriginalName(),
            'record_count' => $totalRecords,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'import_type' => ImportType::ManualCsv,
        ]);

        // Filter out duplicates and track count
        $originalCount = count($records);
        $filtered = $this->filterDuplicates($records, $companyId);
        $records = $filtered['unique'];
        $inFileDuplicates = $filtered['inFileDuplicates'];
        $dbDuplicates = $filtered['dbDuplicates'];

        // Process records: validate and stage
        $validRecords = [];
        $stagingRecords = [];

        foreach ($records as $record) {
            $record['upload_id'] = $upload->id;
            $weighedAt = $record['weighed_at'];
            $tare = $record['tare'];
            $harvesterNumber = $record['harvester_number'];

            $reasons = [];

            // Check tare range
            if ($settings) {
                if ($settings->tare_min !== null && $tare < $settings->tare_min) {
                    $reasons[] = 'tare_out_of_range';
                } elseif ($settings->tare_max !== null && $tare > $settings->tare_max) {
                    $reasons[] = 'tare_out_of_range';
                }
            }

            // Check if harvester exists for the year of weighed_at
            $harvesterExists = HarvesterAssignment::where('company_id', $companyId)
                ->where('year', $weighedAt->year)
                ->where('number', $harvesterNumber)
                ->exists();

            if (! $harvesterExists) {
                $reasons[] = 'harvester_not_found';
            }

            if (empty($reasons)) {
                // Valid: prepare for direct insert to harvest_records
                $validRecords[] = $record;
            } else {
                // Invalid: stage for user review
                $stagingRecord = $record + ['status' => 'invalid', 'validation_reason' => $reasons];
                $stagingRecords[] = $stagingRecord;
            }
        }

        // Insert valid records directly into harvest_records
        foreach (array_chunk($validRecords, 500) as $chunk) {
            HarvestRecord::insert($chunk);
        }

        // Insert invalid records into staging
        foreach (array_chunk($stagingRecords, 500) as $chunk) {
            // JSON-encode validation_reason since insert() bypasses model casts
            foreach ($chunk as &$record) {
                if (is_array($record['validation_reason'])) {
                    $record['validation_reason'] = json_encode($record['validation_reason']);
                }
            }
            unset($record);
            HarvestRecordStaging::insert($chunk);
        }

        // Insert in-file duplicates into staging
        foreach (array_chunk($inFileDuplicates, 500) as $chunk) {
            foreach ($chunk as &$record) {
                $record['upload_id'] = $upload->id;
                $record['status'] = 'invalid';
                $record['validation_reason'] = json_encode(['in_file_duplicate']);
                $record['duplicate_of_sequence'] = $record['_duplicate_of_sequence'];
                unset($record['_duplicate_of_sequence']);
            }
            unset($record);
            HarvestRecordStaging::insert($chunk);
        }

        // Insert database duplicates into staging
        foreach (array_chunk($dbDuplicates, 500) as $chunk) {
            foreach ($chunk as &$record) {
                $record['upload_id'] = $upload->id;
                $record['status'] = 'invalid';
                $record['validation_reason'] = json_encode(['db_duplicate']);
            }
            unset($record);
            HarvestRecordStaging::insert($chunk);
        }

        return [
            'upload' => $upload,
            'inFileDuplicateCount' => count($inFileDuplicates),
            'dbDuplicateCount' => count($dbDuplicates),
        ];
    }

    private function filterDuplicates(array $records, int $companyId): array
    {
        $unique = [];
        $inFileDuplicates = [];
        $dbDuplicates = [];
        $seenKeys = []; // key => sequence_number of first occurrence

        // Collect all unique keys from existing records in database
        $existingKeys = $this->getExistingRecordKeys($companyId);

        foreach ($records as $record) {
            $key = $this->generateRecordKey($record);

            if (isset($existingKeys[$key])) {
                $dbDuplicates[] = $record;

                continue;
            }

            if (isset($seenKeys[$key])) {
                $record['_duplicate_of_sequence'] = $seenKeys[$key];
                $inFileDuplicates[] = $record;

                continue;
            }

            $seenKeys[$key] = $record['sequence_number'];
            $unique[] = $record;
        }

        return ['unique' => $unique, 'inFileDuplicates' => $inFileDuplicates, 'dbDuplicates' => $dbDuplicates];
    }

    private function getExistingRecordKeys(int $companyId): array
    {
        $keys = [];

        // Get keys from harvest_records
        HarvestRecord::where('company_id', $companyId)
            ->select('company_id', 'product_id', 'harvester_number', 'weighed_at')
            ->each(function ($record) use (&$keys) {
                $key = $this->generateRecordKeyFromModel($record);
                $keys[$key] = true;
            });

        // Get keys from harvest_record_staging
        HarvestRecordStaging::where('company_id', $companyId)
            ->select('company_id', 'product_id', 'harvester_number', 'weighed_at')
            ->each(function ($record) use (&$keys) {
                $key = $this->generateRecordKeyFromModel($record);
                $keys[$key] = true;
            });

        return $keys;
    }

    private function generateRecordKey(array $record): string
    {
        $weighedAt = $record['weighed_at'] instanceof Carbon
            ? $record['weighed_at']->format('Y-m-d H:i:s')
            : $record['weighed_at'];

        return "{$record['company_id']}|{$record['product_id']}|{$record['harvester_number']}|{$weighedAt}";
    }

    private function generateRecordKeyFromModel($record): string
    {
        $weighedAt = $record->weighed_at instanceof Carbon
            ? $record->weighed_at->format('Y-m-d H:i:s')
            : $record->weighed_at;

        return "{$record->company_id}|{$record->product_id}|{$record->harvester_number}|{$weighedAt}";
    }
}
