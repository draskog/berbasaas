<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\HarvestPrice;
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
            'name' => 'Borovnica',
            'slug' => 'borovnica',
            'active' => true,
        ]);

        Product::create([
            'company_id' => $company->id,
            'name' => 'Jagoda',
            'slug' => 'jagoda',
            'active' => true,
        ]);

        Product::create([
            'company_id' => $company->id,
            'name' => 'Malina',
            'slug' => 'malina',
            'active' => true,
        ]);

        HarvestPrice::create([
            'company_id' => $company->id,
            'product_id' => 1,
            'price_per_kg' => 100,
            'effective_from' => now()->subYears(4),
        ]);
        HarvestPrice::create([
            'company_id' => $company->id,
            'product_id' => 2,
            'price_per_kg' => 100,
            'effective_from' => now()->subYears(4),
        ]);
    }
}
