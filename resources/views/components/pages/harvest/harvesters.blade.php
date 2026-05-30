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
#[Title('Harvesters')]
class extends Component
{
    use WithPagination;

    public mixed $selectedYear = '';

    public int $perPage = 25;

    public array $assignments = [];

    public ?int $newNumber = null;

    public ?int $newHarvesterId = null;

    public ?int $deletingAssignmentId = null;

    public bool $showDeleteModal = false;

    public string $sortBy = 'number';

    public string $sortDirection = 'asc';

    // Edit Harvester modal
    public ?int $editingHarvesterId = null;

    public string $editHarvesterName = '';

    public string $editHarvesterPrefix = '';

    public bool $editHarvesterActive = true;

    public bool $showEditHarvesterModal = false;

    // Edit Assignment modal
    public ?int $editingAssignmentId = null;

    public ?int $editAssignmentNumber = null;

    public bool $showEditAssignmentModal = false;

    // Add Harvester modal
    public string $newHarvesterName = '';

    public string $newHarvesterPrefix = '';

    // Prefix filter
    public string $selectedPrefix = '';

    // Print config
    public int $printColumns = 3;

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
        $query = HarvesterAssignment::where('harvester_assignments.company_id', auth()->user()->company_id)
            ->join('harvesters', 'harvester_assignments.harvester_id', '=', 'harvesters.id')
            ->select('harvester_assignments.*')
            ->with('harvester');

        if ($this->selectedYear !== '') {
            $query->where('harvester_assignments.year', $this->selectedYear);
        }

        if ($this->selectedPrefix !== '') {
            $query->where('harvesters.prefix', $this->selectedPrefix);
        }

        $sortColumn = match ($this->sortBy) {
            'prefix' => 'harvesters.prefix',
            'name' => 'harvesters.name',
            default => 'harvester_assignments.number',
        };
        $query->orderBy($sortColumn, $this->sortDirection);

        if ($this->perPage === 0) {
            return $query->get();
        }

        return $query->paginate($this->perPage);
    }

    #[Computed]
    public function availablePrefixes()
    {
        $query = HarvesterAssignment::where('harvester_assignments.company_id', auth()->user()->company_id)
            ->join('harvesters', 'harvester_assignments.harvester_id', '=', 'harvesters.id')
            ->whereNotNull('harvesters.prefix')
            ->where('harvesters.prefix', '!=', '');

        if ($this->selectedYear !== '') {
            $query->where('harvester_assignments.year', $this->selectedYear);
        }

        return $query->distinct()
            ->pluck('harvesters.prefix')
            ->sort()
            ->values();
    }

    #[Computed]
    public function printAssignments()
    {
        $query = HarvesterAssignment::where('harvester_assignments.company_id', auth()->user()->company_id)
            ->join('harvesters', 'harvester_assignments.harvester_id', '=', 'harvesters.id')
            ->select('harvester_assignments.*')
            ->with('harvester');

        if ($this->selectedYear !== '') {
            $query->where('harvester_assignments.year', $this->selectedYear);
        }

        return $query->when($this->selectedPrefix !== '', fn ($q) => $q->where('harvesters.prefix', $this->selectedPrefix))
            ->orderBy('harvester_assignments.number')
            ->get();
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

        // Check if assignment already exists for this year and number
        if (HarvesterAssignment::where([
            'company_id' => auth()->user()->company_id,
            'year' => $this->selectedYear,
            'number' => $this->newNumber,
        ])->exists()) {
            Flux::toast(text: 'An assignment with this number already exists for this year.', variant: 'warning');

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

    public function createHarvester(): void
    {
        $this->validate([
            'newHarvesterName' => 'required|string|max:255',
            'newHarvesterPrefix' => 'nullable|string|max:10',
        ]);

        Harvester::create([
            'company_id' => auth()->user()->company_id,
            'name' => $this->newHarvesterName,
            'prefix' => $this->newHarvesterPrefix ?: null,
            'active' => true,
        ]);

        $this->reset(['newHarvesterName', 'newHarvesterPrefix']);
        $this->dispatch('close-modal', name: 'create-harvester');
        Flux::toast(text: 'Harvester added.', variant: 'success');
    }

    public function editHarvester(int $id): void
    {
        $harvester = Harvester::where('company_id', auth()->user()->company_id)->findOrFail($id);
        $this->editingHarvesterId = $id;
        $this->editHarvesterName = $harvester->name;
        $this->editHarvesterPrefix = $harvester->prefix ?? '';
        $this->editHarvesterActive = $harvester->active;
        $this->showEditHarvesterModal = true;
    }

    public function updateHarvester(): void
    {
        $this->validate([
            'editHarvesterName' => 'required|string|max:255',
            'editHarvesterPrefix' => 'nullable|string|max:10',
        ]);

        Harvester::where('company_id', auth()->user()->company_id)
            ->findOrFail($this->editingHarvesterId)
            ->update([
                'name' => $this->editHarvesterName,
                'prefix' => $this->editHarvesterPrefix ?: null,
                'active' => $this->editHarvesterActive,
            ]);

        $this->showEditHarvesterModal = false;
        Flux::toast(text: 'Harvester updated.', variant: 'success');
    }

    public function editAssignment(int $id): void
    {
        $assignment = HarvesterAssignment::where('company_id', auth()->user()->company_id)->findOrFail($id);
        $this->editingAssignmentId = $id;
        $this->editAssignmentNumber = $assignment->number;
        $this->showEditAssignmentModal = true;
    }

    public function updateAssignment(): void
    {
        $this->validate([
            'editAssignmentNumber' => 'required|integer|min:1|max:200',
        ]);

        HarvesterAssignment::where('company_id', auth()->user()->company_id)
            ->findOrFail($this->editingAssignmentId)
            ->update(['number' => $this->editAssignmentNumber]);

        $this->showEditAssignmentModal = false;
        Flux::toast(text: 'Assignment updated.', variant: 'success');
    }

    public function updatedSelectedYear(): void
    {
        $this->selectedPrefix = '';
        $this->resetPage();
    }
}; ?>


<flux:main>
    <flux:header heading="Harvesters" class="flex justify-end space-x-3 items-center">
        <div class="print:hidden" x-data="{ showPrintSettings: false }">
            <flux:button icon="printer" @click="showPrintSettings = !showPrintSettings">Print Labels</flux:button>

            <div x-show="showPrintSettings" class="absolute mt-2 p-4 border rounded space-y-4 bg-white dark:bg-zinc-800 shadow-lg z-10">
                <flux:field>
                    <flux:radio.group wire:model.live="printColumns" label="Columns per row" variant="pills">
                        <flux:radio label="2" value="2" />
                        <flux:radio label="3" value="3" />
                        <flux:radio label="4" value="4" />
                    </flux:radio.group>
                </flux:field>
                <flux:button onclick="window.print()" icon="printer" variant="primary">Print</flux:button>
            </div>
        </div>
        <flux:modal.trigger name="create-harvester">
            <flux:button icon="user-plus" class="mr-3">Add Harvester</flux:button>
        </flux:modal.trigger>
        <flux:modal.trigger name="create-assignment">
            <flux:button icon="plus">Add Assignment</flux:button>
        </flux:modal.trigger>
    </flux:header>

    <div class="p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-4">
                <flux:radio.group wire:model.live="selectedYear" label="Year" variant="pills">
                    <flux:radio label="All" value="" />
                    @foreach($this->availableYears as $year)
                        <flux:radio label="{{ $year }}" value="{{ $year }}" />
                    @endforeach
                </flux:radio.group>
            </div>
            <flux:select wire:model.live="perPage" size="sm" class="w-28">
                <flux:select.option value="25">25</flux:select.option>
                <flux:select.option value="50">50</flux:select.option>
                <flux:select.option value="100">100</flux:select.option>
                <flux:select.option value="0">All</flux:select.option>
            </flux:select>
        </div>

        @if($this->availablePrefixes->isNotEmpty())
            <div class="mb-6">
                <flux:radio.group wire:model.live="selectedPrefix" label="Prefix" variant="pills">
                    <flux:radio label="All" value="" />
                    @foreach($this->availablePrefixes as $prefix)
                        <flux:radio :label="$prefix" :value="$prefix" />
                    @endforeach
                </flux:radio.group>
            </div>
        @endif

        <flux:table :paginate="$this->perPage > 0 ? $this->allAssignments : null">
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'number'" :direction="$sortDirection" wire:click="sort('number')">Number</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'prefix'" :direction="$sortDirection" wire:click="sort('prefix')">Prefix</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">Name</flux:table.column>
                <flux:table.column>Harvest Year</flux:table.column>
                <flux:table.column class="text-right w-fit whitespace-nowrap">Actions</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($this->allAssignments as $assignment)
                    <flux:table.row>
                        <flux:table.cell>{{ $assignment->number }}</flux:table.cell>
                        <flux:table.cell>{{ $assignment->harvester?->prefix ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $assignment->harvester?->name }}</flux:table.cell>
                        <flux:table.cell>{{ $assignment->year }}</flux:table.cell>
                        <flux:table.cell class="text-right">
                            <flux:dropdown position="bottom" align="end">
                                <flux:button icon="ellipsis-horizontal" size="sm" variant="subtle" />
                                <flux:menu>
                                    <flux:menu.item icon="pencil" wire:click="editHarvester({{ $assignment->harvester_id }})">Edit Harvester</flux:menu.item>
                                    <flux:menu.item icon="pencil" wire:click="editAssignment({{ $assignment->id }})">Edit Assignment</flux:menu.item>
                                    <flux:menu.item icon="trash" variant="danger" wire:click="confirmDeleteAssignment({{ $assignment->id }})">Delete</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center text-gray-500">No assignments for {{ $this->selectedYear }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="create-harvester">
        <flux:heading>Add Harvester</flux:heading>

        <div class="mt-6 space-y-4">
            <flux:field>
                <flux:label>Name</flux:label>
                <flux:input wire:model="newHarvesterName" />
                <flux:error name="newHarvesterName" />
            </flux:field>

            <flux:field>
                <flux:label>Prefix</flux:label>
                <flux:input wire:model="newHarvesterPrefix" />
                <flux:error name="newHarvesterPrefix" />
            </flux:field>
        </div>

        <div class="mt-6 flex gap-2 justify-end">
            <flux:modal.close>
                <flux:button variant="ghost">Cancel</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" wire:click="createHarvester">Save</flux:button>
        </div>
    </flux:modal>

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

    <flux:modal name="edit-harvester" wire:model="showEditHarvesterModal">
        <flux:heading>Edit Harvester</flux:heading>

        <div class="mt-6 space-y-4">
            <flux:field>
                <flux:label>Name</flux:label>
                <flux:input wire:model="editHarvesterName" />
                <flux:error name="editHarvesterName" />
            </flux:field>

            <flux:field>
                <flux:label>Prefix</flux:label>
                <flux:input wire:model="editHarvesterPrefix" />
                <flux:error name="editHarvesterPrefix" />
            </flux:field>

            <flux:field>
                <flux:label>Active</flux:label>
                <flux:switch wire:model="editHarvesterActive" />
            </flux:field>
        </div>

        <div class="mt-6 flex gap-2 justify-end">
            <flux:button variant="ghost" wire:click="$set('showEditHarvesterModal', false)">Cancel</flux:button>
            <flux:button variant="primary" wire:click="updateHarvester">Save</flux:button>
        </div>
    </flux:modal>

    <flux:modal name="edit-assignment" wire:model="showEditAssignmentModal">
        <flux:heading>Edit Harvester Assignment</flux:heading>

        <div class="mt-6 space-y-4">
            <flux:field>
                <flux:label>Assignment Number</flux:label>
                <flux:input type="number" wire:model="editAssignmentNumber" />
                <flux:error name="editAssignmentNumber" />
            </flux:field>
        </div>

        <div class="mt-6 flex gap-2 justify-end">
            <flux:button variant="ghost" wire:click="$set('showEditAssignmentModal', false)">Cancel</flux:button>
            <flux:button variant="primary" wire:click="updateAssignment">Save</flux:button>
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

    <div class="hidden print:block">
        <style>
            @page {
                size: A4;
                margin: 0;
            }
        </style>
        <div style="display: grid; grid-template-columns: repeat({{ $printColumns }}, 1fr); gap: 4mm; padding: 10mm;">
            @foreach($this->printAssignments as $assignment)
                <div style="border: 0.3mm solid #999; padding: 3mm; min-height: 25mm; break-inside: avoid;">
                    <div style="font-size: 20pt; font-weight: 700; line-height: 1;">{{ $assignment->number }}</div>
                    <div style="font-size: 9pt; margin-top: 2mm;">{{ $assignment->harvester?->name }}</div>
                    @if($assignment->harvester?->prefix)
                        <div style="font-size: 8pt; color: #555; margin-top: 1mm;">{{ $assignment->harvester->prefix }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</flux:main>
