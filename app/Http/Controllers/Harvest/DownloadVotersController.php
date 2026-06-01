<?php

namespace App\Http\Controllers\Harvest;

use App\Http\Controllers\Controller;
use App\Models\HarvesterAssignment;
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
            $handle = fopen('php://output', 'wb');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, ['Redni broj', 'Ime i prezime berača', 'Prefiks'], ';');

            foreach ($assignments as $assignment) {
                fputcsv($handle, [
                    $assignment->number,
                    $assignment->harvester->name,
                    $assignment->harvester->prefix ?? '',
                ], ';');
            }

            fclose($handle);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
