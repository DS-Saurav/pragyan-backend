<?php

namespace Database\Factories;

use App\Models\AdmissionCycle;
use App\Models\AdmissionCycleStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdmissionCycleStep>
 */
class AdmissionCycleStepFactory extends Factory
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
            'title' => fake()->sentence(4),
            'body' => fake()->sentence(12),
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }
}
