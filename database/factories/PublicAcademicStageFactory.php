<?php

namespace Database\Factories;

use App\Models\PublicAcademicStage;
use App\Models\PublicPageRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PublicAcademicStage>
 */
class PublicAcademicStageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_page_revision_id' => PublicPageRevision::factory(),
            'title' => fake()->words(3, true),
            'stage_label' => fake()->optional()->words(2, true),
            'description_markdown' => fake()->optional()->paragraph(),
            'key_points' => null,
            'display_order' => 0,
        ];
    }
}
