<?php

namespace Database\Factories;

use App\Models\PublicPage;
use App\Models\PublicPageRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PublicPageRevision>
 */
class PublicPageRevisionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_page_id' => PublicPage::factory(),
            'revision_number' => 1,
            'title' => fake()->sentence(4),
            'introduction_markdown' => fake()->paragraph(),
            'history_markdown' => null,
            'mission_markdown' => null,
            'vision_markdown' => null,
            'approach_values' => null,
            'facilities_markdown' => null,
            'workflow_state' => 'draft',
            'submitted_at' => null,
            'reviewed_at' => null,
            'published_at' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'workflow_state' => 'approved',
            'reviewed_at' => now('Asia/Kathmandu'),
        ]);
    }
}
