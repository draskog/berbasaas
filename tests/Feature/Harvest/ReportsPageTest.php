<?php

use App\Models\Company;
use App\Models\Harvester;
use App\Models\HarvesterAssignment;
use App\Models\HarvestRecord;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->for($this->company)->create();
    $this->actingAs($this->user);
});

describe('Reports Page', function () {
    it('renders the page', function () {
        $response = $this->get(route('harvest.reports'));
        $response->assertStatus(200);
    });

    it('displays filter options', function () {
        Product::factory()->for($this->company)->create(['name' => 'Peaches']);

        Livewire::test('pages.harvest.reports')
            ->assertSee('Peaches');
    });

    it('filters by year', function () {
        $year = now()->year;
        $oldYear = $year - 1;

        $product = Product::factory()->for($this->company)->create();

        HarvestRecord::factory()
            ->for($this->company)
            ->for($product)
            ->create(['weighed_at' => "$year-06-15"]);

        HarvestRecord::factory()
            ->for($this->company)
            ->for($product)
            ->create(['weighed_at' => "$oldYear-06-15"]);

        Livewire::test('pages.harvest.reports')
            ->set('selectedYear', $oldYear)
            ->assertSee("$oldYear-06-15");
    });

    it('filters by date range', function () {
        $product = Product::factory()->for($this->company)->create();

        HarvestRecord::factory()
            ->for($this->company)
            ->for($product)
            ->create(['weighed_at' => now()->format('Y-m-15')]);

        HarvestRecord::factory()
            ->for($this->company)
            ->for($product)
            ->create(['weighed_at' => now()->addMonths(2)->format('Y-m-15')]);

        Livewire::test('pages.harvest.reports')
            ->set('fromDate', now()->format('Y-m-01'))
            ->set('toDate', now()->addMonth()->format('Y-m-30'))
            ->assertSee('15');
    });

    it('switches between tabs', function () {
        Livewire::test('pages.harvest.reports')
            ->set('activeTab', 'daily')
            ->assertSet('activeTab', 'daily')
            ->set('activeTab', 'harvesters')
            ->assertSet('activeTab', 'harvesters')
            ->set('activeTab', 'products')
            ->assertSet('activeTab', 'products');
    });

    it('calculates daily totals', function () {
        $product = Product::factory()->for($this->company)->create();
        $today = now()->format('Y-m-d');

        HarvestRecord::factory(3)
            ->for($this->company)
            ->for($product)
            ->create(['weighed_at' => $today, 'weight' => 10]);

        Livewire::test('pages.harvest.reports')
            ->assertSee('30');
    });

    it('shows harvester names in reports', function () {
        $product = Product::factory()->for($this->company)->create();
        $year = now()->year;

        $harvester = Harvester::factory()->for($this->company)->create(['name' => 'Bob']);
        HarvesterAssignment::factory()
            ->for($this->company)
            ->for($harvester)
            ->create(['year' => $year, 'number' => 5]);

        HarvestRecord::factory()
            ->for($this->company)
            ->for($product)
            ->create(['harvester_number' => 5, 'weight' => 15]);

        Livewire::test('pages.harvest.reports')
            ->set('activeTab', 'harvesters')
            ->assertSee('Bob');
    });

    it('shows product data in products tab', function () {
        $product = Product::factory()->for($this->company)->create(['name' => 'Strawberries']);

        HarvestRecord::factory(2)
            ->for($this->company)
            ->for($product)
            ->create(['weight' => 5]);

        Livewire::test('pages.harvest.reports')
            ->set('activeTab', 'products')
            ->assertSee('Strawberries')
            ->assertSee('10');
    });

    it('filters by product', function () {
        $product1 = Product::factory()->for($this->company)->create(['name' => 'Apples']);
        $product2 = Product::factory()->for($this->company)->create(['name' => 'Pears']);

        HarvestRecord::factory()
            ->for($this->company)
            ->for($product1)
            ->create(['weight' => 100]);

        HarvestRecord::factory()
            ->for($this->company)
            ->for($product2)
            ->create(['weight' => 50]);

        Livewire::test('pages.harvest.reports')
            ->set('selectedProductId', $product1->id)
            ->assertSee('100')
            ->assertDontSee('50');
    });

    it('filters by harvester', function () {
        $product = Product::factory()->for($this->company)->create();

        HarvestRecord::factory()
            ->for($this->company)
            ->for($product)
            ->create(['harvester_number' => 1, 'weight' => 100]);

        HarvestRecord::factory()
            ->for($this->company)
            ->for($product)
            ->create(['harvester_number' => 2, 'weight' => 50]);

        Livewire::test('pages.harvest.reports')
            ->set('selectedHarvesterNumber', 1)
            ->set('activeTab', 'harvesters')
            ->assertSee('100')
            ->assertDontSee('50');
    });

    it('only shows company data', function () {
        $otherCompany = Company::factory()->create();
        $otherProduct = Product::factory()->for($otherCompany)->create(['name' => 'Secret']);

        HarvestRecord::factory()
            ->for($otherCompany)
            ->for($otherProduct)
            ->create();

        Livewire::test('pages.harvest.reports')
            ->assertDontSee('Secret');
    });
});
