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
        $prefix = $request->query('prefix', '');

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
                $harvestPrefix = strtolower($assignment->harvester->prefix ?? '');

                return str_contains($name, $searchLower) || str_contains($harvestPrefix, $searchLower);
            })->values();
        }

        if ($prefix !== '') {
            $harvesterNumbers = $harvesterNumbers->filter(function ($number) use ($company, $year, $prefix) {
                $assignment = HarvesterAssignment::where('company_id', $company->id)
                    ->where('year', $year)
                    ->where('number', $number)
                    ->with('harvester')
                    ->first();

                return $assignment?->harvester?->prefix === $prefix;
            })->values();
        }

        $harvesters = [];
        // Load all historical prices (not just currently active)
        $allPrices = HarvestPrice::where('company_id', $company->id)
            ->orderBy('product_id')
            ->orderBy('effective_from')
            ->get();

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

            // Group records by price period for correct math
            $recordsByPriceId = [];
            $payslipRows = [];
            $totalWeight = 0;
            $totalEarnings = 0;

            foreach ($records as $record) {
                // Find price valid on this record's weighed_at date
                $priceModel = $allPrices->first(function ($p) use ($record) {
                    $recordDate = $record->weighed_at->startOfDay();
                    $effectiveFrom = Carbon::parse($p->effective_from)->startOfDay();
                    $effectiveTo = $p->effective_to ? Carbon::parse($p->effective_to)->startOfDay() : null;

                    return $p->product_id === $record->product_id
                        && $effectiveFrom <= $recordDate
                        && ($effectiveTo === null || $effectiveTo >= $recordDate);
                });

                $pricePerKg = $priceModel?->price_per_kg;
                $priceId = $priceModel?->id ?? 'null';

                // Track record for grouping by price (including records without price)
                if (! isset($recordsByPriceId[$priceId])) {
                    $recordsByPriceId[$priceId] = [
                        'price_model' => $priceModel,
                        'weight' => 0,
                    ];
                }
                $recordsByPriceId[$priceId]['weight'] += $record->weight;

                // Build payslip row (old format for detail listing)
                $earnings = $pricePerKg ? round($record->weight * $pricePerKg, 2) : 0;
                $payslipRows[] = [
                    'datetime' => $record->weighed_at->format('d.m.Y H:i'),
                    'product' => $record->product?->name ?? '—',
                    'weight' => round($record->weight, 3),
                    'price_per_kg' => $pricePerKg ? round($pricePerKg, 4) : null,
                    'earnings' => $earnings,
                ];

                $totalWeight += $record->weight;
            }

            // Calculate earnings and price periods using correct math
            $pricePeriods = [];
            foreach ($recordsByPriceId as $priceId => $data) {
                // Skip records without price
                if ($data['price_model'] === null) {
                    continue;
                }

                $totalWeightRounded = round($data['weight'], 2);
                $earnings = round($totalWeightRounded * $data['price_model']->price_per_kg, 2);
                $totalEarnings += $earnings;

                $pricePeriods[] = [
                    'price_id' => $priceId,
                    'price_per_kg' => $data['price_model']->price_per_kg,
                    'effective_from' => $data['price_model']->effective_from,
                    'effective_to' => $data['price_model']->effective_to,
                    'total_weight' => $totalWeightRounded,
                    'earnings' => $earnings,
                ];
            }

            // Sort price periods by effective_from
            usort($pricePeriods, static fn ($a, $b) => strtotime($a['effective_from']) <=> strtotime($b['effective_from']));

            $harvesters[] = [
                'number' => $number,
                'name' => $assignment?->harvester?->name ?? 'Unknown',
                'prefix' => $assignment?->harvester?->prefix,
                'records' => $payslipRows,
                'price_periods' => $pricePeriods,
                'totals' => [
                    'buckets' => count($payslipRows),
                    'weight' => round($totalWeight, 3),
                    'earnings' => round($totalEarnings, 2),
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
