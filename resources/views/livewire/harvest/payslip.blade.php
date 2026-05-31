<?php

use App\Models\HarvesterAssignment;
use App\Models\HarvestPrice;
use App\Models\HarvestRecord;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new
#[Layout('layouts.app')]
#[Title('Payslip')]
class extends Component {
    #[Url]
    public int $selectedYear = 0;

    #[Url]
    public ?string $dateFrom = null;

    #[Url]
    public ?string $dateTo = null;

    #[Computed]
    public function availableYears ()
    {
        return HarvestRecord::where('company_id', auth()->user()->company_id)
            ->get()
            ->map(fn($record) => $record->weighed_at->year)
            ->unique()
            ->sort()
            ->reverse()
            ->values();
    }

    #[Computed]
    public function harvesterNumbers ()
    {
        $query = HarvestRecord::where('company_id', auth()->user()->company_id)
            ->whereYear('weighed_at', $this->selectedYear);

        if ($this->dateFrom) {
            $query->whereDate('weighed_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->whereDate('weighed_at', '<=', $this->dateTo);
        }

        return $query->distinct()
            ->pluck('harvester_number')
            ->sort()
            ->values();
    }

    public function mount (): void
    {
        $years = $this->availableYears;
        if (! $this->selectedYear) {
            $this->selectedYear = $years->isNotEmpty() ? $years->first() : now()->year;
        }

        if (! $this->dateFrom || ! $this->dateTo) {
            $this->updateDatesForSelectedYear();
        }
    }

    #[On('updated-selected-year')]
    public function updatedSelectedYear (): void
    {
        $this->updateDatesForSelectedYear();
    }

    private function updateDatesForSelectedYear (): void
    {
        if ($this->dateFrom) {
            $fromCarbon = Carbon::parse($this->dateFrom);
            $this->dateFrom = Carbon::create($this->selectedYear, $fromCarbon->month, $fromCarbon->day)->format('Y-m-d');
        } else {
            $monday = now()->startOfWeek();
            $this->dateFrom = Carbon::create($this->selectedYear, $monday->month, $monday->day)->format('Y-m-d');
        }

        if ($this->dateTo) {
            $toCarbon = Carbon::parse($this->dateTo);
            $this->dateTo = Carbon::create($this->selectedYear, $toCarbon->month, $toCarbon->day)->format('Y-m-d');
        } else {
            $today = now();
            $this->dateTo = Carbon::create($this->selectedYear, $today->month, $today->day)->format('Y-m-d');
        }
    }
}; ?>


<flux:main>
    <flux:header heading="Harvesters Payslips" >
        Harvesters Payslips
        <flux:spacer/>
        <flux:button icon="printer" variant="primary" size="sm" onclick="window.print()">Print</flux:button>
    </flux:header>

    <div class="p-6">
        <div class="mb-6 flex flex-wrap items-end gap-4">
            <flux:radio.group wire:model.live="selectedYear" label="Year" variant="pills">
                @foreach($this->availableYears as $year)
                    <flux:radio label="{{ $year }}" value="{{ $year }}"/>
                @endforeach
            </flux:radio.group>
        </div>
        <div class="mb-6 flex flex-wrap items-end gap-4">
            <flux:input type="date" size="sm" wire:model.live="dateFrom" label="From"/>
            <flux:input type="date" size="sm" wire:model.live="dateTo" label="To"/>
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
    </div>
</flux:main>

