<?php

use App\Models\Product;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Title('Products')]
class extends Component {
    public string $sortBy = 'name';

    public string $sortDirection = 'asc';

    // Create modal
    public bool $showCreateModal = false;

    public string $newProductName = '';

    public bool $newProductActive = true;

    // Edit modal
    public bool $showEditModal = false;

    public ?int $editingProductId = null;

    public string $editProductName = '';

    public bool $editProductActive = true;

    // Delete modal
    public bool $showDeleteModal = false;

    public ?int $deletingProductId = null;

    #[Computed]
    public function products (): Collection
    {
        return Product::where('company_id', auth()->user()->company_id)
            ->orderBy($this->sortBy, $this->sortDirection)
            ->get();
    }

    public function sort (string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function createProduct (): void
    {
        $this->validate([
            'newProductName' => ['required', 'string', 'max:255'],
            'newProductActive' => ['boolean'],
        ]);

        $slug = Str::slug($this->newProductName);

        if (Product::where('company_id', auth()->user()->company_id)->where('slug', $slug)->exists()) {
            $this->addError('newProductName', __('A product with this name already exists.'));

            return;
        }

        Product::create([
            'company_id' => auth()->user()->company_id,
            'name' => $this->newProductName,
            'slug' => $slug,
            'active' => $this->newProductActive,
        ]);

        $this->reset(['newProductName', 'newProductActive']);
        $this->showCreateModal = false;
        Flux::toast(text: __('Product created.'), variant: 'success');
    }

    public function editProduct (int $id): void
    {
        $product = Product::where('company_id', auth()->user()->company_id)->findOrFail($id);
        $this->editingProductId = $id;
        $this->editProductName = $product->name;
        $this->editProductActive = $product->active;
        $this->showEditModal = true;
    }

    public function updateProduct (): void
    {
        $this->validate([
            'editProductName' => ['required', 'string', 'max:255'],
            'editProductActive' => ['boolean'],
        ]);

        $slug = Str::slug($this->editProductName);

        if (Product::where('company_id', auth()->user()->company_id)->where('slug', $slug)->where('id', '!=', $this->editingProductId)->exists()) {
            $this->addError('editProductName', __('A product with this name already exists.'));

            return;
        }

        Product::where('company_id', auth()->user()->company_id)
            ->findOrFail($this->editingProductId)
            ->update([
                'name' => $this->editProductName,
                'slug' => $slug,
                'active' => $this->editProductActive,
            ]);

        $this->showEditModal = false;
        Flux::toast(text: __('Product updated.'), variant: 'success');
    }

    public function confirmDeleteProduct (int $id): void
    {
        $this->deletingProductId = $id;
        $this->showDeleteModal = true;
    }

    public function deleteProduct (): void
    {
        Product::where('company_id', auth()->user()->company_id)
            ->findOrFail($this->deletingProductId)
            ->delete();

        $this->deletingProductId = null;
        $this->showDeleteModal = false;
        Flux::toast(text: __('Product deleted.'), variant: 'warning');
    }
}; ?>

<flux:main>
    <section class="w-full">
        @include('partials.settings-heading')

        <flux:heading class="sr-only">{{ __('Products') }}</flux:heading>

        <x-pages::settings.layout :heading="__('Products')" :subheading="__('Manage the products for your company')">
            <div class="my-6 w-full space-y-4">
                <div class="flex justify-end">
                    <flux:modal.trigger name="create-product">
                        <flux:button icon="plus" size="sm" variant="primary">{{ __('Add Product') }}</flux:button>
                    </flux:modal.trigger>
                </div>

                <flux:table>
                    <flux:table.columns>
                        <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">{{ __('Name') }}</flux:table.column>
                        <flux:table.column>{{ __('Active') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse($this->products as $product)
                            <flux:table.row>
                                <flux:table.cell>{{ $product->name }}</flux:table.cell>
                                <flux:table.cell>
                                    @if ($product->active)
                                        <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                                    @else
                                        <flux:badge color="zinc" size="sm">{{ __('Inactive') }}</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell align="end" class="space-x-2">
                                    <flux:button size="sm" wire:click="editProduct({{ $product->id }})">{{ __('Edit') }}</flux:button>
                                    <flux:button variant="danger" size="sm" wire:click="confirmDeleteProduct({{ $product->id }})">{{ __('Delete') }}</flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="3" class="text-center text-zinc-500">{{ __('No products yet.') }}</flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
        </x-pages::settings.layout>
    </section>

    <flux:modal name="create-product" wire:model="showCreateModal">
        <flux:heading>{{ __('Add Product') }}</flux:heading>

        <div class="mt-6 space-y-4">
            <flux:field>
                <flux:label>{{ __('Name') }}</flux:label>
                <flux:input wire:model="newProductName"/>
                <flux:error name="newProductName"/>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Active') }}</flux:label>
                <flux:switch wire:model="newProductActive"/>
            </flux:field>
        </div>

        <div class="mt-6 flex justify-end gap-2">
            <flux:button variant="ghost" wire:click="$set('showCreateModal', false)">{{ __('Cancel') }}</flux:button>
            <flux:button variant="primary" wire:click="createProduct">{{ __('Save') }}</flux:button>
        </div>
    </flux:modal>

    <flux:modal name="edit-product" wire:model="showEditModal">
        <flux:heading>{{ __('Edit Product') }}</flux:heading>

        <div class="mt-6 space-y-4">
            <flux:field>
                <flux:label>{{ __('Name') }}</flux:label>
                <flux:input wire:model="editProductName"/>
                <flux:error name="editProductName"/>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Active') }}</flux:label>
                <flux:switch wire:model="editProductActive"/>
            </flux:field>
        </div>

        <div class="mt-6 flex justify-end gap-2">
            <flux:button variant="ghost" wire:click="$set('showEditModal', false)">{{ __('Cancel') }}</flux:button>
            <flux:button variant="primary" wire:click="updateProduct">{{ __('Save') }}</flux:button>
        </div>
    </flux:modal>

    <flux:modal name="confirm-delete-product" :dismissible="false" wire:model="showDeleteModal">
        <flux:heading>{{ __('Delete Product') }}</flux:heading>
        <flux:text>{{ __('Are you sure you want to delete this product? This cannot be undone.') }}</flux:text>

        <div class="mt-6 flex justify-end gap-2">
            <flux:button variant="ghost" wire:click="$set('showDeleteModal', false)">{{ __('Cancel') }}</flux:button>
            <flux:button variant="danger" wire:click="deleteProduct">{{ __('Delete') }}</flux:button>
        </div>
    </flux:modal>
</flux:main>
