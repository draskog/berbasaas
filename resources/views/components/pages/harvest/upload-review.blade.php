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

    public string $sortBy = 'weighed_at';

    public string $sortDirection = 'asc';

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
            ->orderBy($this->sortBy, $this->sortDirection);

        if ($this->perPage === 0) {
            return $query->get();
        }

        return $query->paginate($this->perPage);
    }

    #[Computed]
    public function validNumbers()
    {
        return HarvesterAssignment::where('company_id', auth()->user()->company_id)
            ->where('year', $this->year)
            ->with('harvester')
            ->orderBy('number')
            ->get();
    }

    #[Computed]
    public function harvestersByNumber()
    {
        return $this->validNumbers->keyBy('number');
    }

    #[Computed]
    public function validTares(): array
    {
        $fromRecords = HarvestRecord::where('upload_id', $this->upload->id)
            ->distinct()->pluck('tare');

        $fromStaging = HarvestRecordStaging::where('upload_id', $this->upload->id)
            ->distinct()->pluck('tare');

        return $fromRecords->merge($fromStaging)
            ->unique()
            ->filter(fn ($t) => $t > 0)
            ->sort()
            ->values()
            ->toArray();
    }

    public function mount(): void
    {
        $this->perPage = auth()->user()->userSettings?->default_per_page ?? 25;
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    public function resolve(int $recordId): void
    {
        $correctionValue = $this->corrections[$recordId] ?? null;
        $stagingRecord = HarvestRecordStaging::findOrFail($recordId);

        // Validate that the staging record belongs to this user's company and upload
        if ($stagingRecord->company_id !== auth()->user()->company_id || $stagingRecord->upload_id !== $this->upload->id) {
            Flux::toast(text: 'Unauthorized access.', variant: 'danger');

            return;
        }

        if ($stagingRecord->validation_reason === 'harvester_not_found') {
            // Harvester number needs correction
            $rule = new HarvesterExistsForYear(auth()->user()->company_id, $stagingRecord->weighed_at);
            $this->validate(
                ["corrections.$recordId" => ['required', 'integer', 'min:1', 'max:200', $rule]],
                ["corrections.$recordId.required" => 'Harvester number is required.']
            );

            HarvestRecord::create([
                'company_id' => $stagingRecord->company_id,
                'upload_id' => $stagingRecord->upload_id,
                'product_id' => $stagingRecord->product_id,
                'harvester_number' => (int) $correctionValue,
                'weight' => $stagingRecord->weight,
                'tare' => $stagingRecord->tare,
                'gross' => $stagingRecord->gross,
                'weighed_at' => $stagingRecord->weighed_at,
                'corrected' => true,
            ]);
        } elseif ($stagingRecord->validation_reason === 'tare_out_of_range') {
            // Tare value needs correction
            $this->validate(
                ["corrections.$recordId" => ['required', 'numeric', 'min:0']],
                ["corrections.$recordId.required" => 'Tare value is required.']
            );

            $newTare = (float) $correctionValue;
            $newWeight = $stagingRecord->gross - $newTare;

            HarvestRecord::create([
                'company_id' => $stagingRecord->company_id,
                'upload_id' => $stagingRecord->upload_id,
                'product_id' => $stagingRecord->product_id,
                'harvester_number' => $stagingRecord->harvester_number,
                'weight' => $newWeight,
                'tare' => $newTare,
                'gross' => $stagingRecord->gross,
                'weighed_at' => $stagingRecord->weighed_at,
                'corrected' => true,
            ]);
        }

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
                    <flux:table.column sortable :sorted="$sortBy === 'weighed_at'" :direction="$sortDirection" wire:click="sort('weighed_at')">Date / Time</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'weight'" :direction="$sortDirection" wire:click="sort('weight')">Weight (kg)</flux:table.column>
                    <flux:table.column>Tare</flux:table.column>
                    <flux:table.column>Gross</flux:table.column>
                    <flux:table.column>Original #</flux:table.column>
                    <flux:table.column>Corrected #</flux:table.column>
                    <flux:table.column>Reason</flux:table.column>
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
                                {{ number_format($record->tare, 3) }}
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ number_format($record->gross, 3) }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge variant="warning">{{ $record->harvester_number }}</flux:badge>
                                @if($this->harvestersByNumber->has($record->harvester_number))
                                    <span class="text-sm text-gray-400 ml-1">
                                        {{ $this->harvestersByNumber[$record->harvester_number]->harvester?->name }}
                                    </span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:autocomplete
                                    wire:model="corrections.{{ $record->id }}"
                                    placeholder="Select..."
                                    size="sm"
                                    class="w-40"
                                >
                                    @if($record->validation_reason === 'harvester_not_found')
                                        @foreach($this->validNumbers as $assignment)
                                            <flux:autocomplete.item value="{{ $assignment->number }}">
                                                {{ $assignment->number }} — {{ $assignment->harvester?->name }}
                                            </flux:autocomplete.item>
                                        @endforeach
                                    @elseif($record->validation_reason === 'tare_out_of_range')
                                        @foreach($this->validTares as $tare)
                                            <flux:autocomplete.item value="{{ $tare }}">
                                                {{ $tare }}
                                            </flux:autocomplete.item>
                                        @endforeach
                                    @endif
                                </flux:autocomplete>
                                @error('corrections.' . $record->id)
                                    <flux:error>{{ $message }}</flux:error>
                                @enderror
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
