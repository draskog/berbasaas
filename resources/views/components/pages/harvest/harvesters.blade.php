<?php

use App\Models\HarvesterAssignment;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Flux\Flux;
use Livewire\Volt\Component;

new
#[Layout('layouts.app')]
#[Title('Harvesters · eBorovnica')]
class extends Component {
    public int $selectedYear;
    public array $assignments = [];
    public ?int $newNumber = null;
    public ?string $newName = null;
    public ?int $deletingAssignmentId = null;
    public bool $showDeleteModal = false;

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


    public function createAssignment(): void
    {
        $this->validate([
            'newNumber' => 'required|integer|min:1',
            'newName' => 'required|string|max:255',
        ]);

        HarvesterAssignment::create([
            'company_id' => auth()->user()->company_id,
            'year' => $this->selectedYear,
            'number' => $this->newNumber,
            'name' => $this->newName,
        ]);

        $this->reset(['newNumber', 'newName']);
        $this->dispatch('close-modal', name: 'create-assignment');
        Flux::toast(text: 'Assignment added.', variant: 'success');
    }

    public function confirmDeleteAssignment(int $id): void
    {
        $this->deletingAssignmentId = $id;
        $this->showDeleteModal = true;
    }

    public function deleteAssignment(): void
    {
        HarvesterAssignment::find($this->deletingAssignmentId)?->delete();
        $this->deletingAssignmentId = null;
        $this->showDeleteModal = false;
        Flux::toast(text: 'Assignment deleted.', variant: 'warning');
    }
}; ?>


<flux:main>
    <flux:header heading="Harvesters">
        <flux:spacer />
        <flux:modal.trigger name="create-assignment">
            <flux:button icon="plus">Add Assignment</flux:button>
        </flux:modal.trigger>
    </flux:header>

    <div class="p-6">
        <div class="w-32">
            <flux:field>
                <flux:label>Year</flux:label>
                <flux:select wire:model.live="selectedYear">
                    @for ($year = now()->year - 5; $year <= now()->year; $year++)
                        <flux:select.option value="{{ $year }}">{{ $year }}</flux:select.option>
                    @endfor
                </flux:select>
            </flux:field>
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
                            <flux:button variant="danger" size="sm" wire:click="confirmDeleteAssignment({{ $assignment->id }})">Delete</flux:button>
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

    <flux:modal name="create-assignment">
    <flux:heading>Add Harvester Assignment</flux:heading>
    <flux:subheading>Assign a harvester number and name for {{ $this->selectedYear }}</flux:subheading>

    <div class="mt-6 space-y-4">
        <flux:field>
            <flux:label>Number</flux:label>
            <flux:input type="number" wire:model="newNumber" />
            <flux:error name="newNumber" />
        </flux:field>

        <flux:field>
            <flux:label>Name</flux:label>
            <flux:input wire:model="newName" />
            <flux:error name="newName" />
        </flux:field>
    </div>

    <div class="mt-6 flex gap-2 justify-end">
        <flux:modal.close>
            <flux:button variant="ghost">Cancel</flux:button>
        </flux:modal.close>
        <flux:button variant="primary" wire:click="createAssignment">Save</flux:button>
    </div>
</flux:modal>

<flux:modal name="confirm-delete-assignment" :dismissible="false" wire:model="showDeleteModal">
    <flux:heading>Delete Assignment</flux:heading>
    <flux:text>Are you sure you want to delete this harvester assignment? This cannot be undone.</flux:text>

    <div class="mt-6 flex gap-2 justify-end">
        <flux:button variant="ghost" wire:click="$set('showDeleteModal', false)">Cancel</flux:button>
        <flux:button variant="danger" wire:click="deleteAssignment">Delete</flux:button>
    </div>
</flux:modal>
</flux:main>
