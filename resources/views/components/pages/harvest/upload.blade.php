<?php

use App\Models\HarvestRecordStaging;
use App\Models\HarvestUpload;
use App\Models\Product;
use App\Services\HarvestImportService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Flux\Flux;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new
#[Layout('layouts.app')]
#[Title('Upload · eBorovnica')]
class extends Component {
    use WithFileUploads;

    public int $selectedProductId = 0;
    public $uploadedFile;
    public array $uploads = [];
    public ?int $deletingUploadId = null;
    public bool $showDeleteModal = false;

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
        return HarvestUpload::where('company_id', auth()->user()->company_id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();
    }

    public function getInvalidCount(HarvestUpload $upload): int
    {
        return HarvestRecordStaging::where('upload_id', $upload->id)
            ->where('status', 'invalid')
            ->count();
    }

    public function mount(): void
    {
        $product = $this->products->first();
        if ($product) {
            $this->selectedProductId = $product->id;
        }
    }

    public function uploadFile(): void
    {
        $this->validate([
            'selectedProductId' => 'required|exists:products,id',
            'uploadedFile' => 'required|file|mimes:csv|max:10240',
        ]);

        $service = new HarvestImportService();
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

        <flux:heading size="lg" class="mb-4">Recent Uploads</flux:heading>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Filename</flux:table.column>
                <flux:table.column>Product</flux:table.column>
                <flux:table.column>Records</flux:table.column>
                <flux:table.column>Date Range</flux:table.column>
                <flux:table.column>Uploaded By</flux:table.column>
                <flux:table.column>Actions</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($this->recentUploads as $upload)
                    <flux:table.row>
                        <flux:table.cell>{{ $upload->original_filename }}</flux:table.cell>
                        <flux:table.cell>{{ $upload->product->name }}</flux:table.cell>
                        <flux:table.cell>{{ $upload->record_count }}</flux:table.cell>
                        <flux:table.cell>{{ $upload->date_from->format('d.m.Y') }} - {{ $upload->date_to->format('d.m.Y') }}</flux:table.cell>
                        <flux:table.cell>{{ $upload->uploadedBy->name }}</flux:table.cell>
                        <flux:table.cell class="flex gap-2">
                            @php
                                $invalidCount = $this->getInvalidCount($upload);
                            @endphp

                            @if($invalidCount > 0)
                                <a href="{{ route('harvest.upload.review', $upload) }}" wire:navigate>
                                    <flux:badge variant="warning">{{ $invalidCount }} invalid</flux:badge>
                                </a>
                            @else
                                <flux:badge variant="success">✓ Valid</flux:badge>
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
