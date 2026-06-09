<?php

use App\Models\Company;
use App\Models\Harvester;
use App\Models\HarvesterAssignment;
use App\Models\HarvestRecord;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

test('top harvester link navigates to harvest reports with harvesters tab', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSee(route('harvest.reports', ['tab' => 'harvesters']));
});

test('over limit link navigates to harvest reports with over_limit tab', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSee(route('harvest.reports', ['tab' => 'over_limit']));
});

test('top harvester shows daily data when last harvest is within 5 days', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $harvester = Harvester::factory()->create(['company_id' => $company->id]);

    $year = now()->year;
    $assignment = HarvesterAssignment::factory()->create([
        'company_id' => $company->id,
        'harvester_id' => $harvester->id,
        'year' => $year,
    ]);

    // Create harvest data for today with 100 kg
    $today = now()->startOfDay();
    HarvestRecord::factory()->create([
        'company_id' => $company->id,
        'harvester_number' => $assignment->number,
        'weight' => 100,
        'weighed_at' => $today,
    ]);

    // Create harvest data for 10 days ago with 50 kg (old data)
    $tenDaysAgo = now()->subDays(10)->startOfDay();
    HarvestRecord::factory()->create([
        'company_id' => $company->id,
        'harvester_number' => $assignment->number,
        'weight' => 50,
        'weighed_at' => $tenDaysAgo,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSee($harvester->name);
});

test('top harvester shows yearly data when last harvest is more than 5 days ago', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $harvester1 = Harvester::factory()->create(['company_id' => $company->id]);
    $harvester2 = Harvester::factory()->create(['company_id' => $company->id]);

    $year = now()->year;
    $assignment1 = HarvesterAssignment::factory()->create([
        'company_id' => $company->id,
        'harvester_id' => $harvester1->id,
        'year' => $year,
    ]);
    $assignment2 = HarvesterAssignment::factory()->create([
        'company_id' => $company->id,
        'harvester_id' => $harvester2->id,
        'year' => $year,
    ]);

    // Create harvest data 10 days ago with 100 kg for harvester 1
    $tenDaysAgo = now()->subDays(10)->startOfDay();
    HarvestRecord::factory()->create([
        'company_id' => $company->id,
        'harvester_number' => $assignment1->number,
        'weight' => 100,
        'weighed_at' => $tenDaysAgo,
    ]);

    // Create harvest data for this year with 150 kg for harvester 2
    HarvestRecord::factory()->create([
        'company_id' => $company->id,
        'harvester_number' => $assignment2->number,
        'weight' => 150,
        'weighed_at' => now()->subMonths(3)->startOfDay(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSee($harvester2->name);
});
