<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Harvester;
use App\Models\HarvesterAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HarvesterAssignment>
 */
class HarvesterAssignmentFactory extends Factory
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
            'harvester_id' => Harvester::factory(),
            'year' => now()->year,
            'number' => $this->faker->numberBetween(1, 200),
        ];
    }
}
