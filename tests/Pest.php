<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\DuskTestCase;
use Tests\TestCase;

uses(
    DuskTestCase::class,
    // Illuminate\Foundation\Testing\DatabaseMigrations::class,
)->in('Browser');

uses(TestCase::class)->in('Feature');

uses(RefreshDatabase::class)->in('Feature');

function createCsvFile(array $rows, ?array $header = null, string $delimiter = ','): UploadedFile
{
    if ($header === null) {
        $header = ['No', 'Product', 'weight', 'tare', 'Gross', 'date', 'time'];
    }

    $content = implode($delimiter, $header)."\n";
    foreach ($rows as $row) {
        $content .= implode($delimiter, $row)."\n";
    }

    $path = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($path, $content);

    return new UploadedFile(
        $path,
        'test.csv',
        'text/csv',
        null,
        true
    );
}

function createManualCsvFile(array $rows, string $delimiter = ','): UploadedFile
{
    $header = ['berac_br', 'bruto_tezina'];
    $content = implode($delimiter, $header)."\n";
    foreach ($rows as $row) {
        $content .= implode($delimiter, $row)."\n";
    }

    $path = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($path, $content);

    return new UploadedFile(
        $path,
        'manual.csv',
        'text/csv',
        null,
        true
    );
}
