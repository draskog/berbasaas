<?php

use App\Models\Company;
use App\Models\HarvesterAssignment;
use App\Models\Product;
use App\Models\User;
use App\Services\HarvestImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new HarvestImportService;
    $this->company = Company::factory()->create();
    $this->product = Product::factory()->create(['company_id' => $this->company->id]);
    $this->user = User::factory()->create(['company_id' => $this->company->id]);

    HarvesterAssignment::factory()
        ->create([
            'company_id' => $this->company->id,
            'year' => now()->year,
            'number' => 1,
        ]);
});

it('status is duplikat when all records are skipped as duplicates', function () {
    // First import
    $csv1 = createCsvFile([
        [1, 1, 3.175, 0, 3.175, '2026-01-15', '09:30:45'],
    ]);

    $result1 = $this->service->parse($csv1, $this->company->id, $this->product->id, $this->user->id);
    $upload1 = $result1['upload'];

    // Second import (same data)
    $csv2 = createCsvFile([
        [1, 1, 3.175, 0, 3.175, '2026-01-15', '09:30:45'],
    ]);

    $result2 = $this->service->parse($csv2, $this->company->id, $this->product->id, $this->user->id);
    $upload2 = $result2['upload'];

    // Reload with counts
    $upload2->loadCount('harvestRecords as valid_count');
    $upload2->loadCount('stagingRecords as invalid_count');

    expect($upload2->valid_count)->toBe(0);
    expect($upload2->invalid_count)->toBe(1); // Now duplicates are staged as invalid
    expect($upload2->record_count)->toBe(1);
});

it('status is ispravno when all records are valid', function () {
    $csv = createCsvFile([
        [1, 1, 3.175, 0, 3.175, '2026-01-15', '09:30:45'],
        [2, 1, 2.88, 0, 2.88, '2026-01-15', '09:31:00'],
    ]);

    $result = $this->service->parse($csv, $this->company->id, $this->product->id, $this->user->id);
    $upload = $result['upload'];

    $upload->loadCount('harvestRecords as valid_count');
    $upload->loadCount(['stagingRecords as invalid_count' => fn ($q) => $q->where('status', 'invalid')]);

    expect($upload->valid_count)->toBeGreaterThan(0);
    expect($upload->invalid_count)->toBe(0);
});

it('status is neispravno when all records are invalid', function () {
    $csv = createCsvFile([
        [1, 999, 3.175, 0, 3.175, '2026-01-15', '09:30:45'],
        [2, 999, 2.88, 0, 2.88, '2026-01-15', '09:31:00'],
    ]);

    $result = $this->service->parse($csv, $this->company->id, $this->product->id, $this->user->id);
    $upload = $result['upload'];

    $upload->loadCount('harvestRecords as valid_count');
    $upload->loadCount(['stagingRecords as invalid_count' => fn ($q) => $q->where('status', 'invalid')]);

    expect($upload->valid_count)->toBe(0);
    expect($upload->invalid_count)->toBeGreaterThan(0);
});

it('status is delimicno ispravno when some records are valid and some are invalid', function () {
    $csv = createCsvFile([
        [1, 1, 3.175, 0, 3.175, '2026-01-15', '09:30:45'],
        [2, 999, 2.88, 0, 2.88, '2026-01-15', '09:31:00'],
    ]);

    $result = $this->service->parse($csv, $this->company->id, $this->product->id, $this->user->id);
    $upload = $result['upload'];

    $upload->loadCount('harvestRecords as valid_count');
    $upload->loadCount(['stagingRecords as invalid_count' => fn ($q) => $q->where('status', 'invalid')]);

    expect($upload->valid_count)->toBeGreaterThan(0);
    expect($upload->invalid_count)->toBeGreaterThan(0);
});

it('resolved_at is set when upload is marked as resolved', function () {
    $csv = createCsvFile([
        [1, 1, 3.175, 0, 3.175, '2026-01-15', '09:30:45'],
        [2, 1, 2.88, 0, 2.88, '2026-01-15', '09:31:00'],
    ]);

    $result = $this->service->parse($csv, $this->company->id, $this->product->id, $this->user->id);
    $upload = $result['upload'];

    expect($upload->resolved_at)->toBeNull();

    $upload->update(['resolved_at' => now()]);

    $upload->refresh();
    expect($upload->resolved_at)->not->toBeNull();
});
