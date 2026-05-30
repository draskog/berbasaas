<?php

use App\Models\HarvestPrice;
use App\Models\Product;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new
#[Layout('layouts.app.sidebar')]
#[Title('Prices · eBorovnica')]
class extends Component {
    use WithPagination;

    public ?int $selectedProductId = null;

    public string $productFilter = '';

    public int $perPage = 25;

    public array $prices = [];

    public ?int $newProductId = null;

    public ?string $newPricePerKg = null;

    public ?string $newEffectiveFrom = null;

    public ?string $newEffectiveTo = null;

    public bool $showCreatePriceModal = false;

    public ?int $editingPriceId = null;

    public ?string $editPricePerKg = null;

    public ?string $editEffectiveFrom = null;

    public ?string $editEffectiveTo = null;

    public bool $showEditPriceModal = false;

    public ?int $deletingPriceId = null;

    public bool $showDeleteModal = false;

    public string $sortBy = 'effective_from';

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
    public function pricesForProduct()
    {
        $query = HarvestPrice::where('company_id', auth()->user()->company_id)
            ->orderBy($this->sortBy, $this->sortDirection);

        if ($this->productFilter !== '' && $this->productFilter !== 'all') {
            $query->where('product_id', $this->productFilter);
        }

        if ($this->perPage === 0) {
            return $query->get();
        }

        return $query->paginate($this->perPage);
    }

    public function mount(): void
    {
        $this->perPage = auth()->user()->userSettings?->default_per_page ?? 25;
        $this->productFilter = 'all';
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

    #[On('updated-productFilter')]
    public function updatePrices(): void
    {
        $this->resetPage();
    }

    public function openCreatePriceModal(): void
    {
        if ($this->productFilter !== '' && $this->productFilter !== 'all') {
            $this->newProductId = (int) $this->productFilter;
        }
        $this->showCreatePriceModal = true;
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

        HarvestPrice::closeOpenPrecedingPrices(
            companyId: auth()->user()->company_id,
            productId: $this->newProductId,
            effectiveFrom: Carbon::parse($this->newEffectiveFrom),
        );

        $this->reset(['newProductId', 'newPricePerKg', 'newEffectiveFrom', 'newEffectiveTo']);
        $this->showCreatePriceModal = false;
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

    public function editPrice(int $id): void
    {
        $price = HarvestPrice::where('company_id', auth()->user()->company_id)->findOrFail($id);
        $this->editingPriceId = $id;
        $this->editPricePerKg = (string) $price->price_per_kg;
        $this->editEffectiveFrom = $price->effective_from->format('Y-m-d');
        $this->editEffectiveTo = $price->effective_to?->format('Y-m-d');
        $this->showEditPriceModal = true;
    }

    public function updatePrice(): void
    {
        $this->validate([
            'editPricePerKg' => 'required|numeric|min:0',
            'editEffectiveFrom' => 'required|date',
            'editEffectiveTo' => 'nullable|date|after_or_equal:editEffectiveFrom',
        ]);

        $price = HarvestPrice::where('company_id', auth()->user()->company_id)
            ->findOrFail($this->editingPriceId);

        $price->update([
            'price_per_kg' => $this->editPricePerKg,
            'effective_from' => $this->editEffectiveFrom,
            'effective_to' => $this->editEffectiveTo,
        ]);

        HarvestPrice::closeOpenPrecedingPrices(
            companyId: auth()->user()->company_id,
            productId: $price->product_id,
            effectiveFrom: Carbon::parse($this->editEffectiveFrom),
            excludeId: $this->editingPriceId,
        );

        $this->showEditPriceModal = false;
        Flux::toast(text: 'Price updated.', variant: 'success');
    }
}; ?>

<flux:main>
    <flux:header heading="Harvest Prices">
        <flux:spacer />
        <flux:button icon="plus" wire:click="openCreatePriceModal">Add Price</flux:button>
    </flux:header>

    <div class="p-6">
        <div class="flex items-center justify-between mb-6">
            <div class="flex-1">
                <flux:radio.group wire:model.live="productFilter" variant="pills">
                    <flux:radio label="All" value="all" />
                    @foreach($this->products as $product)
                        <flux:radio :label="$product->name" :value="$product->id" />
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

        <flux:table :paginate="$this->perPage > 0 ? $this->pricesForProduct : null">
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'product_id'" :direction="$sortDirection" wire:click="sort('product_id')">Product</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'price_per_kg'" :direction="$sortDirection" wire:click="sort('price_per_kg')">Price (per kg)</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'effective_from'" :direction="$sortDirection" wire:click="sort('effective_from')">Effective From</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'effective_to'" :direction="$sortDirection" wire:click="sort('effective_to')">Effective To</flux:table.column>
                <flux:table.column>Actions</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($this->pricesForProduct as $price)
                    <flux:table.row>
                        <flux:table.cell>{{ $price->product->name }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($price->price_per_kg, 3, ',', '.') }}</flux:table.cell>
                        <flux:table.cell>{{ $price->effective_from->format('d.m.Y') }}</flux:table.cell>
                        <flux:table.cell>{{ $price->effective_to?->format('d.m.Y') ?? 'Current' }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex gap-1">
                                <flux:button size="sm" wire:click="editPrice({{ $price->id }})">Edit</flux:button>
                                <flux:button variant="danger" size="sm" wire:click="confirmDeletePrice({{ $price->id }})">Delete</flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center text-gray-500">No prices recorded</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="create-price" wire:model="showCreatePriceModal">
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

    <flux:modal name="edit-price" wire:model="showEditPriceModal">
        <flux:heading>Edit Price</flux:heading>

        <div class="mt-6 space-y-4">
            <flux:field>
                <flux:label>Price per kg</flux:label>
                <flux:input type="number" step="0.0001" wire:model="editPricePerKg" />
                <flux:error name="editPricePerKg" />
            </flux:field>

            <flux:field>
                <flux:label>Effective From</flux:label>
                <flux:input type="date" wire:model="editEffectiveFrom" />
                <flux:error name="editEffectiveFrom" />
            </flux:field>

            <flux:field>
                <flux:label>Effective To</flux:label>
                <flux:input type="date" wire:model="editEffectiveTo" />
                <flux:error name="editEffectiveTo" />
            </flux:field>
        </div>

        <div class="mt-6 flex gap-2 justify-end">
            <flux:button variant="ghost" wire:click="$set('showEditPriceModal', false)">Cancel</flux:button>
            <flux:button variant="primary" wire:click="updatePrice">Save</flux:button>
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
