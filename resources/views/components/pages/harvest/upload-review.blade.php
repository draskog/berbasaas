<?php

use App\Models\HarvestRecord;
use App\Models\HarvesterAssignment;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Flux\Flux;
use Livewire\Volt\Component;

new
#[Layout('layouts.app')]
#[Title('Review Upload')]
class extends Component {
    public $upload;

    public array $corrections = [];

    #[Computed]
    public function year(): int
    {
        return $this->upload->date_from->year;
    }

    #[Computed]
    public function validNumbers()
    {
        return HarvesterAssignment::where('company_id', auth()->user()->company_id)
            ->where('year', $this->year)
            ->orderBy('number')
            ->get();
    }

    #[Computed]
    public function invalidRecords()
    {
        $validNumbers = $this->validNumbers->pluck('number')->toArray();

        return HarvestRecord::where('upload_id', $this->upload->id)
            ->where('corrected', false)
            ->whereNotIn('harvester_number', $validNumbers)
            ->orderBy('weighed_at')
            ->get();
    }

    public function resolve(int $recordId): void
    {
        $newNumber = $this->corrections[$recordId] ?? null;
        $this->validate(["corrections.$recordId" => 'required|integer|min:1|max:200']);

        HarvestRecord::where('id', $recordId)
            ->where('company_id', auth()->user()->company_id)
            ->update(['harvester_number' => (int) $newNumber, 'corrected' => true]);

        unset($this->corrections[$recordId]);
        $this->resetComputedCache();

        Flux::toast(text: 'Record updated.', variant: 'success');
    }

    private function resetComputedCache(): void
    {
        #[\Livewire\Attributes\Computed] $invalidRecords = null;
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
            <div class="mb-6">
                <flux:text variant="subtle">
                    {{ $this->invalidRecords->count() }} record(s) need correction
                </flux:text>
            </div>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Date / Time</flux:table.column>
                    <flux:table.column>Weight (kg)</flux:table.column>
                    <flux:table.column>Original #</flux:table.column>
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
