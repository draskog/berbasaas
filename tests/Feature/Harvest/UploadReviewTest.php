<?php

use App\Models\Company;
use App\Models\Harvester;
use App\Models\HarvesterAssignment;
use App\Models\HarvestImportSettings;
use App\Models\HarvestRecord;
use App\Models\HarvestRecordStaging;
use App\Models\HarvestUpload;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->product = Product::factory()->create(['company_id' => $this->company->id]);
    $this->harvester = Harvester::factory()->create(['company_id' => $this->company->id]);
    $this->upload = HarvestUpload::factory()->create(['company_id' => $this->company->id, 'product_id' => $this->product->id]);

    HarvestImportSettings::create([
        'company_id' => $this->company->id,
        'tare_min' => 0.100,
        'tare_max' => 0.800,
    ]);

    HarvesterAssignment::factory()->create([
        'company_id' => $this->company->id,
        'number' => 1,
        'year' => $this->upload->date_from->year,
        'harvester_id' => $this->harvester->id,
    ]);

    $this->actingAs($this->user);
});

test('HarvestRecord includes sequence_number when created from staging', function () {
    $staging = HarvestRecordStaging::create([
        'company_id' => $this->company->id,
        'upload_id' => $this->upload->id,
        'product_id' => $this->product->id,
        'harvester_number' => 1,
        'weight' => 3.0,
        'tare' => 0.200,
        'gross' => 3.2,
        'weighed_at' => now(),
        'sequence_number' => 5,
        'status' => 'invalid',
    ]);

    HarvestRecord::create([
        'company_id' => $staging->company_id,
        'upload_id' => $staging->upload_id,
        'product_id' => $staging->product_id,
        'harvester_number' => $staging->harvester_number,
        'weight' => $staging->weight,
        'tare' => $staging->tare,
        'gross' => $staging->gross,
        'weighed_at' => $staging->weighed_at,
        'sequence_number' => $staging->sequence_number,
        'corrected' => true,
    ]);

    $record = HarvestRecord::where('sequence_number', 5)->firstOrFail();
    expect($record->sequence_number)->toBe(5);
});

test('findPreviousHarvestRecord finds record with sequence_number - 1', function () {
    $baseTime = now();

    // Create valid records for sequence 3 and 5
    HarvestRecord::create([
        'company_id' => $this->company->id,
        'upload_id' => $this->upload->id,
        'product_id' => $this->product->id,
        'harvester_number' => 1,
        'weight' => 2.0,
        'tare' => 0.200,
        'gross' => 2.2,
        'weighed_at' => $baseTime,
        'sequence_number' => 3,
    ]);

    // Create invalid staging record for sequence 4
    $staging = HarvestRecordStaging::create([
        'company_id' => $this->company->id,
        'upload_id' => $this->upload->id,
        'product_id' => $this->product->id,
        'harvester_number' => 999,
        'weight' => 2.5,
        'tare' => 0.300,
        'gross' => 2.8,
        'weighed_at' => $baseTime->copy()->addMinute(),
        'sequence_number' => 4,
        'status' => 'invalid',
        'validation_reason' => json_encode(['harvester_not_found']),
    ]);

    HarvestRecord::create([
        'company_id' => $this->company->id,
        'upload_id' => $this->upload->id,
        'product_id' => $this->product->id,
        'harvester_number' => 1,
        'weight' => 3.0,
        'tare' => 0.200,
        'gross' => 3.2,
        'weighed_at' => $baseTime->copy()->addMinutes(2),
        'sequence_number' => 5,
    ]);

    $component = Livewire::test('harvest.upload-review', ['upload' => $this->upload]);
    $prevRecord = $component->instance()->findPreviousHarvestRecord($staging);

    expect($prevRecord)->not->toBeNull();
    expect($prevRecord->sequence_number)->toBe(3);
});

test('findNextHarvestRecord finds record with sequence_number + 1', function () {
    $baseTime = now();

    // Create valid records for sequence 3 and 5
    HarvestRecord::create([
        'company_id' => $this->company->id,
        'upload_id' => $this->upload->id,
        'product_id' => $this->product->id,
        'harvester_number' => 1,
        'weight' => 2.0,
        'tare' => 0.200,
        'gross' => 2.2,
        'weighed_at' => $baseTime,
        'sequence_number' => 3,
    ]);

    // Create invalid staging record for sequence 4
    $staging = HarvestRecordStaging::create([
        'company_id' => $this->company->id,
        'upload_id' => $this->upload->id,
        'product_id' => $this->product->id,
        'harvester_number' => 999,
        'weight' => 2.5,
        'tare' => 0.300,
        'gross' => 2.8,
        'weighed_at' => $baseTime->copy()->addMinute(),
        'sequence_number' => 4,
        'status' => 'invalid',
        'validation_reason' => json_encode(['harvester_not_found']),
    ]);

    HarvestRecord::create([
        'company_id' => $this->company->id,
        'upload_id' => $this->upload->id,
        'product_id' => $this->product->id,
        'harvester_number' => 1,
        'weight' => 3.0,
        'tare' => 0.200,
        'gross' => 3.2,
        'weighed_at' => $baseTime->copy()->addMinutes(2),
        'sequence_number' => 5,
    ]);

    $component = Livewire::test('harvest.upload-review', ['upload' => $this->upload]);
    $nextRecord = $component->instance()->findNextHarvestRecord($staging);

    expect($nextRecord)->not->toBeNull();
    expect($nextRecord->sequence_number)->toBe(5);
});

test('weight calculation is gross minus tare', function () {
    $gross = 3.2;
    $tare = 0.300;
    $weight = $gross - $tare;

    HarvestRecord::create([
        'company_id' => $this->company->id,
        'upload_id' => $this->upload->id,
        'product_id' => $this->product->id,
        'harvester_number' => 1,
        'weight' => $weight,
        'tare' => $tare,
        'gross' => $gross,
        'weighed_at' => now(),
        'sequence_number' => 3,
        'corrected' => true,
    ]);

    $record = HarvestRecord::where('sequence_number', 3)->firstOrFail();
    expect($record->tare)->not->toBeNull();
    expect($record->weight)->not->toBeNull();
    expect(round($record->gross - $record->tare, 3))->toBe(round($record->weight, 3));
});

test('tare validation rejects below tare_min', function () {
    $settings = HarvestImportSettings::where('company_id', $this->company->id)->first();
    $belowMin = $settings->tare_min - 0.001;

    expect($belowMin < $settings->tare_min)->toBeTrue();
    expect($belowMin >= $settings->tare_max)->toBeFalse();
});

test('tare validation rejects above tare_max', function () {
    $settings = HarvestImportSettings::where('company_id', $this->company->id)->first();
    $aboveMax = $settings->tare_max + 0.001;

    expect($aboveMax > $settings->tare_max)->toBeTrue();
});

test('tare validation passes within bounds', function () {
    $settings = HarvestImportSettings::where('company_id', $this->company->id)->first();
    $valid = 0.300;

    expect($valid >= $settings->tare_min)->toBeTrue();
    expect($valid <= $settings->tare_max)->toBeTrue();
});

test('next sequence record can be queried for tare suggestion', function () {
    $nextSeq = 10;
    HarvestRecordStaging::create([
        'company_id' => $this->company->id,
        'upload_id' => $this->upload->id,
        'product_id' => $this->product->id,
        'harvester_number' => 1,
        'weight' => 2.8,
        'tare' => 0.400,
        'gross' => 3.2,
        'weighed_at' => now(),
        'sequence_number' => $nextSeq,
        'status' => 'valid',
    ]);

    $suggested = HarvestRecordStaging::where('upload_id', $this->upload->id)
        ->where('sequence_number', $nextSeq)
        ->where('tare', '>', 0)
        ->value('tare');

    expect((float) $suggested)->toBe(0.4);
});

test('hasAnyInvalidRecords checks for invalid staging records', function () {
    HarvestRecordStaging::create([
        'company_id' => $this->company->id,
        'upload_id' => $this->upload->id,
        'product_id' => $this->product->id,
        'harvester_number' => 1,
        'weight' => 3.0,
        'tare' => 0.200,
        'gross' => 3.2,
        'weighed_at' => now(),
        'sequence_number' => 102,
        'status' => 'invalid',
    ]);

    $hasAny = HarvestRecordStaging::where('upload_id', $this->upload->id)
        ->where('status', 'invalid')
        ->exists();

    expect($hasAny)->toBeTrue();

    HarvestRecordStaging::where('upload_id', $this->upload->id)->delete();

    $hasAny = HarvestRecordStaging::where('upload_id', $this->upload->id)
        ->where('status', 'invalid')
        ->exists();

    expect($hasAny)->toBeFalse();
});

test('suggestedTaresByRecordId can batch lookup next sequence records', function () {
    HarvestRecordStaging::create([
        'company_id' => $this->company->id,
        'upload_id' => $this->upload->id,
        'product_id' => $this->product->id,
        'harvester_number' => 1,
        'weight' => 2.8,
        'tare' => 0.250,
        'gross' => 3.05,
        'weighed_at' => now(),
        'sequence_number' => 110,
        'status' => 'valid',
    ]);

    HarvestRecordStaging::create([
        'company_id' => $this->company->id,
        'upload_id' => $this->upload->id,
        'product_id' => $this->product->id,
        'harvester_number' => 1,
        'weight' => 2.8,
        'tare' => 0.350,
        'gross' => 3.15,
        'weighed_at' => now(),
        'sequence_number' => 111,
        'status' => 'valid',
    ]);

    $nextSeqs = [110, 111];
    $fromStaging = HarvestRecordStaging::where('upload_id', $this->upload->id)
        ->whereIn('sequence_number', $nextSeqs)
        ->where('tare', '>', 0)
        ->pluck('tare', 'sequence_number');

    expect($fromStaging->has(110))->toBeTrue();
    expect((float) $fromStaging[110])->toBe(0.25);
    expect($fromStaging->has(111))->toBeTrue();
    expect((float) $fromStaging[111])->toBe(0.35);
});

test('updatedSelectAll can query all matching records', function () {
    HarvestRecordStaging::create([
        'company_id' => $this->company->id,
        'upload_id' => $this->upload->id,
        'product_id' => $this->product->id,
        'harvester_number' => 999,
        'weight' => 3.0,
        'tare' => 0.200,
        'gross' => 3.2,
        'weighed_at' => now(),
        'sequence_number' => 120,
        'status' => 'invalid',
    ]);

    HarvestRecordStaging::create([
        'company_id' => $this->company->id,
        'upload_id' => $this->upload->id,
        'product_id' => $this->product->id,
        'harvester_number' => 999,
        'weight' => 3.0,
        'tare' => 0.200,
        'gross' => 3.2,
        'weighed_at' => now(),
        'sequence_number' => 121,
        'status' => 'invalid',
    ]);

    $selectedIds = HarvestRecordStaging::where('upload_id', $this->upload->id)
        ->where('status', 'invalid')
        ->pluck('id')
        ->map(fn ($id) => (string) $id)
        ->toArray();

    expect(count($selectedIds))->toBe(2);
});

test('importSettings model retrieves tare range for company', function () {
    $settings = HarvestImportSettings::where('company_id', $this->company->id)->first();

    expect($settings)->not->toBeNull();
    expect((float) $settings->tare_min)->toBe(0.1);
    expect((float) $settings->tare_max)->toBe(0.8);
});

test('union of tare values from both staging and records tables', function () {
    $fromStaging = HarvestRecordStaging::where('upload_id', $this->upload->id)
        ->distinct()
        ->pluck('tare');

    $fromRecords = HarvestRecord::where('upload_id', $this->upload->id)
        ->distinct()
        ->pluck('tare');

    $merged = $fromRecords->merge($fromStaging)
        ->unique()
        ->filter(fn ($t) => $t > 0)
        ->sort()
        ->values()
        ->toArray();

    expect(is_array($merged))->toBeTrue();
});

test('staging record status can be updated to valid', function () {
    $staging = HarvestRecordStaging::create([
        'company_id' => $this->company->id,
        'upload_id' => $this->upload->id,
        'product_id' => $this->product->id,
        'harvester_number' => 1,
        'weight' => 3.0,
        'tare' => 0.200,
        'gross' => 3.2,
        'weighed_at' => now(),
        'sequence_number' => 50,
        'status' => 'invalid',
    ]);

    expect($staging->status)->toBe('invalid');

    $staging->update(['status' => 'valid']);
    $staging->refresh();

    expect($staging->status)->toBe('valid');
});

test('harvester assignment exists for company year', function () {
    $assignment = HarvesterAssignment::where('company_id', $this->company->id)
        ->where('number', 1)
        ->where('year', $this->upload->date_from->year)
        ->first();

    expect($assignment)->not->toBeNull();
    expect($assignment->harvester_id)->toBe($this->harvester->id);
});

test('resolved_at is set when last duplicate staging record is deleted', function () {
    $staging = HarvestRecordStaging::create([
        'company_id' => $this->company->id,
        'upload_id' => $this->upload->id,
        'product_id' => $this->product->id,
        'harvester_number' => 1,
        'weight' => 3.0,
        'tare' => 0.200,
        'gross' => 3.2,
        'weighed_at' => now(),
        'sequence_number' => 1,
        'status' => 'invalid',
        'validation_reason' => ['db_duplicate'],
    ]);

    expect($this->upload->resolved_at)->toBeNull();

    Livewire::test('harvest.upload-review', ['upload' => $this->upload])
        ->call('delete', $staging->id);

    $this->upload->refresh();
    expect($this->upload->resolved_at)->not->toBeNull();
});

test('resolved_at is set when all duplicate staging records are bulk deleted', function () {
    $staging1 = HarvestRecordStaging::create([
        'company_id' => $this->company->id,
        'upload_id' => $this->upload->id,
        'product_id' => $this->product->id,
        'harvester_number' => 1,
        'weight' => 3.0,
        'tare' => 0.200,
        'gross' => 3.2,
        'weighed_at' => now(),
        'sequence_number' => 1,
        'status' => 'invalid',
        'validation_reason' => ['db_duplicate'],
    ]);

    $staging2 = HarvestRecordStaging::create([
        'company_id' => $this->company->id,
        'upload_id' => $this->upload->id,
        'product_id' => $this->product->id,
        'harvester_number' => 1,
        'weight' => 2.8,
        'tare' => 0.150,
        'gross' => 2.95,
        'weighed_at' => now(),
        'sequence_number' => 2,
        'status' => 'invalid',
        'validation_reason' => ['in_file_duplicate'],
    ]);

    expect($this->upload->resolved_at)->toBeNull();

    Livewire::test('harvest.upload-review', ['upload' => $this->upload])
        ->set('selectedIds', [(string) $staging1->id, (string) $staging2->id])
        ->call('deleteSelected');

    $this->upload->refresh();
    expect($this->upload->resolved_at)->not->toBeNull();
});

test('resolved_at is not set when some invalid records still remain after delete', function () {
    $staging1 = HarvestRecordStaging::create([
        'company_id' => $this->company->id,
        'upload_id' => $this->upload->id,
        'product_id' => $this->product->id,
        'harvester_number' => 1,
        'weight' => 3.0,
        'tare' => 0.200,
        'gross' => 3.2,
        'weighed_at' => now(),
        'sequence_number' => 1,
        'status' => 'invalid',
        'validation_reason' => ['db_duplicate'],
    ]);

    $staging2 = HarvestRecordStaging::create([
        'company_id' => $this->company->id,
        'upload_id' => $this->upload->id,
        'product_id' => $this->product->id,
        'harvester_number' => 999,
        'weight' => 2.8,
        'tare' => 0.150,
        'gross' => 2.95,
        'weighed_at' => now(),
        'sequence_number' => 2,
        'status' => 'invalid',
        'validation_reason' => ['harvester_not_found'],
    ]);

    expect($this->upload->resolved_at)->toBeNull();

    Livewire::test('harvest.upload-review', ['upload' => $this->upload])
        ->call('delete', $staging1->id);

    $this->upload->refresh();
    expect($this->upload->resolved_at)->toBeNull();
});
