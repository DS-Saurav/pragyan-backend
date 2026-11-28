<?php

namespace Tests\Feature;

use App\Models\GalleryAlbum;
use App\Models\GalleryCategory;
use App\Models\GalleryPhoto;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GalleryPublicVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_gallery_only_exposes_active_albums(): void
    {
        $live = $this->createAlbum('community-learning-day', 'Community Learning Day', true);
        $inactive = $this->createAlbum('private-gallery-draft', 'Private Gallery Draft', false);

        $this->getJson('/api/public/gallery')
            ->assertOk()
            ->assertSee($live->title)
            ->assertDontSee($inactive->title);

        $this->getJson('/api/public/gallery/'.$live->slug)
            ->assertOk()
            ->assertSee($live->title);

        $this->getJson('/api/public/gallery/'.$inactive->slug)->assertNotFound();
        $this->getJson('/api/public/gallery/archive')->assertOk();
    }

    public function test_empty_gallery_returns_empty_paginated_data(): void
    {
        $this->getJson('/api/public/gallery')
            ->assertOk()
            ->assertJsonCount(0, 'albums.data')
            ->assertJsonPath('albums.total', 0);
    }

    public function test_featured_gallery_preview_requires_an_active_album(): void
    {
        $album = $this->createAlbum('featured-science-album', 'Featured Science Album', true);
        Setting::set('website_featured_gallery_album_id', $album->id);

        $this->getJson('/api/public/homepage')
            ->assertOk()
            ->assertJsonPath('featuredAlbum.slug', 'featured-science-album')
            ->assertJsonPath('featuredAlbum.title', 'Featured Science Album');

        $album->update(['is_active' => false]);

        $this->getJson('/api/public/homepage')
            ->assertOk()
            ->assertJsonPath('featuredAlbum', null);
    }

    private function createAlbum(string $slug, string $title, bool $isActive): GalleryAlbum
    {
        $category = GalleryCategory::firstOrCreate(
            ['slug' => 'learning'],
            ['name' => 'Learning', 'is_active' => true],
        );

        $album = GalleryAlbum::create([
            'category_id' => $category->id,
            'title' => $title,
            'slug' => $slug,
            'description' => 'A concise public summary of this school moment.',
            'cover_image' => "gallery/covers/{$slug}.jpg",
            'event_date' => '2026-10-28',
            'is_active' => $isActive,
        ]);

        GalleryPhoto::create([
            'album_id' => $album->id,
            'alt' => 'A descriptive public alternative text.',
            'caption' => 'A school learning moment',
            'image_path' => "gallery/photos/{$slug}-1.jpg",
            'sort_order' => 0,
        ]);

        return $album;
    }
}
