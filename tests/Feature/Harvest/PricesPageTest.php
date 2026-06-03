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

        Livewire::test('harvest.prices')
            ->assertSee('Apples');
    });

    it('displays prices for selected product', function () {
        $product = Product::factory()->for($this->company)->create();
        $price = HarvestPrice::factory()
            ->for($this->company)
            ->for($product)
            ->create(['price_per_kg' => 2.5]);

        Livewire::test('harvest.prices')
            ->set('selectedProduct', $product->id)
            ->assertSee('3');
    });

    it('creates new price', function () {
        $product = Product::factory()->for($this->company)->create();

        Livewire::test('harvest.prices')
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

        Livewire::test('harvest.prices')
            ->set('newProductId', $product->id)
            ->set('newPricePerKg', null)
            ->set('newEffectiveFrom', '2026-01-01')
            ->call('createPrice')
            ->assertHasErrors('newPricePerKg');
    });

    it('validates price is numeric', function () {
        $product = Product::factory()->for($this->company)->create();

        Livewire::test('harvest.prices')
            ->set('newProductId', $product->id)
            ->set('newPricePerKg', 'invalid')
            ->set('newEffectiveFrom', '2026-01-01')
            ->call('createPrice')
            ->assertHasErrors('newPricePerKg');
    });

    it('validates price is not negative', function () {
        $product = Product::factory()->for($this->company)->create();

        Livewire::test('harvest.prices')
            ->set('newProductId', $product->id)
            ->set('newPricePerKg', '-1.5')
            ->set('newEffectiveFrom', '2026-01-01')
            ->call('createPrice')
            ->assertHasErrors('newPricePerKg');
    });

    it('validates effective from date is required', function () {
        $product = Product::factory()->for($this->company)->create();

        Livewire::test('harvest.prices')
            ->set('newProductId', $product->id)
            ->set('newPricePerKg', '2.5')
            ->set('newEffectiveFrom', null)
            ->call('createPrice')
            ->assertHasErrors('newEffectiveFrom');
    });

    it('validates effective to is after or equal effective from', function () {
        $product = Product::factory()->for($this->company)->create();

        Livewire::test('harvest.prices')
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

        Livewire::test('harvest.prices')
            ->set('selectedProductId', $product->id)
            ->call('confirmDeletePrice', $price->id)
            ->call('deletePrice')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('harvest_prices', ['id' => $price->id]);
    });

    it('resets form after creating price', function () {
        $product = Product::factory()->for($this->company)->create();

        Livewire::test('harvest.prices')
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

        Livewire::test('harvest.prices')
            ->assertDontSee('9.99');
    });

    it('can edit price', function () {
        $product = Product::factory()->for($this->company)->create();
        $price = HarvestPrice::factory()
            ->for($this->company)
            ->for($product)
            ->create(['price_per_kg' => 2.5]);

        Livewire::test('harvest.prices')
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

        Livewire::test('harvest.prices')
            ->call('editPrice', $price->id)
            ->set('editPricePerKg', '3.99')
            ->set('editEffectiveFrom', '2026-06-01')
            ->set('editEffectiveDateRange', null)
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

        Livewire::test('harvest.prices')
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

        Livewire::test('harvest.prices')
            ->call('editPrice', $price->id)
            ->set('editPricePerKg', '2.5')
            ->set('editEffectiveFrom', '2026-12-31')
            ->set('editEffectiveTo', '2026-01-01')
            ->set('editEffectiveDateRange', null)
            ->call('updatePrice')
            ->assertHasErrors('editEffectiveTo');
    });

    it('clears edit form after updating price', function () {
        $product = Product::factory()->for($this->company)->create();
        $price = HarvestPrice::factory()
            ->for($this->company)
            ->for($product)
            ->create();

        Livewire::test('harvest.prices')
            ->call('editPrice', $price->id)
            ->set('editPricePerKg', '3.5')
            ->set('editEffectiveFrom', '2026-01-01')
            ->set('editEffectiveDateRange', null)
            ->set('editEffectiveTo', null)
            ->call('updatePrice')
            ->assertHasNoErrors();
    });

    it('auto-closes open-ended preceding price when creating a new one', function () {
        $product = Product::factory()->for($this->company)->create();

        $oldPrice = HarvestPrice::factory()
            ->for($this->company)
            ->for($product)
            ->create([
                'effective_from' => '2026-01-01',
                'effective_to' => null,
            ]);

        Livewire::test('harvest.prices')
            ->set('newProductId', $product->id)
            ->set('newPricePerKg', '5.00')
            ->set('newEffectiveFrom', '2026-06-01')
            ->set('newEffectiveTo', null)
            ->call('createPrice')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('harvest_prices', [
            'id' => $oldPrice->id,
        ]);
        $this->assertEquals('2026-05-31', HarvestPrice::find($oldPrice->id)->effective_to->format('Y-m-d'));
    });

    it('auto-closes open-ended preceding price when editing an existing one', function () {
        $product = Product::factory()->for($this->company)->create();

        $oldPrice = HarvestPrice::factory()
            ->for($this->company)
            ->for($product)
            ->create([
                'effective_from' => '2026-01-01',
                'effective_to' => null,
            ]);

        $editedPrice = HarvestPrice::factory()
            ->for($this->company)
            ->for($product)
            ->create([
                'effective_from' => '2026-03-01',
                'effective_to' => '2026-04-30',
            ]);

        Livewire::test('harvest.prices')
            ->call('editPrice', $editedPrice->id)
            ->set('editEffectiveFrom', '2026-06-01')
            ->set('editEffectiveDateRange', null)
            ->set('editEffectiveTo', null)
            ->call('updatePrice')
            ->assertHasNoErrors();

        $this->assertEquals('2026-05-31', HarvestPrice::find($oldPrice->id)->effective_to->format('Y-m-d'));
    });

    it('does not modify prices that already have an effective_to date set', function () {
        $product = Product::factory()->for($this->company)->create();

        $closedPrice = HarvestPrice::factory()
            ->for($this->company)
            ->for($product)
            ->create([
                'effective_from' => '2026-01-01',
                'effective_to' => '2026-03-31',
            ]);

        Livewire::test('harvest.prices')
            ->set('newProductId', $product->id)
            ->set('newPricePerKg', '5.00')
            ->set('newEffectiveFrom', '2026-06-01')
            ->set('newEffectiveTo', null)
            ->call('createPrice')
            ->assertHasNoErrors();

        $this->assertEquals('2026-03-31', HarvestPrice::find($closedPrice->id)->effective_to->format('Y-m-d'));
    });

    it('does not close the record being edited when updating', function () {
        $product = Product::factory()->for($this->company)->create();

        $price = HarvestPrice::factory()
            ->for($this->company)
            ->for($product)
            ->create([
                'effective_from' => '2026-01-01',
                'effective_to' => null,
            ]);

        Livewire::test('harvest.prices')
            ->call('editPrice', $price->id)
            ->set('editEffectiveFrom', '2026-01-01')
            ->set('editEffectiveTo', null)
            ->call('updatePrice')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('harvest_prices', [
            'id' => $price->id,
            'effective_to' => null,
        ]);
    });
});
