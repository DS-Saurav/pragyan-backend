<?php

namespace Database\Factories;

use App\Models\NewsStory;
use App\Models\NewsStorySlug;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsStorySlug>
 */
class NewsStorySlugFactory extends Factory
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
            'slug' => fake()->unique()->slug(4),
        ];
    }
}
