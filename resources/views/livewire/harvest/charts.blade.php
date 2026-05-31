<?php

use App\Models\HarvesterAssignment;
use App\Models\HarvestRecord;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new
#[Layout('layouts.app')]
#[Title('Charts')]
class extends Component
{
    #[Url]
    public int $selectedYear = 0;

    #[Url]
    public ?string $fromDate = null;

    #[Url]
    public ?string $toDate = null;

    #[Url]
    public int $selectedProductId = 0;

    #[Url]
    public int $selectedHarvesterNumber = 0;

    #[Url]
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
            ->whereHas('harvestRecords', fn ($q) => $q
                ->where('company_id', auth()->user()->company_id)
                ->whereYear('weighed_at', $this->selectedYear))
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
        if (! $this->selectedYear) {
            $this->selectedYear = $years->isNotEmpty() ? $years->first() : now()->year;
        }

        if (! $this->fromDate || ! $this->toDate) {
            $this->updateDatesForSelectedYear();
        }
    }

    public function updatedSelectedYear(): void
    {
        $this->updateDatesForSelectedYear();
    }

    private function updateDatesForSelectedYear(): void
    {
        if ($this->fromDate) {
            $fromCarbon = Carbon::parse($this->fromDate);
            $this->fromDate = Carbon::create($this->selectedYear, $fromCarbon->month, $fromCarbon->day)->format('Y-m-d');
        } else {
            $this->fromDate = Carbon::create($this->selectedYear, 1, 1)->format('Y-m-d');
        }

        if ($this->toDate) {
            $toCarbon = Carbon::parse($this->toDate);
            $this->toDate = Carbon::create($this->selectedYear, $toCarbon->month, $toCarbon->day)->format('Y-m-d');
        } else {
            $this->toDate = Carbon::create($this->selectedYear, 12, 31)->format('Y-m-d');
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
            ->when($this->fromDate, fn ($q) => $q->whereDate('weighed_at', '>=', $this->fromDate))
            ->when($this->toDate, fn ($q) => $q->whereDate('weighed_at', '<=', $this->toDate))
            ->when($this->selectedProductId, fn ($q) => $q->where('product_id', $this->selectedProductId))
            ->when($this->selectedHarvesterNumber, fn ($q) => $q->where('harvester_number', $this->selectedHarvesterNumber));
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
            ->map(fn ($row) => [
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
    public function dailyWeightDisplay(): array
    {
        $kg = $this->dailyTotals['weight'];
        if ($kg >= 1000) {
            return [
                'value' => number_format($kg / 1000, 2, ',', '.'),
                'unit' => 't',
            ];
        }

        return [
            'value' => number_format($kg, 2, ',', '.'),
            'unit' => 'kg',
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
            ->map(fn ($row) => [
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
            ->map(fn ($row) => [
                'name' => $row->product->name,
                'bucket_count' => $row->bucket_count,
                'total_weight' => round($row->total_weight, 3),
            ])
            ->sortBy(fn ($row) => $row[$this->chartProductSortBy], SORT_REGULAR, $this->chartProductSortDirection === 'desc')
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
            'labels' => $data->map(fn ($row) => Carbon::parse($row->date)->format('d.m'))->values()->toArray(),
            'datasets' => [
                [
                    'label' => 'Total kg',
                    'data' => $data->map(fn ($row) => round($row->total_weight, 2))->values()->toArray(),
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
            'labels' => $data->map(fn ($row) => $names[$row->harvester_number] ?? "#$row->harvester_number")->values()->toArray(),
            'datasets' => [
                [
                    'label' => 'Total kg',
                    'data' => $data->map(fn ($row) => round($row->total_weight, 2))->values()->toArray(),
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

        $allHours = collect(range(0, 23))->mapWithKeys(fn ($h) => [$h => 0]);
        foreach ($data as $row) {
            $allHours[$row->hour] = $row->bucket_count;
        }

        return [
            'labels' => $allHours->keys()->map(fn ($h) => sprintf('%02dh', $h))->values()->toArray(),
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
            $labels = collect($labels)->filter(fn ($_, $i) => $i % $step === 0)->values()->toArray();
            $data = collect($data)->filter(fn ($_, $i) => $i % $step === 0)->values()->toArray();
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

    #[Computed]
    public function dailyChartRows()
    {
        $data = $this->baseQuery()
            ->selectRaw('DATE(weighed_at) as date, SUM(weight) as total_weight')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => Carbon::parse($row->date)->format('d.m.Y'),
                'total_weight' => round($row->total_weight, 2),
            ]);

        if ($data->count() === 1) {
            $firstDate = Carbon::createFromFormat('d.m.Y', $data->first()['date'])->subDay();
            $data->prepend([
                'date' => $firstDate->format('d.m.Y'),
                'total_weight' => 0,
            ]);
        }

        return $data;
    }

    #[Computed]
    public function harvesterChartRows()
    {
        $names = $this->harvesterNames();

        return $this->baseQuery()
            ->selectRaw('harvester_number, SUM(weight) as total_weight')
            ->groupBy('harvester_number')
            ->orderByDesc('total_weight')
            ->limit(20)
            ->get()
            ->map(fn ($row) => [
                'name' => $names[$row->harvester_number] ?? "#$row->harvester_number",
                'total_weight' => round($row->total_weight, 2),
            ]);
    }
}; ?>


    <flux:main>
        <flux:header heading="Harvest Charts">
            Harvest Charts
        </flux:header>

        <div class="p-6">
            <!-- Summary Cards -->
            <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @php
                    $dailyData = $this->dailyData;
                    $harvesterData = $this->harvesterData;
                    $productData = $this->productData;
                @endphp

                @if (!empty($dailyData))
                    <flux:card class="p-4">
                        <flux:text size="sm" class="text-zinc-500">Total Harvest</flux:text>
                        <div class="mt-2 text-2xl font-semibold">{{ $this->dailyWeightDisplay['value'] }} {{ $this->dailyWeightDisplay['unit'] }}</div>
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
            <!-- Year Pills -->
            <div class="mb-6 flex flex-wrap items-end gap-4">
                <flux:radio.group wire:model.live="selectedYear" label="Year" variant="pills">
                    @foreach($this->availableYears as $year)
                        <flux:radio label="{{ $year }}" value="{{ $year }}" />
                    @endforeach
                </flux:radio.group>
            </div>

            <!-- Product Pills -->
            <div class="mb-6 flex flex-wrap items-end gap-4">
                <flux:radio.group wire:model.live="selectedProductId" label="Product" variant="pills">
                    <flux:radio label="All" value="0" />
                    @foreach ($this->products as $product)
                        <flux:radio label="{{ $product->name }}" value="{{ $product->id }}" />
                    @endforeach
                </flux:radio.group>
            </div>

            <!-- Date Filters -->
            <div class="mb-6 flex flex-wrap items-end gap-4">
                <flux:input type="date" size="sm" wire:model.live="fromDate" label="From"/>
                <flux:input type="date" size="sm" wire:model.live="toDate" label="To"/>
            </div>

            <!-- Tabs -->
            <flux:tab.group>
                <flux:tabs wire:model.live="activeTab" class="mb-6">
                    <flux:tab name="daily">Daily Summary</flux:tab>
                    <flux:tab name="harvesters">Harvesters</flux:tab>
                    <flux:tab name="products">Products</flux:tab>
                </flux:tabs>

                <!-- Daily Summary Tab -->
                <flux:tab.panel name="daily">
                    @if ($this->dailyChartRows->isNotEmpty())
                        <flux:card>
                            <flux:chart :value="$this->dailyChartRows->toArray()" class="aspect-3/1">
                                <flux:chart.svg>
                                    <flux:chart.bar field="total_weight" class="text-blue-500" />
                                    <flux:chart.axis axis="x" field="date">
                                        <flux:chart.axis.tick />
                                        <flux:chart.axis.line />
                                    </flux:chart.axis>
                                    <flux:chart.axis axis="y">
                                        <flux:chart.axis.grid />
                                        <flux:chart.axis.tick />
                                    </flux:chart.axis>
                                </flux:chart.svg>
                            </flux:chart>
                        </flux:card>
                    @else
                        <flux:card>
                            <div class="flex items-center justify-center h-96 text-zinc-500">No data available</div>
                        </flux:card>
                    @endif
                </flux:tab.panel>

                <!-- Harvester Summary Tab -->
                <flux:tab.panel name="harvesters">
                    @if ($this->harvesterChartRows->isNotEmpty())
                        <flux:card>
                            <flux:chart :value="$this->harvesterChartRows->toArray()" class="aspect-3/1">
                                <flux:chart.svg>
                                    <flux:chart.bar field="total_weight" class="text-green-500" />
                                    <flux:chart.axis axis="x" field="name">
                                        <flux:chart.axis.tick />
                                        <flux:chart.axis.line />
                                    </flux:chart.axis>
                                    <flux:chart.axis axis="y">
                                        <flux:chart.axis.grid />
                                        <flux:chart.axis.tick />
                                    </flux:chart.axis>
                                </flux:chart.svg>
                            </flux:chart>
                        </flux:card>
                    @else
                        <flux:card>
                            <div class="flex items-center justify-center h-96 text-zinc-500">No data available</div>
                        </flux:card>
                    @endif
                </flux:tab.panel>

                <!-- Products Summary Tab -->
                <flux:tab.panel name="products">
                    @if (count($this->productData) > 0)
                        <flux:card>
                            <div class="overflow-x-auto">
                                <flux:table>
                                    <flux:table.columns>
                                        <flux:table.column>Product</flux:table.column>
                                        <flux:table.column class="text-right">Weight (kg)</flux:table.column>
                                        <flux:table.column class="text-right">Buckets</flux:table.column>
                                    </flux:table.columns>
                                    <flux:table.rows>
                                        @foreach ($this->productData as $row)
                                            <flux:table.row>
                                                <flux:table.cell>{{ $row['name'] }}</flux:table.cell>
                                                <flux:table.cell class="text-right">{{ number_format($row['total_weight'], 3, ',', '.') }}</flux:table.cell>
                                                <flux:table.cell class="text-right">{{ $row['bucket_count'] }}</flux:table.cell>
                                            </flux:table.row>
                                        @endforeach
                                    </flux:table.rows>
                                </flux:table>
                            </div>
                        </flux:card>
                    @else
                        <flux:card>
                            <div class="flex items-center justify-center h-96 text-zinc-500">No data available</div>
                        </flux:card>
                    @endif
                </flux:tab.panel>
            </flux:tab.group>
        </div>
    </flux:main>
