<?php

namespace App\Http\Controllers\Harvest;

use App\Http\Controllers\Controller;
use App\Models\HarvesterAssignment;
use App\Models\HarvestImportSettings;
use Illuminate\Http\Response;

class DownloadVotersController extends Controller
{
    public function __invoke(): Response
    {
        $lastYear = HarvesterAssignment::where('company_id', auth()->user()->company_id)
            ->orderByDesc('year')
            ->value('year');

        if (! $lastYear) {
            abort(404, __('No voter list available for download.'));
        }

        $assignments = HarvesterAssignment::where('company_id', auth()->user()->company_id)
            ->where('year', $lastYear)
            ->with('harvester')
            ->orderBy('number')
            ->get();

        $filename = "Spisak_beraca_{$lastYear}.csv";

        $callback = function () use ($assignments) {
            $settings = HarvestImportSettings::where('company_id', auth()->user()->company_id)->first();
            $delimiter = $settings?->csv_delimiter ?? ',';

            $handle = fopen('php://output', 'wb');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, ['Redni broj', 'Ime i prezime berača', 'Prefiks'], $delimiter);

            foreach ($assignments as $assignment) {
                fputcsv($handle, [
                    $assignment->number,
                    $assignment->harvester->name,
                    $assignment->harvester->prefix ?? '',
                ], $delimiter);
            }

            fclose($handle);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
