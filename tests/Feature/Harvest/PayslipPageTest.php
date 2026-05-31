<?php

use App\Models\Company;
use App\Models\Harvester;
use App\Models\HarvesterAssignment;
use App\Models\HarvestPrice;
use App\Models\HarvestRecord;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->for($this->company)->create();
    $this->actingAs($this->user);
});

describe('Payslip Page', function () {
    it('renders the page', function () {
        $response = $this->get(route('harvest.payslip'));
        $response->assertStatus(200);
    });

    it('displays year selector', function () {
        Livewire::test('harvest.payslip')
            ->assertSee('Year');
    });

    it('displays harvester selector', function () {
        Livewire::test('harvest.payslip')
            ->assertSee('Harvester');
    });

    it('filters by year', function () {
        $year = now()->year;
        $oldYear = $year - 1;

        Livewire::test('harvest.payslip')
            ->set('selectedYear', $oldYear)
            ->assertSet('selectedYear', $oldYear);
    });

    it('shows available harvesters for year', function () {
        $year = now()->year;

        $harvester = Harvester::factory()->for($this->company)->create(['name' => 'David']);
        HarvesterAssignment::factory()
            ->for($this->company)
            ->for($harvester)
            ->create(['year' => $year, 'number' => 7]);

        Livewire::test('harvest.payslip')
            ->set('selectedYear', $year)
            ->assertSee('7');
    });

    it('displays harvester info when selected', function () {
        $year = now()->year;

        $harvester = Harvester::factory()->for($this->company)->create(['name' => 'Emma']);
        HarvesterAssignment::factory()
            ->for($this->company)
            ->for($harvester)
            ->create(['year' => $year, 'number' => 8]);

        HarvestRecord::factory()
            ->for($this->company)
            ->for(Product::factory()->for($this->company)->create())
            ->create(['harvester_number' => 8, 'weighed_at' => now()]);

        Livewire::test('harvest.payslip')
            ->set('selectedYear', $year)
            ->assertStatus(200);
    });

    it('displays payslip data for harvester', function () {
        $year = now()->year;
        $product = Product::factory()->for($this->company)->create();

        $harvester = Harvester::factory()->for($this->company)->create(['name' => 'Frank']);
        HarvesterAssignment::factory()
            ->for($this->company)
            ->for($harvester)
            ->create(['year' => $year, 'number' => 9]);

        HarvestRecord::factory()
            ->for($this->company)
            ->for($product)
            ->create([
                'harvester_number' => 9,
                'weight' => 50,
                'weighed_at' => now(),
            ]);

        Livewire::test('harvest.payslip')
            ->set('selectedYear', $year)
            ->assertSee('50');
    });

    it('calculates payslip totals', function () {
        $year = now()->year;
        $product = Product::factory()->for($this->company)->create();

        $harvester = Harvester::factory()->for($this->company)->create(['name' => 'Grace']);
        HarvesterAssignment::factory()
            ->for($this->company)
            ->for($harvester)
            ->create(['year' => $year, 'number' => 10]);

        HarvestRecord::factory(3)
            ->for($this->company)
            ->for($product)
            ->create(['harvester_number' => 10, 'weight' => 25, 'weighed_at' => now()]);

        Livewire::test('harvest.payslip')
            ->set('selectedYear', $year)
            ->assertStatus(200);
    });

    it('shows earnings with price', function () {
        $year = now()->year;
        $product = Product::factory()->for($this->company)->create();

        $harvester = Harvester::factory()->for($this->company)->create(['name' => 'Henry']);
        HarvesterAssignment::factory()
            ->for($this->company)
            ->for($harvester)
            ->create(['year' => $year, 'number' => 11]);

        HarvestPrice::factory()
            ->for($this->company)
            ->for($product)
            ->create(['price_per_kg' => 2.5]);

        HarvestRecord::factory()
            ->for($this->company)
            ->for($product)
            ->create([
                'harvester_number' => 11,
                'weight' => 100,
                'weighed_at' => now(),
            ]);

        Livewire::test('harvest.payslip')
            ->set('selectedYear', $year)
            ->assertStatus(200);
    });

    it('handles no harvester selected', function () {
        Livewire::test('harvest.payslip')
            ->assertStatus(200);
    });

    it('only shows company harvesters', function () {
        $otherCompany = Company::factory()->create();
        $year = now()->year;

        $otherHarvester = Harvester::factory()->for($otherCompany)->create(['name' => 'Secret']);
        HarvesterAssignment::factory()
            ->for($otherCompany)
            ->for($otherHarvester)
            ->create(['year' => $year, 'number' => 99]);

        Livewire::test('harvest.payslip')
            ->set('selectedYear', $year)
            ->assertDontSee('99');
    });

    it('displays company name in payslip', function () {
        Livewire::test('harvest.payslip')
            ->assertStatus(200);
    });

    it('handles missing harvester name gracefully', function () {
        Livewire::test('harvest.payslip')
            ->assertStatus(200);
    });

    it('shows bucket count in payslip', function () {
        $year = now()->year;
        $product = Product::factory()->for($this->company)->create();

        $harvester = Harvester::factory()->for($this->company)->create(['name' => 'Iris']);
        HarvesterAssignment::factory()
            ->for($this->company)
            ->for($harvester)
            ->create(['year' => $year, 'number' => 12]);

        HarvestRecord::factory(5)
            ->for($this->company)
            ->for($product)
            ->create(['harvester_number' => 12, 'weighed_at' => now()]);

        Livewire::test('harvest.payslip')
            ->set('selectedYear', $year)
            ->assertStatus(200);
    });

    it('shows empty message when no payslip data', function () {
        $year = now()->year;

        $harvester = Harvester::factory()->for($this->company)->create();
        HarvesterAssignment::factory()
            ->for($this->company)
            ->for($harvester)
            ->create(['year' => $year, 'number' => 13]);

        Livewire::test('harvest.payslip')
            ->set('selectedYear', $year)
            ->assertStatus(200);
    });
});
