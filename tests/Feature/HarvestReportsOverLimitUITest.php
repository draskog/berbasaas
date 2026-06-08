<?php

use App\Models\Company;
use App\Models\Harvester;
use App\Models\HarvesterAssignment;
use App\Models\HarvestImportSettings;
use App\Models\HarvestRecord;
use App\Models\Product;
use App\Models\User;

test('over limit tab loads with data', function () {
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

    // Kreiraj gajbice koje prelaze limit
    for ($i = 0; $i < 5; $i++) {
        HarvestRecord::create([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'harvester_number' => 1,
            'weight' => 22.5 + $i,
            'tare' => 2.0,
            'gross' => 24.5 + $i,
            'weighed_at' => now()->addHours($i)->toDateTimeString(),
        ]);
    }

    // Kreiraj normalne gajbice
    for ($i = 0; $i < 3; $i++) {
        HarvestRecord::create([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'harvester_number' => 1,
            'weight' => 15.0 + $i,
            'tare' => 2.0,
            'gross' => 17.0 + $i,
            'weighed_at' => now()->addHours(10 + $i)->toDateTimeString(),
        ]);
    }

    $response = $this->actingAs($user)
        ->get('/harvest/reports?activeTab=over_limit');

    $response->assertSuccessful();
    $response->assertSeeText('Preko limita');
    $response->assertSeeText('Gajbice preko limita');
    $response->assertSeeText('Avg težina preko');
    $response->assertSeeText('Avg težina');
});
