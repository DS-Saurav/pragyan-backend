<?php

namespace Tests\Feature;

use App\Models\GalleryAlbum;
use App\Models\GalleryAlbumRevision;
use App\Models\GalleryAlbumSlug;
use App\Models\MediaAsset;
use App\Models\MediaAttachment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GalleryPublicVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_gallery_and_navigation_only_expose_currently_published_albums(): void
    {
        $draft = GalleryAlbum::factory()->create(['slug' => 'private-gallery-draft']);
        GalleryAlbumRevision::factory()->create([
            'gallery_album_id' => $draft->id,
            'title' => 'Private Gallery Draft',
        ]);
        $live = $this->publishedAlbum('community-learning-day', 'Community Learning Day');
        $withdrawn = $this->publishedAlbum('withdrawn-gallery-album', 'Withdrawn Gallery Album');
        $withdrawn->update([
            'publication_state' => 'withdrawn',
            'withdrawn_at' => now('Asia/Kathmandu'),
        ]);
        $scheduled = GalleryAlbum::factory()->create([
            'slug' => 'future-gallery-album',
            'publication_state' => 'scheduled',
            'scheduled_for' => now()->addDay(),
        ]);
        GalleryAlbumRevision::factory()->create([
            'gallery_album_id' => $scheduled->id,
            'title' => 'Future Gallery Album',
            'workflow_state' => 'scheduled',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('href="'.route('public.gallery').'"', false);

        $this->get(route('public.gallery'))
            ->assertOk()
            ->assertSee($live->publishedRevision->title)
            ->assertDontSee('Private Gallery Draft')
            ->assertDontSee('Withdrawn Gallery Album')
            ->assertDontSee('Future Gallery Album');

        $this->get(route('public.gallery.show', $live->slug))
            ->assertOk()
            ->assertSee($live->publishedRevision->title);

        $this->get(route('public.gallery.show', $draft->slug))->assertNotFound();
        $this->get(route('public.gallery.show', $withdrawn->slug))->assertNotFound();
        $this->get(route('public.gallery.show', $scheduled->slug))->assertNotFound();
        $this->get(route('public.gallery.archive'))->assertOk();
    }

    public function test_empty_gallery_has_an_honest_empty_state_and_is_hidden_from_navigation(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('href="'.route('public.gallery').'"', false);

        $this->get(route('public.gallery'))
            ->assertOk()
            ->assertSee('No published albums yet');
    }

    public function test_former_public_slug_redirects_and_ordered_public_photos_do_not_expose_original_paths(): void
    {
        $album = $this->publishedAlbum(
            'school-library-renewal',
            'School Library Renewal',
            ['First ordered photograph', 'Second ordered photograph'],
        );
        GalleryAlbumSlug::create([
            'gallery_album_id' => $album->id,
            'slug' => 'library-renewal-before-opening',
        ]);

        $this->get(route('public.gallery.show', 'library-renewal-before-opening'))
            ->assertRedirect(route('public.gallery.show', $album->slug))
            ->assertStatus(301);

        $asset = $album->publishedRevision->photos->firstOrFail()->asset;
        $this->get(route('public.gallery.show', $album->slug))
            ->assertOk()
            ->assertSeeInOrder(['First ordered photograph', 'Second ordered photograph'])
            ->assertDontSee((string) $asset->original_path)
            ->assertDontSee(route('admin.media.original', $asset));
    }

    public function test_featured_gallery_preview_requires_a_currently_displayable_album(): void
    {
        $album = $this->publishedAlbum('featured-science-album', 'Featured Science Album');
        Setting::set('website_featured_gallery_album_id', $album->id);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Featured gallery')
            ->assertSee('Featured Science Album');

        $album->update([
            'publication_state' => 'withdrawn',
            'withdrawn_at' => now('Asia/Kathmandu'),
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Featured gallery')
            ->assertDontSee('Featured Science Album');
    }

    /** @param array<int, string> $captions */
    private function publishedAlbum(string $slug, string $title, array $captions = ['A school learning moment']): GalleryAlbum
    {
        $owner = User::factory()->create();
        $album = GalleryAlbum::factory()->create(['slug' => $slug]);
        $revision = GalleryAlbumRevision::factory()->create([
            'gallery_album_id' => $album->id,
            'slug' => $slug,
            'title' => $title,
            'summary' => 'A concise public summary of this school moment.',
            'album_date' => '2026-10-28',
            'category' => 'Learning',
            'workflow_state' => 'published',
            'published_at' => now('UTC'),
        ]);
        $coverAsset = $this->safeAsset($owner, 'Cover image for '.$title);
        MediaAttachment::create([
            'media_asset_id' => $coverAsset->id,
            'attachable_type' => 'gallery-album-revision',
            'attachable_id' => $revision->id,
            'placement' => 'cover',
            'display_order' => 0,
            'alt_text' => 'A school learning moment shown on the album cover.',
        ]);

        foreach ($captions as $index => $caption) {
            $asset = $this->safeAsset($owner, 'Photo '.($index + 1).' for '.$title);
            MediaAttachment::create([
                'media_asset_id' => $asset->id,
                'attachable_type' => 'gallery-album-revision',
                'attachable_id' => $revision->id,
                'placement' => 'photo',
                'display_order' => $index,
                'caption' => $caption,
                'alt_text' => 'A descriptive public alternative text for photograph '.($index + 1).'.',
            ]);
        }

        $album->update([
            'publication_state' => 'published',
            'published_revision_id' => $revision->id,
            'published_at' => $revision->published_at,
        ]);

        return $album->fresh(['publishedRevision.cover.asset', 'publishedRevision.photos.asset']);
    }

    private function safeAsset(User $owner, string $title): MediaAsset
    {
        $filename = (string) Str::uuid();

        return MediaAsset::create([
            'disk' => 'media_originals',
            'original_path' => 'originals/'.$filename.'.jpg',
            'public_disk' => 'media_derivatives',
            'public_path' => 'published/'.$filename.'.jpg',
            'original_filename' => 'gallery.jpg',
            'storage_filename' => $filename.'.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 2048,
            'width' => 1200,
            'height' => 800,
            'checksum' => hash('sha256', $filename),
            'title' => $title,
            'default_alt_text' => 'Students taking part in a school learning activity.',
            'category' => 'gallery',
            'uploaded_by' => $owner->id,
            'status' => 'published',
            'published_by' => $owner->id,
            'published_at' => now(),
            'consent_state' => 'confirmed',
            'consent_confirmed_by' => $owner->id,
            'consent_confirmed_at' => now(),
        ]);
    }
}
