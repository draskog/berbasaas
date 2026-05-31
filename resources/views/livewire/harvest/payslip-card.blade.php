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

    public function harvesterName(): ?string
    {
        return HarvesterAssignment::where('company_id', auth()->user()->company_id)
            ->where('year', $this->year)
            ->where('number', $this->harvesterNumber)
            ->first()
            ?->harvester
            ?->name;
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
            ->whereYear('weighed_at', $this->year);

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
            return ['buckets' => 0, 'weight' => 0, 'earnings' => 0];
        }

        return [
            'buckets' => count($data),
            'weight' => round(collect($data)->sum('weight'), 3),
            'earnings' => round(collect($data)->sum('earnings') ?? 0, 2),
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

<flux:card class="p-4 print:border-0 print:bg-white">
    <!-- Header: Harvester left, Company right -->
    <div class="border-b border-gray-200 pb-6 dark:border-zinc-700 flex justify-between items-start">
        <div>
            <flux:heading size="lg">#{{ $this->harvesterNumber }} {{ $this->harvesterName() ?? 'Unknown' }}</flux:heading>
        </div>
        <div class="text-right">
            <flux:text size="sm" class="text-gray-500 dark:text-zinc-400">{{ auth()->user()->company->name }}</flux:text>
        </div>
    </div>

    @if (empty($this->payslipData))
        <div class="text-center text-gray-500 py-8">
            <flux:text>No data for this harvester in the selected date range.</flux:text>
        </div>
    @else
        <!-- Multi-column table layout -->
        <div class="mb-8 grid gap-8 {{ $this->gridClass }}">
            @foreach ($this->chunkedData as $chunk)
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Date/Time</flux:table.column>
                        <flux:table.column>Weight (kg)</flux:table.column>
                        <flux:table.column>Price/kg</flux:table.column>
                        <flux:table.column>Earnings</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($chunk as $row)
                            <flux:table.row>
                                <flux:table.cell>{{ Carbon::parse($row['datetime'])->format('d.m.Y H:i') }}</flux:table.cell>
                                <flux:table.cell>{{ number_format($row['weight'], 3, ',', '.') }}</flux:table.cell>
                                <flux:table.cell>
                                    @if ($row['price_per_kg'])
                                        {{ number_format($row['price_per_kg'], 2, ',', '.') }}
                                    @else
                                        —
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($row['earnings'] !== null)
                                        {{ number_format($row['earnings'], 2, ',', '.') }}
                                    @else
                                        —
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endforeach
        </div>

        <!-- Totals bar -->
        <div class="border-t border-gray-200 pt-6 dark:border-zinc-700">
            <div class="flex flex-wrap gap-6 text-sm font-semibold">
                <div>
                    <flux:text size="sm" class="text-center text-gray-500 dark:text-zinc-400">Buckets</flux:text>
                    <flux:text size="md" class="text-center">{{ $this->payslipTotals['buckets'] }}</flux:text>
                </div>
                <div>
                    <flux:text size="sm" class="text-center text-gray-500 dark:text-zinc-400">Total Weight</flux:text>
                    <flux:text size="md" class="text-center">{{ number_format($this->payslipTotals['weight'], 3, ',', '.') }} kg</flux:text>
                </div>
                <div>
                    <flux:text size="sm" class="text-center text-gray-500 dark:text-zinc-400">Total Earnings</flux:text>
                    <flux:text size="md" class="text-center">{{ number_format($this->payslipTotals['earnings'], 2, ',', '.') }}</flux:text>
                </div>
            </div>
        </div>
    @endif
</flux:card>
