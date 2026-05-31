<?php

use App\Models\Company;
use App\Models\Harvester;
use App\Models\HarvesterAssignment;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->for($this->company)->create();
    $this->actingAs($this->user);
});

describe('Harvesters Page', function () {

    it('renders the page', function () {
        Livewire::test('harvest.harvesters')
            ->assertStatus(200);
    });

    it('displays current year assignments', function () {
        $year = now()->year;
        $harvester = Harvester::factory()->for($this->company)->create(['name' => 'John Doe']);
        HarvesterAssignment::factory()
            ->for($this->company)
            ->for($harvester)
            ->create([
                'year' => $year,
                'number' => 1,
            ]);

        Livewire::test('harvest.harvesters')
            ->assertSee('John Doe')
            ->assertSee('1');
    });

    it('creates new harvester assignment', function () {
        $harvester = Harvester::factory()->for($this->company)->create();

        Livewire::test('harvest.harvesters')
            ->set('newNumber', 42)
            ->set('newHarvesterId', $harvester->id)
            ->call('createAssignment')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('harvester_assignments', [
            'company_id' => $this->company->id,
            'number' => 42,
            'harvester_id' => $harvester->id,
        ]);
    });

    it('validates harvester number is required', function () {
        $harvester = Harvester::factory()->for($this->company)->create();

        Livewire::test('harvest.harvesters')
            ->set('newNumber', null)
            ->set('newHarvesterId', $harvester->id)
            ->call('createAssignment')
            ->assertHasErrors('newNumber');
    });

    it('validates harvester is required', function () {
        Livewire::test('harvest.harvesters')
            ->set('newNumber', 42)
            ->set('newHarvesterId', null)
            ->call('createAssignment')
            ->assertHasErrors('newHarvesterId');
    });

    it('can be used to manage harvester assignments', function () {
        $harvester = Harvester::factory()->for($this->company)->create(['name' => 'Test Harvester']);

        Livewire::test('harvest.harvesters')
            ->set('newNumber', 99)
            ->set('newHarvesterId', $harvester->id)
            ->call('createAssignment')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('harvester_assignments', [
            'number' => 99,
            'harvester_id' => $harvester->id,
        ]);
    });

    it('validates harvester number is at least 1', function () {
        $harvester = Harvester::factory()->for($this->company)->create();

        Livewire::test('harvest.harvesters')
            ->set('newNumber', 0)
            ->set('newHarvesterId', $harvester->id)
            ->call('createAssignment')
            ->assertHasErrors('newNumber');
    });

    it('deletes harvester assignment', function () {
        $assignment = HarvesterAssignment::factory()
            ->for($this->company)
            ->create(['year' => now()->year]);

        Livewire::test('harvest.harvesters')
            ->call('confirmDeleteAssignment', $assignment->id)
            ->call('deleteAssignment')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('harvester_assignments', [
            'id' => $assignment->id,
        ]);
    });

    it('confirms before deleting assignment', function () {
        $assignment = HarvesterAssignment::factory()
            ->for($this->company)
            ->create(['year' => now()->year]);

        Livewire::test('harvest.harvesters')
            ->call('confirmDeleteAssignment', $assignment->id)
            ->assertSet('deletingAssignmentId', $assignment->id);
    });

    it('resets form after creating assignment', function () {
        $harvester = Harvester::factory()->for($this->company)->create();

        Livewire::test('harvest.harvesters')
            ->set('newNumber', 42)
            ->set('newHarvesterId', $harvester->id)
            ->call('createAssignment')
            ->assertSet('newNumber', null)
            ->assertSet('newHarvesterId', null);
    });

    it('only shows assignments for authenticated user company', function () {
        $otherCompany = Company::factory()->create();
        $otherHarvester = Harvester::factory()->for($otherCompany)->create(['name' => 'OtherCompanyHarvester']);
        HarvesterAssignment::factory()
            ->for($otherCompany)
            ->for($otherHarvester)
            ->create(['year' => now()->year, 'number' => 99]);

        Livewire::test('harvest.harvesters')
            ->assertDontSee('OtherCompanyHarvester');
    });
});
