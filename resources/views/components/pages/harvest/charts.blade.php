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

            <!-- Charts Grid -->
            <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
                <!-- Daily kg Chart -->
                <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
                    <h3 class="mb-4 text-lg font-semibold">Daily Harvest</h3>
                    <flux:chart type="bar" :data='@json($this->dailyKgChartData)' />
                </div>

                <!-- Harvester Comparison Chart -->
                <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
                    <h3 class="mb-4 text-lg font-semibold">Top Harvesters</h3>
                    <flux:chart type="bar" :data='@json($this->harvesterComparisonChartData)' :options='@json(["indexAxis" => "y"])' />
                </div>

                <!-- Hourly Distribution Chart -->
                <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
                    <h3 class="mb-4 text-lg font-semibold">Hourly Distribution</h3>
                    <flux:chart type="bar" :data='@json($this->hourlyDistributionChartData)' />
                </div>

                <!-- Cumulative kg Chart -->
                <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
                    <h3 class="mb-4 text-lg font-semibold">Cumulative Harvest</h3>
                    <flux:chart type="line" :data='@json($this->cumulativeKgChartData)' />
                </div>
            </div>
        </div>
    </flux:main>

