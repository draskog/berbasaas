<?php

use App\Models\Company;
use App\Models\HarvesterAssignment;
use App\Models\HarvestImportSettings;
use App\Models\HarvestRecord;
use App\Models\HarvestRecordStaging;
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

it('imports valid records successfully', function () {
    $csv = createCsvFile([
        [1, 1, 3.175, 0, 3.175, '2026-01-15', '09:30:45'],
        [2, 1, 2.88, 0, 2.88, '2026-01-15', '09:31:00'],
    ]);

    $result = $this->service->parse(
        $csv,
        $this->company->id,
        $this->product->id,
        $this->user->id
    );

    $upload = $result['upload'];
    expect(HarvestRecord::count())->toBe(2);
    expect($upload->record_count)->toBe(2);
    expect($result['skippedCount'])->toBe(0);
});

it('stages duplicate records in same import', function () {
    $csv = createCsvFile([
        [1, 1, 3.175, 0, 3.175, '2026-01-15', '09:30:45'],
        [1, 1, 3.175, 0, 3.175, '2026-01-15', '09:30:45'], // Duplicate
    ]);

    $result = $this->service->parse(
        $csv,
        $this->company->id,
        $this->product->id,
        $this->user->id
    );

    expect(HarvestRecord::count())->toBe(1);
    expect(HarvestRecordStaging::count())->toBe(1);
    expect($result['skippedCount'])->toBe(0);
    expect($result['inFileDuplicateCount'])->toBe(1);

    $staged = HarvestRecordStaging::first();
    expect($staged->validation_reason)->toContain('in_file_duplicate');
    expect($staged->duplicate_of_sequence)->toBe(1);
});

it('stages duplicate when sequence numbers differ but timestamp same', function () {
    $csv = createCsvFile([
        [1, 1, 3.175, 0, 3.175, '2026-01-15', '09:30:45'],
        [2, 1, 3.175, 0, 3.175, '2026-01-15', '09:30:45'], // Different sequence, same timestamp
    ]);

    $result = $this->service->parse(
        $csv,
        $this->company->id,
        $this->product->id,
        $this->user->id
    );

    expect(HarvestRecord::count())->toBe(1);
    expect(HarvestRecordStaging::count())->toBe(1);
    expect($result['inFileDuplicateCount'])->toBe(1);

    $staged = HarvestRecordStaging::first();
    expect($staged->duplicate_of_sequence)->toBe(1);
});

it('validates tare with import settings', function () {
    HarvestImportSettings::create([
        'company_id' => $this->company->id,
        'tare_min' => 0.1,
        'tare_max' => 5.0,
    ]);

    HarvesterAssignment::factory()->create([
        'company_id' => $this->company->id,
        'year' => now()->year,
        'number' => 2,
    ]);

    HarvesterAssignment::factory()->create([
        'company_id' => $this->company->id,
        'year' => now()->year,
        'number' => 3,
    ]);

    $csv = createCsvFile([
        [1, 1, 3.175, 0.5, 3.175, '2026-01-15', '09:30:45'], // Valid
        [2, 2, 2.88, 0.05, 2.88, '2026-01-15', '09:31:00'],  // Below min
        [3, 3, 4.0, 6.0, 4.0, '2026-01-15', '09:32:00'],     // Above max
    ]);

    $result = $this->service->parse(
        $csv,
        $this->company->id,
        $this->product->id,
        $this->user->id
    );

    $upload = $result['upload'];

    expect(HarvestRecord::count())->toBe(1);
    expect(HarvestRecordStaging::count())->toBe(2);

    HarvestRecordStaging::where('upload_id', $upload->id)
        ->each(function ($record) {
            expect($record->validation_reason)->toContain('tare_out_of_range');
        });
});

it('auto-detects tare variation and flags zero tare', function () {
    HarvesterAssignment::factory()->create([
        'company_id' => $this->company->id,
        'year' => now()->year,
        'number' => 2,
    ]);

    $csv = createCsvFile([
        [1, 1, 3.175, 1.5, 3.175, '2026-01-15', '09:30:45'], // Non-zero
        [2, 2, 2.88, 0, 2.88, '2026-01-15', '09:31:00'],     // Zero (flagged)
    ]);

    $result = $this->service->parse(
        $csv,
        $this->company->id,
        $this->product->id,
        $this->user->id
    );

    expect(HarvestRecord::count())->toBe(1);
    expect(HarvestRecordStaging::count())->toBe(1);

    $invalid = HarvestRecordStaging::first();
    expect($invalid->validation_reason)->toContain('tare_out_of_range');
});

it('allows all zero tare when no variation', function () {
    HarvesterAssignment::factory()->create([
        'company_id' => $this->company->id,
        'year' => now()->year,
        'number' => 2,
    ]);

    $csv = createCsvFile([
        [1, 1, 3.175, 0, 3.175, '2026-01-15', '09:30:45'],
        [2, 2, 2.88, 0, 2.88, '2026-01-15', '09:31:00'],
    ]);

    $result = $this->service->parse(
        $csv,
        $this->company->id,
        $this->product->id,
        $this->user->id
    );

    expect(HarvestRecord::count())->toBe(2);
    expect(HarvestRecordStaging::count())->toBe(0);
});

it('flags harvester not found', function () {
    $csv = createCsvFile([
        [1, 1, 3.175, 0, 3.175, '2026-01-15', '09:30:45'],   // Valid
        [2, 999, 2.88, 0, 2.88, '2026-01-15', '09:31:00'],   // Invalid harvester
    ]);

    $result = $this->service->parse(
        $csv,
        $this->company->id,
        $this->product->id,
        $this->user->id
    );

    $upload = $result['upload'];

    expect(HarvestRecord::count())->toBe(1);
    expect(HarvestRecordStaging::count())->toBe(1);

    $invalid = HarvestRecordStaging::where('upload_id', $upload->id)->first();
    expect($invalid->validation_reason)->toContain('harvester_not_found');
});

it('records multiple validation reasons', function () {
    HarvestImportSettings::create([
        'company_id' => $this->company->id,
        'tare_min' => 0.1,
        'tare_max' => 5.0,
    ]);

    $csv = createCsvFile([
        [1, 999, 3.175, 0.05, 3.175, '2026-01-15', '09:30:45'], // Both invalid
    ]);

    $this->service->parse(
        $csv,
        $this->company->id,
        $this->product->id,
        $this->user->id
    );

    $invalid = HarvestRecordStaging::first();

    expect($invalid->validation_reason)->toContain('tare_out_of_range');
    expect($invalid->validation_reason)->toContain('harvester_not_found');
});

it('stores sequence number from csv', function () {
    $csv = createCsvFile([
        [1, 1, 3.175, 0, 3.175, '2026-01-15', '09:30:45'],
        [2, 1, 2.88, 0, 2.88, '2026-01-16', '09:31:00'],
    ]);

    $this->service->parse(
        $csv,
        $this->company->id,
        $this->product->id,
        $this->user->id
    );

    $records = HarvestRecord::orderBy('sequence_number')->get();

    expect($records[0]->sequence_number)->toBe(1);
    expect($records[1]->sequence_number)->toBe(2);
});

it('stages multiple duplicates for same harvester and timestamp', function () {
    $csv = createCsvFile([
        [1, 1, 3.175, 0, 3.175, '2026-01-15', '09:30:45'],
        [2, 1, 3.175, 0, 3.175, '2026-01-15', '09:30:45'],
        [3, 1, 3.175, 0, 3.175, '2026-01-15', '09:30:45'],
    ]);

    $result = $this->service->parse(
        $csv,
        $this->company->id,
        $this->product->id,
        $this->user->id
    );

    expect(HarvestRecord::count())->toBe(1);
    expect(HarvestRecordStaging::count())->toBe(2);
    expect($result['inFileDuplicateCount'])->toBe(2);

    $staged = HarvestRecordStaging::orderBy('sequence_number')->get();
    expect($staged[0]->duplicate_of_sequence)->toBe(1);
    expect($staged[1]->duplicate_of_sequence)->toBe(1);
});

it('calculates correct date range', function () {
    $csv = createCsvFile([
        [1, 1, 3.175, 0, 3.175, '2026-01-10', '09:30:45'],
        [2, 1, 2.88, 0, 2.88, '2026-01-20', '09:31:00'],
    ]);

    $result = $this->service->parse(
        $csv,
        $this->company->id,
        $this->product->id,
        $this->user->id
    );

    $upload = $result['upload'];

    expect($upload->date_from->format('Y-m-d'))->toBe('2026-01-10');
    expect($upload->date_to->format('Y-m-d'))->toBe('2026-01-20');
});
