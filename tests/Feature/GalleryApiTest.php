<?php

namespace Tests\Feature;

use App\Models\GalleryAlbum;
use App\Models\GalleryCategory;
use App\Models\GalleryPhoto;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GalleryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_gallery_listing_returns_active_albums_with_cover_urls_photo_counts_and_category_filtering(): void
    {
        Storage::fake('r2');

        $scienceCat = GalleryCategory::create(['name' => 'Science', 'slug' => 'science', 'is_active' => true]);
        $sportsCat = GalleryCategory::create(['name' => 'Sports', 'slug' => 'sports', 'is_active' => true]);

        $science = $this->createAlbum('science-fair-2026', 'Science Fair 2026', $scienceCat->id, ['Solar model', 'Robotics demo']);
        $sports = $this->createAlbum('sports-day-2026', 'Annual Sports Day', $sportsCat->id, ['Relay race'], true, false);

        // Inactive album must be excluded
        $this->createAlbum('hidden-album', 'Hidden Album', $scienceCat->id, ['Hidden'], false);

        $response = $this->getJson('/api/public/gallery');
        $response->assertOk()
            ->assertJsonPath('isArchive', false)
            ->assertJsonPath('activeCategory', null)
            ->assertJsonCount(2, 'albums.data')
            ->assertJsonPath('categories', ['Science', 'Sports']);

        $data = collect($response->json('albums.data'))->keyBy('slug');
        $this->assertSame(2, $data[$science->slug]['photos_count']);
        $this->assertSame(1, $data[$sports->slug]['photos_count']);
        $this->assertStringContainsString('gallery/covers/', $data[$science->slug]['cover']['url']);
        $this->assertStringContainsString('gallery/photos/sports-day-2026-0.jpg', $data[$sports->slug]['cover']['url']);
        $this->assertArrayNotHasKey('photos', $data[$science->slug]);

        // Filter by category name or slug
        $filtered = $this->getJson('/api/public/gallery?category=Science');
        $filtered->assertOk()
            ->assertJsonPath('activeCategory', 'Science')
            ->assertJsonCount(1, 'albums.data')
            ->assertJsonPath('albums.data.0.slug', $science->slug);

        // Archive endpoint
        $this->getJson('/api/public/gallery/archive')
            ->assertOk()
            ->assertJsonPath('isArchive', true)
            ->assertJsonCount(2, 'albums.data');
    }

    public function test_public_album_detail_returns_ordered_photos_and_404_for_inactive(): void
    {
        Storage::fake('r2');

        $cat = GalleryCategory::create(['name' => 'Campus', 'slug' => 'campus', 'is_active' => true]);
        $album = $this->createAlbum('library-renewal-2026', 'Library Renewal 2026', $cat->id, ['First ordered photo', 'Second ordered photo']);
        $inactive = $this->createAlbum('inactive-album', 'Inactive Album', $cat->id, ['Hidden'], false);

        $detail = $this->getJson('/api/public/gallery/library-renewal-2026');
        $detail->assertOk()
            ->assertJsonPath('album.slug', 'library-renewal-2026')
            ->assertJsonPath('album.title', 'Library Renewal 2026')
            ->assertJsonPath('album.category', 'Campus')
            ->assertJsonPath('album.photos_count', 2)
            ->assertJsonCount(2, 'album.photos')
            ->assertJsonPath('album.photos.0.caption', 'First ordered photo')
            ->assertJsonPath('album.photos.1.caption', 'Second ordered photo')
            ->assertJsonPath('album.photos.0.sort_order', 0)
            ->assertJsonPath('album.photos.1.sort_order', 1);

        $this->assertStringContainsString('gallery/photos/', $detail->json('album.photos.0.url'));

        $this->getJson('/api/public/gallery/'.$inactive->slug)->assertNotFound();
        $this->getJson('/api/public/gallery/nonexistent-album')->assertNotFound();
    }

    public function test_admin_can_manage_categories_albums_photos_and_reordering(): void
    {
        Storage::fake('r2');
        config(['permissions.roles.teacher' => ['gallery.author']]);

        $author = $this->userWithRole('teacher');
        $unauthorized = $this->userWithRole('librarian');

        $this->getJson('/api/admin/gallery')->assertUnauthorized();

        Sanctum::actingAs($unauthorized);
        $this->getJson('/api/admin/gallery')->assertForbidden();

        Sanctum::actingAs($author);

        // Create category
        $catId = $this->postJson('/api/admin/gallery/categories', [
            'name' => 'Arts & Crafts',
        ])->assertCreated()->json('category.id');

        // Create album with cover upload
        $albumId = $this->post('/api/admin/gallery', [
            'title' => 'Art Exhibition',
            'description' => 'Student artworks from term two.',
            'category_id' => $catId,
            'event_date' => '2026-11-15',
            'cover_image' => UploadedFile::fake()->image('cover.jpg', 800, 600),
        ])->assertCreated()->json('album.id');

        // Upload 2 photos
        $uploadRes = $this->post("/api/admin/gallery/{$albumId}/photos", [
            'images' => [
                UploadedFile::fake()->image('one.jpg', 800, 600),
                UploadedFile::fake()->image('two.jpg', 800, 600),
            ],
            'alt' => 'Exhibition artwork',
        ])->assertCreated();

        $p1 = $uploadRes->json('photos.0.id');
        $p2 = $uploadRes->json('photos.1.id');

        // Reorder photos
        $this->patchJson("/api/admin/gallery/{$albumId}/photos/reorder", [
            'photo_ids' => [$p2, $p1],
        ])->assertOk();

        $this->assertDatabaseHas('gallery', ['id' => $p2, 'sort_order' => 0]);
        $this->assertDatabaseHas('gallery', ['id' => $p1, 'sort_order' => 1]);

        // Author cannot delete album (requires gallery.publish)
        $this->deleteJson("/api/admin/gallery/{$albumId}")->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    /** @param array<int, string> $captions */
    private function createAlbum(
        string $slug,
        string $title,
        ?int $categoryId,
        array $captions,
        bool $isActive = true,
        bool $withExplicitCover = true,
    ): GalleryAlbum {
        $coverPath = $withExplicitCover ? "gallery/covers/{$slug}.jpg" : null;
        if ($coverPath !== null) {
            Storage::disk('r2')->put($coverPath, 'cover');
        }

        $album = GalleryAlbum::create([
            'category_id' => $categoryId,
            'title' => $title,
            'slug' => $slug,
            'description' => 'Public description for '.$title,
            'cover_image' => $coverPath,
            'event_date' => '2026-10-28',
            'is_active' => $isActive,
        ]);

        foreach ($captions as $index => $caption) {
            $photoPath = "gallery/photos/{$slug}-{$index}.jpg";
            Storage::disk('r2')->put($photoPath, 'photo');

            GalleryPhoto::create([
                'album_id' => $album->id,
                'alt' => 'Alt for '.$caption,
                'caption' => $caption,
                'image_path' => $photoPath,
                'sort_order' => $index,
            ]);
        }

        return $album;
    }
}
