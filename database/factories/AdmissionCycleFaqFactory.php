<?php

namespace Database\Factories;

use App\Models\AdmissionCycle;
use App\Models\AdmissionCycleFaq;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdmissionCycleFaq>
 */
class AdmissionCycleFaqFactory extends Factory
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
            'question' => fake()->sentence(6),
            'answer' => fake()->sentence(14),
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }
}
