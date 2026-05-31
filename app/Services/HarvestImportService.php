<?php

namespace App\Services;

use App\Models\HarvesterAssignment;
use App\Models\HarvestImportSettings;
use App\Models\HarvestRecord;
use App\Models\HarvestRecordStaging;
use App\Models\HarvestUpload;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;

class HarvestImportService
{
    public function parse(UploadedFile $file, int $companyId, int $productId, int $userId): HarvestUpload
    {
        $path = $file->getRealPath();
        $handle = fopen($path, 'rb');

        // Read header
        $header = fgetcsv($handle, null, ',', '"');
        $columnCount = count($header);

        // Determine schema: full (≥90 cols) or simple (7 cols)
        $isFullSchema = $columnCount >= 90;

        // Map column names to indices
        $columns = array_flip($header);
        $productCol = $columns['Product'] ?? null;
        $weightCol = $columns['weight'] ?? null;
        $tareCol = $columns['tare'] ?? null;
        $grossCol = $columns['Gross'] ?? null;
        $dateCol = $columns['date'] ?? null;
        $timeCol = $columns['time'] ?? null;

        $records = [];
        $dateFrom = null;
        $dateTo = null;
        $rowCount = 0;

        while (($row = fgetcsv($handle, null, ',', '"')) !== false) {
            if (empty($row) || ! isset($row[$productCol])) {
                continue;
            }

            $harvesterNumber = (int) $row[$productCol];
            $weight = (float) $row[$weightCol];
            $tare = (float) $row[$tareCol];
            $gross = (float) $row[$grossCol];

            // Parse date & time
            $dateStr = trim($row[$dateCol]);
            $timeStr = trim($row[$timeCol]);

            // Detect date format: YYYY-MM-DD or DD-MM-YY
            $datetime = $this->parseDateTime($dateStr, $timeStr);

            if (! $datetime) {
                continue;
            }

            // Track date range
            if (! $dateFrom || $datetime->format('Y-m-d') < $dateFrom) {
                $dateFrom = $datetime->format('Y-m-d');
            }
            if (! $dateTo || $datetime->format('Y-m-d') > $dateTo) {
                $dateTo = $datetime->format('Y-m-d');
            }

            $records[] = [
                'company_id' => $companyId,
                'upload_id' => null, // Set after upload created
                'product_id' => $productId,
                'harvester_number' => $harvesterNumber,
                'weight' => $weight,
                'tare' => $tare,
                'gross' => $gross,
                'weighed_at' => $datetime,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $rowCount++;
        }

        fclose($handle);

        // Create upload record
        $upload = HarvestUpload::create([
            'company_id' => $companyId,
            'product_id' => $productId,
            'uploaded_by' => $userId,
            'original_filename' => $file->getClientOriginalName(),
            'record_count' => $rowCount,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);

        // Load import settings for validation
        $settings = HarvestImportSettings::where('company_id', $companyId)->first();

        // Auto-detect tare variation if no settings configured
        $tareVariation = null;
        if (! $settings) {
            $tareVariation = $this->detectTareVariation($records);
        }

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
            } elseif ($tareVariation && $tare === 0.0) {
                // Auto-detect: flag records with tare=0 when others have tare>0
                $reasons[] = 'tare_out_of_range';
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

        return $upload;
    }

    private function detectTareVariation(array $records): bool
    {
        $hasZeroTare = false;
        $hasNonZeroTare = false;

        foreach ($records as $record) {
            if ($record['tare'] === 0.0) {
                $hasZeroTare = true;
            } elseif ($record['tare'] > 0) {
                $hasNonZeroTare = true;
            }

            if ($hasZeroTare && $hasNonZeroTare) {
                return true;
            }
        }

        return false;
    }

    private function parseDateTime(string $dateStr, string $timeStr): ?Carbon
    {
        if (empty($dateStr) || empty($timeStr)) {
            return null;
        }

        // Try YYYY-MM-DD format
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
            try {
                return Carbon::createFromFormat('Y-m-d H:i:s', "{$dateStr} {$timeStr}");
            } catch (\Exception) {
                return null;
            }
        }

        // Try DD-MM-YY format
        if (preg_match('/^\d{2}-\d{2}-\d{2}$/', $dateStr)) {
            try {
                return Carbon::createFromFormat('d-m-y H:i:s', "{$dateStr} {$timeStr}");
            } catch (\Exception) {
                return null;
            }
        }

        return null;
    }
}
