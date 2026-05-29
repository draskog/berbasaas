<?php

use App\Models\Company;
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
        $response = $this->get(route('harvest.harvesters'));
        $response->assertStatus(200);
        $response->assertViewIs('components.pages.harvest.harvesters');
    });

    it('displays current year assignments', function () {
        $year = now()->year;
        HarvesterAssignment::factory()
            ->for($this->company)
            ->create([
                'year' => $year,
                'number' => 1,
                'name' => 'John Doe',
            ]);

        Livewire::test('pages.harvest.harvesters')
            ->assertSee('John Doe')
            ->assertSee('1');
    });

    it('filters assignments by year', function () {
        $year = now()->year;
        $oldYear = $year - 1;

        HarvesterAssignment::factory()
            ->for($this->company)
            ->create(['year' => $year, 'number' => 1, 'name' => 'Current Year']);

        HarvesterAssignment::factory()
            ->for($this->company)
            ->create(['year' => $oldYear, 'number' => 2, 'name' => 'Old Year']);

        Livewire::test('pages.harvest.harvesters')
            ->set('selectedYear', $oldYear)
            ->assertSee('Old Year')
            ->assertDontSee('Current Year');
    });

    it('creates new harvester assignment', function () {
        Livewire::test('pages.harvest.harvesters')
            ->set('newNumber', 42)
            ->set('newName', 'Alice Johnson')
            ->call('createAssignment')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('harvester_assignments', [
            'company_id' => $this->company->id,
            'number' => 42,
            'name' => 'Alice Johnson',
        ]);
    });

    it('validates harvester number is required', function () {
        Livewire::test('pages.harvest.harvesters')
            ->set('newNumber', null)
            ->set('newName', 'Alice Johnson')
            ->call('createAssignment')
            ->assertHasErrors('newNumber');
    });

    it('validates harvester name is required', function () {
        Livewire::test('pages.harvest.harvesters')
            ->set('newNumber', 42)
            ->set('newName', null)
            ->call('createAssignment')
            ->assertHasErrors('newName');
    });

    it('validates harvester number is integer', function () {
        Livewire::test('pages.harvest.harvesters')
            ->set('newNumber', 'not-a-number')
            ->set('newName', 'Alice Johnson')
            ->call('createAssignment')
            ->assertHasErrors('newNumber');
    });

    it('validates harvester number is at least 1', function () {
        Livewire::test('pages.harvest.harvesters')
            ->set('newNumber', 0)
            ->set('newName', 'Alice Johnson')
            ->call('createAssignment')
            ->assertHasErrors('newNumber');
    });

    it('validates harvester name max length', function () {
        Livewire::test('pages.harvest.harvesters')
            ->set('newNumber', 42)
            ->set('newName', str_repeat('a', 256))
            ->call('createAssignment')
            ->assertHasErrors('newName');
    });

    it('deletes harvester assignment', function () {
        $assignment = HarvesterAssignment::factory()
            ->for($this->company)
            ->create(['year' => now()->year]);

        Livewire::test('pages.harvest.harvesters')
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

        Livewire::test('pages.harvest.harvesters')
            ->call('confirmDeleteAssignment', $assignment->id)
            ->assertSet('deletingAssignmentId', $assignment->id);
    });

    it('resets form after creating assignment', function () {
        Livewire::test('pages.harvest.harvesters')
            ->set('newNumber', 42)
            ->set('newName', 'Alice Johnson')
            ->call('createAssignment')
            ->assertSet('newNumber', null)
            ->assertSet('newName', null);
    });

    it('only shows assignments for authenticated user company', function () {
        $otherCompany = Company::factory()->create();
        HarvesterAssignment::factory()
            ->for($otherCompany)
            ->create(['year' => now()->year, 'number' => 99]);

        Livewire::test('pages.harvest.harvesters')
            ->assertDontSee('99');
    });
});
