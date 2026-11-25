<?php

namespace Database\Factories;

use App\Models\GalleryAlbum;
use App\Models\GalleryAlbumRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GalleryAlbum>
 */
class GalleryAlbumFactory extends Factory
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
        return $this->afterCreating(function (GalleryAlbum $album): void {
            $revision = GalleryAlbumRevision::factory()->create([
                'gallery_album_id' => $album->id,
                'slug' => $album->slug,
                'workflow_state' => 'published',
                'published_at' => now('UTC'),
            ]);

            $album->update([
                'publication_state' => 'published',
                'published_revision_id' => $revision->id,
                'published_at' => $revision->published_at,
            ]);
        });
    }
}
