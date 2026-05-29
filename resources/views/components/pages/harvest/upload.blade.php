<?php

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

    public string $sortBy = 'created_at';

    public string $sortDirection = 'desc';

    #[Computed]
    public function products()
    {
        return Product::where('company_id', auth()->user()->company_id)
            ->where('active', true)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function recentUploads()
    {
        $query = HarvestUpload::where('company_id', auth()->user()->company_id)
            ->withCount('harvestRecords as valid_count')
            ->withCount(['stagingRecords as invalid_count' => fn ($q) => $q->where('status', 'invalid')])
            ->orderBy($this->sortBy, $this->sortDirection);

        if ($this->perPage === 0) {
            return $query->get();
        }

        return $query->paginate($this->perPage);
    }

    public function mount(): void
    {
        $this->perPage = auth()->user()->userSettings?->default_per_page ?? 25;
        $product = $this->products->first();
        if ($product) {
            $this->selectedProductId = $product->id;
        }
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
}; ?>

<flux:main>
    <flux:header heading="Upload Harvest Records">
    </flux:header>

    <div class="p-6">
        <flux:card class="mb-8">
            <flux:heading size="lg" class="mb-6">Upload CSV File</flux:heading>

            <div class="space-y-4">
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

                <flux:button variant="primary" wire:click="uploadFile" wire:loading.attr="disabled">
                    <span wire:loading.remove>Upload</span>
                    <span wire:loading>Uploading...</span>
                </flux:button>
            </div>
        </flux:card>

        <div class="flex items-center justify-between mb-4">
            <flux:heading size="lg">Recent Uploads</flux:heading>
            <flux:select wire:model.live="perPage" size="sm" class="w-28">
                <flux:select.option value="25">25</flux:select.option>
                <flux:select.option value="50">50</flux:select.option>
                <flux:select.option value="100">100</flux:select.option>
                <flux:select.option value="0">All</flux:select.option>
            </flux:select>
        </div>

        <flux:table :paginate="$this->perPage > 0 ? $this->recentUploads : null">
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'original_filename'" :direction="$sortDirection" wire:click="sort('original_filename')">Filename</flux:table.column>
                <flux:table.column>Product</flux:table.column>
                <flux:table.column>Records</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection" wire:click="sort('created_at')">Date Range</flux:table.column>
                <flux:table.column>Uploaded By</flux:table.column>
                <flux:table.column>Actions</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($this->recentUploads as $upload)
                    <flux:table.row>
                        <flux:table.cell>{{ $upload->original_filename }}</flux:table.cell>
                        <flux:table.cell>{{ $upload->product->name }}</flux:table.cell>
                        <flux:table.cell>{{ $upload->valid_count }} / {{ $upload->record_count }}</flux:table.cell>
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
                                <a href="{{ route('harvest.upload.review', $upload) }}" wire:navigate>
                                    <flux:badge variant="warning">{{ $upload->invalid_count }} invalid</flux:badge>
                                </a>
                            @else
                                <flux:badge variant="success">All Valid</flux:badge>
                            @endif

                            <flux:button variant="danger" size="sm" wire:click="confirmDeleteUpload({{ $upload->id }})">Delete</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center text-gray-500">No uploads yet</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="confirm-delete-upload" :dismissible="false" wire:model="showDeleteModal">
    <flux:heading>Delete Upload</flux:heading>
    <flux:text>Are you sure you want to delete this upload? This cannot be undone.</flux:text>

    <div class="mt-6 flex gap-2 justify-end">
        <flux:button variant="ghost" wire:click="$set('showDeleteModal', false)">Cancel</flux:button>
        <flux:button variant="danger" wire:click="deleteUpload">Delete</flux:button>
    </div>
</flux:modal>
</flux:main>
