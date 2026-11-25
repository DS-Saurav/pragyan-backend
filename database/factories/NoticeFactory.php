<?php

namespace Database\Factories;

use App\Models\Notice;
use App\Models\NoticeRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notice>
 */
class NoticeFactory extends Factory
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
            'title' => fake()->sentence(5),
            'content' => fake()->paragraphs(2, true),
            'audience' => 'public',
            'posted_by' => fake()->name(),
            'date' => now('Asia/Kathmandu')->toDateString(),
            'status' => 'draft',
            'publication_state' => 'draft',
            'published_revision_id' => null,
            'scheduled_revision_id' => null,
            'scheduled_for' => null,
            'published_at' => null,
            'expires_at' => null,
            'priority' => null,
            'is_urgent' => false,
            'withdrawn_at' => null,
            'archived_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->afterCreating(function (Notice $notice): void {
            $publishedAt = now('Asia/Kathmandu')->utc();
            $revision = NoticeRevision::factory()->create([
                'notice_id' => $notice->id,
                'slug' => $notice->slug,
                'title' => $notice->title,
                'content' => $notice->content,
                'audience' => $notice->audience,
                'date' => $notice->date,
                'workflow_state' => 'published',
                'published_at' => $publishedAt,
            ]);

            $notice->update([
                'status' => 'published',
                'publication_state' => 'published',
                'published_revision_id' => $revision->id,
                'published_at' => $publishedAt,
            ]);
        });
    }
}
