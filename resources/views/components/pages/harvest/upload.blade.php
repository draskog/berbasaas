<?php

use App\Models\HarvesterAssignment;
use App\Models\HarvestRecord;
use App\Models\HarvestRecordStaging;
use App\Models\HarvestUpload;
use App\Models\Product;
use App\Services\HarvestImportService;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new
#[Layout('layouts.app')]
#[Title('Upload · eBorovnica')]
class extends Component
{
    use WithFileUploads, WithPagination;

    public int $selectedProductId = 0;

    public int $perPage = 25;

    public $uploadedFile;

    public array $uploads = [];

    public ?int $deletingUploadId = null;

    public bool $showDeleteModal = false;

    public ?int $resolvingUploadId = null;

    public bool $showResolveModal = false;

    public bool $showUploadModal = false;

    public string $sortBy = 'created_at';

    public string $sortDirection = 'desc';

    public string $filterProduct = '';

    public string $filterStatus = 'all';

    public string $filterResolved = 'all';

    public int $filterYear = 0;

    #[Computed]
    public function products()
    {
        return Product::where('company_id', auth()->user()->company_id)
            ->where('active', true)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function availableYears()
    {
        return HarvestUpload::where('company_id', auth()->user()->company_id)
            ->selectRaw('EXTRACT(YEAR FROM date_from) as year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($year) => (int) $year)
            ->toArray();
    }

    #[Computed]
    public function recentUploads()
    {
        $query = HarvestUpload::where('company_id', auth()->user()->company_id)
            ->withCount('harvestRecords as valid_count')
            ->withCount(['stagingRecords as invalid_count' => fn ($q) => $q->where('status', 'invalid')]);

        if ($this->filterYear !== 0) {
            $query->whereYear('date_from', $this->filterYear);
        }

        if ($this->filterProduct !== '') {
            $query->where('product_id', $this->filterProduct);
        }

        if ($this->filterStatus === 'valid') {
            $query->doesntHave('stagingRecords', function ($q) {
                $q->where('status', 'invalid');
            });
        } elseif ($this->filterStatus === 'invalid') {
            $query->whereHas('stagingRecords', function ($q) {
                $q->where('status', 'invalid');
            });
        }

        if ($this->filterResolved === 'resolved') {
            $query->doesntHave('stagingRecords', function ($q) {
                $q->where('status', 'invalid');
            });
        } elseif ($this->filterResolved === 'unresolved') {
            $query->whereHas('stagingRecords', function ($q) {
                $q->where('status', 'invalid');
            });
        }

        $query->orderBy($this->sortBy, $this->sortDirection);

        if ($this->perPage === 0) {
            return $query->get();
        }

        return $query->paginate($this->perPage);
    }

    public function mount(): void
    {
        $this->perPage = auth()->user()->userSettings?->default_per_page ?? 25;
        $currentYear = now()->year;
        $this->filterYear = in_array($currentYear, $this->availableYears, true) ? $currentYear : 0;
        $product = $this->products->first();
        if ($product) {
            $this->selectedProductId = $product->id;
        }
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function updatedFilterProduct(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterResolved(): void
    {
        $this->resetPage();
    }

    public function updatedFilterYear(): void
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

    public function uploadFile(): void
    {
        $this->validate([
            'selectedProductId' => 'required|exists:products,id',
            'uploadedFile' => 'required|file|mimes:csv|max:10240',
        ]);

        $service = new HarvestImportService;
        $upload = $service->parse(
            $this->uploadedFile,
            auth()->user()->company_id,
            $this->selectedProductId,
            auth()->id()
        );

        $this->uploadedFile = null;
        $this->showUploadModal = false;
        Flux::toast(
            text: "Successfully imported {$upload->record_count} records from {$upload->original_filename} ({$upload->date_from} to {$upload->date_to})",
            variant: 'success'
        );
    }

    public function confirmDeleteUpload(int $id): void
    {
        $this->deletingUploadId = $id;
        $this->showDeleteModal = true;
    }

    public function deleteUpload(): void
    {
        HarvestUpload::find($this->deletingUploadId)?->delete();
        $this->deletingUploadId = null;
        $this->showDeleteModal = false;
        Flux::toast(text: 'Upload deleted.', variant: 'warning');
    }

    public function autoResolve(int $uploadId): void
    {
        $upload = HarvestUpload::findOrFail($uploadId);

        // Authorize access
        if ($upload->company_id !== auth()->user()->company_id) {
            Flux::toast(text: 'Unauthorized access.', variant: 'danger');

            return;
        }

        $year = $upload->date_from->year;

        // Get all invalid staging records for this upload with 'harvester_not_found' reason
        $invalidRecords = HarvestRecordStaging::where('upload_id', $uploadId)
            ->where('status', 'invalid')
            ->where('validation_reason', 'harvester_not_found')
            ->get();

        // Get all valid harvester assignments for the company in the upload's year
        $validAssignments = HarvesterAssignment::where('company_id', $upload->company_id)
            ->where('year', $year)
            ->get()
            ->keyBy('number');

        $resolved = 0;

        foreach ($invalidRecords as $record) {
            // Simple pattern: try exact match first
            if ($validAssignments->has($record->harvester_number)) {
                $this->promoteRecord($record);
                $resolved++;
            } else {
                // Try to find closest harvester number
                $closest = $validAssignments->keys()
                    ->sortBy(fn ($num) => abs($num - $record->harvester_number))
                    ->first();

                if ($closest !== null && abs($closest - $record->harvester_number) <= 5) {
                    $record->update(['harvester_number' => $closest]);
                    $this->promoteRecord($record);
                    $resolved++;
                }
            }
        }

        $this->showResolveModal = false;
        $this->resolvingUploadId = null;
        $this->dispatch('$refresh');

        $message = $resolved === 0
            ? 'No records could be auto-resolved. Please resolve manually.'
            : "Auto-resolved {$resolved} record(s).";

        Flux::toast(text: $message, variant: $resolved > 0 ? 'success' : 'warning');
    }

    private function promoteRecord(HarvestRecordStaging $record): void
    {
        HarvestRecord::create([
            'company_id' => $record->company_id,
            'upload_id' => $record->upload_id,
            'product_id' => $record->product_id,
            'harvester_number' => $record->harvester_number,
            'weight' => $record->weight,
            'tare' => $record->tare,
            'gross' => $record->gross,
            'weighed_at' => $record->weighed_at,
        ]);

        $record->update(['status' => 'valid']);
        $record->delete();
    }
}; ?>

<flux:main>
    <flux:header heading="Upload Harvest Records">
        <flux:spacer />
        <flux:button variant="primary" size="sm" icon="arrow-up-tray" wire:click="$set('showUploadModal', true)">
            Upload CSV File
        </flux:button>
    </flux:header>

    <div class="p-6">
        <div class="flex items-center justify-between mb-6">
            <flux:heading size="lg">Recent Upload Harvest Records</flux:heading>
            <flux:select wire:model.live="perPage" size="sm" class="w-28">
                <flux:select.option value="25">25</flux:select.option>
                <flux:select.option value="50">50</flux:select.option>
                <flux:select.option value="100">100</flux:select.option>
                <flux:select.option value="0">All</flux:select.option>
            </flux:select>
        </div>

        <div class="space-y-4 mb-6">
            <div>
                <flux:radio.group wire:model.live="filterYear" label="Year" variant="pills">
                    <flux:radio value="0" label="All Years" />
                    @foreach($this->availableYears as $year)
                        <flux:radio value="{{ $year }}" label="{{ $year }}" />
                    @endforeach
                </flux:radio.group>
            </div>

            <div>
                <flux:radio.group wire:model.live="filterProduct" label="Product" variant="pills">
                    <flux:radio value="" label="All Products" />
                    @foreach($this->products as $product)
                        <flux:radio value="{{ $product->id }}" label="{{ $product->name }}" />
                    @endforeach
                </flux:radio.group>
            </div>

            <div>
                <flux:radio.group wire:model.live="filterStatus" label="Status" variant="pills">
                    <flux:radio value="all" label="All" />
                    <flux:radio value="valid" label="Valid" />
                    <flux:radio value="invalid" label="Invalid" />
                </flux:radio.group>
            </div>

            <div>
                <flux:radio.group wire:model.live="filterResolved" label="Resolution Status" variant="pills">
                    <flux:radio value="all" label="All" />
                    <flux:radio value="resolved" label="Resolved" />
                    <flux:radio value="unresolved" label="Unresolved" />
                </flux:radio.group>
            </div>
        </div>

        <flux:table :paginate="$this->perPage > 0 ? $this->recentUploads : null">
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'original_filename'" :direction="$sortDirection" wire:click="sort('original_filename')">Filename</flux:table.column>
                <flux:table.column>Product</flux:table.column>
                <flux:table.column>Total</flux:table.column>
                <flux:table.column>Valid</flux:table.column>
                <flux:table.column>Invalid</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection" wire:click="sort('created_at')">Date Range</flux:table.column>
                <flux:table.column>Uploaded By</flux:table.column>
                <flux:table.column>Actions</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($this->recentUploads as $upload)
                    <flux:table.row>
                        <flux:table.cell>{{ $upload->original_filename }}</flux:table.cell>
                        <flux:table.cell>{{ $upload->product->name }}</flux:table.cell>
                        <flux:table.cell>
                            @if($upload->invalid_count > 0)
                                <flux:badge color="orange">{{ $upload->record_count }}</flux:badge>
                            @else
                                <flux:badge color="green">{{ $upload->record_count }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($upload->invalid_count === 0)
                                <flux:badge color="green">{{ $upload->valid_count }}</flux:badge>
                            @else
                                {{ $upload->valid_count }}
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $upload->invalid_count }}</flux:table.cell>
                        <flux:table.cell>
                            @if($upload->date_from->isSameDay($upload->date_to))
                                {{ $upload->date_from->format('d.m.Y') }}
                            @else
                                {{ $upload->date_from->format('d.m.Y') }} - {{ $upload->date_to->format('d.m.Y') }}
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $upload->uploadedBy->name }}</flux:table.cell>
                        <flux:table.cell class="flex gap-2">
                            @if($upload->invalid_count > 0)
                                <flux:button size="sm" variant="primary" wire:click="$set('resolvingUploadId', {{ $upload->id }}); $set('showResolveModal', true)">
                                    Resolve
                                </flux:button>
                            @endif

                            <flux:button variant="danger" size="sm" wire:click="confirmDeleteUpload({{ $upload->id }})">Delete</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="text-center text-gray-500">No uploads yet</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    @php
        $resolvingUpload = $this->resolvingUploadId ? HarvestUpload::find($this->resolvingUploadId) : null;
    @endphp

    <flux:modal name="resolve-upload" :dismissible="true" wire:model="showResolveModal">
        @if($resolvingUpload)
            <flux:heading>Resolve {{ $resolvingUpload->invalid_count }} invalid record(s)</flux:heading>
            <flux:text class="mt-4">
                Choose how to handle the invalid records in this upload.
            </flux:text>

            <div class="mt-6 flex flex-col gap-3">
                <flux:button
                    variant="primary"
                    wire:click="autoResolve({{ $resolvingUpload->id }})"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove>Resolve Automatically</span>
                    <span wire:loading>Resolving...</span>
                </flux:button>
                <a href="{{ route('harvest.upload.review', $resolvingUpload) }}" wire:navigate>
                    <flux:button variant="ghost" class="w-full">Resolve Manually</flux:button>
                </a>
            </div>
        @endif
    </flux:modal>

    <flux:modal name="confirm-delete-upload" :dismissible="false" wire:model="showDeleteModal">
    <flux:heading>Delete Upload</flux:heading>
    <flux:text>Are you sure you want to delete this upload? This cannot be undone.</flux:text>

    <div class="mt-6 flex gap-2 justify-end">
        <flux:button variant="ghost" wire:click="$set('showDeleteModal', false)">Cancel</flux:button>
        <flux:button variant="danger" wire:click="deleteUpload">Delete</flux:button>
    </div>
</flux:modal>

    <flux:modal name="upload-csv" :dismissible="true" wire:model="showUploadModal">
        <flux:heading>Upload CSV File</flux:heading>

        <div class="mt-6 space-y-4">
            <flux:field>
                <flux:label>Product</flux:label>
                <flux:select wire:model="selectedProductId">
                    <flux:select.option value="">Select a product...</flux:select.option>
                    @foreach($this->products as $product)
                        <flux:select.option value="{{ $product->id }}">{{ $product->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="selectedProductId" />
            </flux:field>

            <flux:field>
                <flux:label>CSV File</flux:label>
                <flux:input type="file" wire:model="uploadedFile" accept=".csv" />
                <flux:error name="uploadedFile" />
            </flux:field>
        </div>

        <div class="mt-6 flex gap-2 justify-end">
            <flux:button variant="ghost" wire:click="$set('showUploadModal', false)">Cancel</flux:button>
            <flux:button variant="primary" wire:click="uploadFile" wire:loading.attr="disabled">
                <span wire:loading.remove>Upload</span>
                <span wire:loading>Uploading...</span>
            </flux:button>
        </div>
    </flux:modal>
</flux:main>
