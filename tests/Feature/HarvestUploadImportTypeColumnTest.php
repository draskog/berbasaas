<?php

use App\Enums\ImportType;
use App\Models\Company;
use App\Models\HarvestUpload;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->for($this->company)->create();
    $this->actingAs($this->user);
});

it('displays import type column with scale_csv icon', function () {
    $product = Product::factory()->for($this->company)->create();
    HarvestUpload::factory()
        ->for($this->company)
        ->for($product)
        ->for($this->user, 'uploadedBy')
        ->create([
            'original_filename' => 'scale-export.csv',
            'import_type' => ImportType::ScaleCsv,
        ]);

    Livewire::test('harvest.upload')
        ->assertSee('Iz vage');
});

it('displays import type column with manual_csv icon', function () {
    $product = Product::factory()->for($this->company)->create();
    HarvestUpload::factory()
        ->for($this->company)
        ->for($product)
        ->for($this->user, 'uploadedBy')
        ->create([
            'original_filename' => 'manual-harvest.csv',
            'import_type' => ImportType::ManualCsv,
        ]);

    Livewire::test('harvest.upload')
        ->assertSee('Ručni');
});

it('renders correctly when import_type is null', function () {
    $product = Product::factory()->for($this->company)->create();
    HarvestUpload::factory()
        ->for($this->company)
        ->for($product)
        ->for($this->user, 'uploadedBy')
        ->create([
            'original_filename' => 'old-upload.csv',
            'import_type' => null,
        ]);

    Livewire::test('harvest.upload')
        ->assertSee('old-upload.csv');
});
