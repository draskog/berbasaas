<?php

use App\Models\Company;
use App\Models\Product;
use App\Models\User;

test('products settings page loads', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();

    $this->actingAs($user)
        ->get('/settings/products')
        ->assertStatus(200)
        ->assertSee($this->trans('Products'))
        ->assertSee($this->trans('Add Product'));
});

test('can view products list', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    Product::factory()->for($company)->create([
        'name' => 'Widget',
    ]);

    $this->actingAs($user)
        ->get('/settings/products')
        ->assertStatus(200)
        ->assertSee('Widget');
});

test('products are scoped to company', function () {
    $company1 = Company::factory()->create();
    $company2 = Company::factory()->create();
    $user = User::factory()->for($company1)->create();

    Product::factory()->for($company1)->create([
        'name' => 'User Product',
    ]);

    Product::factory()->for($company2)->create([
        'name' => 'Other Product',
    ]);

    $this->actingAs($user)
        ->get('/settings/products')
        ->assertSee('User Product')
        ->assertDontSee('Other Product');
});
