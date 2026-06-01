<?php

use App\Models\Company;
use App\Models\Harvester;
use App\Models\HarvesterAssignment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->for($this->company)->create();
    $this->actingAs($this->user);
});

describe('Harvesters Import/Export', function () {

    it('renders harvesters page successfully', function () {
        Livewire::test('harvest.harvesters')
            ->assertStatus(200);
    });

    it('downloads harvesters as CSV', function () {
        Harvester::factory()
            ->for($this->company)
            ->create(['name' => 'John Doe', 'prefix' => 'A', 'active' => true]);
        Harvester::factory()
            ->for($this->company)
            ->create(['name' => 'Jane Smith', 'prefix' => 'B', 'active' => true]);

        $response = $this->get(route('harvest.harvesters.download'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $response->assertHeader('Content-Disposition');

        $content = $response->streamedContent();
        expect($content)
            ->toContain('John Doe')
            ->toContain('Jane Smith')
            ->toContain('Redni broj')
            ->toContain('Ime i prezime berača');
    });

    it('returns 404 when no harvesters available for download', function () {
        $response = $this->get(route('harvest.harvesters.download'));

        $response->assertStatus(404);
    });

    it('imports harvesters from CSV file', function () {
        $csv = "Redni broj;Ime i prezime berača;Prefiks\n1;John Doe;A\n2;Jane Smith;B\n";
        $file = UploadedFile::fake()->createWithContent('harvesters.csv', $csv);

        Livewire::test('harvest.harvesters')
            ->set('importYear', 2025)
            ->set('importedFile', $file)
            ->call('importHarvesters')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('harvesters', [
            'company_id' => $this->company->id,
            'name' => 'John Doe',
            'prefix' => 'A',
            'active' => true,
        ]);

        $this->assertDatabaseHas('harvesters', [
            'company_id' => $this->company->id,
            'name' => 'Jane Smith',
            'prefix' => 'B',
            'active' => true,
        ]);

        $this->assertDatabaseCount('harvester_assignments', 2);
    });

    it('validates import year is required', function () {
        $csv = "Redni broj;Ime i prezime berača;Prefiks\n1;John Doe;A\n";
        $file = UploadedFile::fake()->createWithContent('harvesters.csv', $csv);

        Livewire::test('harvest.harvesters')
            ->set('importedFile', $file)
            ->call('importHarvesters')
            ->assertHasErrors('importYear');
    });

    it('validates import file is required', function () {
        Livewire::test('harvest.harvesters')
            ->set('importYear', 2025)
            ->call('importHarvesters')
            ->assertHasErrors('importedFile');
    });

    it('validates import year is valid number', function () {
        $csv = "Redni broj;Ime i prezime berača;Prefiks\n1;John Doe;A\n";
        $file = UploadedFile::fake()->createWithContent('harvesters.csv', $csv);

        Livewire::test('harvest.harvesters')
            ->set('importYear', 1999)
            ->set('importedFile', $file)
            ->call('importHarvesters')
            ->assertHasErrors('importYear');
    });

    it('prevents duplicate year imports', function () {
        HarvesterAssignment::factory()
            ->for($this->company)
            ->create(['year' => 2025]);

        $csv = "Redni broj;Ime i prezime berača;Prefiks\n1;John Doe;A\n";
        $file = UploadedFile::fake()->createWithContent('harvesters.csv', $csv);

        Livewire::test('harvest.harvesters')
            ->set('importYear', 2025)
            ->set('importedFile', $file)
            ->call('importHarvesters');

        expect($this->company->harvesters()->where('name', 'John Doe')->exists())->toBeFalse();
    });

    it('rejects file with missing required name field', function () {
        $csv = "Redni broj;Ime i prezime berača;Prefiks\n1;;A\n";
        $file = UploadedFile::fake()->createWithContent('harvesters.csv', $csv);

        Livewire::test('harvest.harvesters')
            ->set('importYear', 2025)
            ->set('importedFile', $file)
            ->call('importHarvesters');

        $this->assertDatabaseMissing('harvesters', [
            'company_id' => $this->company->id,
            'name' => '',
        ]);
    });

    it('allows null or empty prefix', function () {
        $csv = "Redni broj;Ime i prezime berača;Prefiks\n1;John Doe;\n2;Jane Smith;\n";
        $file = UploadedFile::fake()->createWithContent('harvesters.csv', $csv);

        Livewire::test('harvest.harvesters')
            ->set('importYear', 2025)
            ->set('importedFile', $file)
            ->call('importHarvesters')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('harvesters', [
            'company_id' => $this->company->id,
            'name' => 'John Doe',
            'prefix' => null,
        ]);
    });

    it('skips empty rows in CSV', function () {
        $csv = "Redni broj;Ime i prezime berača;Prefiks\n1;John Doe;A\n\n2;Jane Smith;B\n\n";
        $file = UploadedFile::fake()->createWithContent('harvesters.csv', $csv);

        Livewire::test('harvest.harvesters')
            ->set('importYear', 2025)
            ->set('importedFile', $file)
            ->call('importHarvesters')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('harvester_assignments', 2);
    });

    it('creates harvester assignments with import data', function () {
        $csv = "Redni broj;Ime i prezime berača;Prefiks\n5;John Doe;A\n10;Jane Smith;B\n";
        $file = UploadedFile::fake()->createWithContent('harvesters.csv', $csv);

        Livewire::test('harvest.harvesters')
            ->set('importYear', 2025)
            ->set('importedFile', $file)
            ->call('importHarvesters')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('harvester_assignments', [
            'company_id' => $this->company->id,
            'year' => 2025,
            'number' => 5,
        ]);

        $this->assertDatabaseHas('harvester_assignments', [
            'company_id' => $this->company->id,
            'year' => 2025,
            'number' => 10,
        ]);
    });

    it('updates existing harvester when importing', function () {
        $harvester = Harvester::factory()
            ->for($this->company)
            ->create(['name' => 'John Doe', 'prefix' => 'A']);

        $csv = "Redni broj;Ime i prezime berača;Prefiks\n1;John Doe;A\n";
        $file = UploadedFile::fake()->createWithContent('harvesters.csv', $csv);

        Livewire::test('harvest.harvesters')
            ->set('importYear', 2025)
            ->set('importedFile', $file)
            ->call('importHarvesters')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('harvesters', 1);
        $this->assertDatabaseHas('harvester_assignments', [
            'company_id' => $this->company->id,
            'harvester_id' => $harvester->id,
            'year' => 2025,
        ]);
    });

    it('isolates imports by company', function () {
        $otherCompany = Company::factory()->create();
        $otherUser = User::factory()->for($otherCompany)->create();

        $csv = "Redni broj;Ime i prezime berača;Prefiks\n1;John Doe;A\n";
        $file = UploadedFile::fake()->createWithContent('harvesters.csv', $csv);

        $this->actingAs($otherUser);

        Livewire::test('harvest.harvesters')
            ->set('importYear', 2025)
            ->set('importedFile', $file)
            ->call('importHarvesters')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('harvesters', [
            'company_id' => $otherCompany->id,
            'name' => 'John Doe',
        ]);

        $this->assertDatabaseMissing('harvesters', [
            'company_id' => $this->company->id,
            'name' => 'John Doe',
        ]);
    });

    it('closes modal after successful import', function () {
        $csv = "Redni broj;Ime i prezime berača;Prefiks\n1;John Doe;A\n";
        $file = UploadedFile::fake()->createWithContent('harvesters.csv', $csv);

        Livewire::test('harvest.harvesters')
            ->set('importYear', 2025)
            ->set('importedFile', $file)
            ->call('importHarvesters')
            ->assertSet('showImportHarvestersModal', false)
            ->assertSet('importYear', null)
            ->assertSet('importedFile', null);
    });

    it('refreshes component after successful import', function () {
        $csv = "Redni broj;Ime i prezime berača;Prefiks\n1;John Doe;A\n";
        $file = UploadedFile::fake()->createWithContent('harvesters.csv', $csv);

        Livewire::test('harvest.harvesters')
            ->set('importYear', 2025)
            ->set('importedFile', $file)
            ->call('importHarvesters')
            ->assertDispatched('$refresh');
    });

});
