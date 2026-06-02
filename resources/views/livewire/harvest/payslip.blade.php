<?php

use App\Models\HarvestRecord;
use App\Models\HarvesterAssignment;
use Carbon\Carbon;
use Illuminate\Support\Collection;
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

    public bool $showDateRangeModal = false;

    public string|array|null $dateRangeValue = null;

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
        $query = HarvestRecord::where('company_id', auth()->user()->company_id)
            ->whereYear('weighed_at', $this->selectedYear);

        if ($this->dateFrom) {
            $query->whereDate('weighed_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->whereDate('weighed_at', '<=', $this->dateTo);
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

        return $harvesterNumbers->filter(function ($number) use ($search, $companyId) {
            $assignment = HarvesterAssignment::where('company_id', $companyId)
                ->where('year', $this->selectedYear)
                ->where('number', $number)
                ->with('harvester')
                ->first();

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
        return HarvestRecord::query()
            ->where('company_id', auth()->user()->company_id)
            ->whereYear('weighed_at', $this->selectedYear)
            ->selectRaw('DATE(weighed_at) as record_date')
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

        $this->updateDatesForSelectedYear();

        if ($this->dateFrom && $this->dateTo) {
            $this->dateRangeValue = $this->dateFrom.'/'.$this->dateTo;
        }
    }

    #[On('updated-selected-year')]
    public function updatedSelectedYear(): void
    {
        $this->updateDatesForSelectedYear();
        if ($this->dateFrom && $this->dateTo) {
            $this->dateRangeValue = $this->dateFrom.'/'.$this->dateTo;
        }
    }

    public function updatedDateRangeValue(string|array|null $value): void
    {
        if (! $value) {
            return;
        }

        if (is_array($value) && isset($value['start'], $value['end'])) {
            $this->dateFrom = $value['start'];
            $this->dateTo = $value['end'];
            $this->showDateRangeModal = false;
        } elseif (is_string($value) && str_contains($value, '/')) {
            [$from, $to] = explode('/', $value, 2);
            if ($from && $to) {
                $this->dateFrom = $from;
                $this->dateTo = $to;
                $this->showDateRangeModal = false;
            }
        }
    }

    private function updateDatesForSelectedYear(): void
    {
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
        <flux:button
            tag="a"
            icon="printer"
            variant="primary"
            size="sm"
            :href="route('harvest.print-payslips', ['year' => $selectedYear, 'date_from' => $dateFrom, 'date_to' => $dateTo])"
            target="_blank"
        >{{ __('Print') }}</flux:button>
    </flux:header>

    <div class="p-6 space-y-6">
        <div class="flex flex-wrap items-end gap-4">
            <flux:radio.group wire:model.live="selectedYear" label="{{ __('Year') }}" variant="pills">
                @foreach($this->availableYears as $year)
                    <flux:radio label="{{ $year }}" value="{{ $year }}"/>
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
                {{ $dateFrom ? Carbon::parse($dateFrom)->isoFormat('D MMM YYYY') : '—' }}
                –
                {{ $dateTo ? Carbon::parse($dateTo)->isoFormat('D MMM YYYY') : '—' }}
            </flux:button>
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

        <flux:modal name="date-range-picker" wire:model="showDateRangeModal" :dismissible="false" class="md:max-w-3xl! md:w-3xl!">
            <flux:heading size="lg">{{ __('Select Date Range') }}</flux:heading>

            <flux:calendar
                mode="range"
                selectable-header
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

