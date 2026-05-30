<?php

use App\Models\HarvesterAssignment;
use App\Models\HarvestPrice;
use App\Models\HarvestRecord;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('layouts.app')]
#[Title('Payslip')]
class extends Component
{
    public int $selectedYear;

    public int $selectedHarvesterNumber = 0;

    public string $sortBy = 'date';

    public string $sortDirection = 'asc';

    #[Computed]
    public function availableYears()
    {
        return HarvesterAssignment::where('company_id', auth()->user()->company_id)
            ->distinct()
            ->pluck('year')
            ->sort()
            ->reverse()
            ->values();
    }

    #[Computed]
    public function harvesterNumbers()
    {
        return HarvesterAssignment::where('company_id', auth()->user()->company_id)
            ->where('year', $this->selectedYear)
            ->distinct()
            ->pluck('number')
            ->sort()
            ->values();
    }

    #[Computed]
    public function harvesterOptions()
    {
        return HarvesterAssignment::where('company_id', auth()->user()->company_id)
            ->where('year', $this->selectedYear)
            ->with('harvester')
            ->distinct('number')
            ->orderBy('number')
            ->get()
            ->map(fn ($assignment) => [
                'value' => $assignment->number,
                'label' => "#{$assignment->number}",
                'name' => $assignment->harvester?->name ?? 'Unknown',
                'prefix' => $assignment->harvester?->prefix ?? '-',
            ]);
    }

    public function mount(): void
    {
        $years = $this->availableYears;
        $this->selectedYear = $years->isNotEmpty() ? $years->first() : now()->year;
        $firstNumber = $this->harvesterNumbers->first();
        if ($firstNumber) {
            $this->selectedHarvesterNumber = $firstNumber;
        }
    }

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function harvesterName(): ?string
    {
        if (! $this->selectedHarvesterNumber) {
            return null;
        }

        return HarvesterAssignment::where('company_id', auth()->user()->company_id)
            ->where('year', $this->selectedYear)
            ->where('number', $this->selectedHarvesterNumber)
            ->first()
            ?->harvester
            ?->name;
    }

    private function priceAt(string $date): ?float
    {
        $record = HarvestRecord::where('company_id', auth()->user()->company_id)
            ->where('harvester_number', $this->selectedHarvesterNumber)
            ->whereDate('weighed_at', $date)
            ->first();

        if (! $record) {
            return null;
        }

        return HarvestPrice::where('company_id', auth()->user()->company_id)
            ->where('product_id', $record->product_id)
            ->where('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date))
            ->value('price_per_kg');
    }

    #[Computed]
    public function payslipData()
    {
        if (! $this->selectedHarvesterNumber) {
            return [];
        }

        $records = HarvestRecord::where('company_id', auth()->user()->company_id)
            ->where('harvester_number', $this->selectedHarvesterNumber)
            ->selectRaw('DATE(weighed_at) as date, COUNT(*) as bucket_count, SUM(weight) as total_weight, product_id')
            ->groupBy('date', 'product_id')
            ->with('product')
            ->orderBy('date')
            ->get();

        $groupedByDate = $records->groupBy('date');
        $data = [];

        foreach ($groupedByDate as $date => $dayRecords) {
            $bucketCount = $dayRecords->sum('bucket_count');
            $totalWeight = $dayRecords->sum('total_weight');
            $price = $this->priceAt($date);
            $earnings = $price ? $totalWeight * $price : null;

            $data[] = [
                'date' => $date,
                'bucket_count' => $bucketCount,
                'total_weight' => round($totalWeight, 3),
                'price_per_kg' => $price,
                'earnings' => $earnings ? round($earnings, 2) : null,
            ];
        }

        return collect($data)
            ->sortBy(fn ($row) => $row[$this->sortBy], SORT_REGULAR, $this->sortDirection === 'desc')
            ->values()
            ->all();
    }

    #[Computed]
    public function payslipTotals()
    {
        $data = $this->payslipData;
        if (empty($data)) {
            return ['buckets' => 0, 'weight' => 0, 'earnings' => 0];
        }

        return [
            'buckets' => collect($data)->sum('bucket_count'),
            'weight' => round(collect($data)->sum('total_weight'), 3),
            'earnings' => round(collect($data)->sum('earnings') ?? 0, 2),
        ];
    }
}; ?>


    <flux:main>
        <flux:header heading="Harvesters Payslips" class="flex justify-end space-x-3 items-center">
            <flux:button icon="printer" variant="primary" onclick="window.print()">Print</flux:button>
        </flux:header>

        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-4">
                    <flux:radio.group wire:model.live="selectedYear" label="Year" variant="pills">
                        <flux:radio label="All" value="" />
                        @foreach($this->availableYears as $year)
                            <flux:radio label="{{ $year }}" value="{{ $year }}" />
                        @endforeach
                    </flux:radio.group>
                </div>
                <flux:select wire:model.live="perPage" size="sm" class="w-28">
                    <flux:select.option value="25">25</flux:select.option>
                    <flux:select.option value="50">50</flux:select.option>
                    <flux:select.option value="100">100</flux:select.option>
                    <flux:select.option value="0">All</flux:select.option>
                </flux:select>
            </div>
            <div class="mb-6">
                <flux:field>
                    <flux:label>Harvester</flux:label>
                    <flux:select wire:model.live="selectedHarvesterNumber" searchable placeholder="Search by number, name, or prefix...">
                        @foreach ($this->harvesterOptions as $option)
                            <flux:select.option value="{{ $option['value'] }}">
                                <div class="flex items-center justify-between">
                                    <span class="font-medium">{{ $option['label'] }} - {{ $option['name'] }}</span>
                                    <span class="text-xs text-zinc-500 ml-2">{{ $option['prefix'] }}</span>
                                </div>
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>
            </div>

            <!-- Print Area -->
            <flux:card class="p-8 print:border-0 print:bg-white">
                <!-- Header -->
                <div class="mb-8 border-b border-gray-200 pb-6 dark:border-zinc-700">
                    <flux:heading size="xl">{{ auth()->user()->company->name }}</flux:heading>
                    <flux:subheading class="mt-2">Harvester Payslip</flux:subheading>
                </div>

                <!-- Harvester Info -->
                @if ($selectedHarvesterNumber)
                    <div class="mb-8 grid grid-cols-3 gap-6 print:gap-4">
                        <div>
                            <flux:text size="sm">Harvester Number</flux:text>
                            <flux:heading class="mt-1">#{{ $selectedHarvesterNumber }}</flux:heading>
                        </div>
                        <div>
                            <flux:text size="sm">Harvester Name</flux:text>
                            <flux:heading class="mt-1">{{ $this->harvesterName() ?? 'Unknown' }}</flux:heading>
                        </div>
                        <div>
                            <flux:text size="sm">Season</flux:text>
                            <flux:heading class="mt-1">{{ $selectedYear }}</flux:heading>
                        </div>
                    </div>

                    <!-- Payslip Table -->
                    <div class="mb-8">
                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column sortable :sorted="$sortBy === 'date'" :direction="$sortDirection" wire:click="sort('date')">Date</flux:table.column>
                                <flux:table.column sortable :sorted="$sortBy === 'bucket_count'" :direction="$sortDirection" wire:click="sort('bucket_count')">Buckets</flux:table.column>
                                <flux:table.column sortable :sorted="$sortBy === 'total_weight'" :direction="$sortDirection" wire:click="sort('total_weight')">Total kg</flux:table.column>
                                <flux:table.column sortable :sorted="$sortBy === 'price_per_kg'" :direction="$sortDirection" wire:click="sort('price_per_kg')">Price/kg</flux:table.column>
                                <flux:table.column sortable :sorted="$sortBy === 'earnings'" :direction="$sortDirection" wire:click="sort('earnings')">Earnings</flux:table.column>
                            </flux:table.columns>

                            <flux:table.rows>
                                @forelse ($this->payslipData as $row)
                                    <flux:table.row>
                                        <flux:table.cell>{{ \Carbon\Carbon::parse($row['date'])->format('d.m.Y') }}</flux:table.cell>
                                        <flux:table.cell>{{ $row['bucket_count'] }}</flux:table.cell>
                                        <flux:table.cell>{{ number_format($row['total_weight'], 3, ',', '.') }}</flux:table.cell>
                                        <flux:table.cell>
                                            @if ($row['price_per_kg'])
                                                €{{ number_format($row['price_per_kg'], 3, ',', '.') }}
                                            @else
                                                —
                                            @endif
                                        </flux:table.cell>
                                        <flux:table.cell>
                                            @if ($row['earnings'] !== null)
                                                €{{ number_format($row['earnings'], 2, ',', '.') }}
                                            @else
                                                —
                                            @endif
                                        </flux:table.cell>
                                    </flux:table.row>
                                @empty
                                    <flux:table.row>
                                        <flux:table.cell colspan="5" class="text-center text-gray-500">No data for this harvester</flux:table.cell>
                                    </flux:table.row>
                                @endforelse

                                @if (!empty($this->payslipData))
                                    <flux:table.row class="border-t-2 border-gray-200 font-semibold dark:border-zinc-700">
                                        <flux:table.cell>Total</flux:table.cell>
                                        <flux:table.cell>{{ $this->payslipTotals['buckets'] }}</flux:table.cell>
                                        <flux:table.cell>{{ number_format($this->payslipTotals['weight'], 3, ',', '.') }}</flux:table.cell>
                                        <flux:table.cell>—</flux:table.cell>
                                        <flux:table.cell>€{{ number_format($this->payslipTotals['earnings'], 2, ',', '.') }}</flux:table.cell>
                                    </flux:table.row>
                                @endif
                            </flux:table.rows>
                        </flux:table>
                    </div>

                    <!-- Footer -->
                    <div class="border-t border-gray-200 pt-6 text-xs text-gray-500 dark:border-zinc-700 dark:text-zinc-400">
                        <p>Generated on {{ now()->format('d.m.Y H:i') }}</p>
                    </div>
                @endif
            </flux:card>
        </div>
    </flux:main>

