<?php

namespace App\Http\Controllers\Harvest;

use App\Http\Controllers\Controller;
use App\Models\HarvesterAssignment;
use App\Models\HarvestPrice;
use App\Models\HarvestRecord;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PrintPayslipsController extends Controller
{
    public function __invoke(Request $request)
    {
        $year = (int) $request->query('year');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $company = auth()->user()->company;

        $query = HarvestRecord::where('company_id', $company->id)
            ->where('weighed_at', '>=', Carbon::parse($dateFrom)->startOfDay())
            ->where('weighed_at', '<=', Carbon::parse($dateTo)->endOfDay())
            ->whereYear('weighed_at', $year)
            ->with('product');

        $harvesterNumbers = $query->distinct()
            ->pluck('harvester_number')
            ->sort()
            ->values();

        $harvesters = [];
        $prices = HarvestPrice::where('company_id', $company->id)
            ->where(function ($q) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', now()->toDateString());
            })
            ->get()
            ->keyBy(fn ($p) => (string) $p->product_id);

        foreach ($harvesterNumbers as $number) {
            $assignment = HarvesterAssignment::where('company_id', $company->id)
                ->where('year', $year)
                ->where('number', $number)
                ->with('harvester')
                ->first();

            $records = HarvestRecord::where('company_id', $company->id)
                ->where('harvester_number', $number)
                ->where('weighed_at', '>=', Carbon::parse($dateFrom)->startOfDay())
                ->where('weighed_at', '<=', Carbon::parse($dateTo)->endOfDay())
                ->whereYear('weighed_at', $year)
                ->with('product')
                ->orderBy('weighed_at')
                ->get();

            $payslipRows = [];
            $totalWeight = 0;
            $totalEarnings = 0;
            $firstPrice = null;

            foreach ($records as $record) {
                $price = $prices->get((string) $record->product_id)?->price_per_kg;
                $earnings = $record->weight * ($price ?? 0);
                $earnings = round($earnings, 2);

                if ($firstPrice === null && $price !== null) {
                    $firstPrice = $price;
                }

                $payslipRows[] = [
                    'datetime' => $record->weighed_at->format('d.m.Y H:i'),
                    'product' => $record->product?->name ?? '—',
                    'weight' => round($record->weight, 3),
                    'price_per_kg' => $price ? round($price, 4) : null,
                    'earnings' => $earnings,
                ];

                $totalWeight += $record->weight;
                $totalEarnings += $earnings;
            }

            $harvesters[] = [
                'number' => $number,
                'name' => $assignment?->harvester?->name ?? 'Unknown',
                'prefix' => $assignment?->harvester?->prefix,
                'records' => $payslipRows,
                'totals' => [
                    'buckets' => count($payslipRows),
                    'weight' => round($totalWeight, 3),
                    'earnings' => round($totalEarnings, 2),
                    'price_per_kg' => $firstPrice,
                ],
            ];
        }

        return view('harvest.payslip-print', [
            'harvesters' => $harvesters,
            'company' => $company,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'year' => $year,
        ]);
    }
}
