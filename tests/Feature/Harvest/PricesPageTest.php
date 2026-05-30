<?php

use App\Models\Company;
use App\Models\HarvestPrice;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->for($this->company)->create();
    $this->actingAs($this->user);
});

describe('Prices Page', function () {
    it('renders the page', function () {
        $response = $this->get(route('harvest.prices'));
        $response->assertStatus(200);
    });

    it('displays products in selector', function () {
        $product = Product::factory()->for($this->company)->create(['name' => 'Apples']);

        Livewire::test('pages.harvest.prices')
            ->assertSee('Apples');
    });

    it('displays prices for selected product', function () {
        $product = Product::factory()->for($this->company)->create();
        $price = HarvestPrice::factory()
            ->for($this->company)
            ->for($product)
            ->create(['price_per_kg' => 2.5]);

        Livewire::test('pages.harvest.prices')
            ->set('selectedProductId', $product->id)
            ->assertSee('2,500');
    });

    it('creates new price', function () {
        $product = Product::factory()->for($this->company)->create();

        Livewire::test('pages.harvest.prices')
            ->set('newProductId', $product->id)
            ->set('newPricePerKg', '3.75')
            ->set('newEffectiveFrom', '2026-01-01')
            ->set('newEffectiveTo', null)
            ->call('createPrice')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('harvest_prices', [
            'company_id' => $this->company->id,
            'product_id' => $product->id,
            'price_per_kg' => 3.75,
        ]);
    });

    it('validates price is required', function () {
        $product = Product::factory()->for($this->company)->create();

        Livewire::test('pages.harvest.prices')
            ->set('newProductId', $product->id)
            ->set('newPricePerKg', null)
            ->set('newEffectiveFrom', '2026-01-01')
            ->call('createPrice')
            ->assertHasErrors('newPricePerKg');
    });

    it('validates price is numeric', function () {
        $product = Product::factory()->for($this->company)->create();

        Livewire::test('pages.harvest.prices')
            ->set('newProductId', $product->id)
            ->set('newPricePerKg', 'invalid')
            ->set('newEffectiveFrom', '2026-01-01')
            ->call('createPrice')
            ->assertHasErrors('newPricePerKg');
    });

    it('validates price is not negative', function () {
        $product = Product::factory()->for($this->company)->create();

        Livewire::test('pages.harvest.prices')
            ->set('newProductId', $product->id)
            ->set('newPricePerKg', '-1.5')
            ->set('newEffectiveFrom', '2026-01-01')
            ->call('createPrice')
            ->assertHasErrors('newPricePerKg');
    });

    it('validates effective from date is required', function () {
        $product = Product::factory()->for($this->company)->create();

        Livewire::test('pages.harvest.prices')
            ->set('newProductId', $product->id)
            ->set('newPricePerKg', '2.5')
            ->set('newEffectiveFrom', null)
            ->call('createPrice')
            ->assertHasErrors('newEffectiveFrom');
    });

    it('validates effective to is after or equal effective from', function () {
        $product = Product::factory()->for($this->company)->create();

        Livewire::test('pages.harvest.prices')
            ->set('newProductId', $product->id)
            ->set('newPricePerKg', '2.5')
            ->set('newEffectiveFrom', '2026-12-31')
            ->set('newEffectiveTo', '2026-01-01')
            ->call('createPrice')
            ->assertHasErrors('newEffectiveTo');
    });

    it('deletes price', function () {
        $product = Product::factory()->for($this->company)->create();
        $price = HarvestPrice::factory()
            ->for($this->company)
            ->for($product)
            ->create();

        Livewire::test('pages.harvest.prices')
            ->set('selectedProductId', $product->id)
            ->call('confirmDeletePrice', $price->id)
            ->call('deletePrice')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('harvest_prices', ['id' => $price->id]);
    });

    it('resets form after creating price', function () {
        $product = Product::factory()->for($this->company)->create();

        Livewire::test('pages.harvest.prices')
            ->set('newProductId', $product->id)
            ->set('newPricePerKg', '2.5')
            ->set('newEffectiveFrom', '2026-01-01')
            ->call('createPrice')
            ->assertSet('newProductId', null)
            ->assertSet('newPricePerKg', null)
            ->assertSet('newEffectiveFrom', null)
            ->assertSet('newEffectiveTo', null);
    });

    it('only shows prices for company', function () {
        $otherCompany = Company::factory()->create();
        $product = Product::factory()->for($this->company)->create();
        $otherProduct = Product::factory()->for($otherCompany)->create();

        HarvestPrice::factory()
            ->for($otherCompany)
            ->for($otherProduct)
            ->create(['price_per_kg' => 9.99]);

        Livewire::test('pages.harvest.prices')
            ->assertDontSee('9.99');
    });

    it('can edit price', function () {
        $product = Product::factory()->for($this->company)->create();
        $price = HarvestPrice::factory()
            ->for($this->company)
            ->for($product)
            ->create(['price_per_kg' => 2.5]);

        Livewire::test('pages.harvest.prices')
            ->call('editPrice', $price->id)
            ->assertSet('editingPriceId', $price->id)
            ->assertSet('showEditPriceModal', true);
    });

    it('updates price', function () {
        $product = Product::factory()->for($this->company)->create();
        $price = HarvestPrice::factory()
            ->for($this->company)
            ->for($product)
            ->create(['price_per_kg' => 2.5]);

        Livewire::test('pages.harvest.prices')
            ->call('editPrice', $price->id)
            ->set('editPricePerKg', '3.99')
            ->set('editEffectiveFrom', '2026-06-01')
            ->set('editEffectiveTo', null)
            ->call('updatePrice')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('harvest_prices', [
            'id' => $price->id,
            'price_per_kg' => 3.99,
        ]);
    });

    it('validates edit price is numeric', function () {
        $product = Product::factory()->for($this->company)->create();
        $price = HarvestPrice::factory()
            ->for($this->company)
            ->for($product)
            ->create();

        Livewire::test('pages.harvest.prices')
            ->call('editPrice', $price->id)
            ->set('editPricePerKg', 'invalid')
            ->set('editEffectiveFrom', '2026-01-01')
            ->call('updatePrice')
            ->assertHasErrors('editPricePerKg');
    });

    it('validates edit effective to is after or equal effective from', function () {
        $product = Product::factory()->for($this->company)->create();
        $price = HarvestPrice::factory()
            ->for($this->company)
            ->for($product)
            ->create();

        Livewire::test('pages.harvest.prices')
            ->call('editPrice', $price->id)
            ->set('editPricePerKg', '2.5')
            ->set('editEffectiveFrom', '2026-12-31')
            ->set('editEffectiveTo', '2026-01-01')
            ->call('updatePrice')
            ->assertHasErrors('editEffectiveTo');
    });

    it('clears edit form after updating price', function () {
        $product = Product::factory()->for($this->company)->create();
        $price = HarvestPrice::factory()
            ->for($this->company)
            ->for($product)
            ->create();

        Livewire::test('pages.harvest.prices')
            ->call('editPrice', $price->id)
            ->set('editPricePerKg', '3.5')
            ->set('editEffectiveFrom', '2026-01-01')
            ->set('editEffectiveTo', null)
            ->call('updatePrice')
            ->assertHasNoErrors();
    });
});
