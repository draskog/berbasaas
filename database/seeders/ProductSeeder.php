<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $company = Company::first();

        if (! $company) {
            return;
        }

        Product::create([
            'company_id' => $company->id,
            'name' => 'Blueberries',
            'slug' => 'blueberries',
            'active' => true,
        ]);

        Product::create([
            'company_id' => $company->id,
            'name' => 'Strawberries',
            'slug' => 'strawberries',
            'active' => true,
        ]);
    }
}
