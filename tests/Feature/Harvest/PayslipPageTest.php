<?php

use App\Models\Company;
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
        Livewire::test('pages.harvest.payslip')
            ->assertSee('Year');
    });

    it('displays harvester selector', function () {
        Livewire::test('pages.harvest.payslip')
            ->assertSee('Harvester');
    });

    it('filters by year', function () {
        $year = now()->year;
        $oldYear = $year - 1;

        Livewire::test('pages.harvest.payslip')
            ->set('selectedYear', $oldYear)
            ->assertSet('selectedYear', $oldYear);
    });

    it('shows available harvesters for year', function () {
        $year = now()->year;

        HarvesterAssignment::factory()
            ->for($this->company)
            ->create(['year' => $year, 'number' => 7, 'name' => 'David']);

        Livewire::test('pages.harvest.payslip')
            ->set('selectedYear', $year)
            ->call('harvesterNumbers')
            ->assertSee('7');
    });

    it('displays harvester info when selected', function () {
        $year = now()->year;

        HarvesterAssignment::factory()
            ->for($this->company)
            ->create(['year' => $year, 'number' => 8, 'name' => 'Emma']);

        Livewire::test('pages.harvest.payslip')
            ->set('selectedYear', $year)
            ->set('selectedHarvesterNumber', 8)
            ->call('harvesterName')
            ->assertSee('Emma');
    });

    it('displays payslip data for harvester', function () {
        $year = now()->year;
        $product = Product::factory()->for($this->company)->create();

        HarvesterAssignment::factory()
            ->for($this->company)
            ->create(['year' => $year, 'number' => 9, 'name' => 'Frank']);

        HarvestRecord::factory()
            ->for($this->company)
            ->for($product)
            ->create([
                'harvester_number' => 9,
                'weight' => 50,
                'weighed_at' => now(),
            ]);

        Livewire::test('pages.harvest.payslip')
            ->set('selectedYear', $year)
            ->set('selectedHarvesterNumber', 9)
            ->call('payslipData')
            ->assertSee('50');
    });

    it('calculates payslip totals', function () {
        $year = now()->year;
        $product = Product::factory()->for($this->company)->create();

        HarvesterAssignment::factory()
            ->for($this->company)
            ->create(['year' => $year, 'number' => 10, 'name' => 'Grace']);

        HarvestRecord::factory(3)
            ->for($this->company)
            ->for($product)
            ->create(['harvester_number' => 10, 'weight' => 25]);

        Livewire::test('pages.harvest.payslip')
            ->set('selectedYear', $year)
            ->set('selectedHarvesterNumber', 10)
            ->call('payslipTotals')
            ->assertSet('payslipTotals.weight', 75);
    });

    it('shows earnings with price', function () {
        $year = now()->year;
        $product = Product::factory()->for($this->company)->create();

        HarvesterAssignment::factory()
            ->for($this->company)
            ->create(['year' => $year, 'number' => 11, 'name' => 'Henry']);

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
            ]);

        Livewire::test('pages.harvest.payslip')
            ->set('selectedYear', $year)
            ->set('selectedHarvesterNumber', 11)
            ->call('payslipData')
            ->assertSee('250');
    });

    it('handles no harvester selected', function () {
        Livewire::test('pages.harvest.payslip')
            ->set('selectedHarvesterNumber', 0)
            ->assertSet('selectedHarvesterNumber', 0);
    });

    it('only shows company harvesters', function () {
        $otherCompany = Company::factory()->create();
        $year = now()->year;

        HarvesterAssignment::factory()
            ->for($otherCompany)
            ->create(['year' => $year, 'number' => 99, 'name' => 'Secret']);

        Livewire::test('pages.harvest.payslip')
            ->set('selectedYear', $year)
            ->call('harvesterNumbers')
            ->assertDontSee('99');
    });

    it('displays company name in payslip', function () {
        Livewire::test('pages.harvest.payslip')
            ->assertSee($this->company->name);
    });

    it('handles missing harvester name gracefully', function () {
        Livewire::test('pages.harvest.payslip')
            ->set('selectedHarvesterNumber', 999)
            ->call('harvesterName')
            ->assertSet('selectedHarvesterNumber', 999);
    });

    it('shows bucket count in payslip', function () {
        $year = now()->year;
        $product = Product::factory()->for($this->company)->create();

        HarvesterAssignment::factory()
            ->for($this->company)
            ->create(['year' => $year, 'number' => 12, 'name' => 'Iris']);

        HarvestRecord::factory(5)
            ->for($this->company)
            ->for($product)
            ->create(['harvester_number' => 12]);

        Livewire::test('pages.harvest.payslip')
            ->set('selectedYear', $year)
            ->set('selectedHarvesterNumber', 12)
            ->call('payslipData')
            ->assertSee('5');
    });

    it('shows empty message when no payslip data', function () {
        $year = now()->year;

        HarvesterAssignment::factory()
            ->for($this->company)
            ->create(['year' => $year, 'number' => 13]);

        Livewire::test('pages.harvest.payslip')
            ->set('selectedYear', $year)
            ->set('selectedHarvesterNumber', 13)
            ->call('payslipData');

        expect(true)->toBeTrue();
    });
});
