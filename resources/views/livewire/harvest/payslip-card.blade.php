<?php

use App\Models\HarvesterAssignment;
use App\Models\HarvestPrice;
use App\Models\HarvestRecord;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component
{
    public int $harvesterNumber;

    public int $year;

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public function harvesterInfo(): array
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

    private function priceForRecord(HarvestRecord $record): ?float
    {
        return HarvestPrice::where('company_id', auth()->user()->company_id)
            ->where('product_id', $record->product_id)
            ->where('effective_from', '<=', $record->weighed_at->format('Y-m-d'))
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $record->weighed_at->format('Y-m-d')))
            ->value('price_per_kg');
    }

    #[Computed]
    public function payslipData(): array
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
            $price = $this->priceForRecord($record);
            $earnings = $price ? round($record->weight * $price, 2) : null;

            $data[] = [
                'datetime' => $record->weighed_at,
                'product' => $record->product?->name ?? '—',
                'weight' => round($record->weight, 3),
                'price_per_kg' => $price,
                'earnings' => $earnings,
            ];
        }

        return $data;
    }

    #[Computed]
    public function payslipTotals(): array
    {
        $data = $this->payslipData;
        if (empty($data)) {
            return ['buckets' => 0, 'weight' => 0, 'earnings' => 0, 'price_per_kg' => null];
        }

        // Group by date and aggregate
        $groupedByDate = collect($data)->groupBy(fn ($r) => Carbon::parse($r['datetime'])->format('Y-m-d'));

        $totalWeight = 0;
        $totalEarnings = 0;
        $pricesByDate = [];

        foreach ($groupedByDate as $date => $dateRecords) {
            $dateWeight = 0;
            $datePrice = null;

            foreach ($dateRecords as $record) {
                $dateWeight += $record['weight'];
                if ($datePrice === null && $record['price_per_kg'] !== null) {
                    $datePrice = $record['price_per_kg'];
                }
            }

            if ($datePrice !== null) {
                $totalEarnings += round($dateWeight * $datePrice, 2);
                $pricesByDate[$date] = $datePrice;
            }

            $totalWeight += $dateWeight;
        }

        // Calculate average price weighted by date occurrences
        $avgPrice = ! empty($pricesByDate) ? round(collect($pricesByDate)->avg(), 4) : null;

        return [
            'buckets' => count($data),
            'weight' => round($totalWeight, 3),
            'earnings' => round($totalEarnings, 2),
            'price_per_kg' => $avgPrice,
        ];
    }

    #[Computed]
    public function chunkedData(): array
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
    public function gridClass(): string
    {
        $count = count($this->chunkedData);

        return match ($count) {
            1 => 'grid-cols-1',
            2 => 'grid-cols-2',
            default => 'grid-cols-3',
        };
    }

    public function placeholder(): string
    {
        return <<<'HTML'
            <div class="p-8">
                <div class="space-y-4">
                    <div class="h-8 bg-gray-200 dark:bg-zinc-700 rounded animate-pulse"></div>
                    <div class="space-y-2 mt-8">
                        <div class="h-10 bg-gray-200 dark:bg-zinc-700 rounded animate-pulse"></div>
                        <div class="h-10 bg-gray-200 dark:bg-zinc-700 rounded animate-pulse"></div>
                        <div class="h-10 bg-gray-200 dark:bg-zinc-700 rounded animate-pulse"></div>
                    </div>
                </div>
            </div>
        HTML;
    }
}; ?>

<flux:card class="p-4 print:border-0 print:bg-white print:shadow-none shadow-sm">
    <!-- Header: Harvester left, Company right -->
    <div class="border-b-2 border-blue-200 pb-4 dark:border-blue-900 flex justify-between items-start print:border-b print:border-gray-200 print:pb-2 mb-4 print:mb-2">
        <div>
            <flux:heading size="lg">#{{ $this->harvesterNumber }}
                @if ($this->harvesterInfo()['prefix'])
                    <span class="font-normal italic text-gray-500 dark:text-zinc-400">{{ $this->harvesterInfo()['prefix'] }}</span>
                @endif
                {{ $this->harvesterInfo()['name'] }}</flux:heading>
        </div>
        <div class="text-right">
            <flux:text size="sm" class="text-gray-500 dark:text-zinc-400">{{ auth()->user()->company->name }}</flux:text>
        </div>
    </div>

    @if (empty($this->payslipData))
        <div class="text-center text-gray-500 py-8">
            <flux:text>{{ __('No data for this harvester in the selected date range.') }}</flux:text>
        </div>
    @else
        <!-- Summary row -->
        <div class="border-b-2 border-green-200 pb-4 dark:border-green-900 mb-6 print:border-b print:border-gray-200 print:pb-2 print:mb-4">
            <div class="flex flex-wrap gap-8 text-sm font-semibold print:gap-6">
                <div>
                    <flux:text size="xs" class="text-gray-500 dark:text-zinc-400 mb-1">{{ __('Ukupna težina ubrano') }}</flux:text>
                    <flux:text size="md">{{ number_format($this->payslipTotals['weight'], 2, ',', '.') }} kg</flux:text>
                </div>
                <div>
                    <flux:text size="xs" class="text-gray-500 dark:text-zinc-400 mb-1">{{ __('Cena po kg') }}</flux:text>
                    <flux:text size="md">
                        @if ($this->payslipTotals['price_per_kg'])
                            {{ number_format($this->payslipTotals['price_per_kg'], 0, ',', '.') }}
                        @else
                            —
                        @endif
                    </flux:text>
                </div>
                <div>
                    <flux:text size="xs" class="text-gray-500 dark:text-zinc-400 mb-1">{{ __('Ukupna zarada') }}</flux:text>
                    <flux:text size="md">{{ number_format($this->payslipTotals['earnings'], 0, ',', '.') }}</flux:text>
                </div>
            </div>
        </div>

        <!-- Multi-column detail table -->
        <div class="mb-4 grid gap-8 {{ $this->gridClass }}">
            @foreach ($this->chunkedData as $chunk)
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Datum') }}</flux:table.column>
                        <flux:table.column>{{ __('Tezina (kg)') }}</flux:table.column>
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
