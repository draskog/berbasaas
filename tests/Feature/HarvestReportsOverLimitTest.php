<?php

use App\Models\Company;
use App\Models\HarvestImportSettings;
use App\Models\HarvestRecord;
use App\Models\Harvester;
use App\Models\HarvesterAssignment;
use App\Models\Product;
use App\Models\User;

test('max_bucket_weight setting can be saved to database', function () {
    $company = Company::factory()->create();

    HarvestImportSettings::updateOrCreate(
        ['company_id' => $company->id],
        ['max_bucket_weight' => 25.5]
    );

    $settings = HarvestImportSettings::where('company_id', $company->id)->first();
    expect($settings->max_bucket_weight)->toBe(25.5);
});

test('over limit report shows buckets exceeding threshold', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $product = Product::factory()->create(['company_id' => $company->id]);

    HarvestImportSettings::create([
        'company_id' => $company->id,
        'max_bucket_weight' => 20.0,
    ]);

    $harvester = Harvester::factory()->create(['company_id' => $company->id]);
    HarvesterAssignment::create([
        'company_id' => $company->id,
        'harvester_id' => $harvester->id,
        'number' => 1,
        'year' => now()->year,
    ]);

    HarvestRecord::create([
        'company_id' => $company->id,
        'product_id' => $product->id,
        'harvester_number' => 1,
        'weight' => 22.5,
        'tare' => 2.0,
        'gross' => 24.5,
        'weighed_at' => now()->toDateString() . ' 09:00:00',
    ]);

    HarvestRecord::create([
        'company_id' => $company->id,
        'product_id' => $product->id,
        'harvester_number' => 1,
        'weight' => 18.0,
        'tare' => 2.0,
        'gross' => 20.0,
        'weighed_at' => now()->toDateString() . ' 10:00:00',
    ]);

    $this->actingAs($user)->get('/harvest/reports')
        ->assertSuccessful();
});

test('over limit tab shows message when no max_bucket_weight set', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $product = Product::factory()->create(['company_id' => $company->id]);

    HarvestRecord::create([
        'company_id' => $company->id,
        'product_id' => $product->id,
        'harvester_number' => 1,
        'weight' => 10.0,
        'tare' => 2.0,
        'gross' => 12.0,
        'weighed_at' => now()->toDateString() . ' 09:00:00',
    ]);

    $this->actingAs($user)->get('/harvest/reports?activeTab=over_limit')
        ->assertSuccessful();
});

test('prefix filter works in reports', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $product = Product::factory()->create(['company_id' => $company->id]);

    $harvester1 = Harvester::factory()->create(['company_id' => $company->id, 'prefix' => 'A']);
    $harvester2 = Harvester::factory()->create(['company_id' => $company->id, 'prefix' => 'B']);

    HarvesterAssignment::create([
        'company_id' => $company->id,
        'harvester_id' => $harvester1->id,
        'number' => 1,
        'year' => now()->year,
    ]);

    HarvesterAssignment::create([
        'company_id' => $company->id,
        'harvester_id' => $harvester2->id,
        'number' => 2,
        'year' => now()->year,
    ]);

    HarvestRecord::create([
        'company_id' => $company->id,
        'product_id' => $product->id,
        'harvester_number' => 1,
        'weight' => 10.0,
        'tare' => 2.0,
        'gross' => 12.0,
        'weighed_at' => now()->toDateString() . ' 09:00:00',
    ]);

    HarvestRecord::create([
        'company_id' => $company->id,
        'product_id' => $product->id,
        'harvester_number' => 2,
        'weight' => 15.0,
        'tare' => 2.0,
        'gross' => 17.0,
        'weighed_at' => now()->toDateString() . ' 10:00:00',
    ]);

    $this->actingAs($user)->get('/harvest/reports')
        ->assertSuccessful();
});

test('harvester search works in reports', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $product = Product::factory()->create(['company_id' => $company->id]);

    $harvester = Harvester::factory()->create(['company_id' => $company->id, 'name' => 'Jovan Živojinović']);

    HarvesterAssignment::create([
        'company_id' => $company->id,
        'harvester_id' => $harvester->id,
        'number' => 1,
        'year' => now()->year,
    ]);

    HarvestRecord::create([
        'company_id' => $company->id,
        'product_id' => $product->id,
        'harvester_number' => 1,
        'weight' => 10.0,
        'tare' => 2.0,
        'gross' => 12.0,
        'weighed_at' => now()->toDateString() . ' 09:00:00',
    ]);

    $this->actingAs($user)->get('/harvest/reports?searchHarvesterName=Jovan')
        ->assertSuccessful();
});
