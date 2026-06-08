<?php

use App\Models\Company;
use App\Models\Harvester;
use App\Models\HarvesterAssignment;
use App\Models\HarvestImportSettings;
use App\Models\HarvestRecord;
use App\Models\Product;
use App\Models\User;

test('over limit tab displays 2025 data when year is selected', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $product = Product::factory()->create(['company_id' => $company->id]);

    HarvestImportSettings::create([
        'company_id' => $company->id,
        'max_bucket_weight' => 20.0,
    ]);

    // Create harvester for 2025
    $harvester = Harvester::factory()->create(['company_id' => $company->id, 'name' => 'Test Berač 2025']);
    HarvesterAssignment::create([
        'company_id' => $company->id,
        'harvester_id' => $harvester->id,
        'number' => 98,
        'year' => 2025,
    ]);

    // Create over-limit buckets for 2025
    for ($i = 0; $i < 5; $i++) {
        HarvestRecord::create([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'harvester_number' => 98,
            'weight' => 22.5 + $i,
            'tare' => 2.0,
            'gross' => 24.5 + $i,
            'weighed_at' => '2025-06-08 '.sprintf('%02d', $i).':00:00',
        ]);
    }

    // Create normal buckets for 2025
    for ($i = 0; $i < 3; $i++) {
        HarvestRecord::create([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'harvester_number' => 98,
            'weight' => 15.0 + $i,
            'tare' => 2.0,
            'gross' => 17.0 + $i,
            'weighed_at' => '2025-06-08 '.sprintf('%02d', 10 + $i).':00:00',
        ]);
    }

    // Test with URL query for 2025 and over_limit tab
    $response = $this->actingAs($user)->get(
        '/harvest/reports?activeTab=over_limit&selectedYear=2025'
    );

    $response->assertSuccessful();
    $response->assertSeeText('Preko limita');
    $response->assertSeeText('5 od 8');
    $response->assertSeeText('Test Berač 2025');
});
