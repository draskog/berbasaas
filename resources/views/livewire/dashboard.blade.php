<?php

use App\Models\HarvesterAssignment;
use App\Models\HarvestRecord;
use App\Models\HarvestRecordStaging;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('layouts.app')]
#[Title('Dashboard')]
class extends Component
{
    #[Computed]
    public function currentYearKg(): float
    {
        return HarvestRecord::where('company_id', auth()->user()->company_id)
            ->whereYear('weighed_at', now()->year)
            ->sum('weight');
    }

    #[Computed]
    public function currentYearKgDisplay(): array
    {
        $kg = $this->currentYearKg;
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
    public function currentYearBuckets(): int
    {
        return HarvestRecord::where('company_id', auth()->user()->company_id)
            ->whereYear('weighed_at', now()->year)
            ->count();
    }

    #[Computed]
    public function pendingReview(): int
    {
        return HarvestRecordStaging::where('company_id', auth()->user()->company_id)
            ->where('status', 'invalid')
            ->count();
    }

    #[Computed]
    public function topHarvester(): ?array
    {
        $year = now()->year;
        $today = now()->startOfDay();

        // Dohvati poslednji dan sa podacima
        $lastDate = HarvestRecord::where('company_id', auth()->user()->company_id)
            ->whereYear('weighed_at', $year)
            ->selectRaw('DATE(weighed_at) as record_date')
            ->distinct()
            ->orderByDesc('record_date')
            ->value('record_date');

        if (! $lastDate) {
            return null;
        }

        $lastDateObj = Carbon::createFromFormat('Y-m-d', $lastDate);
        $daysSinceLast = $today->diffInDays($lastDateObj);

        // Ako je poslednji dan < 5 dana, prikaži top berača za taj dan
        // Inače prikaži top berača za godinu
        if ($daysSinceLast < 5) {
            $record = HarvestRecord::where('company_id', auth()->user()->company_id)
                ->whereDate('weighed_at', $lastDate)
                ->selectRaw('harvester_number, SUM(weight) as total_weight')
                ->groupBy('harvester_number')
                ->orderByDesc('total_weight')
                ->first();
        } else {
            $record = HarvestRecord::where('company_id', auth()->user()->company_id)
                ->whereYear('weighed_at', $year)
                ->selectRaw('harvester_number, SUM(weight) as total_weight')
                ->groupBy('harvester_number')
                ->orderByDesc('total_weight')
                ->first();
        }

        if (! $record) {
            return null;
        }

        $assignment = HarvesterAssignment::where('company_id', auth()->user()->company_id)
            ->where('year', $year)
            ->where('number', $record->harvester_number)
            ->with('harvester')
            ->first();

        return [
            'name' => $assignment?->harvester?->name ?? __('Harvester #:number', ['number' => $record->harvester_number]),
            'weight' => $record->total_weight,
        ];
    }

    #[Computed]
    public function bestBucket(): ?array
    {
        $year = now()->year;
        $record = HarvestRecord::where('company_id', auth()->user()->company_id)
            ->whereYear('weighed_at', $year)
            ->orderByDesc('weight')
            ->first();

        if (! $record) {
            return null;
        }

        $assignment = HarvesterAssignment::where('company_id', auth()->user()->company_id)
            ->where('year', $year)
            ->where('number', $record->harvester_number)
            ->with('harvester')
            ->first();

        return [
            'weight' => $record->weight,
            'harvester_name' => $assignment?->harvester?->name ?? __('Harvester #:number', ['number' => $record->harvester_number]),
            'date' => $record->weighed_at,
        ];
    }

    #[Computed]
    public function activeProducts(): Collection
    {
        return Product::where('company_id', auth()->user()->company_id)
            ->where('active', true)
            ->get();
    }
}; ?>
<flux:main>
    <div class="p-6">
        <flux:heading size="lg" class="mb-6">{{ __('Dashboard') }}</flux:heading>

        <!-- Row 1: Counters -->
        <div class="grid gap-4 mb-6 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3">
            <!-- Total harvest -->
            <a href="{{ route('harvest.reports') }}" wire:navigate class="block">
                <flux:card class="h-full hover:border-blue-300 transition-colors">
                    <flux:heading size="sm">{{ __('Total harvest') }}</flux:heading>
                    <flux:text class="text-2xl font-bold mt-2">
                        {{ $this->currentYearKgDisplay['value'] }} <span class="text-gray-500 dark:text-zinc-400 text-sm">{{ $this->currentYearKgDisplay['unit'] }}</span>
                    </flux:text>
                    <flux:text size="sm" class="text-gray-500 mt-1">{{ __('This year') }}</flux:text>
                </flux:card>
            </a>

            <!-- Total buckets -->
            <a href="{{ route('harvest.reports') }}" wire:navigate class="block">
                <flux:card class="h-full hover:border-blue-300 transition-colors">
                    <flux:heading size="sm">{{ __('Total buckets') }}</flux:heading>
                    <flux:text class="text-2xl font-bold mt-2">
                        {{ number_format($this->currentYearBuckets, 0, '', '') }} <span class="text-gray-500 dark:text-zinc-400 text-sm">{{ __('kom') }}</span>
                    </flux:text>
                    <flux:text size="sm" class="text-gray-500 mt-1">{{ __('Weigh-ins this year') }}</flux:text>
                </flux:card>
            </a>

            <!-- Pending Review -->
            <a href="{{ route('harvest.upload') }}" wire:navigate class="block">
                <flux:card class="h-full hover:border-blue-300 transition-colors">
                    <div class="flex items-center justify-between">
                        <flux:heading size="sm">{{ __('Pending Review') }}</flux:heading>
                        @if($this->pendingReview > 0)
                            <flux:badge color="orange">{{ $this->pendingReview }}</flux:badge>
                        @else
                            <flux:badge color="green">0</flux:badge>
                        @endif
                    </div>
                    <flux:text size="sm" class="text-gray-500 mt-2">{{ __('Records to resolve') }}</flux:text>
                </flux:card>
            </a>
        </div>

        <!-- Row 2: Highlights -->
        <div class="grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3">
            <!-- Top Harvester -->
            <a href="{{ route('harvest.reports', ['tab' => 'harvesters']) }}" wire:navigate class="block">
                <flux:card class="h-full hover:border-blue-300 transition-colors">
                    <flux:heading size="sm">{{ __('Top Harvester') }}</flux:heading>
                    @if($this->topHarvester)
                        <flux:text class="text-2xl font-bold mt-2">
                            {{ $this->topHarvester['name'] }}
                        </flux:text>
                        <flux:text size="sm" class="text-gray-500 mt-1">
                            {{ number_format($this->topHarvester['weight'], 1) }} kg
                        </flux:text>
                    @else
                        <flux:text class="text-2xl font-bold mt-2 text-gray-400">—</flux:text>
                        <flux:text size="sm" class="text-gray-500 mt-1">{{ __('No data') }}</flux:text>
                    @endif
                </flux:card>
            </a>

            <!-- Best Single Bucket -->
            <a href="{{ route('harvest.reports') }}" wire:navigate class="block">
                <flux:card class="h-full hover:border-blue-300 transition-colors">
                    <flux:heading size="sm">{{ __('Best Single Bucket') }}</flux:heading>
                    @if($this->bestBucket)
                        <flux:text class="text-2xl font-bold mt-2">
                            {{ number_format($this->bestBucket['weight'], 1) }} kg
                        </flux:text>
                        <flux:text size="sm" class="text-gray-500 mt-1">
                            {{ $this->bestBucket['harvester_name'] }}<br/>
                            {{ $this->bestBucket['date']->format('d.m.Y') }}
                        </flux:text>
                    @else
                        <flux:text class="text-2xl font-bold mt-2 text-gray-400">—</flux:text>
                        <flux:text size="sm" class="text-gray-500 mt-1">{{ __('No data') }}</flux:text>
                    @endif
                </flux:card>
            </a>

            <!-- Active Products -->
            <a href="{{ route('products.settings') }}" wire:navigate class="block">
                <flux:card class="h-full hover:border-blue-300 transition-colors">
                    <flux:heading size="sm">{{ __('Active Products') }}</flux:heading>
                    <flux:text class="text-2xl font-bold mt-2">
                        {{ $this->activeProducts->count() }}
                    </flux:text>
                    @if($this->activeProducts->count() > 0)
                        <flux:text size="sm" class="text-gray-500 mt-1">
                            {{ $this->activeProducts->take(3)->pluck('name')->join(', ') }}
                            @if($this->activeProducts->count() > 3)
                                <span class="text-gray-400">...</span>
                            @endif
                        </flux:text>
                    @else
                        <flux:text size="sm" class="text-gray-500 mt-1">{{ __('None configured') }}</flux:text>
                    @endif
                    </flux:card>
                </a>
            </div>

        <!-- Weather Forecast Widget -->
        <livewire:weather.forecast />
        </div>
    </flux:main>
