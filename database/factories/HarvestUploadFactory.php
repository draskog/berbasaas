<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\HarvestUpload;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HarvestUpload>
 */
class HarvestUploadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $dateFrom = $this->faker->dateTime();
        $dateTo = $this->faker->dateTimeBetween($dateFrom);

        return [
            'company_id' => Company::factory(),
            'product_id' => Product::factory(),
            'uploaded_by' => User::factory(),
            'original_filename' => $this->faker->word().'.csv',
            'record_count' => $this->faker->numberBetween(5, 500),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
    }
}
