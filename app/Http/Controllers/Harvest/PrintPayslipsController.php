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
        $search = $request->query('search', '');

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

        if ($search) {
            $searchLower = strtolower($search);
            $harvesterNumbers = $harvesterNumbers->filter(function ($number) use ($searchLower, $company, $year) {
                $assignment = HarvesterAssignment::where('company_id', $company->id)
                    ->where('year', $year)
                    ->where('number', $number)
                    ->with('harvester')
                    ->first();

                if (! $assignment || ! $assignment->harvester) {
                    return false;
                }

                $name = strtolower($assignment->harvester->name);
                $prefix = strtolower($assignment->harvester->prefix ?? '');

                return str_contains($name, $searchLower) || str_contains($prefix, $searchLower);
            })->values();
        }

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
            $weightByDate = []; // Track weight and price by date

            foreach ($records as $record) {
                $price = $prices->get((string) $record->product_id)?->price_per_kg;
                $earnings = $record->weight * ($price ?? 0);
                $earnings = round($earnings, 2);

                $payslipRows[] = [
                    'datetime' => $record->weighed_at->format('d.m.Y H:i'),
                    'product' => $record->product?->name ?? '—',
                    'weight' => round($record->weight, 3),
                    'price_per_kg' => $price ? round($price, 4) : null,
                    'earnings' => $earnings,
                ];

                $totalWeight += $record->weight;
                $totalEarnings += $earnings;

                // Group by date for weighted price calculation
                $date = $record->weighed_at->format('Y-m-d');
                if (! isset($weightByDate[$date])) {
                    $weightByDate[$date] = ['weight' => 0, 'price' => null];
                }
                $weightByDate[$date]['weight'] += $record->weight;
                if ($weightByDate[$date]['price'] === null && $price !== null) {
                    $weightByDate[$date]['price'] = $price;
                }
            }

            // Calculate weighted average price: sum(weight * price) / total weight
            $avgPrice = null;
            if ($totalWeight > 0) {
                $weightedPriceSum = 0;
                foreach ($weightByDate as $dateData) {
                    if ($dateData['price'] !== null) {
                        $weightedPriceSum += $dateData['weight'] * $dateData['price'];
                    }
                }
                if ($weightedPriceSum > 0) {
                    $avgPrice = round($weightedPriceSum / $totalWeight, 4);
                }
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
                    'price_per_kg' => $avgPrice,
                ],
            ];
        }

        return view('layouts.payslip-print', [
            'harvesters' => $harvesters,
            'company' => $company,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'year' => $year,
        ]);
    }
}
