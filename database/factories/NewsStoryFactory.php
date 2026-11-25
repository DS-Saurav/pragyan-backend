<?php

namespace Database\Factories;

use App\Models\NewsStory;
use App\Models\NewsStoryRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsStory>
 */
class NewsStoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(4),
            'publication_state' => 'draft',
            'published_revision_id' => null,
            'scheduled_revision_id' => null,
            'scheduled_for' => null,
            'published_at' => null,
            'withdrawn_at' => null,
            'archived_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->afterCreating(function (NewsStory $story): void {
            $revision = NewsStoryRevision::factory()->create([
                'news_story_id' => $story->id,
                'slug' => $story->slug,
                'workflow_state' => 'published',
                'published_at' => now('UTC'),
            ]);

            $story->update([
                'publication_state' => 'published',
                'published_revision_id' => $revision->id,
                'published_at' => $revision->published_at,
            ]);
        });
    }
}
