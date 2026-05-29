<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Harvester;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Harvester>
 */
class HarvesterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => 'Harvester '.$this->faker->numberBetween(1, 100),
            'prefix' => null,
            'active' => true,
        ];
    }
}
