<?php

use App\Models\HarvestRecord;
use App\Models\HarvesterAssignment;
use App\Models\HarvestPrice;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('layouts.app')]
#[Title('Charts · eBorovnica')]
class extends Component {
    public int $selectedYear;
    public ?string $fromDate = null;
    public ?string $toDate = null;
    public int $selectedProductId = 0;
    public int $selectedHarvesterNumber = 0;
    public string $activeTab = 'daily';

    #[Computed]
    public function products()
    {
        return Product::where('company_id', auth()->user()->company_id)
            ->where('active', true)
            ->orderBy('name')
            ->get();
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

    public function mount(): void
    {
        $this->selectedYear = now()->year;
        $this->fromDate = now()->startOfYear()->format('Y-m-d');
        $this->toDate = now()->endOfYear()->format('Y-m-d');
        $product = $this->products->first();
        if ($product) {
            $this->selectedProductId = $product->id;
        }
    }

    private function baseQuery(): Builder
    {
        return HarvestRecord::where('company_id', auth()->user()->company_id)
            ->when($this->fromDate, fn($q) => $q->whereDate('weighed_at', '>=', $this->fromDate))
            ->when($this->toDate, fn($q) => $q->whereDate('weighed_at', '<=', $this->toDate))
            ->when($this->selectedProductId, fn($q) => $q->where('product_id', $this->selectedProductId))
            ->when($this->selectedHarvesterNumber, fn($q) => $q->where('harvester_number', $this->selectedHarvesterNumber));
    }

    private function harvesterNames(): array
    {
        return HarvesterAssignment::where('company_id', auth()->user()->company_id)
            ->where('year', $this->selectedYear)
            ->pluck('name', 'number')
            ->all();
    }

    #[Computed]
    public function dailyData()
    {
        return $this->baseQuery()
            ->selectRaw('DATE(weighed_at) as date, COUNT(*) as bucket_count, SUM(weight) as total_weight')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn($row) => [
                'date' => $row->date,
                'bucket_count' => $row->bucket_count,
                'total_weight' => round($row->total_weight, 3),
            ]);
    }

    #[Computed]
    public function dailyTotals()
    {
        $data = $this->dailyData;
        if ($data->isEmpty()) {
            return ['buckets' => 0, 'weight' => 0];
        }

        return [
            'buckets' => $data->sum('bucket_count'),
            'weight' => round($data->sum('total_weight'), 3),
        ];
    }

    #[Computed]
    public function harvesterData()
    {
        $names = $this->harvesterNames();

        return $this->baseQuery()
            ->selectRaw('harvester_number, COUNT(*) as bucket_count, SUM(weight) as total_weight')
            ->groupBy('harvester_number')
            ->orderByDesc('total_weight')
            ->get()
            ->map(fn($row) => [
                'number' => $row->harvester_number,
                'name' => $names[$row->harvester_number] ?? "#{$row->harvester_number}",
                'bucket_count' => $row->bucket_count,
                'total_weight' => round($row->total_weight, 3),
            ]);
    }

    #[Computed]
    public function harvesterTotals()
    {
        $data = $this->harvesterData;
        if ($data->isEmpty()) {
            return ['buckets' => 0, 'weight' => 0];
        }

        return [
            'buckets' => $data->sum('bucket_count'),
            'weight' => round($data->sum('total_weight'), 3),
        ];
    }

    #[Computed]
    public function productData()
    {
        return $this->baseQuery()
            ->selectRaw('product_id, COUNT(*) as bucket_count, SUM(weight) as total_weight')
            ->groupBy('product_id')
            ->with('product')
            ->get()
            ->map(fn($row) => [
                'name' => $row->product->name,
                'bucket_count' => $row->bucket_count,
                'total_weight' => round($row->total_weight, 3),
            ]);
    }

    #[Computed]
    public function productTotals()
    {
        $data = $this->productData;
        if ($data->isEmpty()) {
            return ['buckets' => 0, 'weight' => 0];
        }

        return [
            'buckets' => $data->sum('bucket_count'),
            'weight' => round($data->sum('total_weight'), 3),
        ];
    }

    #[Computed]
    public function dailyKgChartData()
    {
        $data = $this->baseQuery()
            ->selectRaw('DATE(weighed_at) as date, SUM(weight) as total_weight')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'labels' => $data->map(fn($row) => \Carbon\Carbon::parse($row->date)->format('d.m'))->values()->toArray(),
            'datasets' => [
                [
                    'label' => 'Total kg',
                    'data' => $data->map(fn($row) => round($row->total_weight, 2))->values()->toArray(),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.8)',
                    'borderColor' => 'rgba(59, 130, 246, 1)',
                    'borderWidth' => 1,
                ],
            ],
        ];
    }

    #[Computed]
    public function harvesterComparisonChartData()
    {
        $names = $this->harvesterNames();
        $data = $this->baseQuery()
            ->selectRaw('harvester_number, SUM(weight) as total_weight')
            ->groupBy('harvester_number')
            ->orderByDesc('total_weight')
            ->limit(20)
            ->get();

        return [
            'labels' => $data->map(fn($row) => $names[$row->harvester_number] ?? "#$row->harvester_number")->values()->toArray(),
            'datasets' => [
                [
                    'label' => 'Total kg',
                    'data' => $data->map(fn($row) => round($row->total_weight, 2))->values()->toArray(),
                    'backgroundColor' => 'rgba(34, 197, 94, 0.8)',
                    'borderColor' => 'rgba(34, 197, 94, 1)',
                    'borderWidth' => 1,
                ],
            ],
        ];
    }

    #[Computed]
    public function hourlyDistributionChartData()
    {
        $data = $this->baseQuery()
            ->selectRaw('HOUR(weighed_at) as hour, COUNT(*) as bucket_count')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        $allHours = collect(range(0, 23))->mapWithKeys(fn($h) => [$h => 0]);
        foreach ($data as $row) {
            $allHours[$row->hour] = $row->bucket_count;
        }

        return [
            'labels' => $allHours->keys()->map(fn($h) => sprintf('%02dh', $h))->values()->toArray(),
            'datasets' => [
                [
                    'label' => 'Buckets',
                    'data' => $allHours->values()->toArray(),
                    'backgroundColor' => 'rgba(168, 85, 247, 0.8)',
                    'borderColor' => 'rgba(168, 85, 247, 1)',
                    'borderWidth' => 1,
                ],
            ],
        ];
    }

    #[Computed]
    public function cumulativeKgChartData()
    {
        $records = $this->baseQuery()
            ->selectRaw('weighed_at, weight')
            ->orderBy('weighed_at')
            ->get();

        $cumulative = 0;
        $labels = [];
        $data = [];

        foreach ($records as $record) {
            $cumulative += $record->weight;
            $labels[] = $record->weighed_at->format('d.m H:i');
            $data[] = round($cumulative, 2);
        }

        // Limit to every 50th point to avoid overcrowding
        if (count($labels) > 50) {
            $step = ceil(count($labels) / 50);
            $labels = collect($labels)->filter(fn($_, $i) => $i % $step === 0)->values()->toArray();
            $data = collect($data)->filter(fn($_, $i) => $i % $step === 0)->values()->toArray();
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Cumulative kg',
                    'data' => $data,
                    'borderColor' => 'rgba(239, 68, 68, 1)',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'tension' => 0.3,
                    'fill' => true,
                    'borderWidth' => 2,
                ],
            ],
        ];
    }
}; ?>


    <flux:main>
        <flux:header heading="Harvest Charts">
        </flux:header>

        <div class="p-6">
            <!-- Filter Panel -->
            <div class="mb-8 rounded-lg border border-gray-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300">Year</label>
                        <select wire:model.live="selectedYear" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-zinc-600 dark:bg-zinc-700">
                            @for ($y = now()->year; $y >= now()->year - 5; $y--)
                                <option value="{{ $y }}">{{ $y }}</option>
                            @endfor
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300">From Date</label>
                        <input type="date" wire:model.live="fromDate" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-zinc-600 dark:bg-zinc-700" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300">To Date</label>
                        <input type="date" wire:model.live="toDate" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-zinc-600 dark:bg-zinc-700" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300">Product</label>
                        <select wire:model.live="selectedProductId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-zinc-600 dark:bg-zinc-700">
                            <option value="0">All products</option>
                            @foreach ($this->products as $product)
                                <option value="{{ $product->id }}">{{ $product->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @php
                    $dailyData = $this->dailyData;
                    $harvesterData = $this->harvesterData;
                    $productData = $this->productData;
                @endphp

                @if (!empty($dailyData))
                    <flux:card class="p-4">
                        <flux:text size="sm" class="text-zinc-500">Total Harvest (kg)</flux:text>
                        <div class="mt-2 text-2xl font-semibold">{{ number_format($this->dailyTotals['weight'], 3, '.', ',') }}</div>
                        <div class="text-xs text-zinc-400">{{ count($this->dailyData) }} days</div>
                    </flux:card>

                    <flux:card class="p-4">
                        <flux:text size="sm" class="text-zinc-500">Total Buckets</flux:text>
                        <div class="mt-2 text-2xl font-semibold">{{ $this->dailyTotals['buckets'] }}</div>
                    </flux:card>
                @endif

                @if (!empty($harvesterData))
                    <flux:card class="p-4">
                        <flux:text size="sm" class="text-zinc-500">Top Harvester</flux:text>
                        <div class="mt-2 text-lg font-semibold">{{ $this->harvesterData[0]['name'] ?? 'N/A' }}</div>
                        <div class="text-xs text-zinc-400">{{ number_format($this->harvesterData[0]['total_weight'] ?? 0, 3, '.', ',') }} kg</div>
                    </flux:card>
                @endif

                @if (!empty($productData))
                    <flux:card class="p-4">
                        <flux:text size="sm" class="text-zinc-500">Product Count</flux:text>
                        <div class="mt-2 text-2xl font-semibold">{{ count($this->productData) }}</div>
                    </flux:card>
                @endif
            </div>

            <!-- Data Tables -->
            <div class="space-y-6">
                <!-- Daily Summary -->
                @if ($activeTab === 'daily')
                    <flux:card>
                        <flux:heading size="sm">Daily Summary</flux:heading>
                        <flux:table class="mt-4">
                            <flux:table.columns>
                                <flux:table.column>Date</flux:table.column>
                                <flux:table.column>Buckets</flux:table.column>
                                <flux:table.column>Total kg</flux:table.column>
                            </flux:table.columns>

                            <flux:table.rows>
                                @forelse ($this->dailyData as $row)
                                    <flux:table.row>
                                        <flux:table.cell>{{ \Carbon\Carbon::parse($row['date'])->format('d.m.Y') }}</flux:table.cell>
                                        <flux:table.cell>{{ $row['bucket_count'] }}</flux:table.cell>
                                        <flux:table.cell>{{ number_format($row['total_weight'], 3, '.', ',') }}</flux:table.cell>
                                    </flux:table.row>
                                @empty
                                    <flux:table.row>
                                        <flux:table.cell colspan="3" class="text-center text-gray-500">No data</flux:table.cell>
                                    </flux:table.row>
                                @endforelse

                                @if (!empty($this->dailyData))
                                    <flux:table.row class="border-t-2 border-gray-200 font-semibold dark:border-zinc-700">
                                        <flux:table.cell>Total</flux:table.cell>
                                        <flux:table.cell>{{ $this->dailyTotals['buckets'] }}</flux:table.cell>
                                        <flux:table.cell>{{ number_format($this->dailyTotals['weight'], 3, '.', ',') }}</flux:table.cell>
                                    </flux:table.row>
                                @endif
                            </flux:table.rows>
                        </flux:table>
                    </flux:card>
                @endif

                <!-- Harvester Summary -->
                @if ($activeTab === 'harvesters')
                    <flux:card>
                        <flux:heading size="sm">Harvester Summary</flux:heading>
                        <flux:table class="mt-4">
                            <flux:table.columns>
                                <flux:table.column>#</flux:table.column>
                                <flux:table.column>Name</flux:table.column>
                                <flux:table.column>Buckets</flux:table.column>
                                <flux:table.column>Total kg</flux:table.column>
                            </flux:table.columns>

                            <flux:table.rows>
                                @forelse ($this->harvesterData as $row)
                                    <flux:table.row>
                                        <flux:table.cell>{{ $row['number'] }}</flux:table.cell>
                                        <flux:table.cell>{{ $row['name'] }}</flux:table.cell>
                                        <flux:table.cell>{{ $row['bucket_count'] }}</flux:table.cell>
                                        <flux:table.cell>{{ number_format($row['total_weight'], 3, '.', ',') }}</flux:table.cell>
                                    </flux:table.row>
                                @empty
                                    <flux:table.row>
                                        <flux:table.cell colspan="4" class="text-center text-gray-500">No data</flux:table.cell>
                                    </flux:table.row>
                                @endforelse

                                @if (!empty($this->harvesterData))
                                    <flux:table.row class="border-t-2 border-gray-200 font-semibold dark:border-zinc-700">
                                        <flux:table.cell colspan="2">Total</flux:table.cell>
                                        <flux:table.cell>{{ $this->harvesterTotals['buckets'] }}</flux:table.cell>
                                        <flux:table.cell>{{ number_format($this->harvesterTotals['weight'], 3, '.', ',') }}</flux:table.cell>
                                    </flux:table.row>
                                @endif
                            </flux:table.rows>
                        </flux:table>
                    </flux:card>
                @endif

                <!-- Products Summary -->
                @if ($activeTab === 'products')
                    <flux:card>
                        <flux:heading size="sm">Product Summary</flux:heading>
                        <flux:table class="mt-4">
                            <flux:table.columns>
                                <flux:table.column>Product</flux:table.column>
                                <flux:table.column>Buckets</flux:table.column>
                                <flux:table.column>Total kg</flux:table.column>
                            </flux:table.columns>

                            <flux:table.rows>
                                @forelse ($this->productData as $row)
                                    <flux:table.row>
                                        <flux:table.cell>{{ $row['name'] }}</flux:table.cell>
                                        <flux:table.cell>{{ $row['bucket_count'] }}</flux:table.cell>
                                        <flux:table.cell>{{ number_format($row['total_weight'], 3, '.', ',') }}</flux:table.cell>
                                    </flux:table.row>
                                @empty
                                    <flux:table.row>
                                        <flux:table.cell colspan="3" class="text-center text-gray-500">No data</flux:table.cell>
                                    </flux:table.row>
                                @endforelse

                                @if (!empty($this->productData))
                                    <flux:table.row class="border-t-2 border-gray-200 font-semibold dark:border-zinc-700">
                                        <flux:table.cell>Total</flux:table.cell>
                                        <flux:table.cell>{{ $this->productTotals['buckets'] }}</flux:table.cell>
                                        <flux:table.cell>{{ number_format($this->productTotals['weight'], 3, '.', ',') }}</flux:table.cell>
                                    </flux:table.row>
                                @endif
                            </flux:table.rows>
                        </flux:table>
                    </flux:card>
                @endif
            </div>
        </div>
    </flux:main>

