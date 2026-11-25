<?php

namespace Database\Factories;

use App\Models\NewsStory;
use App\Models\NewsStoryRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsStoryRevision>
 */
class NewsStoryRevisionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'news_story_id' => NewsStory::factory(),
            'revision_number' => 1,
            'slug' => fake()->slug(4),
            'title' => fake()->sentence(5),
            'summary' => fake()->sentence(18),
            'body_markdown' => fake()->paragraphs(3, true),
            'category' => 'school-life',
            'public_attribution' => null,
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
