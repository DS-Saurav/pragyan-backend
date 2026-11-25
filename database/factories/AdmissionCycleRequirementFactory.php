<?php

namespace Database\Factories;

use App\Models\AdmissionCycle;
use App\Models\AdmissionCycleRequirement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdmissionCycleRequirement>
 */
class AdmissionCycleRequirementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'admission_cycle_id' => AdmissionCycle::factory(),
            'label' => fake()->sentence(3),
            'detail' => fake()->sentence(8),
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }
}
