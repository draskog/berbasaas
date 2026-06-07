<?php

namespace App\Http\Controllers\Harvest;

use App\Http\Controllers\Controller;
use App\Models\Harvester;
use App\Models\HarvestImportSettings;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadHarvestersController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        $harvesters = Harvester::where('company_id', auth()->user()->company_id)
            ->where('active', true)
            ->orderBy('name')
            ->get();

        if ($harvesters->isEmpty()) {
            abort(404, __('No harvester list available for download.'));
        }

        $filename = 'Spisak_beraca_'.now()->format('Y-m-d').'.csv';

        $callback = function () use ($harvesters) {
            $settings = HarvestImportSettings::where('company_id', auth()->user()->company_id)->first();
            $delimiter = $settings?->csv_delimiter ?? ',';

            $handle = fopen('php://output', 'wb');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, ['Redni broj', 'Ime i prezime berača', 'Prefiks'], $delimiter);

            $index = 1;
            foreach ($harvesters as $harvester) {
                fputcsv($handle, [
                    $index,
                    $harvester->name,
                    $harvester->prefix ?? '',
                ], $delimiter);
                $index++;
            }

            fclose($handle);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
