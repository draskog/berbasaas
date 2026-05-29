<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $company = Company::first();

        if (!$company) {
            return;
        }

        Product::create([
            'company_id' => $company->id,
            'name' => 'Blueberry',
            'slug' => 'blueberry',
            'active' => true,
        ]);

        Product::create([
            'company_id' => $company->id,
            'name' => 'Strawberry',
            'slug' => 'strawberry',
            'active' => true,
        ]);
    }
}
