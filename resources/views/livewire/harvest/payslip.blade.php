<?php

use App\Models\HarvestRecord;
use App\Models\HarvesterAssignment;
use Carbon\Carbon;
use Flux\DateRange;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Session;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('layouts.app')]
#[Title('Payslip')]
class extends Component
{
    #[Session]
    public int $selectedYear = 0;

    #[Session]
    public ?string $dateFrom = null;

    #[Session]
    public ?string $dateTo = null;

    #[Session]
    public string $selectedPrefix = '';

    public ?DateRange $dateRange = null;

    public string $searchHarvesterName = '';

    #[Computed]
    public function availableYears()
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
    public function harvesterNumbers(): Collection
    {
        $query = HarvestRecord::where('company_id', auth()->user()->company_id);

        if ($this->selectedYear > 0) {
            $query->whereYear('weighed_at', $this->selectedYear);
        }

        if ($this->dateFrom) {
            $query->whereDate('weighed_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->whereDate('weighed_at', '<=', $this->dateTo);
        }

        if ($this->selectedPrefix !== '') {
            $query->whereExists(function ($sub) {
                $sub->select('harvester_assignments.id')
                    ->from('harvester_assignments')
                    ->join('harvesters', 'harvester_assignments.harvester_id', '=', 'harvesters.id')
                    ->whereColumn('harvester_assignments.company_id', 'harvest_records.company_id')
                    ->whereColumn('harvester_assignments.number', 'harvest_records.harvester_number');

                if (DB::getDefaultConnection() === 'sqlite') {
                    $sub->whereRaw('harvester_assignments.year = CAST(strftime(\'%Y\', harvest_records.weighed_at) AS INTEGER)');
                } else {
                    $sub->whereRaw('harvester_assignments.year = YEAR(harvest_records.weighed_at)');
                }

                $sub->where('harvesters.prefix', $this->selectedPrefix);
            });
        }

        $harvesterNumbers = $query->distinct()
            ->pluck('harvester_number')
            ->sort()
            ->values();

        if (! $this->searchHarvesterName) {
            return $harvesterNumbers;
        }

        $search = strtolower($this->searchHarvesterName);
        $companyId = auth()->user()->company_id;
        $selectedYear = $this->selectedYear;

        return $harvesterNumbers->filter(function ($number) use ($search, $companyId, $selectedYear) {
            $assignment = HarvesterAssignment::where('company_id', $companyId)
                ->where('number', $number);

            if ($selectedYear > 0) {
                $assignment->where('year', $selectedYear);
            }

            $assignment = $assignment->with('harvester')->first();

            if (! $assignment || ! $assignment->harvester) {
                return false;
            }

            $name = strtolower($assignment->harvester->name);
            $prefix = strtolower($assignment->harvester->prefix ?? '');

            return str_contains($name, $search) || str_contains($prefix, $search);
        });
    }

    #[Computed]
    public function datesWithData(): array
    {
        $q = HarvestRecord::query()
            ->where('company_id', auth()->user()->company_id);

        if ($this->selectedYear > 0) {
            $q->whereYear('weighed_at', $this->selectedYear);
        }

        return $q->selectRaw('DATE(weighed_at) as record_date')
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
    public function availablePrefixes(): Collection
    {
        $query = HarvestRecord::from('harvest_records')
            ->where('harvest_records.company_id', auth()->user()->company_id)
            ->join('harvester_assignments', function ($join) {
                $join->on('harvester_assignments.company_id', '=', 'harvest_records.company_id')
                     ->on('harvester_assignments.number', '=', 'harvest_records.harvester_number');

                if (DB::getDefaultConnection() === 'sqlite') {
                    $join->whereRaw('harvester_assignments.year = CAST(strftime(\'%Y\', harvest_records.weighed_at) AS INTEGER)');
                } else {
                    $join->whereRaw('harvester_assignments.year = YEAR(harvest_records.weighed_at)');
                }
            })
            ->join('harvesters', 'harvester_assignments.harvester_id', '=', 'harvesters.id')
            ->whereNotNull('harvesters.prefix')
            ->where('harvesters.prefix', '!=', '');

        if ($this->selectedYear > 0) {
            $query->whereYear('harvest_records.weighed_at', $this->selectedYear);
        }
        if ($this->dateFrom) {
            $query->whereDate('harvest_records.weighed_at', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->whereDate('harvest_records.weighed_at', '<=', $this->dateTo);
        }

        return $query->distinct()
            ->pluck('harvesters.prefix')
            ->sort()
            ->values();
    }

    #[Computed]
    public function minDate(): string
    {
        if (! $this->selectedYear) {
            return Carbon::create(now()->year - 5)->format('Y-m-d');
        }

        return Carbon::create($this->selectedYear)->format('Y-m-d');
    }

    #[Computed]
    public function maxDate(): string
    {
        if (! $this->selectedYear) {
            return now()->addYears(1)->format('Y-m-d');
        }

        return Carbon::create($this->selectedYear, 12, 31)->format('Y-m-d');
    }

    public function mount(): void
    {
        if (! $this->dateFrom && ! $this->dateTo) {
            $this->dateFrom = now()->subDays(7)->format('Y-m-d');
            $this->dateTo = now()->format('Y-m-d');
        }

        if ($this->dateFrom && $this->dateTo) {
            $this->dateRange = new DateRange($this->dateFrom, $this->dateTo);
        }
    }

    #[On('updated-selected-year')]
    public function updatedSelectedYear(): void
    {
        $this->selectedPrefix = '';
        $this->dateFrom = null;
        $this->dateTo = null;
        $this->dateRange = null;
        $this->updateDatesForSelectedYear();
        if ($this->dateFrom && $this->dateTo) {
            $this->dateRange = new DateRange($this->dateFrom, $this->dateTo);
        }
    }

    public function updatedDateRange(): void
    {
        $this->selectedPrefix = '';
        if ($this->dateRange instanceof DateRange) {
            $this->dateFrom = $this->dateRange->start()->format('Y-m-d');
            $this->dateTo = $this->dateRange->end()->format('Y-m-d');
        }
    }

    private function updateDatesForSelectedYear(): void
    {
        if ($this->selectedYear === 0) {
            $this->dateFrom = now()->subDays(7)->format('Y-m-d');
            $this->dateTo = now()->format('Y-m-d');
            return;
        }

        if ($this->dateFrom) {
            $fromCarbon = Carbon::parse($this->dateFrom);
            $this->dateFrom = Carbon::create($this->selectedYear, $fromCarbon->month, $fromCarbon->day)->format('Y-m-d');
        } else {
            $latestDate = HarvestRecord::where('company_id', auth()->user()->company_id)
                ->whereYear('weighed_at', $this->selectedYear)
                ->max('weighed_at');

            if ($latestDate) {
                $this->dateFrom = Carbon::parse($latestDate)->subDays(7)->format('Y-m-d');
            } else {
                $monday = now()->startOfWeek();
                $this->dateFrom = Carbon::create($this->selectedYear, $monday->month, $monday->day)->format('Y-m-d');
            }
        }

        if ($this->dateTo) {
            $toCarbon = Carbon::parse($this->dateTo);
            $this->dateTo = Carbon::create($this->selectedYear, $toCarbon->month, $toCarbon->day)->format('Y-m-d');
        } else {
            $latestDate = HarvestRecord::where('company_id', auth()->user()->company_id)
                ->whereYear('weighed_at', $this->selectedYear)
                ->max('weighed_at');

            if ($latestDate) {
                $this->dateTo = Carbon::parse($latestDate)->format('Y-m-d');
            } else {
                $today = now();
                $this->dateTo = Carbon::create($this->selectedYear, $today->month, $today->day)->format('Y-m-d');
            }
        }
    }
}; ?>


<flux:main>
    <flux:header heading="{{ __('Harvesters Payslips') }}">
        {{ __('Harvesters Payslips') }}
        <flux:spacer/>
        @if($this->harvesterNumbers->isNotEmpty())
            <flux:button
                tag="a"
                icon="printer"
                variant="primary"
                size="sm"
                :href="route('harvest.print-payslips', ['year' => $selectedYear, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'search' => $searchHarvesterName, 'prefix' => $selectedPrefix])"
                target="_blank"
            >{{ __('Print') }}</flux:button>
        @endif
    </flux:header>

    <div class="p-6 space-y-6">
        <div class="flex flex-wrap items-end gap-4">
            <flux:radio.group wire:model.live="selectedYear" label="{{ __('Year') }}" variant="pills">
                <flux:radio :label="__('All')" value="0"/>
                @foreach($this->availableYears as $year)
                    <flux:radio label="{{ $year }}" value="{{ $year }}"/>
                @endforeach
            </flux:radio.group>
        </div>
        @if($this->availablePrefixes->isNotEmpty())
            <div class="flex flex-wrap items-end gap-4">
                <flux:radio.group wire:model.live="selectedPrefix" :label="__('Prefix')" variant="pills">
                    <flux:radio :label="__('All')" value=""/>
                    @foreach($this->availablePrefixes as $prefix)
                        <flux:radio :label="$prefix" :value="$prefix"/>
                    @endforeach
                </flux:radio.group>
            </div>
        @endif
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
        <div class="flex flex-wrap items-end gap-4">
            <flux:input
                size="sm"
                wire:model.live.debounce.300ms="searchHarvesterName"
                type="search"
                placeholder="{{ __('Search by harvester name...') }}"
                icon="magnifying-glass"
                clearable
                class="w-72!"
            />
        </div>
        @if($this->harvesterNumbers->isEmpty())
            <div class="flex items-center justify-center py-12">
                <div class="text-center">
                    <p class="text-gray-500 text-lg">{{ __('No data available for the selected period') }}</p>
                </div>
            </div>
        @else
            <div class="space-y-8">
                @foreach ($this->harvesterNumbers as $harvesterNumber)
                    <div
                        x-data="{ inView: false }"
                        x-intersect="inView = true"
                        :class="inView ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-10'"
                        class="transition-all duration-700 ease-out"
                    >
                        <livewire:harvest.payslip-card
                            :harvester-number="$harvesterNumber"
                            :year="$selectedYear"
                            :date-from="$dateFrom"
                            :date-to="$dateTo"
                            lazy
                            :key="'payslip-'.$harvesterNumber.'-'.$selectedYear"
                        />
                    </div>
                @endforeach
            </div>
        @endif

    </div>
</flux:main>

