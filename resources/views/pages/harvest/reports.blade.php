<?php

use App\Models\HarvestRecord;
use App\Models\HarvesterAssignment;
use App\Models\HarvestPrice;
use App\Models\Product;
use Illuminate\Database\Query\Builder;
use Livewire\Volt\Component;

new class extends Component {
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

    private function priceAt(?string $date): ?float
    {
        if (!$this->selectedProductId || !$date) {
            return null;
        }

        return HarvestPrice::where('company_id', auth()->user()->company_id)
            ->where('product_id', $this->selectedProductId)
            ->where('effective_from', '<=', $date)
            ->where(fn($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date))
            ->value('price_per_kg');
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
        $price = $this->priceAt($this->fromDate);

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
                'earnings' => $price ? round($row->total_weight * $price, 2) : null,
            ]);
    }

    #[Computed]
    public function harvesterTotals()
    {
        $data = $this->harvesterData;
        if ($data->isEmpty()) {
            return ['buckets' => 0, 'weight' => 0, 'earnings' => 0];
        }

        return [
            'buckets' => $data->sum('bucket_count'),
            'weight' => round($data->sum('total_weight'), 3),
            'earnings' => round($data->sum('earnings') ?? 0, 2),
        ];
    }

    #[Computed]
    public function productData()
    {
        $price = $this->priceAt($this->fromDate);

        return $this->baseQuery()
            ->selectRaw('product_id, COUNT(*) as bucket_count, SUM(weight) as total_weight')
            ->groupBy('product_id')
            ->with('product')
            ->get()
            ->map(fn($row) => [
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
        $data = $this->productData;
        if ($data->isEmpty()) {
            return ['buckets' => 0, 'weight' => 0, 'earnings' => 0];
        }

        return [
            'buckets' => $data->sum('bucket_count'),
            'weight' => round($data->sum('total_weight'), 3),
            'earnings' => round($data->sum('earnings') ?? 0, 2),
        ];
    }
}; ?>

<x-layouts::app.sidebar title="Reports">
    <flux:main>
        <flux:header heading="Harvest Reports">
        </flux:header>

        <div class="p-6">
            <!-- Filter Panel -->
            <div class="mb-8 rounded-lg border border-gray-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <!-- Year Selector -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300">Year</label>
                        <select wire:model.live="selectedYear" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-zinc-600 dark:bg-zinc-700">
                            @for ($y = now()->year; $y >= now()->year - 5; $y--)
                                <option value="{{ $y }}">{{ $y }}</option>
                            @endfor
                        </select>
                    </div>

                    <!-- Date From -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300">From Date</label>
                        <input type="date" wire:model.live="fromDate" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-zinc-600 dark:bg-zinc-700" />
                    </div>

                    <!-- Date To -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300">To Date</label>
                        <input type="date" wire:model.live="toDate" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-zinc-600 dark:bg-zinc-700" />
                    </div>

                    <!-- Product Selector -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300">Product</label>
                        <select wire:model.live="selectedProductId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-zinc-600 dark:bg-zinc-700">
                            <option value="0">All products</option>
                            @foreach ($this->products as $product)
                                <option value="{{ $product->id }}">{{ $product->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Harvester Selector -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300">Harvester</label>
                        <select wire:model.live="selectedHarvesterNumber" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-zinc-600 dark:bg-zinc-700">
                            <option value="0">All harvesters</option>
                            @foreach ($this->harvesterNumbers as $number)
                                <option value="{{ $number }}">#{{ $number }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <!-- Tab Navigation -->
            <div class="mb-6 flex gap-2 border-b border-gray-200 dark:border-zinc-700">
                <button wire:click="$set('activeTab', 'daily')" :class="{ 'border-b-2 border-blue-600 text-blue-600': activeTab === 'daily' }" class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-900 dark:text-zinc-400 dark:hover:text-zinc-200">
                    Daily Summary
                </button>
                <button wire:click="$set('activeTab', 'harvesters')" :class="{ 'border-b-2 border-blue-600 text-blue-600': activeTab === 'harvesters' }" class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-900 dark:text-zinc-400 dark:hover:text-zinc-200">
                    Harvesters
                </button>
                <button wire:click="$set('activeTab', 'products')" :class="{ 'border-b-2 border-blue-600 text-blue-600': activeTab === 'products' }" class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-900 dark:text-zinc-400 dark:hover:text-zinc-200">
                    Products
                </button>
            </div>

            <!-- Daily Summary Tab -->
            @if ($activeTab === 'daily')
                <flux:table>
                    <flux:columns>
                        <flux:column>Date</flux:column>
                        <flux:column>Buckets</flux:column>
                        <flux:column>Total kg</flux:column>
                    </flux:columns>

                    <flux:rows>
                        @forelse ($this->dailyData as $row)
                            <flux:row>
                                <flux:cell>{{ \Carbon\Carbon::parse($row['date'])->format('d.m.Y') }}</flux:cell>
                                <flux:cell>{{ $row['bucket_count'] }}</flux:cell>
                                <flux:cell>{{ number_format($row['total_weight'], 3, '.', ',') }}</flux:cell>
                            </flux:row>
                        @empty
                            <flux:row>
                                <flux:cell colspan="3" class="text-center text-gray-500">No data</flux:cell>
                            </flux:row>
                        @endforelse

                        @if ($this->dailyData->isNotEmpty())
                            <flux:row class="border-t-2 border-gray-200 font-semibold dark:border-zinc-700">
                                <flux:cell>Total</flux:cell>
                                <flux:cell>{{ $this->dailyTotals['buckets'] }}</flux:cell>
                                <flux:cell>{{ number_format($this->dailyTotals['weight'], 3, '.', ',') }}</flux:cell>
                            </flux:row>
                        @endif
                    </flux:rows>
                </flux:table>
            @endif

            <!-- Harvester Totals Tab -->
            @if ($activeTab === 'harvesters')
                <flux:table>
                    <flux:columns>
                        <flux:column>#</flux:column>
                        <flux:column>Name</flux:column>
                        <flux:column>Buckets</flux:column>
                        <flux:column>Total kg</flux:column>
                        <flux:column>Earnings</flux:column>
                    </flux:columns>

                    <flux:rows>
                        @forelse ($this->harvesterData as $row)
                            <flux:row>
                                <flux:cell>{{ $row['number'] }}</flux:cell>
                                <flux:cell>{{ $row['name'] }}</flux:cell>
                                <flux:cell>{{ $row['bucket_count'] }}</flux:cell>
                                <flux:cell>{{ number_format($row['total_weight'], 3, '.', ',') }}</flux:cell>
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
                                <flux:cell colspan="5" class="text-center text-gray-500">No data</flux:cell>
                            </flux:row>
                        @endforelse

                        @if ($this->harvesterData->isNotEmpty())
                            <flux:row class="border-t-2 border-gray-200 font-semibold dark:border-zinc-700">
                                <flux:cell colspan="2">Total</flux:cell>
                                <flux:cell>{{ $this->harvesterTotals['buckets'] }}</flux:cell>
                                <flux:cell>{{ number_format($this->harvesterTotals['weight'], 3, '.', ',') }}</flux:cell>
                                <flux:cell>€{{ number_format($this->harvesterTotals['earnings'], 2, ',', '.') }}</flux:cell>
                            </flux:row>
                        @endif
                    </flux:rows>
                </flux:table>
            @endif

            <!-- Product Totals Tab -->
            @if ($activeTab === 'products')
                <flux:table>
                    <flux:columns>
                        <flux:column>Product</flux:column>
                        <flux:column>Total kg</flux:column>
                        <flux:column>Price/kg</flux:column>
                        <flux:column>Total Earnings</flux:column>
                    </flux:columns>

                    <flux:rows>
                        @forelse ($this->productData as $row)
                            <flux:row>
                                <flux:cell>{{ $row['name'] }}</flux:cell>
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
                                <flux:cell colspan="4" class="text-center text-gray-500">No data</flux:cell>
                            </flux:row>
                        @endforelse

                        @if ($this->productData->isNotEmpty())
                            <flux:row class="border-t-2 border-gray-200 font-semibold dark:border-zinc-700">
                                <flux:cell>Total</flux:cell>
                                <flux:cell>{{ number_format($this->productTotals['weight'], 3, '.', ',') }}</flux:cell>
                                <flux:cell>—</flux:cell>
                                <flux:cell>€{{ number_format($this->productTotals['earnings'], 2, ',', '.') }}</flux:cell>
                            </flux:row>
                        @endif
                    </flux:rows>
                </flux:table>
            @endif
        </div>
    </flux:main>
</x-layouts::app.sidebar>
