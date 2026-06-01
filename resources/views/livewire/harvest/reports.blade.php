<?php

use App\Models\HarvesterAssignment;
use App\Models\HarvestPrice;
use App\Models\HarvestRecord;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
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

    public bool $showDateRangeModal = false;

    public ?string $dateRangeValue = null;

    #[Url]
    public int $selectedHarvesterNumber = 0;

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
    public function harvesterNumbers (): Collection
    {
        return HarvesterAssignment::where('company_id', auth()->user()->company_id)
            ->where('year', $this->selectedYear)
            ->distinct()
            ->pluck('number')
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
        return Carbon::create($this->selectedYear)->format('Y-m-d');
    }

    #[Computed]
    public function maxDate (): string
    {
        return Carbon::create($this->selectedYear, 12, 31)->format('Y-m-d');
    }

    public function mount (): void
    {
        $this->perPage = auth()->user()->userSettings?->default_per_page ?? 25;
        $years = $this->availableYears;
        if (! $this->selectedYear) {
            $this->selectedYear = $years->isNotEmpty() ? $years->first() : now()->year;
        }

        if (! $this->fromDate || ! $this->toDate) {
            $this->fromDate = now()->startOfYear()->format('Y-m-d');
            $this->toDate = now()->endOfYear()->format('Y-m-d');
        }

        if (! $this->selectedProductId) {
            $product = $this->products->first();
            if ($product) {
                $this->selectedProductId = $product->id;
            }
        }

        if ($this->fromDate && $this->toDate) {
            $this->dateRangeValue = $this->fromDate.'/'.$this->toDate;
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
        $this->selectedHarvesterNumber = 0;
    }

    public function updatedDateRangeValue (?string $value): void
    {
        if ($value && str_contains($value, '/')) {
            [$from, $to] = explode('/', $value, 2);
            if ($from && $to) {
                $this->fromDate = $from;
                $this->toDate = $to;
                $this->showDateRangeModal = false;
            }
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
        return HarvestRecord::where('company_id', auth()->user()->company_id)
            ->when($this->fromDate, fn($q) => $q->whereDate('weighed_at', '>=', $this->fromDate))
            ->when($this->toDate, fn($q) => $q->whereDate('weighed_at', '<=', $this->toDate))
            ->when($this->selectedProductId, fn($q) => $q->where('product_id', $this->selectedProductId))
            ->when($this->selectedHarvesterNumber, fn($q) => $q->where('harvester_number', $this->selectedHarvesterNumber));
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
                <flux:button
                    wire:click="$set('showDateRangeModal', true)"
                    variant="ghost"
                    size="sm"
                    icon="calendar-days"
                >
                    {{ $fromDate ? Carbon::parse($fromDate)->isoFormat('D MMM YYYY') : '—' }}
                    –
                    {{ $toDate ? Carbon::parse($toDate)->isoFormat('D MMM YYYY') : '—' }}
                </flux:button>
            </div>

            <div>
                <flux:radio.group wire:model.live="selectedProductId" label="{{ __('Product') }}" variant="pills">
                    <flux:radio value="0" label="{{ __('All') }}"/>
                    @foreach ($this->products as $product)
                        <flux:radio value="{{ $product->id }}" label="{{ $product->name }}"/>
                    @endforeach
                </flux:radio.group>
            </div>

            <div>
                <flux:radio.group wire:model.live="selectedHarvesterNumber" label="{{ __('Harvester') }}" variant="pills">
                    <flux:radio value="0" label="{{ __('All') }}"/>
                    @foreach ($this->harvesterNumbers as $number)
                        <flux:radio value="{{ $number }}" label="#{{ $number }}"/>
                    @endforeach
                </flux:radio.group>
            </div>
        </div>

        <!-- Tab Navigation -->
        <flux:tabs wire:model="activeTab" class="mb-6">
            <flux:tab name="daily">{{ __('Daily Summary') }}</flux:tab>
            <flux:tab name="harvesters">{{ __('Harvesters') }}</flux:tab>
            <flux:tab name="products">{{ __('Products') }}</flux:tab>
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
                    <flux:table.column sortable :sorted="$productSortBy === 'total_weight'" :direction="$productSortDirection" wire:click="sortProducts('total_weight')">{{ __('Total Earnings') }}</flux:table.column>
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

        <flux:modal name="date-range-picker" wire:model="showDateRangeModal">
            <flux:heading size="lg">{{ __('Select Date Range') }}</flux:heading>

            <flux:calendar
                mode="range"
                week-numbers
                locale="{{ app()->getLocale() }}"
                :unavailable="$this->unavailableDates"
                :min="$this->minDate"
                :max="$this->maxDate"
                wire:model.live="dateRangeValue"
                class="mt-4"
            />

            <div class="mt-6 flex justify-end">
                <flux:button variant="ghost" wire:click="$set('showDateRangeModal', false)">
                    {{ __('Close') }}
                </flux:button>
            </div>
        </flux:modal>
    </div>
</flux:main>

