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
#[Title('Charts')]
class extends Component {
    public int $selectedYear;
    public ?string $fromDate = null;
    public ?string $toDate = null;
    public int $selectedProductId = 0;
    public int $selectedHarvesterNumber = 0;
    public string $activeTab = 'daily';

    public string $chartDailySortBy = 'date';
    public string $chartDailySortDirection = 'asc';

    public string $chartHarvesterSortBy = 'total_weight';
    public string $chartHarvesterSortDirection = 'desc';

    public string $chartProductSortBy = 'total_weight';
    public string $chartProductSortDirection = 'desc';

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
        $years = $this->availableYears;
        $this->selectedYear = $years->isNotEmpty() ? $years->first() : now()->year;
        $this->fromDate = now()->startOfYear()->format('Y-m-d');
        $this->toDate = now()->endOfYear()->format('Y-m-d');
        $product = $this->products->first();
        if ($product) {
            $this->selectedProductId = $product->id;
        }
    }

    public function sortChartDaily(string $column): void
    {
        if ($this->chartDailySortBy === $column) {
            $this->chartDailySortDirection = $this->chartDailySortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->chartDailySortBy = $column;
            $this->chartDailySortDirection = 'asc';
        }
    }

    public function sortChartHarvesters(string $column): void
    {
        if ($this->chartHarvesterSortBy === $column) {
            $this->chartHarvesterSortDirection = $this->chartHarvesterSortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->chartHarvesterSortBy = $column;
            $this->chartHarvesterSortDirection = 'asc';
        }
    }

    public function sortChartProducts(string $column): void
    {
        if ($this->chartProductSortBy === $column) {
            $this->chartProductSortDirection = $this->chartProductSortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->chartProductSortBy = $column;
            $this->chartProductSortDirection = 'asc';
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
            ->with('harvester')
            ->get()
            ->pluck('harvester.name', 'number')
            ->all();
    }

    #[Computed]
    public function dailyData()
    {
        return $this->baseQuery()
            ->selectRaw('DATE(weighed_at) as date, COUNT(*) as bucket_count, SUM(weight) as total_weight')
            ->groupBy('date')
            ->orderBy($this->chartDailySortBy, $this->chartDailySortDirection)
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
            ->orderBy($this->chartHarvesterSortBy, $this->chartHarvesterSortDirection)
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
            ])
            ->sortBy(fn($row) => $row[$this->chartProductSortBy], SORT_REGULAR, $this->chartProductSortDirection === 'desc')
            ->values();
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
            <flux:card class="mb-8">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <flux:field>
                        <flux:label>Year</flux:label>
                        <flux:select wire:model.live="selectedYear">
                            @foreach($this->availableYears as $year)
                                <flux:select.option value="{{ $year }}">{{ $year }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:field>

                    <flux:field>
                        <flux:label>From Date</flux:label>
                        <flux:input type="date" wire:model.live="fromDate" />
                    </flux:field>

                    <flux:field>
                        <flux:label>To Date</flux:label>
                        <flux:input type="date" wire:model.live="toDate" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Product</flux:label>
                        <flux:select wire:model.live="selectedProductId">
                            <flux:select.option value="0">All products</flux:select.option>
                            @foreach ($this->products as $product)
                                <flux:select.option value="{{ $product->id }}">{{ $product->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:field>
                </div>
            </flux:card>

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
                        <div class="mt-2 text-2xl font-semibold">{{ number_format($this->dailyTotals['weight'], 3, ',', '.') }}</div>
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
                        <div class="text-xs text-zinc-400">{{ number_format($this->harvesterData[0]['total_weight'] ?? 0, 3, ',', '.') }} kg</div>
                    </flux:card>
                @endif

                @if (!empty($productData))
                    <flux:card class="p-4">
                        <flux:text size="sm" class="text-zinc-500">Product Count</flux:text>
                        <div class="mt-2 text-2xl font-semibold">{{ count($this->productData) }}</div>
                    </flux:card>
                @endif
            </div>

            <!-- Tab Navigation -->
            <flux:tabs wire:model="activeTab" class="mb-6">
                <flux:tab name="daily">Daily Summary</flux:tab>
                <flux:tab name="harvesters">Harvesters</flux:tab>
                <flux:tab name="products">Products</flux:tab>
            </flux:tabs>

            <!-- Data Tables -->
            <!-- Daily Summary Tab -->
            <flux:tab.panel name="daily">
                <flux:card>
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column sortable :sorted="$chartDailySortBy === 'date'" :direction="$chartDailySortDirection" wire:click="sortChartDaily('date')">Date</flux:table.column>
                            <flux:table.column sortable :sorted="$chartDailySortBy === 'bucket_count'" :direction="$chartDailySortDirection" wire:click="sortChartDaily('bucket_count')">Buckets</flux:table.column>
                            <flux:table.column sortable :sorted="$chartDailySortBy === 'total_weight'" :direction="$chartDailySortDirection" wire:click="sortChartDaily('total_weight')">Total kg</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @forelse ($this->dailyData as $row)
                                <flux:table.row>
                                    <flux:table.cell>{{ \Carbon\Carbon::parse($row['date'])->format('d.m.Y') }}</flux:table.cell>
                                    <flux:table.cell>{{ $row['bucket_count'] }}</flux:table.cell>
                                    <flux:table.cell>{{ number_format($row['total_weight'], 3, ',', '.') }}</flux:table.cell>
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
                                    <flux:table.cell>{{ number_format($this->dailyTotals['weight'], 3, ',', '.') }}</flux:table.cell>
                                </flux:table.row>
                            @endif
                        </flux:table.rows>
                    </flux:table>
                </flux:card>
            </flux:tab.panel>

            <!-- Harvester Summary Tab -->
            <flux:tab.panel name="harvesters">
                <flux:card>
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column sortable :sorted="$chartHarvesterSortBy === 'harvester_number'" :direction="$chartHarvesterSortDirection" wire:click="sortChartHarvesters('harvester_number')">#</flux:table.column>
                            <flux:table.column>Name</flux:table.column>
                            <flux:table.column sortable :sorted="$chartHarvesterSortBy === 'bucket_count'" :direction="$chartHarvesterSortDirection" wire:click="sortChartHarvesters('bucket_count')">Buckets</flux:table.column>
                            <flux:table.column sortable :sorted="$chartHarvesterSortBy === 'total_weight'" :direction="$chartHarvesterSortDirection" wire:click="sortChartHarvesters('total_weight')">Total kg</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @forelse ($this->harvesterData as $row)
                                <flux:table.row>
                                    <flux:table.cell>{{ $row['number'] }}</flux:table.cell>
                                    <flux:table.cell>{{ $row['name'] }}</flux:table.cell>
                                    <flux:table.cell>{{ $row['bucket_count'] }}</flux:table.cell>
                                    <flux:table.cell>{{ number_format($row['total_weight'], 3, ',', '.') }}</flux:table.cell>
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
                                    <flux:table.cell>{{ number_format($this->harvesterTotals['weight'], 3, ',', '.') }}</flux:table.cell>
                                </flux:table.row>
                            @endif
                        </flux:table.rows>
                    </flux:table>
                </flux:card>
            </flux:tab.panel>

            <!-- Products Summary Tab -->
            <flux:tab.panel name="products">
                <flux:card>
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column sortable :sorted="$chartProductSortBy === 'name'" :direction="$chartProductSortDirection" wire:click="sortChartProducts('name')">Product</flux:table.column>
                            <flux:table.column sortable :sorted="$chartProductSortBy === 'bucket_count'" :direction="$chartProductSortDirection" wire:click="sortChartProducts('bucket_count')">Buckets</flux:table.column>
                            <flux:table.column sortable :sorted="$chartProductSortBy === 'total_weight'" :direction="$chartProductSortDirection" wire:click="sortChartProducts('total_weight')">Total kg</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @forelse ($this->productData as $row)
                                <flux:table.row>
                                    <flux:table.cell>{{ $row['name'] }}</flux:table.cell>
                                    <flux:table.cell>{{ $row['bucket_count'] }}</flux:table.cell>
                                    <flux:table.cell>{{ number_format($row['total_weight'], 3, ',', '.') }}</flux:table.cell>
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
                                    <flux:table.cell>{{ number_format($this->productTotals['weight'], 3, ',', '.') }}</flux:table.cell>
                                </flux:table.row>
                            @endif
                        </flux:table.rows>
                    </flux:table>
                </flux:card>
            </flux:tab.panel>
        </div>
    </flux:main>

