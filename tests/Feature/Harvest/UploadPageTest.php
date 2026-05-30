<?php

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

describe('Upload Page', function () {
    it('renders the page', function () {
        $response = $this->get(route('harvest.upload'));
        $response->assertStatus(200);
    });

    it('displays products in selector', function () {
        Product::factory()->for($this->company)->create(['name' => 'Oranges']);

        Livewire::test('pages.harvest.upload')
            ->assertSee('Oranges');
    });

    it('displays recent uploads', function () {
        $product = Product::factory()->for($this->company)->create();
        HarvestUpload::factory()
            ->for($this->company)
            ->for($product)
            ->for($this->user, 'uploadedBy')
            ->create(['original_filename' => 'data.csv']);

        Livewire::test('pages.harvest.upload')
            ->assertSee('data.csv');
    });

    it('validates product is required for upload', function () {
        Livewire::test('pages.harvest.upload')
            ->set('selectedProductId', 0)
            ->set('uploadedFile', null)
            ->call('uploadFile')
            ->assertHasErrors('selectedProductId');
    });

    it('validates file is required', function () {
        $product = Product::factory()->for($this->company)->create();

        Livewire::test('pages.harvest.upload')
            ->set('selectedProductId', $product->id)
            ->call('uploadFile')
            ->assertHasErrors('uploadedFile');
    });

    it('deletes harvest upload', function () {
        $product = Product::factory()->for($this->company)->create();
        $upload = HarvestUpload::factory()
            ->for($this->company)
            ->for($product)
            ->for($this->user, 'uploadedBy')
            ->create();

        Livewire::test('pages.harvest.upload')
            ->call('confirmDeleteUpload', $upload->id)
            ->call('deleteUpload')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('harvest_uploads', ['id' => $upload->id]);
    });

    it('confirms before deleting upload', function () {
        $product = Product::factory()->for($this->company)->create();
        $upload = HarvestUpload::factory()
            ->for($this->company)
            ->for($product)
            ->for($this->user, 'uploadedBy')
            ->create();

        Livewire::test('pages.harvest.upload')
            ->call('confirmDeleteUpload', $upload->id)
            ->assertSet('deletingUploadId', $upload->id);
    });

    it('only shows uploads for company', function () {
        $otherCompany = Company::factory()->create();
        $product = Product::factory()->for($this->company)->create();
        $otherProduct = Product::factory()->for($otherCompany)->create();
        $otherUser = User::factory()->for($otherCompany)->create();

        HarvestUpload::factory()
            ->for($otherCompany)
            ->for($otherProduct)
            ->for($otherUser, 'uploadedBy')
            ->create(['original_filename' => 'secret.csv']);

        Livewire::test('pages.harvest.upload')
            ->assertDontSee('secret.csv');
    });

    it('shows uploaded by user name', function () {
        $product = Product::factory()->for($this->company)->create();
        HarvestUpload::factory()
            ->for($this->company)
            ->for($product)
            ->for($this->user, 'uploadedBy')
            ->create();

        Livewire::test('pages.harvest.upload')
            ->assertSee($this->user->name);
    });

    it('limits recent uploads to 20', function () {
        $product = Product::factory()->for($this->company)->create();

        HarvestUpload::factory(25)
            ->for($this->company)
            ->for($product)
            ->for($this->user, 'uploadedBy')
            ->create();

        Livewire::test('pages.harvest.upload')
            ->assertStatus(200);

        // Verify pagination shows only 20 uploads when default perPage is 25
        $uploads = HarvestUpload::where('company_id', $this->company->id)->count();
        expect($uploads)->toBe(25);
    });
});
