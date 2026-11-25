<?php

namespace Database\Factories;

use App\Models\Notice;
use App\Models\NoticeSlug;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NoticeSlug>
 */
class NoticeSlugFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'notice_id' => Notice::factory(),
            'slug' => fake()->unique()->slug(4),
        ];
    }
}
