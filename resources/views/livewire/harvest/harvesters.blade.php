<?php

use App\Models\Harvester;
use App\Models\HarvesterAssignment;
use App\Models\HarvestImportSettings;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Session;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new
#[Layout('layouts.app')]
#[Title('Harvesters')]
class extends Component {
    use WithFileUploads, WithPagination;

    #[Session]
    public int $selectedYear = 0;

    // Prefix filter
    #[Session]
    public string $selectedPrefix = '';

    public int $perPage = 25;

    public array $assignments = [];

    public ?int $newNumber = null;

    public ?int $newHarvesterId = null;

    public ?int $deletingAssignmentId = null;

    public bool $showDeleteModal = false;

    public string $sortBy = 'number';

    public string $sortDirection = 'desc';

    public string $search = '';

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

    public bool $showCreateHarvesterModal = false;

    // Add Assignment modal
    public bool $showCreateAssignmentModal = false;

    // Print config
    public int $printColumns = 3;

    // Import/Download harvesters
    public bool $showImportHarvestersModal = false;

    public ?int $importYear = null;

    public mixed $importedFile = null;

    #[Computed]
    public function availableYears (): Collection
    {
        return HarvesterAssignment::where('company_id', auth()->user()->company_id)
            ->distinct()
            ->pluck('year')
            ->sort()
            ->reverse()
            ->values();
    }

    #[Computed]
    public function harvesters (): Collection
    {
        return Harvester::where('company_id', auth()->user()->company_id)
            ->where('active', true)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function allAssignments ()
    {
        $query = HarvesterAssignment::where('harvester_assignments.company_id', auth()->user()->company_id)
            ->join('harvesters', 'harvester_assignments.harvester_id', '=', 'harvesters.id')
            ->select('harvester_assignments.*')
            ->with('harvester');

        if ($this->selectedYear > 0) {
            $query->where('harvester_assignments.year', $this->selectedYear);
        }

        if ($this->selectedPrefix !== '') {
            $query->where('harvesters.prefix', $this->selectedPrefix);
        }

        if ($this->search !== '') {
            // Ako počinje sa #, traži exact harvester number
            if (str_starts_with($this->search, '#')) {
                $number = (int) substr($this->search, 1);
                $query->where('harvester_assignments.number', $number);
            } else {
                $query->where(fn($q) => $q
                    ->where('harvesters.name', 'like', "%$this->search%")
                    ->orWhere(DB::raw('CAST(harvester_assignments.number AS CHAR)'), 'like', "%$this->search%")
                );
            }
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
    public function availablePrefixes (): Collection
    {
        $query = HarvesterAssignment::where('harvester_assignments.company_id', auth()->user()->company_id)
            ->join('harvesters', 'harvester_assignments.harvester_id', '=', 'harvesters.id')
            ->whereNotNull('harvesters.prefix')
            ->where('harvesters.prefix', '!=', '');

        if ($this->selectedYear > 0) {
            $query->where('harvester_assignments.year', $this->selectedYear);
        }

        return $query->distinct()
            ->pluck('harvesters.prefix')
            ->sort()
            ->values();
    }

    #[Computed]
    public function printAssignments ()
    {
        $query = HarvesterAssignment::where('harvester_assignments.company_id', auth()->user()->company_id)
            ->join('harvesters', 'harvester_assignments.harvester_id', '=', 'harvesters.id')
            ->select('harvester_assignments.*')
            ->with('harvester');

        if ($this->selectedYear > 0) {
            $query->where('harvester_assignments.year', $this->selectedYear);
        }

        return $query->when($this->selectedPrefix !== '', fn($q) => $q->where('harvesters.prefix', $this->selectedPrefix))
            ->orderBy('harvester_assignments.number')
            ->get();
    }

    public function mount (): void
    {
        $this->perPage = auth()->user()->userSettings?->default_per_page ?? 25;
        $years = $this->availableYears;
        $this->importYear = now()->year;
    }

    public function updatedPerPage (): void
    {
        $this->resetPage();
    }

    public function updatedSearch (): void
    {
        $this->resetPage();
    }

    public function sort (string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    public function createAssignment (): void
    {
        $this->validate([
            'newNumber' => 'required|integer|min:1|max:200',
            'newHarvesterId' => 'required|exists:harvesters,id',
        ]);

        $harvester = Harvester::findOrFail($this->newHarvesterId);

        // Verify harvester belongs to user's company
        if ($harvester->company_id !== auth()->user()->company_id) {
            Flux::toast(text: __('Unauthorized harvester.'), variant: 'danger');

            return;
        }

        $year = $this->selectedYear ?: now()->year;

        // Check if assignment already exists for this year and number
        if (HarvesterAssignment::where([
            'company_id' => auth()->user()->company_id,
            'year' => $year,
            'number' => $this->newNumber,
        ])->exists()) {
            Flux::toast(text: __('An assignment with this number already exists for this year.'), variant: 'warning');

            return;
        }

        HarvesterAssignment::create([
            'company_id' => auth()->user()->company_id,
            'harvester_id' => $this->newHarvesterId,
            'year' => $year,
            'number' => $this->newNumber,
        ]);

        $this->reset(['newNumber', 'newHarvesterId']);
        $this->showCreateAssignmentModal = false;
        Flux::toast(text: __('Assignment added.'), variant: 'success');
    }

    public function confirmDeleteAssignment (int $id): void
    {
        $this->deletingAssignmentId = $id;
        $this->showDeleteModal = true;
    }

    public function deleteAssignment (): void
    {
        HarvesterAssignment::find($this->deletingAssignmentId)?->delete();
        $this->deletingAssignmentId = null;
        $this->showDeleteModal = false;
        Flux::toast(text: __('Assignment deleted.'), variant: 'warning');
    }

    public function createHarvester (): void
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
        $this->showCreateHarvesterModal = false;
        Flux::toast(text: __('Harvester added.'), variant: 'success');
    }

    public function editHarvester (int $id): void
    {
        $harvester = Harvester::where('company_id', auth()->user()->company_id)->findOrFail($id);
        $this->editingHarvesterId = $id;
        $this->editHarvesterName = $harvester->name;
        $this->editHarvesterPrefix = $harvester->prefix ?? '';
        $this->editHarvesterActive = $harvester->active;
        $this->showEditHarvesterModal = true;
    }

    public function updateHarvester (): void
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
        Flux::toast(text: __('Harvester updated.'), variant: 'success');
    }

    public function editAssignment (int $id): void
    {
        $assignment = HarvesterAssignment::where('company_id', auth()->user()->company_id)->findOrFail($id);
        $this->editingAssignmentId = $id;
        $this->editAssignmentNumber = $assignment->number;
        $this->showEditAssignmentModal = true;
    }

    public function updateAssignment (): void
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

    public function updatedSelectedYear (): void
    {
        $this->reset('selectedPrefix');
        $this->resetPage();
    }

    public function downloadHarvesters (): void
    {
        $url = route('harvest.harvesters.download');
        $this->redirect($url);
    }

    public function importHarvesters (): void
    {
        $this->validate([
            'importYear' => 'required|integer|min:2000|max:2099',
            'importedFile' => 'required|file|mimes:csv|max:10240',
        ]);

        $year = $this->importYear;
        $companyId = auth()->user()->company_id;

        try {
            $path = $this->importedFile->getRealPath();
            $file = fopen($path, 'rb');

            if (! $file) {
                throw new RuntimeException(__('Could not open file.'));
            }

            fgets($file);

            $settings = HarvestImportSettings::where('company_id', $companyId)->first();
            $delimiter = $settings?->csv_delimiter ?? ',';

            $addedCount = 0;
            $updatedCount = 0;
            $skippedCount = 0;
            $line = 0;

            while (($data = fgetcsv($file, 0, $delimiter)) !== false) {
                $line++;
                if (empty($data[0])) {
                    continue;
                }

                $number = (int) $data[0];
                $name = $data[1] ?? '';
                $prefix = ! empty($data[2]) ? $data[2] : null;

                if (empty($name)) {
                    fclose($file);
                    Flux::toast(
                        text: __('Invalid data at line :line. Name is required.', ['line' => $line + 1]),
                        variant: 'danger'
                    );

                    return;
                }

                $assignment = HarvesterAssignment::where([
                    'company_id' => $companyId,
                    'year' => $year,
                    'number' => $number,
                ])->with('harvester')->first();

                if ($assignment?->harvester) {
                    if ($assignment->harvester->name === $name && $assignment->harvester->prefix === $prefix) {
                        $skippedCount++;
                    } else {
                        $assignment->harvester->update([
                            'name' => $name,
                            'prefix' => $prefix,
                        ]);
                        $updatedCount++;
                    }

                    continue;
                }

                $harvester = Harvester::firstOrCreate(
                    [
                        'company_id' => $companyId,
                        'name' => $name,
                        'prefix' => $prefix,
                    ],
                    [
                        'active' => true,
                    ]
                );

                HarvesterAssignment::updateOrCreate(
                    [
                        'company_id' => $companyId,
                        'year' => $year,
                        'number' => $number,
                    ],
                    [
                        'harvester_id' => $harvester->id,
                    ]
                );

                $addedCount++;
            }

            fclose($file);

            $this->importedFile = null;
            $this->importYear = null;
            $this->showImportHarvestersModal = false;

            Flux::toast(
                text: __('Import finished: :added added, :updated updated, :skipped skipped.', [
                    'added' => $addedCount,
                    'updated' => $updatedCount,
                    'skipped' => $skippedCount,
                ]),
                variant: 'success'
            );

            $this->dispatch('$refresh');
        } catch (Exception $e) {
            Flux::toast(text: __('Error importing harvesters: :message', ['message' => $e->getMessage()]), variant: 'danger');
        }
    }
}; ?>


<flux:main>
    <flux:header heading="{{ __('Harvesters') }}">
        {{ __('Harvesters') }}
        <flux:spacer/>
        <div class="flex gap-2 items-center">
            <flux:button variant="ghost" size="sm" icon="arrow-down-tray" wire:click="downloadHarvesters">
                {{ __('Download Harvesters') }}
            </flux:button>
            <flux:button variant="ghost" size="sm" icon="arrow-up-tray" wire:click="$set('showImportHarvestersModal', true)">
                {{ __('Import Harvesters') }}
            </flux:button>
            <flux:modal.trigger name="create-assignment">
                <flux:button icon="plus" size="sm" variant="primary" class="mr-3">{{ __('Add Assignment') }}</flux:button>
            </flux:modal.trigger>
            <flux:modal.trigger name="create-harvester">
                <flux:button icon="user-plus" size="sm">{{ __('Add Harvester') }}</flux:button>
            </flux:modal.trigger>
        </div>
    </flux:header>

    <div class="p-6">
        <div class="space-y-4">
            <div>
                <flux:radio.group wire:model.live="selectedYear" :label="__('Year')" variant="pills">
                    <flux:radio :label="__('All')" value="0"/>
                    @foreach($this->availableYears as $year)
                        <flux:radio label="{{ $year }}" value="{{ $year }}"/>
                    @endforeach
                </flux:radio.group>
            </div>
            @if($this->availablePrefixes->isNotEmpty())
                <div>
                    <flux:radio.group wire:model.live="selectedPrefix" :label="__('Prefix')" variant="pills">
                        <flux:radio :label="__('All')" value=""/>
                        @foreach($this->availablePrefixes as $prefix)
                            <flux:radio :label="$prefix" :value="$prefix"/>
                        @endforeach
                    </flux:radio.group>
                </div>
            @endif
            <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                <div class="flex items-center gap-4 w-full sm:w-auto">
                    <flux:input type="search" size="sm" wire:model.live.debounce.300ms="search"
                                placeholder="{{ __('Search by harvester number or name...') }}"
                                icon="magnifying-glass" class="w-full sm:w-72!"/>
                </div>
                <flux:select wire:model.live="perPage" size="sm" class="w-28">
                    <flux:select.option value="25">25</flux:select.option>
                    <flux:select.option value="50">50</flux:select.option>
                    <flux:select.option value="100">100</flux:select.option>
                    <flux:select.option value="0">{{ __('All') }}</flux:select.option>
                </flux:select>
            </div>
        </div>

        <flux:table :paginate="$this->perPage > 0 ? $this->allAssignments : null">
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'number'" :direction="$sortDirection" wire:click="sort('number')">{{ __('Number') }}</flux:table.column>
                <flux:table.column class="hidden sm:table-cell" sortable :sorted="$sortBy === 'prefix'" :direction="$sortDirection" wire:click="sort('prefix')">{{ __('Prefix') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">{{ __('Name') }}</flux:table.column>
                <flux:table.column class="hidden sm:table-cell">{{ __('Harvest Year') }}</flux:table.column>
                <flux:table.column align="center">{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($this->allAssignments as $assignment)
                    <flux:table.row>
                        <flux:table.cell>{{ $assignment->number }}</flux:table.cell>
                        <flux:table.cell class="hidden sm:table-cell">{{ $assignment->harvester?->prefix ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $assignment->harvester?->name }}</flux:table.cell>
                        <flux:table.cell class="hidden sm:table-cell">{{ $assignment->year }}</flux:table.cell>
                        <flux:table.cell align="end" class="space-x-2">
                            <flux:button size="sm" wire:click="editHarvester({{ $assignment->harvester_id }})">{{ __('Edit Harvester') }}</flux:button>
                            <flux:button size="sm" wire:click="editAssignment({{ $assignment->id }})">{{ __('Edit Assignment') }}</flux:button>
                            <flux:button variant="danger" size="sm" wire:click="confirmDeleteAssignment({{ $assignment->id }})">{{ __('Delete') }}</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center text-gray-500">{{ __('No assignments for :year', ['year' => $this->selectedYear]) }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="create-harvester" wire:model="showCreateHarvesterModal">
        <flux:heading>{{ __('Add Harvester') }}</flux:heading>

        <div class="mt-6 space-y-4">
            <flux:field>
                <flux:label>{{ __('Name') }}</flux:label>
                <flux:input wire:model="newHarvesterName"/>
                <flux:error name="newHarvesterName"/>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Prefix') }}</flux:label>
                <flux:input wire:model="newHarvesterPrefix"/>
                <flux:error name="newHarvesterPrefix"/>
            </flux:field>
        </div>

        <div class="mt-6 flex gap-2 justify-end">
            <flux:button variant="ghost" wire:click="$set('showCreateHarvesterModal', false)">{{ __('Cancel') }}</flux:button>
            <flux:button variant="primary" wire:click="createHarvester">{{ __('Save') }}</flux:button>
        </div>
    </flux:modal>

    <flux:modal name="create-assignment" wire:model="showCreateAssignmentModal">
        <flux:heading>{{ __('Add Harvester Assignment') }}</flux:heading>
        <flux:subheading>{{ __('Assign a harvester for :year', ['year' => $this->selectedYear ?: now()->year]) }}</flux:subheading>

        <div class="mt-6 space-y-4">
            <flux:field>
                <flux:label>{{ __('Assignment Number') }}</flux:label>
                <flux:input type="number" wire:model="newNumber"/>
                <flux:error name="newNumber"/>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Harvester') }}</flux:label>
                <flux:select variant="listbox" searchable wire:model="newHarvesterId" placeholder="{{ __('Select a harvester...') }}">
                    @foreach($this->harvesters as $harvester)
                        <flux:select.option value="{{ $harvester->id }}">
                            {{ $harvester->name }}{{ $harvester->prefix ? ' (' . $harvester->prefix . ')' : '' }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="newHarvesterId"/>
            </flux:field>
        </div>

        <div class="mt-6 flex gap-2 justify-end">
            <flux:button variant="ghost" wire:click="$set('showCreateAssignmentModal', false)">{{ __('Cancel') }}</flux:button>
            <flux:button variant="primary" wire:click="createAssignment">{{ __('Save') }}</flux:button>
        </div>
    </flux:modal>

    <flux:modal name="edit-harvester" wire:model="showEditHarvesterModal">
        <flux:heading>{{ __('Edit Harvester') }}</flux:heading>

        <div class="mt-6 space-y-4">
            <flux:field>
                <flux:label>{{ __('Name') }}</flux:label>
                <flux:input wire:model="editHarvesterName"/>
                <flux:error name="editHarvesterName"/>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Prefix') }}</flux:label>
                <flux:input wire:model="editHarvesterPrefix"/>
                <flux:error name="editHarvesterPrefix"/>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Active') }}</flux:label>
                <flux:switch wire:model="editHarvesterActive"/>
            </flux:field>
        </div>

        <div class="mt-6 flex gap-2 justify-end">
            <flux:button variant="ghost" wire:click="$set('showEditHarvesterModal', false)">{{ __('Cancel') }}</flux:button>
            <flux:button variant="primary" wire:click="updateHarvester">{{ __('Save') }}</flux:button>
        </div>
    </flux:modal>

    <flux:modal name="edit-assignment" wire:model="showEditAssignmentModal">
        <flux:heading>{{ __('Edit Harvester Assignment') }}</flux:heading>

        <div class="mt-6 space-y-4">
            <flux:field>
                <flux:label>{{ __('Assignment Number') }}</flux:label>
                <flux:input type="number" wire:model="editAssignmentNumber"/>
                <flux:error name="editAssignmentNumber"/>
            </flux:field>
        </div>

        <div class="mt-6 flex gap-2 justify-end">
            <flux:button variant="ghost" wire:click="$set('showEditAssignmentModal', false)">{{ __('Cancel') }}</flux:button>
            <flux:button variant="primary" wire:click="updateAssignment">{{ __('Save') }}</flux:button>
        </div>
    </flux:modal>

    <flux:modal name="confirm-delete-assignment" :dismissible="false" wire:model="showDeleteModal">
        <flux:heading>{{ __('Delete Assignment') }}</flux:heading>
        <flux:text>{{ __('Are you sure you want to delete this harvester assignment? This cannot be undone.') }}</flux:text>

        <div class="mt-6 flex gap-2 justify-end">
            <flux:button variant="ghost" wire:click="$set('showDeleteModal', false)">{{ __('Cancel') }}</flux:button>
            <flux:button variant="danger" wire:click="deleteAssignment">{{ __('Delete') }}</flux:button>
        </div>
    </flux:modal>

    <flux:modal name="import-harvesters" wire:model="showImportHarvestersModal">
        <flux:heading>{{ __('Import Harvesters List') }}</flux:heading>

        <div class="mt-6 space-y-4">
            <flux:field>
                <flux:label>{{ __('Harvest Year') }}</flux:label>
                <flux:input type="number" wire:model="importYear" placeholder="{{ now()->year }}"/>
                <flux:error name="importYear"/>
                <div class="text-xs text-zinc-500 mt-1">{{ __('The year for which you are importing the harvester list') }}</div>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('CSV File') }}</flux:label>
                <flux:input type="file" wire:model="importedFile" accept=".csv"/>
                <flux:error name="importedFile"/>
                <div class="text-xs text-zinc-500 mt-1">
                    {{ __('Format: Serial number;Harvester\'s name and surname;Prefix') }}
                </div>
            </flux:field>
        </div>

        <div class="mt-6 flex gap-2 justify-end">
            <flux:button variant="ghost" wire:click="$set('showImportHarvestersModal', false)">{{ __('Cancel') }}</flux:button>
            <flux:button variant="primary" wire:click="importHarvesters" wire:loading.attr="disabled">
                <span wire:loading.remove>{{ __('Import') }}</span>
                <span wire:loading>{{ __('Importing...') }}</span>
            </flux:button>
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
