<?php

namespace App\Services;

use App\Models\HarvestRecord;
use App\Models\HarvestUpload;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;

class HarvestImportService
{
    public function parse(UploadedFile $file, int $companyId, int $productId, int $userId): HarvestUpload
    {
        $path = $file->getRealPath();
        $handle = fopen($path, 'r');

        // Read header
        $header = fgetcsv($handle);
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

        while (($row = fgetcsv($handle)) !== false) {
            if (empty($row) || !isset($row[$productCol])) {
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

            if (!$datetime) {
                continue;
            }

            // Track date range
            if (!$dateFrom || $datetime->format('Y-m-d') < $dateFrom) {
                $dateFrom = $datetime->format('Y-m-d');
            }
            if (!$dateTo || $datetime->format('Y-m-d') > $dateTo) {
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

        // Set upload_id on all records and bulk insert
        foreach ($records as &$record) {
            $record['upload_id'] = $upload->id;
        }

        // Insert in chunks
        foreach (array_chunk($records, 500) as $chunk) {
            HarvestRecord::insert($chunk);
        }

        return $upload;
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
