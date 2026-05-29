<?php

use App\Models\Harvester;
use App\Models\HarvesterAssignment;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new
#[Layout('layouts.app')]
#[Title('Harvesters · eBorovnica')]
class extends Component
{
    use WithPagination;

    public int $selectedYear;

    public int $perPage = 25;

    public array $assignments = [];

    public ?int $newNumber = null;

    public ?int $newHarvesterId = null;

    public ?int $deletingAssignmentId = null;

    public bool $showDeleteModal = false;

    public string $sortBy = 'number';

    public string $sortDirection = 'asc';

    #[Computed]
    public function availableYears()
    {
        return HarvesterAssignment::where('company_id', auth()->user()->company_id)
            ->distinct()
            ->pluck('year')
            ->sort()
            ->reverse()
            ->values();
    }

    #[Computed]
    public function harvesters()
    {
        return Harvester::where('company_id', auth()->user()->company_id)
            ->where('active', true)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function allAssignments()
    {
        $query = HarvesterAssignment::where('company_id', auth()->user()->company_id)
            ->where('year', $this->selectedYear)
            ->with('harvester')
            ->orderBy($this->sortBy, $this->sortDirection);

        if ($this->perPage === 0) {
            return $query->get();
        }

        return $query->paginate($this->perPage);
    }

    public function mount(): void
    {
        $this->perPage = auth()->user()->userSettings?->default_per_page ?? 25;
        $years = $this->availableYears;
        $this->selectedYear = $years->isNotEmpty() ? $years->first() : now()->year;
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

    public function createAssignment(): void
    {
        $this->validate([
            'newNumber' => 'required|integer|min:1|max:200',
            'newHarvesterId' => 'required|exists:harvesters,id',
        ]);

        $harvester = Harvester::findOrFail($this->newHarvesterId);

        // Verify harvester belongs to user's company
        if ($harvester->company_id !== auth()->user()->company_id) {
            Flux::toast(text: 'Unauthorized harvester.', variant: 'danger');

            return;
        }

        HarvesterAssignment::create([
            'company_id' => auth()->user()->company_id,
            'harvester_id' => $this->newHarvesterId,
            'year' => $this->selectedYear,
            'number' => $this->newNumber,
        ]);

        $this->reset(['newNumber', 'newHarvesterId']);
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
        <div class="flex items-center justify-between mb-4">
            <div class="w-32">
                <flux:field>
                    <flux:label>Year</flux:label>
                    <flux:select wire:model.live="selectedYear">
                        @foreach($this->availableYears as $year)
                            <flux:select.option value="{{ $year }}">{{ $year }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>
            </div>
            <flux:select wire:model.live="perPage" size="sm" class="w-28">
                <flux:select.option value="25">25</flux:select.option>
                <flux:select.option value="50">50</flux:select.option>
                <flux:select.option value="100">100</flux:select.option>
                <flux:select.option value="0">All</flux:select.option>
            </flux:select>
        </div>

        <flux:table :paginate="$this->perPage > 0 ? $this->allAssignments : null">
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'number'" :direction="$sortDirection" wire:click="sort('number')">Number</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">Name</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'prefix'" :direction="$sortDirection" wire:click="sort('prefix')">Prefix</flux:table.column>
                <flux:table.column>Actions</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($this->allAssignments as $assignment)
                    <flux:table.row>
                        <flux:table.cell>{{ $assignment->number }}</flux:table.cell>
                        <flux:table.cell>{{ $assignment->harvester?->name }}</flux:table.cell>
                        <flux:table.cell>{{ $assignment->harvester?->prefix ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:button variant="danger" size="sm" wire:click="confirmDeleteAssignment({{ $assignment->id }})">Delete</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center text-gray-500">No assignments for {{ $this->selectedYear }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="create-assignment">
    <flux:heading>Add Harvester Assignment</flux:heading>
    <flux:subheading>Assign a harvester for {{ $this->selectedYear }}</flux:subheading>

    <div class="mt-6 space-y-4">
        <flux:field>
            <flux:label>Assignment Number</flux:label>
            <flux:input type="number" wire:model="newNumber" />
            <flux:error name="newNumber" />
        </flux:field>

        <flux:field>
            <flux:label>Harvester</flux:label>
            <flux:select wire:model="newHarvesterId">
                <flux:select.option value="">Select a harvester...</flux:select.option>
                @foreach($this->harvesters as $harvester)
                    <flux:select.option value="{{ $harvester->id }}">{{ $harvester->name }} @if($harvester->prefix)({{ $harvester->prefix }})@endif</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="newHarvesterId" />
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
