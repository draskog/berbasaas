<?php

use App\Models\HarvesterAssignment;
use App\Models\HarvestPrice;
use App\Models\HarvestRecord;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('layouts.app')]
#[Title('Payslip')]
class extends Component {
    public int $selectedYear;

    public ?string $dateFrom = null;

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
        return HarvestRecord::where('company_id', auth()->user()->company_id)
            ->whereYear('weighed_at', $this->selectedYear)
            ->distinct()
            ->pluck('harvester_number')
            ->sort()
            ->values();
    }

    public function mount (): void
    {
        $years = $this->availableYears;
        $this->selectedYear = $years->isNotEmpty() ? $years->first() : now()->year;
        $this->dateFrom = $this->selectedYear.'-01-01';
        $this->dateTo = $this->selectedYear.'-12-31';
    }

    #[On('updated-selected-year')]
    public function updatedSelectedYear (): void
    {
        $this->dateFrom = $this->selectedYear.'-01-01';
        $this->dateTo = $this->selectedYear.'-12-31';
    }
}; ?>


<flux:main>
    <flux:header heading="Harvesters Payslips" class="flex justify-end space-x-3 items-center">
        <flux:button icon="printer" variant="primary" onclick="window.print()">Print</flux:button>
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

