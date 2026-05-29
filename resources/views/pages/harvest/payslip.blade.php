<?php

use App\Models\HarvestRecord;
use App\Models\HarvesterAssignment;
use App\Models\HarvestPrice;
use App\Models\Product;
use Illuminate\Database\Query\Builder;
use Livewire\Volt\Component;

new class extends Component {
    public int $selectedYear;
    public int $selectedHarvesterNumber = 0;

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

    public function mount(): void
    {
        $this->selectedYear = now()->year;
        $firstNumber = $this->harvesterNumbers->first();
        if ($firstNumber) {
            $this->selectedHarvesterNumber = $firstNumber;
        }
    }

    private function harvesterName(): ?string
    {
        if (!$this->selectedHarvesterNumber) {
            return null;
        }

        return HarvesterAssignment::where('company_id', auth()->user()->company_id)
            ->where('year', $this->selectedYear)
            ->where('number', $this->selectedHarvesterNumber)
            ->value('name');
    }

    private function priceAt(string $date): ?float
    {
        $record = HarvestRecord::where('company_id', auth()->user()->company_id)
            ->where('harvester_number', $this->selectedHarvesterNumber)
            ->whereDate('weighed_at', $date)
            ->first();

        if (!$record) {
            return null;
        }

        return HarvestPrice::where('company_id', auth()->user()->company_id)
            ->where('product_id', $record->product_id)
            ->where('effective_from', '<=', $date)
            ->where(fn($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date))
            ->value('price_per_kg');
    }

    #[Computed]
    public function payslipData()
    {
        if (!$this->selectedHarvesterNumber) {
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

        return $data;
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

<x-layouts::app.sidebar title="Payslip">
    <flux:main>
        <flux:header heading="Harvester Payslip">
        </flux:header>

        <div class="p-6">
            <!-- Selector Panel (hidden on print) -->
            <div class="mb-8 rounded-lg border border-gray-200 bg-white p-6 print:hidden dark:border-zinc-700 dark:bg-zinc-800">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300">Year</label>
                        <select wire:model.live="selectedYear" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-zinc-600 dark:bg-zinc-700">
                            @for ($y = now()->year; $y >= now()->year - 5; $y--)
                                <option value="{{ $y }}">{{ $y }}</option>
                            @endfor
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300">Harvester</label>
                        <select wire:model.live="selectedHarvesterNumber" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-zinc-600 dark:bg-zinc-700">
                            @foreach ($this->harvesterNumbers as $number)
                                <option value="{{ $number }}">#{{ $number }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex items-end">
                        <button onclick="window.print()" class="w-full rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">
                            🖨 Print
                        </button>
                    </div>
                </div>
            </div>

            <!-- Print Area -->
            <div class="rounded-lg border border-gray-200 bg-white p-8 dark:border-zinc-700 dark:bg-zinc-800 print:border-0 print:bg-white">
                <!-- Header -->
                <div class="mb-8 border-b border-gray-200 pb-6 dark:border-zinc-700">
                    <h1 class="text-2xl font-bold">{{ auth()->user()->company->name }}</h1>
                    <p class="mt-2 text-gray-600 dark:text-zinc-400">
                        Harvester Payslip
                    </p>
                </div>

                <!-- Harvester Info -->
                @if ($selectedHarvesterNumber)
                    <div class="mb-8 grid grid-cols-3 gap-6 print:gap-4">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-zinc-400">Harvester Number</p>
                            <p class="text-lg font-semibold">#{{ $selectedHarvesterNumber }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 dark:text-zinc-400">Harvester Name</p>
                            <p class="text-lg font-semibold">{{ $this->harvesterName() ?? 'Unknown' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 dark:text-zinc-400">Season</p>
                            <p class="text-lg font-semibold">{{ $selectedYear }}</p>
                        </div>
                    </div>

                    <!-- Payslip Table -->
                    <div class="mb-8">
                        <flux:table>
                            <flux:columns>
                                <flux:column>Date</flux:column>
                                <flux:column>Buckets</flux:column>
                                <flux:column>Total kg</flux:column>
                                <flux:column>Price/kg</flux:column>
                                <flux:column>Earnings</flux:column>
                            </flux:columns>

                            <flux:rows>
                                @forelse ($this->payslipData as $row)
                                    <flux:row>
                                        <flux:cell>{{ \Carbon\Carbon::parse($row['date'])->format('d.m.Y') }}</flux:cell>
                                        <flux:cell>{{ $row['bucket_count'] }}</flux:cell>
                                        <flux:cell>{{ number_format($row['total_weight'], 3, '.', ',') }}</flux:cell>
                                        <flux:cell>
                                            @if ($row['price_per_kg'])
                                                €{{ number_format($row['price_per_kg'], 4, ',', '.') }}
                                            @else
                                                —
                                            @endif
                                        </flux:cell>
                                        <flux:cell>
                                            @if ($row['earnings'] !== null)
                                                €{{ number_format($row['earnings'], 2, ',', '.') }}
                                            @else
                                                —
                                            @endif
                                        </flux:cell>
                                    </flux:row>
                                @empty
                                    <flux:row>
                                        <flux:cell colspan="5" class="text-center text-gray-500">No data for this harvester</flux:cell>
                                    </flux:row>
                                @endforelse

                                @if (!empty($this->payslipData))
                                    <flux:row class="border-t-2 border-gray-200 font-semibold dark:border-zinc-700">
                                        <flux:cell>Total</flux:cell>
                                        <flux:cell>{{ $this->payslipTotals['buckets'] }}</flux:cell>
                                        <flux:cell>{{ number_format($this->payslipTotals['weight'], 3, '.', ',') }}</flux:cell>
                                        <flux:cell>—</flux:cell>
                                        <flux:cell>€{{ number_format($this->payslipTotals['earnings'], 2, ',', '.') }}</flux:cell>
                                    </flux:row>
                                @endif
                            </flux:rows>
                        </flux:table>
                    </div>

                    <!-- Footer -->
                    <div class="border-t border-gray-200 pt-6 text-xs text-gray-500 dark:border-zinc-700 dark:text-zinc-400">
                        <p>Generated on {{ now()->format('d.m.Y H:i') }}</p>
                    </div>
                @endif
            </div>
        </div>
    </flux:main>
</x-layouts::app.sidebar>
