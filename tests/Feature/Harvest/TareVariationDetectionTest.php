<?php

use App\Models\Company;
use App\Models\Harvester;
use App\Models\HarvesterAssignment;
use App\Models\HarvestRecordStaging;
use App\Models\Product;
use App\Models\User;
use App\Services\HarvestImportService;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->for($this->company)->create();
    $this->product = Product::factory()->for($this->company)->create();

    $harvester1 = Harvester::factory()->create(['name' => 'Harvester 1']);
    $harvester2 = Harvester::factory()->create(['name' => 'Harvester 2']);

    HarvesterAssignment::factory()
        ->for($this->company)
        ->for($harvester1)
        ->create(['year' => 2026, 'number' => 13]);

    HarvesterAssignment::factory()
        ->for($this->company)
        ->for($harvester2)
        ->create(['year' => 2026, 'number' => 8]);
});

it('flags records with tare zero when variation exists', function () {
    $csv = UploadedFile::fake()->createWithContent('harvest.csv', <<<'EOL'
No,Product,Client,id1,id2,id3,id4,weight,weight_unit,result,tare,Gross,date,time
1,13,,,,,,2.5,kg,0,0,2.5,2026-06-01,09:00:00
2,13,,,,,,2.6,kg,0,0,2.6,2026-06-01,09:05:00
3,8,,,,,,3.0,kg,0,0.31,3.31,2026-06-01,10:00:00
4,8,,,,,,3.1,kg,0,0.31,3.41,2026-06-01,10:05:00
EOL
    );

    $service = new HarvestImportService;
    $result = $service->parse($csv, $this->company->id, $this->product->id, $this->user->id);
    $upload = $result['upload'];

    $stagingRecords = HarvestRecordStaging::where('upload_id', $upload->id)->get();

    expect($stagingRecords)->toHaveCount(2);

    foreach ($stagingRecords as $record) {
        expect($record->tare)->toEqual(0);
        expect($record->validation_reason)->toContain('tare_out_of_range');
    }
});

it('ignores tare when all records have tare zero', function () {
    $csv = UploadedFile::fake()->createWithContent('harvest.csv', <<<'EOL'
No,Product,Client,id1,id2,id3,id4,weight,weight_unit,result,tare,Gross,date,time
1,13,,,,,,2.5,kg,0,0,2.5,2026-06-01,09:00:00
2,13,,,,,,2.6,kg,0,0,2.6,2026-06-01,09:05:00
3,8,,,,,,3.0,kg,0,0,3.0,2026-06-01,10:00:00
4,8,,,,,,3.1,kg,0,0,3.1,2026-06-01,10:05:00
EOL
    );

    $service = new HarvestImportService;
    $result = $service->parse($csv, $this->company->id, $this->product->id, $this->user->id);
    $upload = $result['upload'];

    $records = HarvestRecordStaging::where('upload_id', $upload->id)->get();
    $hasTareIssues = $records->some(fn ($r) => in_array('tare_out_of_range', (array) $r->validation_reason, true));
    expect($hasTareIssues)->toBeFalse();
});

it('ignores tare when all records have tare greater than zero', function () {
    $csv = UploadedFile::fake()->createWithContent('harvest.csv', <<<'EOL'
No,Product,Client,id1,id2,id3,id4,weight,weight_unit,result,tare,Gross,date,time
1,13,,,,,,2.5,kg,0,0.31,2.81,2026-06-01,09:00:00
2,13,,,,,,2.6,kg,0,0.31,2.91,2026-06-01,09:05:00
3,8,,,,,,3.0,kg,0,0.31,3.31,2026-06-01,10:00:00
4,8,,,,,,3.1,kg,0,0.31,3.41,2026-06-01,10:05:00
EOL
    );

    $service = new HarvestImportService;
    $result = $service->parse($csv, $this->company->id, $this->product->id, $this->user->id);
    $upload = $result['upload'];

    $records = HarvestRecordStaging::where('upload_id', $upload->id)->get();
    $hasTareIssues = $records->some(fn ($r) => in_array('tare_out_of_range', (array) $r->validation_reason, true));
    expect($hasTareIssues)->toBeFalse();
});
