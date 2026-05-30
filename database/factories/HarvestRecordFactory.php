<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\HarvestRecord;
use App\Models\HarvestUpload;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HarvestRecord>
 */
class HarvestRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $company = Company::factory();
        $product = Product::factory()->for($company);

        return [
            'company_id' => $company,
            'upload_id' => HarvestUpload::factory()->for($company)->for($product),
            'product_id' => $product,
            'harvester_number' => $this->faker->numberBetween(1, 100),
            'weight' => $this->faker->randomFloat(2, 10, 100),
            'tare' => $this->faker->randomFloat(2, 0, 10),
            'gross' => $this->faker->randomFloat(2, 10, 110),
            'weighed_at' => $this->faker->dateTime(),
            'corrected' => false,
        ];
    }
}
