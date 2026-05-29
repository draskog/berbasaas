<?php

use App\Models\HarvesterAssignment;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    public int $selectedYear;
    public array $assignments = [];

    #[Computed]
    public function allAssignments()
    {
        return HarvesterAssignment::where('company_id', auth()->user()->company_id)
            ->where('year', $this->selectedYear)
            ->orderBy('number')
            ->get();
    }

    public function mount(): void
    {
        $this->selectedYear = now()->year;
    }

    #[On('updated-selectedYear')]
    public function updateAssignments(): void
    {
        $this->assignments = $this->allAssignments->toArray();
    }

    public function deleteAssignment($id): void
    {
        HarvesterAssignment::find($id)->delete();
        $this->updateAssignments();
    }
}; ?>

<x-layouts::app.sidebar title="Harvesters">
    <flux:main>
        <flux:header heading="Harvesters">
            <flux:spacer />
            <flux:button href="{{ route('harvesters.index') }}" icon="plus">Add Assignment</flux:button>
        </flux:header>

        <div class="p-6">
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700">Year</label>
                <select wire:model="selectedYear" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    @for ($year = now()->year - 5; $year <= now()->year; $year++)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endfor
                </select>
            </div>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Number</flux:table.column>
                    <flux:table.column>Name</flux:table.column>
                    <flux:table.column>Actions</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse($this->allAssignments as $assignment)
                        <flux:table.row>
                            <flux:table.cell>{{ $assignment->number }}</flux:table.cell>
                            <flux:table.cell>{{ $assignment->name }}</flux:table.cell>
                            <flux:table.cell>
                                <button wire:click="deleteAssignment({{ $assignment->id }})" class="text-red-600 hover:text-red-900">Delete</button>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="3" class="text-center text-gray-500">No assignments for {{ $this->selectedYear }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </flux:main>
</x-layouts::app.sidebar>
