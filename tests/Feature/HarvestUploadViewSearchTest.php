<?php

use App\Models\Company;
use App\Models\Harvester;
use App\Models\HarvesterAssignment;
use App\Models\HarvestRecord;
use App\Models\HarvestUpload;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('harvest records can be searched by harvester number', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $product = Product::factory()->for($company)->create();

    $upload = HarvestUpload::factory()
        ->for($company)
        ->for($product)
        ->for($user, 'uploadedBy')
        ->create(['date_from' => now()->startOfYear(), 'date_to' => now()->endOfYear()]);

    $harvester = Harvester::factory()->for($company)->create(['name' => 'Dragica Jančić']);
    HarvesterAssignment::factory()->for($company)->create([
        'harvester_id' => $harvester->id,
        'number' => 1,
        'year' => 2026,
    ]);

    HarvestRecord::create([
        'company_id' => $company->id,
        'upload_id' => $upload->id,
        'product_id' => $product->id,
        'harvester_number' => 1,
        'weight' => 50,
        'tare' => 2,
        'gross' => 52,
        'weighed_at' => now(),
    ]);

    HarvestRecord::create([
        'company_id' => $company->id,
        'upload_id' => $upload->id,
        'product_id' => $product->id,
        'harvester_number' => 2,
        'weight' => 50,
        'tare' => 2,
        'gross' => 52,
        'weighed_at' => now(),
    ]);

    $query = HarvestRecord::where('upload_id', $upload->id)
        ->where('harvester_number', 'like', '%1%');

    expect($query->count())->toBe(1);
});

test('harvest records can be searched by harvester name', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $product = Product::factory()->for($company)->create();

    $upload = HarvestUpload::factory()
        ->for($company)
        ->for($product)
        ->for($user, 'uploadedBy')
        ->create(['date_from' => now()->startOfYear(), 'date_to' => now()->endOfYear()]);

    $harvester = Harvester::factory()->for($company)->create(['name' => 'Dragica Jančić']);
    $harvester2 = Harvester::factory()->for($company)->create(['name' => 'Nada Jović']);

    HarvesterAssignment::factory()->for($company)->create([
        'harvester_id' => $harvester->id,
        'number' => 1,
        'year' => 2026,
    ]);

    HarvesterAssignment::factory()->for($company)->create([
        'harvester_id' => $harvester2->id,
        'number' => 2,
        'year' => 2026,
    ]);

    HarvestRecord::create([
        'company_id' => $company->id,
        'upload_id' => $upload->id,
        'product_id' => $product->id,
        'harvester_number' => 1,
        'weight' => 50,
        'tare' => 2,
        'gross' => 52,
        'weighed_at' => now(),
    ]);

    HarvestRecord::create([
        'company_id' => $company->id,
        'upload_id' => $upload->id,
        'product_id' => $product->id,
        'harvester_number' => 2,
        'weight' => 50,
        'tare' => 2,
        'gross' => 52,
        'weighed_at' => now(),
    ]);

    $year = 2026;
    $search = 'Dragica';

    $query = HarvestRecord::where('upload_id', $upload->id)
        ->where('harvester_number', 'like', "%$search%")
        ->orWhereRaw('EXISTS (
            SELECT 1 FROM harvester_assignments ha
            JOIN harvesters h ON ha.harvester_id = h.id
            WHERE ha.number = harvest_records.harvester_number
            AND ha.company_id = harvest_records.company_id
            AND ha.year = ?
            AND h.name LIKE ?
        )', [$year, "%$search%"]);

    expect($query->count())->toBe(1);
});
