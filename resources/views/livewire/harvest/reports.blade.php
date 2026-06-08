<?php

use App\Models\HarvesterAssignment;
use App\Models\HarvestPrice;
use App\Models\HarvestRecord;
use App\Models\Product;
use Carbon\Carbon;
use Flux\DateRange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new
#[Layout('layouts.app')]
#[Title('Reports')]
class extends Component {
    use WithPagination;

    #[Url]
    public int $selectedYear = 0;

    #[Url]
    public ?string $fromDate = null;

    #[Url]
    public ?string $toDate = null;

    #[Url]
    public int $selectedProductId = 0;

    public ?DateRange $dateRange = null;

    #[Url]
    public string $selectedPrefix = '';

    #[Url]
    public string $searchHarvesterName = '';

    #[Url]
    public string $activeTab = 'daily';

    public int $perPage = 25;

    public string $dailySortBy = 'date';

    public string $dailySortDirection = 'asc';

    public string $harvesterSortBy = 'total_weight';

    public string $harvesterSortDirection = 'desc';

    public string $productSortBy = 'total_weight';

    public string $productSortDirection = 'desc';

    #[Computed]
    public function availableYears (): Collection
    {
        return HarvesterAssignment::where('company_id', auth()->user()->company_id)
            ->distinct()
            ->pluck('year')
            ->sort()
            ->reverse()
            ->values();
    }

    #[Computed]
    public function products (): Collection
    {
        return Product::where('company_id', auth()->user()->company_id)
            ->whereHas('harvestRecords', fn($q) => $q->where('company_id', auth()->user()->company_id))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function availablePrefixes (): Collection
    {
        $query = HarvestRecord::from('harvest_records')
            ->where('harvest_records.company_id', auth()->user()->company_id)
            ->join('harvester_assignments', function ($join) {
                $join->on('harvester_assignments.company_id', '=', 'harvest_records.company_id')
                    ->on('harvester_assignments.number', '=', 'harvest_records.harvester_number');

                if (\Illuminate\Support\Facades\DB::getDefaultConnection() === 'sqlite') {
                    $join->whereRaw('harvester_assignments.year = CAST(strftime(\'%Y\', harvest_records.weighed_at) AS INTEGER)');
                } else {
                    $join->whereRaw('harvester_assignments.year = YEAR(harvest_records.weighed_at)');
                }
            })
            ->join('harvesters', 'harvester_assignments.harvester_id', '=', 'harvesters.id')
            ->whereNotNull('harvesters.prefix')
            ->where('harvesters.prefix', '!=', '')
            ->when($this->selectedYear, fn($q) => $q->whereYear('harvest_records.weighed_at', $this->selectedYear))
            ->when($this->fromDate, fn($q) => $q->whereDate('harvest_records.weighed_at', '>=', $this->fromDate))
            ->when($this->toDate, fn($q) => $q->whereDate('harvest_records.weighed_at', '<=', $this->toDate));

        return $query->distinct()
            ->pluck('harvesters.prefix')
            ->sort()
            ->values();
    }

    #[Computed]
    public function datesWithData (): array
    {
        return HarvestRecord::query()
            ->where('company_id', auth()->user()->company_id)
            ->whereYear('weighed_at', $this->selectedYear)
            ->selectRaw('DATE(weighed_at) as record_date')
            ->distinct()
            ->pluck('record_date')
            ->toArray();
    }

    #[Computed]
    public function unavailableDates (): array
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
    public function minDate (): string
    {
        $year = $this->selectedYear ?: $this->availableYears->first() ?? now()->year;

        return Carbon::create($year)->format('Y-m-d');
    }

    #[Computed]
    public function maxDate (): string
    {
        $year = $this->selectedYear ?: $this->availableYears->first() ?? now()->year;

        return Carbon::create($year, 12, 31)->format('Y-m-d');
    }

    public function mount (): void
    {
        $this->perPage = auth()->user()->userSettings?->default_per_page ?? 25;
        $years = $this->availableYears;
        if (! $this->selectedYear) {
            $this->selectedYear = $years->isNotEmpty() ? $years->first() : now()->year;
        }

        $this->fromDate = Carbon::create($this->selectedYear)->format('Y-m-d');
        $this->toDate = Carbon::create($this->selectedYear, 12, 31)->format('Y-m-d');

        if (! $this->selectedProductId) {
            $product = $this->products->first();
            if ($product) {
                $this->selectedProductId = $product->id;
            }
        }

        if ($this->fromDate && $this->toDate) {
            $this->dateRange = new DateRange($this->fromDate, $this->toDate);
        }
    }

    public function updatedPerPage (): void
    {
        $this->resetPage('daily');
        $this->resetPage('harvester');
        $this->resetPage('prod');
    }

    public function updatedSelectedYear (): void
    {
        $this->fromDate = Carbon::create($this->selectedYear)->format('Y-m-d');
        $this->toDate = Carbon::create($this->selectedYear, 12, 31)->format('Y-m-d');
        $this->dateRange = new DateRange($this->fromDate, $this->toDate);
    }

    public function updatedDateRange (): void
    {
        if ($this->dateRange instanceof DateRange) {
            $this->fromDate = $this->dateRange->start()->format('Y-m-d');
            $this->toDate = $this->dateRange->end()->format('Y-m-d');
        }
    }

    public function sortDaily (string $column): void
    {
        if ($this->dailySortBy === $column) {
            $this->dailySortDirection = $this->dailySortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->dailySortBy = $column;
            $this->dailySortDirection = 'asc';
        }
        $this->resetPage('daily');
    }

    public function sortHarvesters (string $column): void
    {
        if ($this->harvesterSortBy === $column) {
            $this->harvesterSortDirection = $this->harvesterSortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->harvesterSortBy = $column;
            $this->harvesterSortDirection = 'asc';
        }
        $this->resetPage('harvester');
    }

    public function sortProducts (string $column): void
    {
        if ($this->productSortBy === $column) {
            $this->productSortDirection = $this->productSortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->productSortBy = $column;
            $this->productSortDirection = 'asc';
        }
        $this->resetPage('prod');
    }

    private function baseQuery (): Builder
    {
        $query = HarvestRecord::where('company_id', auth()->user()->company_id)
            ->when($this->fromDate, fn($q) => $q->whereDate('weighed_at', '>=', $this->fromDate))
            ->when($this->toDate, fn($q) => $q->whereDate('weighed_at', '<=', $this->toDate))
            ->when($this->selectedProductId, fn($q) => $q->where('product_id', $this->selectedProductId));

        if ($this->selectedPrefix) {
            $query->whereExists(function ($sub) {
                $sub->select('harvester_assignments.id')
                    ->from('harvester_assignments')
                    ->join('harvesters', 'harvester_assignments.harvester_id', '=', 'harvesters.id')
                    ->whereColumn('harvester_assignments.company_id', 'harvest_records.company_id')
                    ->whereColumn('harvester_assignments.number', 'harvest_records.harvester_number');

                if (\Illuminate\Support\Facades\DB::getDefaultConnection() === 'sqlite') {
                    $sub->whereRaw('harvester_assignments.year = CAST(strftime(\'%Y\', harvest_records.weighed_at) AS INTEGER)');
                } else {
                    $sub->whereRaw('harvester_assignments.year = YEAR(harvest_records.weighed_at)');
                }

                $sub->where('harvesters.prefix', $this->selectedPrefix);
            });
        }

        if ($this->searchHarvesterName) {
            $search = strtolower($this->searchHarvesterName);
            $companyId = auth()->user()->company_id;
            $selectedYear = $this->selectedYear;

            $harvestersWithSearch = HarvesterAssignment::where('company_id', $companyId)
                ->when($selectedYear > 0, fn($q) => $q->where('year', $selectedYear))
                ->with('harvester')
                ->get()
                ->filter(function ($assignment) use ($search) {
                    if (! $assignment->harvester) {
                        return false;
                    }
                    $name = strtolower($assignment->harvester->name);
                    $prefix = strtolower($assignment->harvester->prefix ?? '');

                    return str_contains($name, $search) || str_contains($prefix, $search);
                })
                ->pluck('number')
                ->toArray();

            if (empty($harvestersWithSearch)) {
                $harvestersWithSearch = [0];
            }
            $query->whereIn('harvester_number', $harvestersWithSearch);
        }

        return $query;
    }

    private function priceAt (?string $date): ?float
    {
        if (! $this->selectedProductId || ! $date) {
            return null;
        }

        return HarvestPrice::where('company_id', auth()->user()->company_id)
            ->where('product_id', $this->selectedProductId)
            ->where('effective_from', '<=', $date)
            ->where(fn($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date))
            ->value('price_per_kg');
    }

    private function harvesterNames (): array
    {
        return HarvesterAssignment::where('company_id', auth()->user()->company_id)
            ->where('year', $this->selectedYear)
            ->with('harvester')
            ->get()
            ->mapWithKeys(fn($assignment) => [
                $assignment->number => $assignment->harvester?->name ?? "#$assignment->number",
            ])
            ->all();
    }

    #[Computed]
    public function dailyData ()
    {
        $query = $this->baseQuery()
            ->selectRaw('DATE(weighed_at) as date, COUNT(*) as bucket_count, SUM(weight) as total_weight')
            ->groupBy('date')
            ->orderBy($this->dailySortBy, $this->dailySortDirection);

        if ($this->perPage === 0) {
            return $query->get()->map(fn($row) => [
                'date' => $row->date,
                'bucket_count' => $row->bucket_count,
                'total_weight' => round($row->total_weight, 3),
            ]);
        }

        return $query->paginate($this->perPage, pageName: 'daily')->through(fn($row) => [
            'date' => $row->date,
            'bucket_count' => $row->bucket_count,
            'total_weight' => round($row->total_weight, 3),
        ]);
    }

    #[Computed]
    public function dailyTotals (): array
    {
        $data = $this->baseQuery()
            ->selectRaw('COUNT(*) as bucket_count, SUM(weight) as total_weight')
            ->get();

        return [
            'buckets' => $data[0]?->bucket_count ?? 0,
            'weight' => round($data[0]?->total_weight ?? 0, 3),
        ];
    }

    #[Computed]
    public function harvesterData ()
    {
        $names = $this->harvesterNames();
        $price = $this->priceAt($this->fromDate);
        $query = $this->baseQuery()
            ->selectRaw('harvester_number, COUNT(*) as bucket_count, SUM(weight) as total_weight')
            ->groupBy('harvester_number')
            ->orderBy($this->harvesterSortBy, $this->harvesterSortDirection);

        if ($this->perPage === 0) {
            return $query->get()->map(fn($row) => [
                'number' => $row->harvester_number,
                'name' => $names[$row->harvester_number] ?? "#$row->harvester_number",
                'bucket_count' => $row->bucket_count,
                'total_weight' => round($row->total_weight, 3),
                'earnings' => $price ? round($row->total_weight * $price, 2) : null,
            ]);
        }

        return $query->paginate($this->perPage, pageName: 'harvester')->through(fn($row) => [
            'number' => $row->harvester_number,
            'name' => $names[$row->harvester_number] ?? "#$row->harvester_number",
            'bucket_count' => $row->bucket_count,
            'total_weight' => round($row->total_weight, 3),
            'earnings' => $price ? round($row->total_weight * $price, 2) : null,
        ]);
    }

    #[Computed]
    public function harvesterTotals (): array
    {
        $data = $this->baseQuery()
            ->selectRaw('COUNT(*) as bucket_count, SUM(weight) as total_weight')
            ->get();
        $price = $this->priceAt($this->fromDate);

        return [
            'buckets' => $data[0]?->bucket_count ?? 0,
            'weight' => round($data[0]?->total_weight ?? 0, 3),
            'earnings' => $price ? round(($data[0]?->total_weight ?? 0) * $price, 2) : 0,
        ];
    }

    #[Computed]
    public function productData ()
    {
        $price = $this->priceAt($this->fromDate);
        $query = $this->baseQuery()
            ->selectRaw('product_id, COUNT(*) as bucket_count, SUM(weight) as total_weight')
            ->groupBy('product_id')
            ->with('product')
            ->orderBy($this->productSortBy, $this->productSortDirection);

        if ($this->perPage === 0) {
            return $query->get()->map(fn($row) => [
                'name' => $row->product->name,
                'bucket_count' => $row->bucket_count,
                'total_weight' => round($row->total_weight, 3),
                'price_per_kg' => $price,
                'earnings' => $price ? round($row->total_weight * $price, 2) : null,
            ]);
        }

        return $query->paginate($this->perPage, pageName: 'prod')->through(fn($row) => [
            'name' => $row->product->name,
            'bucket_count' => $row->bucket_count,
            'total_weight' => round($row->total_weight, 3),
            'price_per_kg' => $price,
            'earnings' => $price ? round($row->total_weight * $price, 2) : null,
        ]);
    }

    #[Computed]
    public function productTotals (): array
    {
        $data = $this->baseQuery()
            ->selectRaw('COUNT(*) as bucket_count, SUM(weight) as total_weight')
            ->get();
        $price = $this->priceAt($this->fromDate);

        return [
            'buckets' => $data[0]?->bucket_count ?? 0,
            'weight' => round($data[0]?->total_weight ?? 0, 3),
            'earnings' => $price ? round(($data[0]?->total_weight ?? 0) * $price, 2) : 0,
        ];
    }

    #[Computed]
    public function overLimitCount (): int
    {
        $maxWeight = auth()->user()->company->importSettings?->max_bucket_weight;
        if (! $maxWeight) {
            return 0;
        }

        return $this->baseQuery()
            ->where('weight', '>', $maxWeight)
            ->selectRaw('DATE(weighed_at) as date, harvester_number')
            ->groupByRaw('DATE(weighed_at), harvester_number')
            ->count();
    }

    #[Computed]
    public function overLimitRows ()
    {
        $maxWeight = auth()->user()->company->importSettings?->max_bucket_weight;

        if (! $maxWeight) {
            return new LengthAwarePaginator([], 0, $this->perPage);
        }

        $names = $this->harvesterNames();
        $query = $this->baseQuery()
            ->where('weight', '>', $maxWeight)
            ->selectRaw('
                DATE(weighed_at) as date,
                harvester_number,
                COUNT(*) as over_limit_count,
                MAX(weight) as max_weight,
                SUM(weight) as total_weight
            ')
            ->groupByRaw('DATE(weighed_at), harvester_number')
            ->orderByRaw('DATE(weighed_at) desc')
            ->orderByDesc('over_limit_count');

        if ($this->perPage === 0) {
            return $query->get()->map(fn($row) => [
                'date' => $row->date,
                'number' => $row->harvester_number,
                'name' => $names[$row->harvester_number] ?? "#$row->harvester_number",
                'over_limit_count' => $row->over_limit_count,
                'max_weight' => round($row->max_weight, 3),
                'total_weight' => round($row->total_weight, 3),
            ]);
        }

        return $query->paginate($this->perPage, pageName: 'over_limit')->through(fn($row) => [
            'date' => $row->date,
            'number' => $row->harvester_number,
            'name' => $names[$row->harvester_number] ?? "#$row->harvester_number",
            'over_limit_count' => $row->over_limit_count,
            'max_weight' => round($row->max_weight, 3),
            'total_weight' => round($row->total_weight, 3),
        ]);
    }
}; ?>


<flux:main>
    <flux:header heading="{{ __('Harvest Reports') }}">
    </flux:header>

    <div class="p-6">
        <!-- Records Per Page -->
        <div class="flex justify-end mb-4">
            <flux:select wire:model.live="perPage" size="sm" class="w-28">
                <flux:select.option value="25">25</flux:select.option>
                <flux:select.option value="50">50</flux:select.option>
                <flux:select.option value="100">100</flux:select.option>
                <flux:select.option value="0">{{ __('All') }}</flux:select.option>
            </flux:select>
        </div>

        <!-- Filter Pills -->
        <div class="space-y-4 mb-6">
            <div>
                <flux:radio.group wire:model.live="selectedYear" label="{{ __('Year') }}" variant="pills">
                    @foreach($this->availableYears as $year)
                        <flux:radio value="{{ $year }}" label="{{ $year }}"/>
                    @endforeach
                </flux:radio.group>
            </div>

            <div>
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

            <div>
                <flux:radio.group wire:model.live="selectedProductId" label="{{ __('Product') }}" variant="pills">
                    <flux:radio value="0" label="{{ __('All') }}"/>
                    @foreach ($this->products as $product)
                        <flux:radio value="{{ $product->id }}" label="{{ $product->name }}"/>
                    @endforeach
                </flux:radio.group>
            </div>

            @if ($this->availablePrefixes->isNotEmpty())
                <div>
                    <flux:radio.group wire:model.live="selectedPrefix" label="{{ __('Prefix') }}" variant="pills">
                        <flux:radio value="" label="{{ __('All') }}"/>
                        @foreach ($this->availablePrefixes as $prefix)
                            <flux:radio value="{{ $prefix }}" label="{{ $prefix }}"/>
                        @endforeach
                    </flux:radio.group>
                </div>
            @endif
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-4">
                    <flux:input type="search" size="sm" wire:model.live.debounce.300ms="searchHarvesterName"
                                placeholder="{{ __('Search by harvester number or name...') }}"
                                icon="magnifying-glass" class="w-72!"/>
                </div>
                <flux:select wire:model.live="perPage" size="sm" class="w-28">
                    <flux:select.option value="25">25</flux:select.option>
                    <flux:select.option value="50">50</flux:select.option>
                    <flux:select.option value="100">100</flux:select.option>
                    <flux:select.option value="0">{{ __('All') }}</flux:select.option>
                </flux:select>
            </div>
        </div>

        <!-- Tab Navigation -->
        <flux:tab.group>
            <flux:tabs wire:model="activeTab" class="mb-6">
                <flux:tab name="daily">{{ __('Daily Summary') }}</flux:tab>
                <flux:tab name="harvesters">{{ __('Harvesters') }}</flux:tab>
                <flux:tab name="products">{{ __('Products') }}</flux:tab>
                <flux:tab name="over_limit">
                    {{ __('Over Limit') }}
                    @if ($this->overLimitCount > 0)
                        <flux:badge variant="primary" size="sm">{{ $this->overLimitCount }}</flux:badge>
                    @endif
                </flux:tab>
            </flux:tabs>

            <!-- Daily Summary Tab -->
            <flux:tab.panel name="daily">
                <flux:table :paginate="$this->perPage > 0 ? $this->dailyData : null">
                    <flux:table.columns>
                        <flux:table.column sortable :sorted="$dailySortBy === 'date'" :direction="$dailySortDirection" wire:click="sortDaily('date')">{{ __('Date') }}</flux:table.column>
                        <flux:table.column sortable :sorted="$dailySortBy === 'bucket_count'" :direction="$dailySortDirection" wire:click="sortDaily('bucket_count')">{{ __('Buckets') }}</flux:table.column>
                        <flux:table.column sortable :sorted="$dailySortBy === 'total_weight'" :direction="$dailySortDirection" wire:click="sortDaily('total_weight')">{{ __('Total kg') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($this->dailyData as $row)
                            <flux:table.row>
                                <flux:table.cell>{{ Carbon::parse($row['date'])->format('d.m.Y') }}</flux:table.cell>
                                <flux:table.cell>{{ $row['bucket_count'] }}</flux:table.cell>
                                <flux:table.cell>{{ number_format($row['total_weight'], 3, ',', '.') }}</flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="3" class="text-center text-gray-500">{{ __('No data') }}</flux:table.cell>
                            </flux:table.row>
                        @endforelse

                        @if ($this->dailyData->isNotEmpty())
                            <flux:table.row class="border-t-2 border-gray-200 font-semibold dark:border-zinc-700">
                                <flux:table.cell>{{ __('Total') }}</flux:table.cell>
                                <flux:table.cell>{{ $this->dailyTotals['buckets'] }}</flux:table.cell>
                                <flux:table.cell>{{ number_format($this->dailyTotals['weight'], 3, ',', '.') }}</flux:table.cell>
                            </flux:table.row>
                        @endif
                    </flux:table.rows>
                </flux:table>
            </flux:tab.panel>

            <!-- Harvester Totals Tab -->
            <flux:tab.panel name="harvesters">
                <flux:table :paginate="$this->perPage > 0 ? $this->harvesterData : null">
                    <flux:table.columns>
                        <flux:table.column sortable :sorted="$harvesterSortBy === 'harvester_number'" :direction="$harvesterSortDirection" wire:click="sortHarvesters('harvester_number')">#</flux:table.column>
                        <flux:table.column>{{ __('Name') }}</flux:table.column>
                        <flux:table.column sortable :sorted="$harvesterSortBy === 'bucket_count'" :direction="$harvesterSortDirection" wire:click="sortHarvesters('bucket_count')">{{ __('Buckets') }}</flux:table.column>
                        <flux:table.column sortable :sorted="$harvesterSortBy === 'total_weight'" :direction="$harvesterSortDirection" wire:click="sortHarvesters('total_weight')">{{ __('Total kg') }}</flux:table.column>
                        <flux:table.column>{{ __('Earnings') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($this->harvesterData as $row)
                            <flux:table.row>
                                <flux:table.cell>{{ $row['number'] }}</flux:table.cell>
                                <flux:table.cell>{{ $row['name'] }}</flux:table.cell>
                                <flux:table.cell>{{ $row['bucket_count'] }}</flux:table.cell>
                                <flux:table.cell>{{ number_format($row['total_weight'], 3, ',', '.') }}</flux:table.cell>
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
                                <flux:table.cell colspan="5" class="text-center text-gray-500">{{ __('No data') }}</flux:table.cell>
                            </flux:table.row>
                        @endforelse

                        @if ($this->harvesterData->isNotEmpty())
                            <flux:table.row class="border-t-2 border-gray-200 font-semibold dark:border-zinc-700">
                                <flux:table.cell colspan="2">{{ __('Total') }}</flux:table.cell>
                                <flux:table.cell>{{ $this->harvesterTotals['buckets'] }}</flux:table.cell>
                                <flux:table.cell>{{ number_format($this->harvesterTotals['weight'], 3, ',', '.') }}</flux:table.cell>
                                <flux:table.cell>€{{ number_format($this->harvesterTotals['earnings'], 2, ',', '.') }}</flux:table.cell>
                            </flux:table.row>
                        @endif
                    </flux:table.rows>
                </flux:table>
            </flux:tab.panel>

            <!-- Product Totals Tab -->
            <flux:tab.panel name="products">
                <flux:table :paginate="$this->perPage > 0 ? $this->productData : null">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Product') }}</flux:table.column>
                        <flux:table.column sortable :sorted="$productSortBy === 'bucket_count'" :direction="$productSortDirection" wire:click="sortProducts('bucket_count')">{{ __('Total kg') }}</flux:table.column>
                        <flux:table.column>{{ __('Price/kg') }}</flux:table.column>
                        <flux:table.column sortable :sorted="$productSortBy === 'total_weight'" :direction="$productSortDirection" wire:click="sortProducts('total_weight')">{{ __('Total earnings') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($this->productData as $row)
                            <flux:table.row>
                                <flux:table.cell>{{ $row['name'] }}</flux:table.cell>
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
                                <flux:table.cell colspan="4" class="text-center text-gray-500">{{ __('No data') }}</flux:table.cell>
                            </flux:table.row>
                        @endforelse

                        @if ($this->productData->isNotEmpty())
                            <flux:table.row class="border-t-2 border-gray-200 font-semibold dark:border-zinc-700">
                                <flux:table.cell>{{ __('Total') }}</flux:table.cell>
                                <flux:table.cell>{{ number_format($this->productTotals['weight'], 3, ',', '.') }}</flux:table.cell>
                                <flux:table.cell>—</flux:table.cell>
                                <flux:table.cell>€{{ number_format($this->productTotals['earnings'], 2, ',', '.') }}</flux:table.cell>
                            </flux:table.row>
                        @endif
                    </flux:table.rows>
                </flux:table>
            </flux:tab.panel>

            <!-- Over Limit Tab -->
            <flux:tab.panel name="over_limit">
                @php
                    $maxWeight = auth()->user()->company->importSettings?->max_bucket_weight;
                @endphp

                @if (! $maxWeight)
                    <flux:card class="text-center py-8">
                        <p class="text-zinc-600 dark:text-zinc-400 mb-4">
                            {{ __('Maximum bucket weight limit is not configured.') }}
                        </p>
                        <a href="{{ route('harvest.edit') }}" class="text-blue-600 hover:text-blue-700 dark:text-blue-400">
                            {{ __('Configure in settings') }}
                        </a>
                    </flux:card>
                @else
                    <flux:table :paginate="$this->perPage > 0 ? $this->overLimitRows : null">
                        <flux:table.columns>
                            <flux:table.column>{{ __('Date') }}</flux:table.column>
                            <flux:table.column>{{ __('Harvester') }}</flux:table.column>
                            <flux:table.column>{{ __('Over Limit Count') }}</flux:table.column>
                            <flux:table.column>{{ __('Max Weight (kg)') }}</flux:table.column>
                            <flux:table.column>{{ __('Total Weight (kg)') }}</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @forelse ($this->overLimitRows as $row)
                                <flux:table.row>
                                    <flux:table.cell>{{ Carbon::parse($row['date'])->format('d.m.Y') }}</flux:table.cell>
                                    <flux:table.cell>#{{ $row['number'] }} - {{ $row['name'] }}</flux:table.cell>
                                    <flux:table.cell>{{ $row['over_limit_count'] }}</flux:table.cell>
                                    <flux:table.cell>{{ number_format($row['max_weight'], 3, ',', '.') }}</flux:table.cell>
                                    <flux:table.cell>{{ number_format($row['total_weight'], 3, ',', '.') }}</flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row>
                                    <flux:table.cell colspan="5" class="text-center text-gray-500">{{ __('No data') }}</flux:table.cell>
                                </flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                @endif
            </flux:tab.panel>
        </flux:tab.group>

    </div>
</flux:main>

