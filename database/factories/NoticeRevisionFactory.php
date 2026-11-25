<?php

namespace Database\Factories;

use App\Models\Notice;
use App\Models\NoticeRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NoticeRevision>
 */
class NoticeRevisionFactory extends Factory
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
            'revision_number' => 1,
            'slug' => fake()->slug(4),
            'title' => fake()->sentence(5),
            'content' => fake()->paragraphs(3, true),
            'audience' => 'public',
            'date' => now('Asia/Kathmandu')->toDateString(),
            'priority' => null,
            'is_urgent' => false,
            'publication_start_at' => null,
            'expires_at' => null,
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
