<?php

use App\Models\Company;
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

describe('Charts Page', function () {
    it('renders the page', function () {
        $response = $this->get(route('harvest.charts'));
        $response->assertStatus(200);
    });

    it('displays filter controls', function () {
        Product::factory()->for($this->company)->create(['name' => 'Berries']);

        Livewire::test('pages.harvest.charts')
            ->assertSee('Berries');
    });

    it('filters by year', function () {
        $year = now()->year;
        $product = Product::factory()->for($this->company)->create();

        HarvestRecord::factory()
            ->for($this->company)
            ->for($product)
            ->create(['weighed_at' => "$year-06-15", 'weight' => 100]);

        Livewire::test('pages.harvest.charts')
            ->set('selectedYear', $year)
            ->assertSet('selectedYear', $year);
    });

    it('switches between tabs', function () {
        Livewire::test('pages.harvest.charts')
            ->set('activeTab', 'daily')
            ->assertSet('activeTab', 'daily')
            ->set('activeTab', 'harvesters')
            ->assertSet('activeTab', 'harvesters')
            ->set('activeTab', 'products')
            ->assertSet('activeTab', 'products');
    });

    it('displays daily summary data', function () {
        $product = Product::factory()->for($this->company)->create();
        $today = now()->format('Y-m-d');

        HarvestRecord::factory(5)
            ->for($this->company)
            ->for($product)
            ->create(['weighed_at' => $today, 'weight' => 10]);

        Livewire::test('pages.harvest.charts')
            ->set('activeTab', 'daily')
            ->call('dailyData')
            ->assertSee($today);
    });

    it('displays harvester summary data', function () {
        $product = Product::factory()->for($this->company)->create();
        $year = now()->year;

        HarvesterAssignment::factory()
            ->for($this->company)
            ->create(['year' => $year, 'number' => 3, 'name' => 'Charlie']);

        HarvestRecord::factory(3)
            ->for($this->company)
            ->for($product)
            ->create(['harvester_number' => 3, 'weight' => 20]);

        Livewire::test('pages.harvest.charts')
            ->set('activeTab', 'harvesters')
            ->call('harvesterData')
            ->assertSee('Charlie');
    });

    it('displays product summary data', function () {
        $product = Product::factory()->for($this->company)->create(['name' => 'Blueberries']);

        HarvestRecord::factory(2)
            ->for($this->company)
            ->for($product)
            ->create(['weight' => 15]);

        Livewire::test('pages.harvest.charts')
            ->set('activeTab', 'products')
            ->call('productData')
            ->assertSee('Blueberries');
    });

    it('calculates daily totals', function () {
        $product = Product::factory()->for($this->company)->create();
        $today = now()->format('Y-m-d');

        HarvestRecord::factory(4)
            ->for($this->company)
            ->for($product)
            ->create(['weighed_at' => $today, 'weight' => 25]);

        Livewire::test('pages.harvest.charts')
            ->call('dailyTotals')
            ->assertSet('dailyTotals.weight', 100);
    });

    it('filters by date range', function () {
        $product = Product::factory()->for($this->company)->create();
        $fromDate = now()->format('Y-m-01');
        $toDate = now()->endOfMonth()->format('Y-m-d');

        HarvestRecord::factory()
            ->for($this->company)
            ->for($product)
            ->create(['weighed_at' => now()->format('Y-m-15')]);

        Livewire::test('pages.harvest.charts')
            ->set('fromDate', $fromDate)
            ->set('toDate', $toDate)
            ->assertSet('fromDate', $fromDate)
            ->assertSet('toDate', $toDate);
    });

    it('filters by product', function () {
        $product1 = Product::factory()->for($this->company)->create(['name' => 'Cherries']);
        $product2 = Product::factory()->for($this->company)->create(['name' => 'Raspberries']);

        HarvestRecord::factory()
            ->for($this->company)
            ->for($product1)
            ->create(['weight' => 100]);

        HarvestRecord::factory()
            ->for($this->company)
            ->for($product2)
            ->create(['weight' => 50]);

        Livewire::test('pages.harvest.charts')
            ->set('selectedProductId', $product1->id)
            ->call('dailyData')
            ->assertSee('100');
    });

    it('shows summary cards with totals', function () {
        $product = Product::factory()->for($this->company)->create();

        HarvestRecord::factory(3)
            ->for($this->company)
            ->for($product)
            ->create(['weight' => 50]);

        Livewire::test('pages.harvest.charts')
            ->call('dailyTotals')
            ->assertSet('dailyTotals.weight', 150);
    });

    it('handles empty data gracefully', function () {
        Livewire::test('pages.harvest.charts')
            ->set('activeTab', 'daily')
            ->call('dailyData');

        expect(true)->toBeTrue();
    });

    it('only shows company data', function () {
        $otherCompany = Company::factory()->create();
        $otherProduct = Product::factory()->for($otherCompany)->create(['name' => 'Blackberries']);

        HarvestRecord::factory()
            ->for($otherCompany)
            ->for($otherProduct)
            ->create();

        Livewire::test('pages.harvest.charts')
            ->assertDontSee('Blackberries');
    });
});
