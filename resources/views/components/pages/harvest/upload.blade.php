<?php

use App\Models\HarvestUpload;
use App\Models\Product;
use App\Services\HarvestImportService;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new
#[Layout('layouts.app')]
#[Title('Upload · eBorovnica')]
class extends Component {
    use WithFileUploads;

    public int $selectedProductId = 0;
    public $uploadedFile;
    public array $uploads = [];
    public ?string $successMessage = null;

    #[Computed]
    public function products ()
    {
        return Product::where('company_id', auth()->user()->company_id)
            ->where('active', true)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function recentUploads ()
    {
        return HarvestUpload::where('company_id', auth()->user()->company_id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();
    }

    public function mount (): void
    {
        $product = $this->products->first();
        if ($product) {
            $this->selectedProductId = $product->id;
        }
    }

    public function uploadFile (): void
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

        $this->successMessage = "✓ Successfully imported {$upload->record_count} records from {$upload->original_filename} ({$upload->date_from} to {$upload->date_to})";
        $this->uploadedFile = null;
    }

    public function deleteUpload ($id): void
    {
        HarvestUpload::find($id)->delete();
    }
}; ?>

<flux:main>
    <flux:header heading="Upload Harvest Records">
    </flux:header>

    <div class="p-6">
        @if($successMessage)
            <div class="mb-6 rounded-md bg-green-50 p-4">
                <p class="text-sm text-green-700">{{ $successMessage }}</p>
            </div>
        @endif

        <div class="mb-8 rounded-lg border-2 border-dashed border-gray-300 p-8">
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700">Product</label>
                <select wire:model="selectedProductId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    <option value="">Select a product...</option>
                    @foreach($this->products as $product)
                        <option value="{{ $product->id }}">{{ $product->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700">CSV File</label>
                <input
                    type="file"
                    wire:model="uploadedFile"
                    accept=".csv"
                    class="mt-1 block w-full"
                />
                @error('uploadedFile')
                <span class="text-red-600 text-sm">{{ $message }}</span>
                @enderror
            </div>

            <button
                wire:click="uploadFile"
                wire:loading.attr="disabled"
                class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50"
            >
                <span wire:loading.remove>Upload</span>
                <span wire:loading>Uploading...</span>
            </button>
        </div>

        <h3 class="mb-4 text-lg font-semibold">Recent Uploads</h3>
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
                        <flux:table.cell>
                            <button wire:click="deleteUpload({{ $upload->id }})" class="text-red-600 hover:text-red-900">Delete</button>
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
</flux:main>
