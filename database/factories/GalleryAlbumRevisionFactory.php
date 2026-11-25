<?php

namespace Database\Factories;

use App\Models\GalleryAlbum;
use App\Models\GalleryAlbumRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GalleryAlbumRevision>
 */
class GalleryAlbumRevisionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gallery_album_id' => GalleryAlbum::factory(),
            'revision_number' => 1,
            'slug' => fake()->slug(4),
            'title' => fake()->sentence(5),
            'summary' => fake()->sentence(18),
            'album_date' => fake()->date(),
            'category' => 'school-life',
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
