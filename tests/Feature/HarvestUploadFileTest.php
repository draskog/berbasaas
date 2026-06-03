<?php

use App\Models\Company;
use App\Models\HarvesterAssignment;
use App\Models\Product;
use App\Models\User;
use App\Services\HarvestImportService;
use Illuminate\Http\UploadedFile;

test('harvest import service returns correct duplicate counts and keys', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $product = Product::factory()->create(['company_id' => $company->id]);
    $year = now()->year;

    HarvesterAssignment::create([
        'company_id' => $company->id,
        'year' => $year,
        'number' => 101,
    ]);

    // Create CSV with valid records
    $dateStr = now()->format('Y-m-d');
    $csv = "No,Product,weight,tare,Gross,date,time\n";
    $csv .= "1,101,500,50,550,$dateStr,12:00:00\n";
    $csv .= "2,101,600,60,660,$dateStr,13:00:00\n";

    $file = UploadedFile::fake()->createWithContent('test.csv', $csv);

    $service = new HarvestImportService;
    $result = $service->parse($file, $company->id, $product->id, $user->id);

    expect($result)->toHaveKeys(['upload', 'inFileDuplicateCount', 'dbDuplicateCount'])
        ->and($result['inFileDuplicateCount'])->toBe(0)
        ->and($result['dbDuplicateCount'])->toBe(0);
});
