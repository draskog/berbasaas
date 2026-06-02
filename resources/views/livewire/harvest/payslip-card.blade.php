<?php

use App\Models\HarvesterAssignment;
use App\Models\HarvestPrice;
use App\Models\HarvestRecord;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    public int $harvesterNumber;

    public int $year;

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public function harvesterInfo (): array
    {
        $assignment = HarvesterAssignment::where('company_id', auth()->user()->company_id)
            ->where('year', $this->year)
            ->where('number', $this->harvesterNumber)
            ->with('harvester')
            ->first();

        return [
            'name' => $assignment?->harvester?->name ?? __('Unknown'),
            'prefix' => $assignment?->harvester?->prefix,
        ];
    }

    private function priceForRecord (HarvestRecord $record): ?HarvestPrice
    {
        return HarvestPrice::where('company_id', auth()->user()->company_id)
            ->where('product_id', $record->product_id)
            ->where('effective_from', '<=', $record->weighed_at->format('Y-m-d'))
            ->where(fn($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $record->weighed_at->format('Y-m-d')))
            ->first();
    }

    #[Computed]
    public function payslipData (): array
    {
        $query = HarvestRecord::where('company_id', auth()->user()->company_id)
            ->where('harvester_number', $this->harvesterNumber)
            ->whereYear('weighed_at', $this->year)
            ->with('product');

        if ($this->dateFrom) {
            $query->whereDate('weighed_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->whereDate('weighed_at', '<=', $this->dateTo);
        }

        $records = $query->orderBy('weighed_at', 'asc')->get();

        $data = [];
        foreach ($records as $record) {
            $priceModel = $this->priceForRecord($record);
            $pricePerKg = $priceModel?->price_per_kg;
            $earnings = $pricePerKg ? round($record->weight * $pricePerKg, 2) : null;

            $data[] = [
                'datetime' => $record->weighed_at,
                'product' => $record->product?->name ?? '—',
                'weight' => round($record->weight, 3),
                'price_id' => $priceModel?->id,
                'price_per_kg' => $pricePerKg,
                'price_effective_from' => $priceModel?->effective_from,
                'price_effective_to' => $priceModel?->effective_to,
                'earnings' => round($earnings),
            ];
        }

        return $data;
    }

    #[Computed]
    public function priceBreakdown (): array
    {
        $data = $this->payslipData;
        if (empty($data)) {
            return [];
        }

        // Group by price_id to calculate earnings per price period
        $groupedByPrice = collect($data)->groupBy(fn($r) => $r['price_id'] ?? 'null');

        $breakdown = [];
        foreach ($groupedByPrice as $priceId => $records) {
            if ($priceId === 'null') {
                continue; // Skip records with no price
            }

            $totalWeight = 0;
            $pricePerKg = null;
            $effectiveFrom = null;
            $effectiveTo = null;

            foreach ($records as $record) {
                $totalWeight += $record['weight'];
                if ($pricePerKg === null && $record['price_per_kg'] !== null) {
                    $pricePerKg = $record['price_per_kg'];
                    $effectiveFrom = $record['price_effective_from'];
                    $effectiveTo = $record['price_effective_to'];
                }
            }

            if ($pricePerKg !== null) {
                $totalWeightRounded = round($totalWeight, 2);
                $earnings = round($totalWeightRounded * $pricePerKg, 2);

                $breakdown[] = [
                    'price_id' => $priceId,
                    'price_per_kg' => $pricePerKg,
                    'effective_from' => $effectiveFrom,
                    'effective_to' => $effectiveTo,
                    'total_weight' => $totalWeightRounded,
                    'earnings' => round($earnings),
                ];
            }
        }

        // Sort by effective_from ascending
        usort($breakdown, static fn($a, $b) => strtotime($a['effective_from']) <=> strtotime($b['effective_from']));

        return $breakdown;
    }

    #[Computed]
    public function payslipTotals (): array
    {
        $data = $this->payslipData;
        if (empty($data)) {
            return ['buckets' => 0, 'weight' => 0, 'earnings' => 0];
        }

        $breakdown = $this->priceBreakdown;
        $totalWeight = 0;
        $totalEarnings = 0;

        // Sum totals from price breakdown
        foreach ($breakdown as $period) {
            $totalWeight += $period['total_weight'];
            $totalEarnings += $period['earnings'];
        }

        // Include weight from records without prices
        foreach ($data as $record) {
            if ($record['price_id'] === null) {
                $totalWeight += $record['weight'];
            }
        }

        return [
            'buckets' => count($data),
            'weight' => round($totalWeight, 3),
            'earnings' => round($totalEarnings),
        ];
    }

    #[Computed]
    public function chunkedData (): array
    {
        $data = $this->payslipData;
        if (empty($data)) {
            return [];
        }

        $count = count($data);
        if ($count <= 25) {
            $columnCount = 1;
        } elseif ($count <= 50) {
            $columnCount = 2;
        } else {
            $columnCount = 3;
        }

        $chunkSize = (int) ceil($count / $columnCount);

        return array_chunk($data, $chunkSize);
    }

    #[Computed]
    public function gridClass (): string
    {
        $count = count($this->chunkedData);

        return match ($count) {
            1 => 'grid-cols-1',
            2 => 'grid-cols-2',
            default => 'grid-cols-3',
        };
    }

    #[Computed]
    public function summaryGridClass (): string
    {
        return count($this->priceBreakdown) === 1 ? 'grid-cols-4' : 'grid-cols-3';
    }

    public function placeholder (): string
    {
        return <<<'HTML'
            <flux:card class="p-4 shadow-sm">
                <div class="flex flex-col items-center justify-center py-16">
                    <div class="mb-4">
                        <svg class="w-10 h-10 text-blue-500 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                    <flux:text size="sm" class="text-gray-500 dark:text-zinc-400">{{ __('Loading payslip...') }}</flux:text>
                </div>
            </flux:card>
        HTML;
    }
}; ?>

<flux:card class="p-4 shadow-sm" x-cloak>
    <!-- Header: Harvester left, Company right -->
    <div class="border-b-2 border-green-400 pb-4 dark:border-green-800 flex justify-between items-start mb-4">
        <div>
            <flux:heading size="lg">{{ __('Harvester #') }} {{ $this->harvesterNumber }} -
                @if ($this->harvesterInfo()['prefix'])
                    <span class="font-normal italic text-gray-500 dark:text-zinc-400">{{ $this->harvesterInfo()['prefix'] }}</span>
                @endif
                {{ $this->harvesterInfo()['name'] }}</flux:heading>
        </div>
        <div class="text-right">
            <flux:text size="lg" class="text-gray-500 dark:text-zinc-400">{{ auth()->user()->company->name }}</flux:text>
        </div>
    </div>

    @if (empty($this->payslipData))
        <div class="text-center text-gray-500 py-8">
            <flux:text>{{ __('No data for this harvester in the selected date range.') }}</flux:text>
        </div>
    @else
        <!-- Summary cards -->
        <div class="grid gap-3 {{ $this->summaryGridClass }} mb-6">
            <flux:card class="h-full p-3 hover:border-blue-300 transition-colors">
                <flux:heading size="xs">{{ __('Total buckets') }}</flux:heading>
                <flux:text class="text-lg font-bold mt-1">
                    {{ $this->payslipTotals['buckets'] }} <span class="text-gray-500 dark:text-zinc-400 text-xs">{{ __('kom') }}</span>
                </flux:text>
            </flux:card>
            <flux:card class="h-full p-3 hover:border-blue-300 transition-colors">
                <flux:heading size="xs">{{ __('Total weight') }}</flux:heading>
                <flux:text class="text-lg font-bold mt-1">
                    {{ number_format($this->payslipTotals['weight'], 2, ',', '.') }} <span class="text-gray-500 dark:text-zinc-400 text-xs">{{ __('kg') }}</span>
                </flux:text>
            </flux:card>
            @if (count($this->priceBreakdown) === 1)
                <flux:card class="h-full p-3 hover:border-blue-300 transition-colors">
                    <flux:heading size="xs">{{ __('Price per kg') }}</flux:heading>
                    <flux:text class="text-lg font-bold mt-1">
                        {{ number_format($this->priceBreakdown[0]['price_per_kg'], 0, '', '') }} <span class="text-gray-500 dark:text-zinc-400 text-xs">{{ __('RSD') }}</span>
                    </flux:text>
                </flux:card>
            @endif
            <flux:card class="h-full p-3 hover:border-blue-300 transition-colors">
                <flux:heading size="xs">{{ __('Total earnings') }}</flux:heading>
                <flux:text class="text-lg font-bold mt-1">
                    {{ number_format($this->payslipTotals['earnings'], 0, '', '') }} <span class="text-gray-500 dark:text-zinc-400 text-xs">{{ __('RSD') }}</span>
                </flux:text>
            </flux:card>
        </div>

        <!-- Price breakdown -->
        @if (count($this->priceBreakdown) > 1)
            <!-- Multiple price periods -->
            <div class="mb-6">
                <flux:heading size="xs" class="mb-3">{{ __('Price breakdown') }}</flux:heading>
                <flux:table class="[&_th]:py-2 [&_th]:px-2 [&_td]:py-2 [&_td]:px-2 [&_th]:text-xs [&_td]:text-xs">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Period') }}</flux:table.column>
                        <flux:table.column>{{ __('Weight (kg)') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Price per kg') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Subtotal') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->priceBreakdown as $period)
                            <flux:table.row>
                                <flux:table.cell>
                                    {{ Carbon::parse($period['effective_from'])->format('d.m.Y') }}
                                    –
                                    @if ($period['effective_to'])
                                        {{ Carbon::parse($period['effective_to'])->format('d.m.Y') }}
                                    @else
                                        {{ __('present') }}
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>{{ number_format($period['total_weight'], 2, ',', '.') }}</flux:table.cell>
                                <flux:table.cell class="text-right">{{ number_format($period['price_per_kg'], 0, '', '') }} RSD</flux:table.cell>
                                <flux:table.cell class="text-right font-semibold">{{ number_format(round($period['earnings']), 0, '', '') }} RSD</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif

        <!-- Multi-column detail table -->
        <div class="mb-12 grid gap-4 {{ $this->gridClass }}">
            @foreach ($this->chunkedData as $chunk)
                <flux:table class="[&_th]:py-2 [&_th]:px-2 [&_td]:py-1 [&_td]:px-2 [&_th]:text-xs [&_td]:text-xs">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Date') }}</flux:table.column>
                        <flux:table.column>{{ __('Weight (kg)') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($chunk as $row)
                            <flux:table.row>
                                <flux:table.cell>{{ Carbon::parse($row['datetime'])->format('d.m.Y') }}</flux:table.cell>
                                <flux:table.cell>{{ number_format($row['weight'], 3, ',', '.') }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endforeach
        </div>
    @endif
</flux:card>
