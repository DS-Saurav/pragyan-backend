<?php

namespace Database\Factories;

use App\Models\GalleryAlbum;
use App\Models\GalleryAlbumSlug;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GalleryAlbumSlug>
 */
class GalleryAlbumSlugFactory extends Factory
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
            'slug' => fake()->unique()->slug(4),
        ];
    }
}
