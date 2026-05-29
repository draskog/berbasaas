<?php

use App\Models\HarvesterAssignment;
use App\Models\HarvestPrice;
use App\Models\HarvestRecord;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new
#[Layout('layouts.app')]
#[Title('Reports · eBorovnica')]
class extends Component
{
    use WithPagination;

    public int $selectedYear;

    public ?string $fromDate = null;

    public ?string $toDate = null;

    public int $selectedProductId = 0;

    public int $selectedHarvesterNumber = 0;

    public string $activeTab = 'daily';

    public int $perPage = 25;

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
        $this->perPage = auth()->user()->userSettings?->default_per_page ?? 25;
        $years = $this->availableYears;
        $this->selectedYear = $years->isNotEmpty() ? $years->first() : now()->year;
        $this->fromDate = now()->startOfYear()->format('Y-m-d');
        $this->toDate = now()->endOfYear()->format('Y-m-d');
        $product = $this->products->first();
        if ($product) {
            $this->selectedProductId = $product->id;
        }
    }

    public function updatedPerPage(): void
    {
        $this->resetPage('daily');
        $this->resetPage('harv');
        $this->resetPage('prod');
    }

    private function baseQuery(): Builder
    {
        return HarvestRecord::where('company_id', auth()->user()->company_id)
            ->when($this->fromDate, fn ($q) => $q->whereDate('weighed_at', '>=', $this->fromDate))
            ->when($this->toDate, fn ($q) => $q->whereDate('weighed_at', '<=', $this->toDate))
            ->when($this->selectedProductId, fn ($q) => $q->where('product_id', $this->selectedProductId))
            ->when($this->selectedHarvesterNumber, fn ($q) => $q->where('harvester_number', $this->selectedHarvesterNumber));
    }

    private function priceAt(?string $date): ?float
    {
        if (! $this->selectedProductId || ! $date) {
            return null;
        }

        return HarvestPrice::where('company_id', auth()->user()->company_id)
            ->where('product_id', $this->selectedProductId)
            ->where('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date))
            ->value('price_per_kg');
    }

    private function harvesterNames(): array
    {
        return HarvesterAssignment::where('company_id', auth()->user()->company_id)
            ->where('year', $this->selectedYear)
            ->with('harvester')
            ->get()
            ->mapWithKeys(fn ($assignment) => [
                $assignment->number => $assignment->harvester?->name ?? "#{$assignment->number}",
            ])
            ->all();
    }

    #[Computed]
    public function dailyData()
    {
        $query = $this->baseQuery()
            ->selectRaw('DATE(weighed_at) as date, COUNT(*) as bucket_count, SUM(weight) as total_weight')
            ->groupBy('date')
            ->orderBy('date');

        if ($this->perPage === 0) {
            return $query->get()->map(fn ($row) => [
                'date' => $row->date,
                'bucket_count' => $row->bucket_count,
                'total_weight' => round($row->total_weight, 3),
            ]);
        }

        return $query->paginate($this->perPage, pageName: 'daily')->map(fn ($row) => [
            'date' => $row->date,
            'bucket_count' => $row->bucket_count,
            'total_weight' => round($row->total_weight, 3),
        ]);
    }

    #[Computed]
    public function dailyTotals()
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
    public function harvesterData()
    {
        $names = $this->harvesterNames();
        $price = $this->priceAt($this->fromDate);
        $query = $this->baseQuery()
            ->selectRaw('harvester_number, COUNT(*) as bucket_count, SUM(weight) as total_weight')
            ->groupBy('harvester_number')
            ->orderByDesc('total_weight');

        if ($this->perPage === 0) {
            return $query->get()->map(fn ($row) => [
                'number' => $row->harvester_number,
                'name' => $names[$row->harvester_number] ?? "#{$row->harvester_number}",
                'bucket_count' => $row->bucket_count,
                'total_weight' => round($row->total_weight, 3),
                'earnings' => $price ? round($row->total_weight * $price, 2) : null,
            ]);
        }

        return $query->paginate($this->perPage, pageName: 'harv')->map(fn ($row) => [
            'number' => $row->harvester_number,
            'name' => $names[$row->harvester_number] ?? "#{$row->harvester_number}",
            'bucket_count' => $row->bucket_count,
            'total_weight' => round($row->total_weight, 3),
            'earnings' => $price ? round($row->total_weight * $price, 2) : null,
        ]);
    }

    #[Computed]
    public function harvesterTotals()
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
    public function productData()
    {
        $price = $this->priceAt($this->fromDate);
        $query = $this->baseQuery()
            ->selectRaw('product_id, COUNT(*) as bucket_count, SUM(weight) as total_weight')
            ->groupBy('product_id')
            ->with('product');

        if ($this->perPage === 0) {
            return $query->get()->map(fn ($row) => [
                'name' => $row->product->name,
                'bucket_count' => $row->bucket_count,
                'total_weight' => round($row->total_weight, 3),
                'price_per_kg' => $price,
                'earnings' => $price ? round($row->total_weight * $price, 2) : null,
            ]);
        }

        return $query->paginate($this->perPage, pageName: 'prod')->map(fn ($row) => [
            'name' => $row->product->name,
            'bucket_count' => $row->bucket_count,
            'total_weight' => round($row->total_weight, 3),
            'price_per_kg' => $price,
            'earnings' => $price ? round($row->total_weight * $price, 2) : null,
        ]);
    }

    #[Computed]
    public function productTotals()
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
        <flux:header heading="Harvest Reports">
        </flux:header>

        <div class="p-6">
            <!-- Filter Panel -->
            <flux:card class="mb-8">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <!-- Year Selector -->
                    <flux:field>
                        <flux:label>Year</flux:label>
                        <flux:select wire:model.live="selectedYear">
                            @foreach($this->availableYears as $year)
                                <flux:select.option value="{{ $year }}">{{ $year }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:field>

                    <!-- Date From -->
                    <flux:field>
                        <flux:label>From Date</flux:label>
                        <flux:input type="date" wire:model.live="fromDate" />
                    </flux:field>

                    <!-- Date To -->
                    <flux:field>
                        <flux:label>To Date</flux:label>
                        <flux:input type="date" wire:model.live="toDate" />
                    </flux:field>

                    <!-- Product Selector -->
                    <flux:field>
                        <flux:label>Product</flux:label>
                        <flux:select wire:model.live="selectedProductId">
                            <flux:select.option value="0">All products</flux:select.option>
                            @foreach ($this->products as $product)
                                <flux:select.option value="{{ $product->id }}">{{ $product->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:field>

                    <!-- Harvester Selector -->
                    <flux:field>
                        <flux:label>Harvester</flux:label>
                        <flux:select wire:model.live="selectedHarvesterNumber">
                            <flux:select.option value="0">All harvesters</flux:select.option>
                            @foreach ($this->harvesterNumbers as $number)
                                <flux:select.option value="{{ $number }}">#{{ $number }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:field>

                    <!-- Records Per Page -->
                    <flux:field>
                        <flux:label>Records Per Page</flux:label>
                        <flux:select wire:model.live="perPage">
                            <flux:select.option value="25">25</flux:select.option>
                            <flux:select.option value="50">50</flux:select.option>
                            <flux:select.option value="100">100</flux:select.option>
                            <flux:select.option value="0">All</flux:select.option>
                        </flux:select>
                    </flux:field>
                </div>
            </flux:card>

            <!-- Tab Navigation -->
            <flux:tabs wire:model="activeTab" class="mb-6">
                <flux:tab name="daily">Daily Summary</flux:tab>
                <flux:tab name="harvesters">Harvesters</flux:tab>
                <flux:tab name="products">Products</flux:tab>
            </flux:tabs>

            <!-- Daily Summary Tab -->
            <flux:tab.panel name="daily">
                <flux:table :paginate="$this->perPage > 0 ? $this->dailyData : null">
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

                        @if ($this->dailyData->isNotEmpty())
                            <flux:table.row class="border-t-2 border-gray-200 font-semibold dark:border-zinc-700">
                                <flux:table.cell>Total</flux:table.cell>
                                <flux:table.cell>{{ $this->dailyTotals['buckets'] }}</flux:table.cell>
                                <flux:table.cell>{{ number_format($this->dailyTotals['weight'], 3, '.', ',') }}</flux:table.cell>
                            </flux:table.row>
                        @endif
                    </flux:table.rows>
                </flux:table>
            </flux:tab.panel>

            <!-- Harvester Totals Tab -->
            <flux:tab.panel name="harvesters">
                <flux:table :paginate="$this->perPage > 0 ? $this->harvesterData : null">
                    <flux:table.columns>
                        <flux:table.column>#</flux:table.column>
                        <flux:table.column>Name</flux:table.column>
                        <flux:table.column>Buckets</flux:table.column>
                        <flux:table.column>Total kg</flux:table.column>
                        <flux:table.column>Earnings</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($this->harvesterData as $row)
                            <flux:table.row>
                                <flux:table.cell>{{ $row['number'] }}</flux:table.cell>
                                <flux:table.cell>{{ $row['name'] }}</flux:table.cell>
                                <flux:table.cell>{{ $row['bucket_count'] }}</flux:table.cell>
                                <flux:table.cell>{{ number_format($row['total_weight'], 3, '.', ',') }}</flux:table.cell>
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
                                <flux:table.cell colspan="5" class="text-center text-gray-500">No data</flux:table.cell>
                            </flux:table.row>
                        @endforelse

                        @if ($this->harvesterData->isNotEmpty())
                            <flux:table.row class="border-t-2 border-gray-200 font-semibold dark:border-zinc-700">
                                <flux:table.cell colspan="2">Total</flux:table.cell>
                                <flux:table.cell>{{ $this->harvesterTotals['buckets'] }}</flux:table.cell>
                                <flux:table.cell>{{ number_format($this->harvesterTotals['weight'], 3, '.', ',') }}</flux:table.cell>
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
                        <flux:table.column>Product</flux:table.column>
                        <flux:table.column>Total kg</flux:table.column>
                        <flux:table.column>Price/kg</flux:table.column>
                        <flux:table.column>Total Earnings</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($this->productData as $row)
                            <flux:table.row>
                                <flux:table.cell>{{ $row['name'] }}</flux:table.cell>
                                <flux:table.cell>{{ number_format($row['total_weight'], 3, '.', ',') }}</flux:table.cell>
                                <flux:table.cell>
                                    @if ($row['price_per_kg'])
                                        €{{ number_format($row['price_per_kg'], 4, ',', '.') }}
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
                                <flux:table.cell colspan="4" class="text-center text-gray-500">No data</flux:table.cell>
                            </flux:table.row>
                        @endforelse

                        @if ($this->productData->isNotEmpty())
                            <flux:table.row class="border-t-2 border-gray-200 font-semibold dark:border-zinc-700">
                                <flux:table.cell>Total</flux:table.cell>
                                <flux:table.cell>{{ number_format($this->productTotals['weight'], 3, '.', ',') }}</flux:table.cell>
                                <flux:table.cell>—</flux:table.cell>
                                <flux:table.cell>€{{ number_format($this->productTotals['earnings'], 2, ',', '.') }}</flux:table.cell>
                            </flux:table.row>
                        @endif
                    </flux:table.rows>
                </flux:table>
            </flux:tab.panel>
        </div>
    </flux:main>

