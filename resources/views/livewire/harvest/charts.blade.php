<?php

use App\Models\Harvester;
use App\Models\HarvesterAssignment;
use App\Models\HarvestRecord;
use App\Models\Product;
use Carbon\Carbon;
use Flux\DateRange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Session;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('layouts.app')]
#[Title('Charts')]
class extends Component
{
    #[Session]
    public int $selectedYear = 0;

    #[Session]
    public ?string $fromDate = null;

    #[Session]
    public ?string $toDate = null;

    #[Session]
    public int $selectedProductId = 0;

    public ?DateRange $dateRange = null;

    #[Session]
    public int $selectedHarvesterNumber = 0;

    #[Session]
    public string $activeTab = 'daily';

    public string $chartDailySortBy = 'date';

    public string $chartDailySortDirection = 'asc';

    public string $chartHarvesterSortBy = 'total_weight';

    public string $chartHarvesterSortDirection = 'desc';

    public string $chartProductSortBy = 'total_weight';

    public string $chartProductSortDirection = 'desc';

    #[Computed]
    public function availableYears(): Collection
    {
        return HarvestRecord::where('company_id', auth()->user()->company_id)
            ->get()
            ->map(fn ($record) => $record->weighed_at->year)
            ->unique()
            ->sort()
            ->reverse()
            ->values();
    }

    #[Computed]
    public function products(): Collection
    {
        return Product::where('company_id', auth()->user()->company_id)
            ->whereHas('harvestRecords', fn ($q) => $q
                ->where('company_id', auth()->user()->company_id)
                ->whereYear('weighed_at', $this->selectedYear))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function harvesterNumbers(): Collection
    {
        $query = HarvesterAssignment::where('company_id', auth()->user()->company_id);

        if ($this->selectedYear > 0) {
            $query->where('year', $this->selectedYear);
        }

        return $query->distinct()
            ->pluck('number')
            ->sort()
            ->values();
    }

    #[Computed]
    public function datesWithData(): array
    {
        $query = HarvestRecord::query()
            ->where('company_id', auth()->user()->company_id);

        if ($this->selectedYear > 0) {
            $query->whereYear('weighed_at', $this->selectedYear);
        }

        return $query->selectRaw('DATE(weighed_at) as record_date')
            ->distinct()
            ->pluck('record_date')
            ->toArray();
    }

    #[Computed]
    public function unavailableDates(): array
    {
        $start = Carbon::create($this->selectedYear);
        $end = Carbon::create($this->selectedYear, 12, 31);
        $with = array_flip($this->datesWithData);
        $unavailable = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $key = $d->format('Y-m-d');
            if (! isset($with[$key])) {
                $unavailable[] = $key;
            }
        }

        return $unavailable;
    }

    #[Computed]
    public function minDate(): string
    {
        $year = $this->selectedYear ?: $this->availableYears->first() ?? now()->year;

        return Carbon::create($year)->format('Y-m-d');
    }

    #[Computed]
    public function maxDate(): string
    {
        $year = $this->selectedYear ?: $this->availableYears->first() ?? now()->year;

        return Carbon::create($year, 12, 31)->format('Y-m-d');
    }

    public function mount(): void
    {
        $years = $this->availableYears;
        if (! $this->selectedYear) {
            $this->selectedYear = $years->isNotEmpty() ? $years->first() : now()->year;
        }

        // Učitaj activeTab iz URL query parametra, pa tek onda iz sesije
        $this->activeTab = request()->query('tab', session('harvest_charts_active_tab', 'daily'));

        // Učitaj datume iz URL query parametara
        if (request()->has('from') && request()->has('to')) {
            $this->fromDate = request()->query('from');
            $this->toDate = request()->query('to');
        } else {
            $this->updateDatesForSelectedYear();
        }

        if ($this->fromDate && $this->toDate) {
            $this->dateRange = new DateRange($this->fromDate, $this->toDate);
        }
    }

    public function updatedSelectedYear(): void
    {
        $this->updateDatesForSelectedYear();
        if ($this->fromDate && $this->toDate) {
            $this->dateRange = new DateRange($this->fromDate, $this->toDate);
        }
    }

    public function updatedDateRange(): void
    {
        if ($this->dateRange instanceof DateRange) {
            $this->fromDate = $this->dateRange->start()->format('Y-m-d');
            $this->toDate = $this->dateRange->end()->format('Y-m-d');
        }
    }

    private function updateDatesForSelectedYear(): void
    {
        if ($this->fromDate) {
            $fromCarbon = Carbon::parse($this->fromDate);
            $this->fromDate = Carbon::create($this->selectedYear, $fromCarbon->month, $fromCarbon->day)->format('Y-m-d');
        } else {
            $this->fromDate = Carbon::create($this->selectedYear)->format('Y-m-d');
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
        // Get all harvester names, grouped by number (latest year wins)
        return HarvesterAssignment::where('company_id', auth()->user()->company_id)
            ->with('harvester')
            ->orderByDesc('year')
            ->get()
            ->unique('number')
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
            ->map(fn (object $row) => [
                'date' => $row->date,
                'bucket_count' => $row->bucket_count,
                'total_weight' => round($row->total_weight, 3),
            ]);
    }

    #[Computed]
    public function dailyTotals(): array
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
            ->map(function (object $row) use ($names) {
                return [
                    'number' => $row->harvester_number,
                    'name' => $names[$row->harvester_number] ?? "#$row->harvester_number",
                    'bucket_count' => $row->bucket_count,
                    'total_weight' => round($row->total_weight, 3),
                ];
            });
    }

    #[Computed]
    public function harvesterTotals(): array
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
    public function productData(): Collection
    {
        return $this->baseQuery()
            ->selectRaw('product_id, COUNT(*) as bucket_count, SUM(weight) as total_weight')
            ->groupBy('product_id')
            ->with('product')
            ->get()
            ->map(fn (HarvestRecord $row) => [
                'name' => $row->product->name,
                'bucket_count' => $row->bucket_count,
                'total_weight' => round($row->total_weight, 3),
            ])
            ->sortBy(fn ($row) => $row[$this->chartProductSortBy], SORT_REGULAR, $this->chartProductSortDirection === 'desc')
            ->values();
    }

    #[Computed]
    public function productTotals(): array
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
    public function dailyKgChartData(): array
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
                    'label' => __('Total kg'),
                    'data' => $data->map(fn ($row) => round($row->total_weight, 2))->values()->toArray(),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.8)',
                    'borderColor' => 'rgba(59, 130, 246, 1)',
                    'borderWidth' => 1,
                ],
            ],
        ];
    }

    #[Computed]
    public function harvesterComparisonChartData(): array
    {
        $names = $this->harvesterNames();
        $data = $this->baseQuery()
            ->selectRaw('harvester_number, SUM(weight) as total_weight')
            ->groupBy('harvester_number')
            ->orderByDesc('total_weight')
            ->limit(20)
            ->get();

        return [
            'labels' => $data->map(fn (object $row) => $names[$row->harvester_number] ?? "#$row->harvester_number")->values()->toArray(),
            'datasets' => [
                [
                    'label' => __('Total kg'),
                    'data' => $data->map(fn (object $row) => round($row->total_weight, 2))->values()->toArray(),
                    'backgroundColor' => 'rgba(34, 197, 94, 0.8)',
                    'borderColor' => 'rgba(34, 197, 94, 1)',
                    'borderWidth' => 1,
                ],
            ],
        ];
    }

    #[Computed]
    public function hourlyDistributionChartData(): array
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
                    'label' => __('Buckets'),
                    'data' => $allHours->values()->toArray(),
                    'backgroundColor' => 'rgba(168, 85, 247, 0.8)',
                    'borderColor' => 'rgba(168, 85, 247, 1)',
                    'borderWidth' => 1,
                ],
            ],
        ];
    }

    #[Computed]
    public function cumulativeKgChartData(): array
    {
        $records = $this->baseQuery()
            ->selectRaw('weighed_at, weight')
            ->orderBy('weighed_at')
            ->get();

        $cumulative = 0;
        $labels = [];
        $data = [];

        /** @var HarvestRecord $record */
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
                    'label' => __('Cumulative kg'),
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
            ->selectRaw('DATE(weighed_at) as date, COUNT(*) as bucket_count, SUM(weight) as total_weight, MIN(weight) as min_weight, MAX(weight) as max_weight')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => Carbon::parse($row->date)->format('d.m.Y'),
                'bucket_count' => $row->bucket_count,
                'total_weight' => round($row->total_weight, 2),
                'avg_weight' => $row->bucket_count > 0 ? round($row->total_weight / $row->bucket_count, 3) : 0,
                'min_weight' => round($row->min_weight, 3),
                'max_weight' => round($row->max_weight, 3),
            ]);

        if ($data->count() === 1) {
            $firstDate = Carbon::createFromFormat('d.m.Y', $data->first()['date'])->subDay();
            $data->prepend([
                'date' => $firstDate->format('d.m.Y'),
                'bucket_count' => 0,
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
            ->selectRaw('harvester_number, COUNT(*) as bucket_count, SUM(weight) as total_weight, MIN(weight) as min_weight, MAX(weight) as max_weight')
            ->groupBy('harvester_number')
            ->orderByDesc('total_weight')
            ->limit(20)
            ->get()
            ->map(fn (object $row) => [
                'number' => $row->harvester_number,
                'name' => $names[$row->harvester_number] ?? "#$row->harvester_number",
                'label' => ($names[$row->harvester_number] ?? "#$row->harvester_number")."\n#".$row->harvester_number,
                'bucket_count' => $row->bucket_count,
                'total_weight' => round($row->total_weight, 2),
                'avg_weight' => $row->bucket_count > 0 ? round($row->total_weight / $row->bucket_count, 3) : 0,
                'min_weight' => round($row->min_weight, 3),
                'max_weight' => round($row->max_weight, 3),
            ]);
    }
}; ?>


<flux:main>
    <flux:header heading="{{ __('Harvest Charts') }}">
        {{ __('Harvest Charts') }}
    </flux:header>

    <div class="p-6">
        <!-- Summary Cards -->
        <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @php
                $dailyData = $this->dailyData;
                $harvesterData = $this->harvesterData;
                $productData = $this->productData;
            @endphp

            @if ($dailyData !== null)
                <flux:card class="p-4">
                    <flux:text size="sm" class="text-zinc-500">{{ __('Total harvest') }}</flux:text>
                    <div class="mt-2 text-2xl font-semibold">{{ $this->dailyWeightDisplay['value'] }} <span class="text-gray-500 dark:text-zinc-400 text-sm">{{ $this->dailyWeightDisplay['unit'] }}</span></div>
                    <div class="text-xs text-zinc-400">{{ count($this->dailyData) }} {{ __('days') }}</div>
                </flux:card>

                <flux:card class="p-4">
                    <flux:text size="sm" class="text-zinc-500">{{ __('Total buckets') }}</flux:text>
                    <div class="mt-2 text-2xl font-semibold">{{ $this->dailyTotals['buckets'] }} <span class="text-gray-500 dark:text-zinc-400 text-sm">{{ __('kom') }}</span></div>
                </flux:card>
            @endif

            @if ($harvesterData !== null)
                @php
                    $topHarvester = $this->harvesterData[0] ?? null;
                    $harvesterNumber = $topHarvester['number'] ?? null;
                    $harvesterName = $topHarvester['name'] ?? __('Unknown');
                    $hasCustomName = $harvesterName !== "#$harvesterNumber";
                @endphp
                <flux:card class="p-4">
                    <flux:text size="sm" class="text-zinc-500">{{ __('Top Harvester') }}</flux:text>
                    <div class="mt-2">
                        @if ($hasCustomName)
                            <span class="text-xs italic text-zinc-400">#{{ $harvesterNumber }}</span>
                        @endif
                        <div class="text-lg font-semibold">{{ $harvesterName }}</div>
                    </div>
                    <div class="text-xs text-zinc-400">{{ number_format($topHarvester['total_weight'] ?? 0, 2, ',', '.') }} kg</div>
                </flux:card>
            @endif

            @if ($productData !== null)
                <flux:card class="p-4">
                    <flux:text size="sm" class="text-zinc-500">{{ __('Product Count') }}</flux:text>
                    <div class="mt-2 text-2xl font-semibold">{{ count($this->productData) }}</div>
                </flux:card>
            @endif
        </div>
        <!-- Year Pills -->
        <div class="mb-6 flex flex-wrap items-end gap-4">
            <flux:radio.group wire:model.live="selectedYear" label="{{ __('Year') }}" variant="pills">
                @foreach($this->availableYears as $year)
                    <flux:radio label="{{ $year }}" value="{{ $year }}"/>
                @endforeach
            </flux:radio.group>
        </div>

        <!-- Product Pills -->
        <div class="mb-6 flex flex-wrap items-end gap-4">
            <flux:radio.group wire:model.live="selectedProductId" label="{{ __('Product') }}" variant="pills">
                <flux:radio label="{{ __('All') }}" value="0"/>
                @foreach ($this->products as $product)
                    <flux:radio label="{{ $product->name }}" value="{{ $product->id }}"/>
                @endforeach
            </flux:radio.group>
        </div>

        <!-- Date Filters -->
        <div class="mb-6 flex flex-wrap items-end gap-4">
            <flux:date-picker
                mode="range"
                with-presets
                presets="today yesterday thisWeek last7Days thisMonth yearToDate"
                wire:model.live="dateRange"
                locale="{{ str_replace('_', '-', app()->getLocale()) }}"
            >
                <x-slot name="trigger">
                    <div class="flex flex-col sm:flex-row gap-6 sm:gap-4">
                        <flux:date-picker.input size="sm" variant="custom" label="{{ __('From') }}"/>
                        <flux:date-picker.input size="sm" variant="custom" label="{{ __('To') }}"/>
                    </div>
                </x-slot>
            </flux:date-picker>
        </div>

        <!-- Tabs -->
        <flux:tab.group>
            <flux:tabs wire:model.live="activeTab" class="mb-6">
                <flux:tab name="daily">{{ __('Daily Summary') }}</flux:tab>
                <flux:tab name="harvesters">{{ __('Harvesters') }}</flux:tab>
                <flux:tab name="products">{{ __('Products') }}</flux:tab>
            </flux:tabs>

            <!-- Daily Summary Tab -->
            <flux:tab.panel name="daily">
                @if ($this->dailyChartRows->isNotEmpty())
                    <flux:card>
                        <flux:chart :value="$this->dailyChartRows->toArray()" class="aspect-square sm:aspect-2/1 lg:aspect-3/1">
                            <flux:chart.svg>
                                <flux:chart.bar field="total_weight" class="text-blue-500" stacked/>
                                <flux:chart.bar field="bucket_count" class="text-amber-500" stacked/>
                                <flux:chart.axis axis="x" field="date" tick-count="7">
                                    <flux:chart.axis.tick/>
                                    <flux:chart.axis.line/>
                                </flux:chart.axis>
                                <flux:chart.axis axis="y" :format="['useGrouping' => true]">
                                    <flux:chart.axis.grid/>
                                    <flux:chart.axis.tick/>
                                </flux:chart.axis>
                                <flux:chart.cursor type="area" />
                            </flux:chart.svg>
                            <flux:chart.tooltip>
                                <flux:chart.tooltip.heading field="date" />
                                <flux:chart.tooltip.value field="total_weight" label="{{__('Total weight')}}" :format="['useGrouping' => true]" suffix=" kg" />
                                <flux:chart.tooltip.value field="bucket_count" label="{{__('Buckets')}}" :format="['useGrouping' => true]" suffix=" kom"/>
                                <flux:chart.tooltip.value field="min_weight" label="{{ __('Min per bucket') }}" :format="['minimumFractionDigits' => 3, 'maximumFractionDigits' => 3]" suffix=" kg"/>
                                <flux:chart.tooltip.value field="avg_weight" label="{{ __('Avg per bucket') }}" :format="['minimumFractionDigits' => 3, 'maximumFractionDigits' => 3]" suffix=" kg"/>
                                <flux:chart.tooltip.value field="max_weight" label="{{ __('Max per bucket') }}" :format="['minimumFractionDigits' => 3, 'maximumFractionDigits' => 3]" suffix=" kg"/>
                            </flux:chart.tooltip>
                        </flux:chart>
                    </flux:card>
                @else
                    <flux:card>
                        <div class="flex items-center justify-center h-96 text-zinc-500">{{ __('No data available') }}</div>
                    </flux:card>
                @endif
            </flux:tab.panel>

            <!-- Harvester Summary Tab -->
            <flux:tab.panel name="harvesters">
                @if ($this->harvesterChartRows->isNotEmpty())
                    <flux:card>
                        <flux:chart :value="$this->harvesterChartRows->toArray()" class="aspect-square sm:aspect-2/1 lg:aspect-3/1">
                            <flux:chart.svg>
                                <flux:chart.bar field="total_weight" class="text-blue-500" stacked/>
                                <flux:chart.bar field="bucket_count" class="text-amber-500" stacked/>
                                <flux:chart.axis axis="x" field="label">
                                    <flux:chart.axis.tick/>
                                    <flux:chart.axis.line/>
                                </flux:chart.axis>
                                <flux:chart.axis axis="y" :format="['useGrouping' => true]">
                                    <flux:chart.axis.grid/>
                                    <flux:chart.axis.tick/>
                                    <flux:chart.cursor type="area" />
                                </flux:chart.axis>
                            </flux:chart.svg>
                            <flux:chart.tooltip>
                                <flux:chart.tooltip.heading field="label" />
                                <flux:chart.tooltip.value field="total_weight" label="{{__('Total weight')}}" :format="['useGrouping' => true]" suffix=" kg" />
                                <flux:chart.tooltip.value field="bucket_count" label="{{__('Buckets')}}" :format="['useGrouping' => true]" suffix=" kom"/>
                                <flux:chart.tooltip.value field="min_weight" label="{{ __('Min per bucket') }}" :format="['minimumFractionDigits' => 3, 'maximumFractionDigits' => 3]" suffix=" kg"/>
                                <flux:chart.tooltip.value field="avg_weight" label="{{ __('Avg per bucket') }}" :format="['minimumFractionDigits' => 3, 'maximumFractionDigits' => 3]" suffix=" kg"/>
                                <flux:chart.tooltip.value field="max_weight" label="{{ __('Max per bucket') }}" :format="['minimumFractionDigits' => 3, 'maximumFractionDigits' => 3]" suffix=" kg"/>
                            </flux:chart.tooltip>
                        </flux:chart>
                    </flux:card>
                @else
                    <flux:card>
                        <div class="flex items-center justify-center h-96 text-zinc-500">{{ __('No data available') }}</div>
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
                                    <flux:table.column>{{ __('Product') }}</flux:table.column>
                                    <flux:table.column>{{ __('Weight (kg)') }}</flux:table.column>
                                    <flux:table.column>{{ __('Buckets') }}</flux:table.column>
                                </flux:table.columns>
                                <flux:table.rows>
                                    @foreach ($this->productData as $row)
                                        <flux:table.row>
                                            <flux:table.cell>{{ $row['name'] }}</flux:table.cell>
                                            <flux:table.cell>{{ number_format($row['total_weight'], 2, ',', '.') }}</flux:table.cell>
                                            <flux:table.cell>{{ $row['bucket_count'] }}</flux:table.cell>
                                        </flux:table.row>
                                    @endforeach
                                </flux:table.rows>
                            </flux:table>
                        </div>
                    </flux:card>
                @else
                    <flux:card>
                        <div class="flex items-center justify-center h-96 text-zinc-500">{{ __('No data available') }}</div>
                    </flux:card>
                @endif
            </flux:tab.panel>
        </flux:tab.group>

    </div>
</flux:main>
