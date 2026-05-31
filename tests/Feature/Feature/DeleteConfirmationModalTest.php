<?php

use App\Models\Company;
use App\Models\HarvesterAssignment;
use App\Models\HarvestPrice;
use App\Models\HarvestUpload;
use App\Models\Product;
use App\Models\User;

test('delete confirmation modal appears on harvesters page', function () {
    $company = Company::create(['name' => 'Test Company']);
    $user = User::factory()->create(['company_id' => $company->id]);

    HarvesterAssignment::create([
        'company_id' => $company->id,
        'year' => now()->year,
        'number' => 1,
        'name' => 'Test Harvester',
    ]);

    $response = $this->actingAs($user)
        ->get(route('harvesters.index'));

    $response->assertStatus(200);
    $response->assertSee('confirm-delete-assignment');
    $response->assertSee($this->trans('Delete Assignment'));
    $response->assertSee($this->trans('Are you sure you want to delete this harvester assignment? This cannot be undone.'));
});

test('delete confirmation modal appears on prices page', function () {
    $company = Company::create(['name' => 'Test Company']);
    $user = User::factory()->create(['company_id' => $company->id]);
    $product = Product::create([
        'company_id' => $company->id,
        'name' => 'Test Product',
        'slug' => 'test-product',
        'active' => true,
    ]);

    HarvestPrice::create([
        'company_id' => $company->id,
        'product_id' => $product->id,
        'price_per_kg' => 10.5,
        'effective_from' => now(),
    ]);

    $response = $this->actingAs($user)
        ->get(route('harvest.prices'));

    $response->assertStatus(200);
    $response->assertSee('confirm-delete-price');
    $response->assertSee($this->trans('Delete Price'));
    $response->assertSee($this->trans('Are you sure you want to delete this price? This cannot be undone.'));
});

test('delete confirmation modal appears on upload page', function () {
    $company = Company::create(['name' => 'Test Company']);
    $user = User::factory()->create(['company_id' => $company->id]);
    $product = Product::create([
        'company_id' => $company->id,
        'name' => 'Test Product',
        'slug' => 'test-product',
        'active' => true,
    ]);

    HarvestUpload::create([
        'company_id' => $company->id,
        'product_id' => $product->id,
        'uploaded_by' => $user->id,
        'original_filename' => 'test.csv',
        'record_count' => 10,
        'date_from' => now(),
        'date_to' => now(),
    ]);

    $response = $this->actingAs($user)
        ->get(route('harvest.upload'));

    $response->assertStatus(200);
    $response->assertSee('confirm-delete-upload');
    $response->assertSee($this->trans('Delete Upload'));
    $response->assertSee($this->trans('Are you sure you want to delete this upload? This cannot be undone.'));
});
