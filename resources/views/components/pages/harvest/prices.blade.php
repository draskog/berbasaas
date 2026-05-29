<?php

use App\Models\HarvestPrice;
use App\Models\Product;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Flux\Flux;
use Livewire\Volt\Component;

new
#[Layout('layouts.app.sidebar')]
#[Title('Prices · eBorovnica')]
class extends Component {
    public int $selectedProductId;
    public array $prices = [];
    public ?int $newProductId = null;
    public ?string $newPricePerKg = null;
    public ?string $newEffectiveFrom = null;
    public ?string $newEffectiveTo = null;
    public ?int $deletingPriceId = null;
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
    public function pricesForProduct()
    {
        if (!$this->selectedProductId) {
            return [];
        }

        return HarvestPrice::where('company_id', auth()->user()->company_id)
            ->where('product_id', $this->selectedProductId)
            ->orderByDesc('effective_from')
            ->get();
    }

    public function mount(): void
    {
        $product = $this->products->first();
        if ($product) {
            $this->selectedProductId = $product->id;
        }
    }

    #[On('updated-selectedProductId')]
    public function updatePrices(): void
    {
        // Prices will update via computed property
    }

    public function createPrice(): void
    {
        $this->validate([
            'newProductId' => 'required|exists:products,id',
            'newPricePerKg' => 'required|numeric|min:0',
            'newEffectiveFrom' => 'required|date',
            'newEffectiveTo' => 'nullable|date|after_or_equal:newEffectiveFrom',
        ]);

        HarvestPrice::create([
            'company_id' => auth()->user()->company_id,
            'product_id' => $this->newProductId,
            'price_per_kg' => $this->newPricePerKg,
            'effective_from' => $this->newEffectiveFrom,
            'effective_to' => $this->newEffectiveTo,
        ]);

        $this->reset(['newProductId', 'newPricePerKg', 'newEffectiveFrom', 'newEffectiveTo']);
        $this->dispatch('close-modal', name: 'create-price');
        Flux::toast(text: 'Price added successfully.', variant: 'success');
    }

    public function confirmDeletePrice(int $id): void
    {
        $this->deletingPriceId = $id;
        $this->showDeleteModal = true;
    }

    public function deletePrice(): void
    {
        HarvestPrice::find($this->deletingPriceId)?->delete();
        $this->deletingPriceId = null;
        $this->showDeleteModal = false;
        Flux::toast(text: 'Price deleted.', variant: 'warning');
    }
}; ?>

<flux:main>
    <flux:header heading="Harvest Prices">
        <flux:spacer />
        <flux:modal.trigger name="create-price">
            <flux:button icon="plus">Add Price</flux:button>
        </flux:modal.trigger>
    </flux:header>

    <div class="p-6">
        <flux:field>
            <flux:label>Product</flux:label>
            <flux:select wire:model="selectedProductId">
                <flux:select.option value="">Select a product...</flux:select.option>
                @foreach($this->products as $product)
                    <flux:select.option value="{{ $product->id }}">{{ $product->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>

        @if($this->selectedProductId)
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Price (per kg)</flux:table.column>
                    <flux:table.column>Effective From</flux:table.column>
                    <flux:table.column>Effective To</flux:table.column>
                    <flux:table.column>Actions</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse($this->pricesForProduct as $price)
                        <flux:table.row>
                            <flux:table.cell>{{ number_format($price->price_per_kg, 4) }}</flux:table.cell>
                            <flux:table.cell>{{ $price->effective_from->format('d.m.Y') }}</flux:table.cell>
                            <flux:table.cell>{{ $price->effective_to?->format('d.m.Y') ?? 'Current' }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:button variant="danger" size="sm" wire:click="confirmDeletePrice({{ $price->id }})">Delete</flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4" class="text-center text-gray-500">No prices recorded</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        @endif
    </div>

    <flux:modal name="create-price">
    <flux:heading>Add Price</flux:heading>
    <flux:subheading>Set a new price for a product.</flux:subheading>

    <div class="mt-6 space-y-4">
        <flux:field>
            <flux:label>Product</flux:label>
            <flux:select wire:model="newProductId">
                <flux:select.option value="">Select product...</flux:select.option>
                @foreach ($this->products as $product)
                    <flux:select.option value="{{ $product->id }}">{{ $product->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="newProductId" />
        </flux:field>

        <flux:field>
            <flux:label>Price per kg</flux:label>
            <flux:input type="number" step="0.0001" wire:model="newPricePerKg" />
            <flux:error name="newPricePerKg" />
        </flux:field>

        <flux:field>
            <flux:label>Effective From</flux:label>
            <flux:input type="date" wire:model="newEffectiveFrom" />
            <flux:error name="newEffectiveFrom" />
        </flux:field>

        <flux:field>
            <flux:label>Effective To</flux:label>
            <flux:input type="date" wire:model="newEffectiveTo" />
            <flux:error name="newEffectiveTo" />
        </flux:field>
    </div>

    <div class="mt-6 flex gap-2 justify-end">
        <flux:modal.close>
            <flux:button variant="ghost">Cancel</flux:button>
        </flux:modal.close>
        <flux:button variant="primary" wire:click="createPrice">Save</flux:button>
    </div>
</flux:modal>

<flux:modal name="confirm-delete-price" :dismissible="false" wire:model="showDeleteModal">
    <flux:heading>Delete Price</flux:heading>
    <flux:text>Are you sure you want to delete this price? This cannot be undone.</flux:text>

    <div class="mt-6 flex gap-2 justify-end">
        <flux:button variant="ghost" wire:click="$set('showDeleteModal', false)">Cancel</flux:button>
        <flux:button variant="danger" wire:click="deletePrice">Delete</flux:button>
    </div>
</flux:modal>
</flux:main>
