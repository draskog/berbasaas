<?php

use App\Models\Company;
use App\Models\HarvesterAssignment;
use App\Models\User;
use Livewire\Livewire;

test('default harvest reports tab is daily', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    HarvesterAssignment::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($user)
        ->test('harvest.reports')
        ->assertSet('activeTab', 'daily');
});
