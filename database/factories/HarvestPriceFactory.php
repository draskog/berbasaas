<?php

namespace Database\Factories;

use App\Models\HarvestPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HarvestPrice>
 */
class HarvestPriceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'price_per_kg' => $this->faker->randomFloat(2, 0, 100),
            'effective_from' => $this->faker->dateTime(),
            'effective_to' => $this->faker->optional()->dateTime(),
        ];
    }
}
