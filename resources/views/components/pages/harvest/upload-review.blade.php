<?php

use App\Models\HarvesterAssignment;
use App\Models\HarvestRecord;
use App\Models\HarvestRecordStaging;
use App\Models\HarvestUpload;
use App\Rules\HarvesterExistsForYear;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new
#[Layout('layouts.app')]
#[Title('Review Upload')]
class extends Component
{
    use WithPagination;

    public HarvestUpload $upload;

    public int $perPage = 25;

    public array $corrections = [];

    #[Computed]
    public function year(): int
    {
        return $this->upload->date_from->year;
    }

    #[Computed]
    public function invalidRecords()
    {
        $query = HarvestRecordStaging::where('upload_id', $this->upload->id)
            ->where('status', 'invalid')
            ->orderBy('weighed_at');

        if ($this->perPage === 0) {
            return $query->get();
        }

        return $query->paginate($this->perPage);
    }

    #[Computed]
    public function validNumbers()
    {
        return HarvesterAssignment::where('company_id', auth()->user()->company_id)
            ->orderBy('number')
            ->get();
    }

    public function mount(): void
    {
        $this->perPage = auth()->user()->userSettings?->default_per_page ?? 25;
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function resolve(int $recordId): void
    {
        $newNumber = $this->corrections[$recordId] ?? null;
        $stagingRecord = HarvestRecordStaging::findOrFail($recordId);

        // Validate that the staging record belongs to this user's company and upload
        if ($stagingRecord->company_id !== auth()->user()->company_id || $stagingRecord->upload_id !== $this->upload->id) {
            Flux::toast(text: 'Unauthorized access.', variant: 'danger');

            return;
        }

        // Validate the corrected harvester number using the record's weighed_at date
        $rule = new HarvesterExistsForYear(auth()->user()->company_id, $stagingRecord->weighed_at);
        $this->validate(
            ["corrections.$recordId" => ['required', 'integer', 'min:1', 'max:200', $rule]],
            ["corrections.$recordId.required" => 'Harvester number is required.']
        );

        // Create the corrected record in harvest_records
        HarvestRecord::create([
            'company_id' => $stagingRecord->company_id,
            'upload_id' => $stagingRecord->upload_id,
            'product_id' => $stagingRecord->product_id,
            'harvester_number' => (int) $newNumber,
            'weight' => $stagingRecord->weight,
            'tare' => $stagingRecord->tare,
            'gross' => $stagingRecord->gross,
            'weighed_at' => $stagingRecord->weighed_at,
        ]);

        // Mark staging record as valid and delete it
        $stagingRecord->update(['status' => 'valid']);
        $stagingRecord->delete();

        unset($this->corrections[$recordId]);
        $this->dispatch('$refresh');

        Flux::toast(text: 'Record updated and promoted.', variant: 'success');
    }
}; ?>

<flux:main>
    <flux:header heading="Review Upload: {{ $upload->original_filename }}">
        <flux:spacer />
        <a href="{{ route('harvest.upload') }}" wire:navigate>
            <flux:button variant="ghost">Back</flux:button>
        </a>
    </flux:header>

    <div class="p-6">
        @if($this->invalidRecords->isEmpty())
            <flux:callout type="success" icon="check-circle" title="All Clear">
                All harvester numbers are valid for {{ $this->year }}.
            </flux:callout>
        @else
            <div class="flex items-center justify-between mb-6">
                <flux:text variant="subtle">
                    {{ $this->invalidRecords->count() }} record(s) need correction
                </flux:text>
                <flux:select wire:model.live="perPage" size="sm" class="w-28">
                    <flux:select.option value="25">25</flux:select.option>
                    <flux:select.option value="50">50</flux:select.option>
                    <flux:select.option value="100">100</flux:select.option>
                    <flux:select.option value="0">All</flux:select.option>
                </flux:select>
            </div>

            <flux:table :paginate="$this->perPage > 0 ? $this->invalidRecords : null">
                <flux:table.columns>
                    <flux:table.column>Date / Time</flux:table.column>
                    <flux:table.column>Weight (kg)</flux:table.column>
                    <flux:table.column>Original #</flux:table.column>
                    <flux:table.column>Reason</flux:table.column>
                    <flux:table.column>Corrected #</flux:table.column>
                    <flux:table.column>Action</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach($this->invalidRecords as $record)
                        <flux:table.row>
                            <flux:table.cell>
                                {{ $record->weighed_at->format('d.m.Y H:i') }}
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ number_format($record->weight, 3) }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge variant="warning">{{ $record->harvester_number }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                @if($record->validation_reason === 'harvester_not_found')
                                    <flux:badge variant="warning" size="sm">Harvester not found</flux:badge>
                                @elseif($record->validation_reason === 'tare_out_of_range')
                                    <flux:badge variant="danger" size="sm">Tare out of range</flux:badge>
                                @else
                                    <flux:badge variant="zinc" size="sm">Unknown</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:select
                                    wire:model="corrections.{{ $record->id }}"
                                    size="sm"
                                    class="w-32"
                                >
                                    <flux:select.option value="">Select...</flux:select.option>
                                    @foreach($this->validNumbers as $assignment)
                                        <flux:select.option value="{{ $assignment->number }}">
                                            {{ $assignment->number }} — {{ $assignment->name }}
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>
                                @error("corrections.{$record->id}")
                                    <flux:error>{{ $message }}</flux:error>
                                @enderror
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:button
                                    size="sm"
                                    variant="primary"
                                    wire:click="resolve({{ $record->id }})"
                                    wire:loading.attr="disabled"
                                >
                                    Save
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </div>
</flux:main>
