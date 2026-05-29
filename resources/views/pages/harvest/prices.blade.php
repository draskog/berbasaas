<?php

use App\Models\HarvestPrice;
use App\Models\Product;
use Livewire\Volt\Component;

new class extends Component {
    public int $selectedProductId;
    public array $prices = [];

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

    public function deletePrice($id): void
    {
        HarvestPrice::find($id)->delete();
    }
}; ?>

<x-layouts::app.sidebar title="Prices">
    <flux:main>
        <flux:header heading="Harvest Prices">
            <flux:spacer />
            <flux:button icon="plus">Add Price</flux:button>
        </flux:header>

        <div class="p-6">
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700">Product</label>
                <select wire:model="selectedProductId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    <option value="">Select a product...</option>
                    @foreach($this->products as $product)
                        <option value="{{ $product->id }}">{{ $product->name }}</option>
                    @endforeach
                </select>
            </div>

            @if($this->selectedProductId)
                <flux:table>
                    <flux:columns>
                        <flux:column>Price (per kg)</flux:column>
                        <flux:column>Effective From</flux:column>
                        <flux:column>Effective To</flux:column>
                        <flux:column>Actions</flux:column>
                    </flux:columns>

                    <flux:rows>
                        @forelse($this->pricesForProduct as $price)
                            <flux:row>
                                <flux:cell>{{ number_format($price->price_per_kg, 4) }}</flux:cell>
                                <flux:cell>{{ $price->effective_from->format('d.m.Y') }}</flux:cell>
                                <flux:cell>{{ $price->effective_to?->format('d.m.Y') ?? 'Current' }}</flux:cell>
                                <flux:cell>
                                    <button wire:click="deletePrice({{ $price->id }})" class="text-red-600 hover:text-red-900">Delete</button>
                                </flux:cell>
                            </flux:row>
                        @empty
                            <flux:row>
                                <flux:cell colspan="4" class="text-center text-gray-500">No prices recorded</flux:cell>
                            </flux:row>
                        @endforelse
                    </flux:rows>
                </flux:table>
            @endif
        </div>
    </flux:main>
</x-layouts::app.sidebar>
