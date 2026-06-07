<?php

use App\Enums\ImportType;
use App\Models\Company;
use App\Models\HarvesterAssignment;
use App\Models\HarvestImportSettings;
use App\Models\HarvestRecord;
use App\Models\HarvestRecordStaging;
use App\Models\Product;
use App\Models\User;
use App\Services\ManualHarvestImportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new ManualHarvestImportService;
    $this->company = Company::factory()->create();
    $this->product = Product::factory()->create(['company_id' => $this->company->id]);
    $this->user = User::factory()->create(['company_id' => $this->company->id]);

    HarvesterAssignment::factory()->createMany([
        [
            'company_id' => $this->company->id,
            'year' => now()->year,
            'number' => 1,
        ],
        [
            'company_id' => $this->company->id,
            'year' => now()->year,
            'number' => 2,
        ],
    ]);
});

it('imports valid records successfully', function () {
    $csv = createManualCsvFile([
        [1, 2.230],
        [2, 2.800],
    ]);

    $result = $this->service->parse(
        $csv,
        $this->company->id,
        $this->product->id,
        $this->user->id,
        '2026-01-15',
        '1.5'
    );

    $upload = $result['upload'];
    expect(HarvestRecord::count())->toBe(2);
    expect($upload->record_count)->toBe(2);
    expect($upload->import_type)->toBe(ImportType::ManualCsv);

    // Check weighed_at times (09:00:00, 09:00:01)
    $records = HarvestRecord::where('upload_id', $upload->id)->orderBy('sequence_number')->get();
    expect($records[0]->weighed_at->format('H:i:s'))->toBe('09:00:00');
    expect($records[1]->weighed_at->format('H:i:s'))->toBe('09:01:00');
});

it('stages records with tare out of range', function () {
    HarvestImportSettings::create([
        'company_id' => $this->company->id,
        'tare_min' => 0.5,
        'tare_max' => 2.0,
    ]);

    $csv = createManualCsvFile([
        [1, 2.230],
        [2, 2.800],
    ]);

    $result = $this->service->parse(
        $csv,
        $this->company->id,
        $this->product->id,
        $this->user->id,
        '2026-01-15',
        '0.1'  // Below min
    );

    expect(HarvestRecord::count())->toBe(0);
    expect(HarvestRecordStaging::count())->toBe(2);

    HarvestRecordStaging::each(function ($record) {
        expect($record->validation_reason)->toContain('tare_out_of_range');
    });
});

it('stages records with invalid harvester', function () {
    $csv = createManualCsvFile([
        [99, 2.230],  // Invalid harvester number
    ]);

    $result = $this->service->parse(
        $csv,
        $this->company->id,
        $this->product->id,
        $this->user->id,
        '2026-01-15',
        '1.5'
    );

    expect(HarvestRecord::count())->toBe(0);
    expect(HarvestRecordStaging::count())->toBe(1);

    $staged = HarvestRecordStaging::first();
    expect($staged->validation_reason)->toContain('harvester_not_found');
});

it('handles multiple records with unique timestamps', function () {
    $csv = createManualCsvFile([
        [1, 2.230],
        [1, 2.800],  // Different gross, so different weight
    ]);

    $result = $this->service->parse(
        $csv,
        $this->company->id,
        $this->product->id,
        $this->user->id,
        '2026-01-15',
        '1.5'
    );

    expect(HarvestRecord::count())->toBe(2);
    expect(HarvestRecordStaging::count())->toBe(0);
    expect($result['inFileDuplicateCount'])->toBe(0);
});

it('detects database duplicates', function () {
    // Create existing record
    HarvestRecord::create([
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
        'harvester_number' => 1,
        'weight' => 43.730,
        'tare' => 1.5,
        'gross' => 2.230,
        'weighed_at' => Carbon::createFromFormat('Y-m-d H:i:s', '2026-01-15 09:00:00'),
        'sequence_number' => 1,
    ]);

    $csv = createManualCsvFile([
        [1, 2.230],  // Same as existing
    ]);

    $result = $this->service->parse(
        $csv,
        $this->company->id,
        $this->product->id,
        $this->user->id,
        '2026-01-15',
        '1.5'
    );

    expect(HarvestRecord::count())->toBe(1);  // Original record
    expect(HarvestRecordStaging::count())->toBe(1);  // Duplicate staged
    expect($result['dbDuplicateCount'])->toBe(1);

    $staged = HarvestRecordStaging::first();
    expect($staged->validation_reason)->toContain('db_duplicate');
});

it('calculates weight correctly', function () {
    $csv = createManualCsvFile([
        [1, 2.230],
        [2, 2.800],
    ]);

    $result = $this->service->parse(
        $csv,
        $this->company->id,
        $this->product->id,
        $this->user->id,
        '2026-01-15',
        '0.5'
    );

    $records = HarvestRecord::where('upload_id', $result['upload']->id)->get();
    expect($records[0]->weight)->toBe(2.230 - 0.5);
    expect($records[1]->weight)->toBe(2.800 - 0.5);
});

it('throws exception for missing columns', function () {
    $csv = createCsvFile([[1, 2.230]], ['harvester', 'weight']);

    $this->service->parse(
        $csv,
        $this->company->id,
        $this->product->id,
        $this->user->id,
        '2026-01-15',
        '1.5'
    );
})->throws(InvalidArgumentException::class);

it('throws exception for invalid date format', function () {
    $csv = createManualCsvFile([
        [1, 2.230],
    ]);

    $this->service->parse(
        $csv,
        $this->company->id,
        $this->product->id,
        $this->user->id,
        '01-15-2026',
        '1.5'
    );
})->throws(InvalidArgumentException::class);
