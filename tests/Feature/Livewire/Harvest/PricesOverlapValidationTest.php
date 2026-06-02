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

describe('Prices Overlap Validation', function () {
    it('prevents creating a price that overlaps with existing price', function () {
        $product = Product::factory()->for($this->company)->create();

        // Create first price: 02.06.2026 to 23.06.2026
        HarvestPrice::factory()
            ->for($this->company)
            ->for($product)
            ->create([
                'effective_from' => '2026-06-02',
                'effective_to' => '2026-06-23',
            ]);

        // Try to create overlapping price: 10.06.2026 to 30.06.2026
        Livewire::test('harvest.prices')
            ->set('newProductId', $product->id)
            ->set('newPricePerKg', '100')
            ->set('newEffectiveDateRange', [
                'start' => '2026-06-10',
                'end' => '2026-06-30',
            ])
            ->call('createPrice')
            ->assertHasErrors('newEffectiveFrom');
    });

    it('allows creating a price that overlaps with open-ended price (will auto-close it)', function () {
        $product = Product::factory()->for($this->company)->create();

        // Create open-ended price: 22.06.2026 to current
        $openPrice = HarvestPrice::factory()
            ->for($this->company)
            ->for($product)
            ->create([
                'effective_from' => '2026-06-22',
                'effective_to' => null,
            ]);

        // Creating overlapping price is allowed - open-ended prices are auto-closed
        Livewire::test('harvest.prices')
            ->set('newProductId', $product->id)
            ->set('newPricePerKg', '100')
            ->set('newEffectiveDateRange', [
                'start' => '2026-06-02',
                'end' => '2026-06-23',
            ])
            ->call('createPrice')
            ->assertHasNoErrors();
    });

    it('allows creating a price that starts exactly when another ends', function () {
        $product = Product::factory()->for($this->company)->create();

        HarvestPrice::factory()
            ->for($this->company)
            ->for($product)
            ->create([
                'effective_from' => '2026-06-02',
                'effective_to' => '2026-06-23',
            ]);

        // Create price starting the day after the previous one ends
        Livewire::test('harvest.prices')
            ->set('newProductId', $product->id)
            ->set('newPricePerKg', '100')
            ->set('newEffectiveDateRange', [
                'start' => '2026-06-24',
                'end' => '2026-06-24',
            ])
            ->call('createPrice')
            ->assertHasNoErrors();
    });

    it('allows creating a price before another without overlap', function () {
        $product = Product::factory()->for($this->company)->create();

        HarvestPrice::factory()
            ->for($this->company)
            ->for($product)
            ->create([
                'effective_from' => '2026-06-02',
                'effective_to' => '2026-06-23',
            ]);

        Livewire::test('harvest.prices')
            ->set('newProductId', $product->id)
            ->set('newPricePerKg', '100')
            ->set('newEffectiveDateRange', [
                'start' => '2026-05-01',
                'end' => '2026-06-01',
            ])
            ->call('createPrice')
            ->assertHasNoErrors();
    });

    it('allows different products to have overlapping prices', function () {
        $product1 = Product::factory()->for($this->company)->create();
        $product2 = Product::factory()->for($this->company)->create();

        HarvestPrice::factory()
            ->for($this->company)
            ->for($product1)
            ->create([
                'effective_from' => '2026-06-02',
                'effective_to' => '2026-06-23',
            ]);

        Livewire::test('harvest.prices')
            ->set('newProductId', $product2->id)
            ->set('newPricePerKg', '100')
            ->set('newEffectiveDateRange', [
                'start' => '2026-06-10',
                'end' => '2026-06-30',
            ])
            ->call('createPrice')
            ->assertHasNoErrors();
    });

    it('allows updating a price without creating overlap with itself', function () {
        $product = Product::factory()->for($this->company)->create();

        $price = HarvestPrice::factory()
            ->for($this->company)
            ->for($product)
            ->create([
                'effective_from' => '2026-06-02',
                'effective_to' => '2026-06-23',
            ]);

        Livewire::test('harvest.prices')
            ->call('editPrice', $price->id)
            ->set('editPricePerKg', '150')
            ->set('editEffectiveDateRange', [
                'start' => '2026-06-02',
                'end' => '2026-06-23',
            ])
            ->call('updatePrice')
            ->assertHasNoErrors();
    });

    it('prevents updating a price to create overlap with another', function () {
        $product = Product::factory()->for($this->company)->create();

        $price1 = HarvestPrice::factory()
            ->for($this->company)
            ->for($product)
            ->create([
                'effective_from' => '2026-06-02',
                'effective_to' => '2026-06-15',
            ]);

        HarvestPrice::factory()
            ->for($this->company)
            ->for($product)
            ->create([
                'effective_from' => '2026-06-20',
                'effective_to' => '2026-06-30',
            ]);

        // Try to extend first price to overlap with second
        Livewire::test('harvest.prices')
            ->call('editPrice', $price1->id)
            ->set('editPricePerKg', '150')
            ->set('editEffectiveDateRange', [
                'start' => '2026-06-02',
                'end' => '2026-06-25',
            ])
            ->call('updatePrice')
            ->assertHasErrors('editEffectiveFrom');
    });

    it('only validates overlap within the same company', function () {
        $product1 = Product::factory()->for($this->company)->create();
        $otherCompany = Company::factory()->create();
        $product2 = Product::factory()->for($otherCompany)->create();

        HarvestPrice::factory()
            ->for($otherCompany)
            ->for($product2)
            ->create([
                'effective_from' => '2026-06-02',
                'effective_to' => '2026-06-23',
            ]);

        // Different company can have overlapping dates for similar product
        Livewire::test('harvest.prices')
            ->set('newProductId', $product1->id)
            ->set('newPricePerKg', '100')
            ->set('newEffectiveDateRange', [
                'start' => '2026-06-10',
                'end' => '2026-06-30',
            ])
            ->call('createPrice')
            ->assertHasNoErrors();
    });
});
