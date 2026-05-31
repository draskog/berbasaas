<?php

use App\Models\Company;
use App\Models\Harvester;
use App\Models\User;
use Livewire\Livewire;

test('add harvester modal closes after submission', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('pages.harvest.harvesters')
        ->set('showCreateHarvesterModal', true)
        ->set('newHarvesterName', 'Test Harvester')
        ->set('newHarvesterPrefix', 'TST')
        ->call('createHarvester')
        ->assertSet('showCreateHarvesterModal', false)
        ->assertSet('newHarvesterName', '')
        ->assertSet('newHarvesterPrefix', '');

    expect(Harvester::where('name', 'Test Harvester')->exists())->toBeTrue();
});

test('add assignment modal closes after submission', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $harvester = Harvester::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('pages.harvest.harvesters')
        ->set('showCreateAssignmentModal', true)
        ->set('newNumber', 1)
        ->set('newHarvesterId', $harvester->id)
        ->call('createAssignment')
        ->assertSet('showCreateAssignmentModal', false)
        ->assertSet('newNumber', null)
        ->assertSet('newHarvesterId', null);
});
