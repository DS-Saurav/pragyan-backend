<?php

namespace Database\Factories;

use App\Models\AdmissionCycle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdmissionCycle>
 */
class AdmissionCycleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'label' => fake()->unique()->numerify('Academic Year 20##'),
            'public_status' => 'open',
            'public_intro' => fake()->sentence(12),
            'display_starts_on' => now('Asia/Kathmandu')->subDay()->toDateString(),
            'display_ends_on' => now('Asia/Kathmandu')->addMonths(2)->toDateString(),
            'publication_state' => 'draft',
            'published_at' => null,
            'public_phone' => null,
            'public_email' => null,
            'primary_action_label' => null,
            'primary_action_url' => null,
            'fee_summary' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'publication_state' => 'published',
            'published_at' => now('Asia/Kathmandu'),
        ]);
    }
}
