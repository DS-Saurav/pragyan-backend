<?php

namespace Database\Factories;

use App\Models\AdmissionCycle;
use App\Models\AdmissionCycleGrade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdmissionCycleGrade>
 */
class AdmissionCycleGradeFactory extends Factory
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
            'grade' => 'Grade '.fake()->numberBetween(1, 12),
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }
}
