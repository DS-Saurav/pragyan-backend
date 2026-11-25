<?php

namespace Tests\Feature;

use App\Models\GalleryAlbumRevision;
use App\Models\MediaAsset;
use App\Models\MediaAttachment;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserRole;
use App\Services\Gallery\GalleryPublisher;
use App\Services\Gallery\GalleryVisibility;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GalleryEditorialWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_album_stays_visible_until_a_scheduled_replacement_is_due(): void
    {
        config(['permissions.roles.teacher' => ['gallery.author', 'gallery.review', 'gallery.publish']]);
        $author = $this->editor();
        $reviewer = $this->editor();
        $publisher = app(GalleryPublisher::class);
        $album = $publisher->createDraft($author, $this->draftData());
        $firstRevision = $album->revisions->firstOrFail();
        $this->attachCompletePhotoSet($firstRevision, $this->imageAsset($reviewer));
        $publisher->submit($author, $album, $firstRevision);
        $publisher->approve($reviewer, $album, $firstRevision);
        $publisher->publish($reviewer, $album, $firstRevision);

        $album = $album->fresh();
        $this->assertSame('published', $album->publication_state);
        $this->assertSame($firstRevision->id, $album->published_revision_id);
        $this->assertTrue(app(GalleryVisibility::class)->hasPublished());

        $replacement = $publisher->saveDraft($author, $album, $this->draftData([
            'slug' => 'new-term-gallery',
            'title' => 'New Term Gallery',
        ]));
        $this->assertSame('draft', $replacement->workflow_state);
        $this->assertSame($firstRevision->id, $album->fresh()->published_revision_id);
        $this->attachCompletePhotoSet($replacement, $this->imageAsset($reviewer));
        $publisher->submit($author, $album, $replacement);
        $publisher->approve($reviewer, $album, $replacement);

        $scheduledFor = CarbonImmutable::now(GalleryPublisher::TIMEZONE)->addMinutes(10);
        $publisher->publish($reviewer, $album, $replacement, $scheduledFor);

        $scheduledAlbum = $album->fresh();
        $this->assertSame($firstRevision->id, $scheduledAlbum->published_revision_id);
        $this->assertSame($replacement->id, $scheduledAlbum->scheduled_revision_id);
        $this->assertSame('scheduled', $replacement->fresh()->workflow_state);
        $this->assertSame($album->slug, app(GalleryVisibility::class)->findDisplayableSlug($album->slug)['album']?->slug);

        $publisher->releaseDue($scheduledFor->addSecond());

        $released = $album->fresh();
        $this->assertSame($replacement->id, $released->published_revision_id);
        $this->assertSame('new-term-gallery', $released->slug);
        $this->assertDatabaseHas('gallery_album_slugs', [
            'gallery_album_id' => $released->id,
            'slug' => 'learning-at-pragyan',
        ]);
        $former = app(GalleryVisibility::class)->findDisplayableSlug('learning-at-pragyan', $scheduledFor->addSecond());
        $this->assertSame($released->id, $former['album']?->id);
        $this->assertSame($released->id, $former['redirect']?->id);
    }

    public function test_publication_requires_a_cover_and_at_least_one_ordered_photo_with_current_consent(): void
    {
        config(['permissions.roles.teacher' => ['gallery.author', 'gallery.review', 'gallery.publish']]);
        $author = $this->editor();
        $reviewer = $this->editor();
        $publisher = app(GalleryPublisher::class);
        $album = $publisher->createDraft($author, $this->draftData());
        $revision = $album->revisions->firstOrFail();
        $asset = $this->imageAsset($reviewer);
        $this->attachCover($revision, $asset);
        $publisher->submit($author, $album, $revision);
        $publisher->approve($reviewer, $album, $revision);

        try {
            $publisher->publish($reviewer, $album, $revision);
            $this->fail('Publishing must require at least one ordered Gallery photo.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('photo', json_encode($exception->errors()));
        }

        $this->attachPhoto($revision, $asset, 0);
        $asset->update([
            'consent_state' => 'withdrawn',
            'consent_withdrawn_at' => now(),
        ]);

        try {
            $publisher->publish($reviewer, $album, $revision);
            $this->fail('Withdrawn consent must prevent Gallery publication.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('consent', json_encode($exception->errors()));
        }

        $this->assertSame('approved', $revision->fresh()->workflow_state);
        $this->assertSame('review', $album->fresh()->publication_state);
    }

    public function test_withdraw_archive_and_restore_clear_public_and_featured_state_safely(): void
    {
        config(['permissions.roles.teacher' => ['gallery.author', 'gallery.review', 'gallery.publish']]);
        $author = $this->editor();
        $reviewer = $this->editor();
        $publisher = app(GalleryPublisher::class);
        $album = $publisher->createDraft($author, $this->draftData());
        $revision = $album->revisions->firstOrFail();
        $this->attachCompletePhotoSet($revision, $this->imageAsset($reviewer));
        $publisher->submit($author, $album, $revision);
        $publisher->approve($reviewer, $album, $revision);
        $publisher->publish($reviewer, $album, $revision);
        $album = $album->fresh();
        $publisher->setFeatured($reviewer, $album);

        $publisher->withdraw($reviewer, $album);

        $this->assertSame('withdrawn', $album->fresh()->publication_state);
        $this->assertNull(Setting::get(GalleryPublisher::FEATURED_SETTING));
        $this->assertFalse(app(GalleryVisibility::class)->hasPublished());

        $publisher->archive($reviewer, $album);
        $this->assertSame('archived', $album->fresh()->publication_state);

        $publisher->restore($reviewer, $album);
        $restored = $album->fresh();
        $this->assertSame('draft', $restored->publication_state);
        $this->assertNull($restored->published_revision_id);
    }

    public function test_duplicate_slugs_and_unsafe_images_cannot_be_published_unless_marked_decorative(): void
    {
        config(['permissions.roles.teacher' => ['gallery.author', 'gallery.review', 'gallery.publish']]);
        $author = $this->editor();
        $reviewer = $this->editor();
        $publisher = app(GalleryPublisher::class);
        $publisher->createDraft($author, $this->draftData(['slug' => 'one-school-moment']));

        try {
            $publisher->createDraft($author, $this->draftData(['slug' => 'one-school-moment']));
            $this->fail('A duplicate Gallery slug must remain unavailable.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('URL', json_encode($exception->errors()));
        }

        $album = $publisher->createDraft($author, $this->draftData(['slug' => 'safe-visual-story']));
        $revision = $album->revisions->firstOrFail();
        $asset = $this->imageAsset($reviewer);
        $asset->update([
            'public_path' => null,
            'default_alt_text' => null,
        ]);
        $cover = $this->attachCover($revision, $asset);
        $photo = $this->attachPhoto($revision, $asset, 0);
        $cover->update(['alt_text' => null]);
        $photo->update(['alt_text' => null]);
        $publisher->submit($author, $album, $revision);
        $publisher->approve($reviewer, $album, $revision);

        try {
            $publisher->publish($reviewer, $album, $revision);
            $this->fail('An asset without a public derivative must not be published.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('derivative', json_encode($exception->errors()));
        }

        $asset->update(['public_path' => 'published/reinstated-gallery-image.jpg']);
        try {
            $publisher->publish($reviewer, $album, $revision);
            $this->fail('A meaningful public alt text or decorative declaration is required.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('alt text', json_encode($exception->errors()));
        }

        $asset->update(['is_decorative' => true]);
        $publisher->publish($reviewer, $album, $revision);

        $this->assertSame('published', $album->fresh()->publication_state);
    }

    private function editor(): User
    {
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role' => 'teacher']);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, string|null>
     */
    private function draftData(array $overrides = []): array
    {
        return array_replace([
            'slug' => 'learning-at-pragyan',
            'title' => 'Learning at Pragyan',
            'summary' => 'A photo collection from joyful learning in the new school term.',
            'album_date' => '2026-11-10',
            'category' => 'school-life',
        ], $overrides);
    }

    private function imageAsset(User $owner): MediaAsset
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
            'title' => 'Gallery image',
            'default_alt_text' => 'Students collaborating in class.',
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

    private function attachCompletePhotoSet(GalleryAlbumRevision $revision, MediaAsset $asset): void
    {
        $this->attachCover($revision, $asset);
        $this->attachPhoto($revision, $asset, 0);
    }

    private function attachCover(GalleryAlbumRevision $revision, MediaAsset $asset): MediaAttachment
    {
        return MediaAttachment::create([
            'media_asset_id' => $asset->id,
            'attachable_type' => 'gallery-album-revision',
            'attachable_id' => $revision->id,
            'placement' => 'cover',
            'display_order' => 0,
            'alt_text' => 'Students collaborating in class.',
        ]);
    }

    private function attachPhoto(GalleryAlbumRevision $revision, MediaAsset $asset, int $displayOrder): MediaAttachment
    {
        return MediaAttachment::create([
            'media_asset_id' => $asset->id,
            'attachable_type' => 'gallery-album-revision',
            'attachable_id' => $revision->id,
            'placement' => 'photo',
            'display_order' => $displayOrder,
            'alt_text' => 'Students collaborating in class.',
        ]);
    }
}
