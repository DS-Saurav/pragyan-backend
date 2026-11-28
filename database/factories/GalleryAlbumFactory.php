<?php

namespace Database\Factories;

use App\Models\GalleryAlbum;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GalleryAlbum>
 */
class GalleryAlbumFactory extends Factory
{
    protected $model = GalleryAlbum::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $title = fake()->sentence(3);

        return [
            'category_id' => null,
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(100, 9999),
            'description' => fake()->sentence(12),
            'cover_image_id' => null,
            'event_date' => fake()->date('Y-m-d'),
            'is_active' => true,
        ];
    }
}
