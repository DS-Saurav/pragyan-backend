<?php

namespace Tests\Feature;

use App\Models\GalleryAlbum;
use App\Models\GalleryAlbumRevision;
use App\Models\MediaAsset;
use App\Models\MediaAttachment;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserRole;
use App\Services\Media\MediaLibrary;
use App\Services\Media\MediaUsage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GalleryMediaWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_gallery_author_can_manage_cover_and_ordered_draft_photos_only(): void
    {
        config(['permissions.roles.teacher' => ['gallery.author']]);
        $author = $this->user('teacher');
        $otherUser = $this->user('principal');
        $album = GalleryAlbum::factory()->create(['created_by' => $author->id, 'updated_by' => $author->id]);
        $revision = GalleryAlbumRevision::factory()->create([
            'gallery_album_id' => $album->id,
            'created_by' => $author->id,
            'updated_by' => $author->id,
        ]);
        $firstAsset = $this->safeAsset($author);
        $secondAsset = $this->safeAsset($author);
        $thirdAsset = $this->safeAsset($author);

        $cover = $this->library()->replaceGalleryAlbumRevisionCover($author, $revision, $firstAsset, [
            'caption' => 'Opening assembly',
            'alt_text' => 'Students arriving for the school assembly',
            'focal_x' => 34,
            'focal_y' => 45,
        ]);
        $firstPhoto = $this->library()->addGalleryAlbumRevisionPhoto($author, $revision, $firstAsset, [
            'caption' => 'Students gathering',
            'alt_text' => 'Students gathering in the school courtyard',
        ]);
        $secondPhoto = $this->library()->addGalleryAlbumRevisionPhoto($author, $revision, $secondAsset, [
            'caption' => 'Class activity',
            'alt_text' => 'Students taking part in a class activity',
        ]);

        $this->assertSame('gallery-album-revision', $cover->attachable_type);
        $this->assertSame('cover', $cover->placement);
        $this->assertSame(0, $cover->display_order);
        $this->assertSame(0, $firstPhoto->display_order);
        $this->assertSame(1, $secondPhoto->display_order);

        $this->library()->reorderGalleryAlbumRevisionPhotos($author, $revision, [$secondPhoto->id, $firstPhoto->id]);
        $this->assertSame(0, $secondPhoto->fresh()->display_order);
        $this->assertSame(1, $firstPhoto->fresh()->display_order);

        $updated = $this->library()->updateGalleryAlbumRevisionPhoto($author, $revision, $firstPhoto, [
            'caption' => 'Students exploring together',
            'alt_text' => 'Students exploring a learning activity together',
        ]);
        $this->assertSame('Students exploring together', $updated->caption);
        $this->assertSame($firstAsset->id, $updated->media_asset_id);

        $replaced = $this->library()->replaceGalleryAlbumRevisionPhoto($author, $revision, $firstPhoto, $thirdAsset, [
            'caption' => 'A refreshed learning moment',
            'alt_text' => 'Students sharing a learning moment',
        ]);
        $this->assertSame($firstPhoto->id, $replaced->id);
        $this->assertSame($thirdAsset->id, $replaced->media_asset_id);

        $this->library()->removeGalleryAlbumRevisionPhoto($author, $revision, $secondPhoto);
        $this->assertDatabaseCount('media_attachments', 2);
        $this->assertSame(0, $firstPhoto->fresh()->display_order);

        try {
            $this->library()->addGalleryAlbumRevisionPhoto($otherUser, $revision, $secondAsset);
            $this->fail('A user without gallery.author added a Gallery photo.');
        } catch (AuthorizationException $exception) {
            $this->assertSame(403, $exception->status() ?? 403);
        }

        $revision->update(['workflow_state' => 'review']);
        $this->rejects(
            fn () => $this->library()->addGalleryAlbumRevisionPhoto($author, $revision, $secondAsset),
            'draft Gallery revision',
        );
    }

    public function test_current_published_and_scheduled_gallery_media_blocks_lifecycle_changes_and_detach(): void
    {
        $admin = $this->user();
        $publishedAsset = $this->safeAsset($admin);
        $publishedAlbum = GalleryAlbum::factory()->create(['publication_state' => 'published']);
        $publishedRevision = GalleryAlbumRevision::factory()->create([
            'gallery_album_id' => $publishedAlbum->id,
            'workflow_state' => 'published',
            'published_at' => now(),
        ]);
        $publishedAlbum->update([
            'published_revision_id' => $publishedRevision->id,
            'published_at' => now(),
        ]);
        $publishedPhoto = $this->attachPhoto($publishedAsset, $publishedRevision, 0);

        $this->assertCurrentGalleryMediaBlocksLifecycle($admin, $publishedAsset, $publishedPhoto);

        $scheduledAsset = $this->safeAsset($admin);
        $scheduledAlbum = GalleryAlbum::factory()->create([
            'publication_state' => 'review',
            'scheduled_for' => now()->addDay(),
        ]);
        $scheduledRevision = GalleryAlbumRevision::factory()->create([
            'gallery_album_id' => $scheduledAlbum->id,
            'workflow_state' => 'scheduled',
        ]);
        $scheduledAlbum->update(['scheduled_revision_id' => $scheduledRevision->id]);
        $scheduledPhoto = $this->attachPhoto($scheduledAsset, $scheduledRevision, 0);

        $this->assertCurrentGalleryMediaBlocksLifecycle($admin, $scheduledAsset, $scheduledPhoto);
    }

    public function test_consent_withdrawal_withdraws_live_gallery_and_cancels_only_an_unsafe_scheduled_replacement(): void
    {
        $admin = $this->user();
        $liveAsset = $this->safeAsset($admin);
        $album = GalleryAlbum::factory()->create(['publication_state' => 'published']);
        $liveRevision = GalleryAlbumRevision::factory()->create([
            'gallery_album_id' => $album->id,
            'workflow_state' => 'published',
            'published_at' => now(),
        ]);
        $album->update([
            'published_revision_id' => $liveRevision->id,
            'published_at' => now(),
        ]);
        $this->attachPhoto($liveAsset, $liveRevision, 0);
        Setting::set('website_featured_gallery_album_id', (string) $album->id);
        Cache::put('public-gallery:available', true, now()->addMinutes(5));
        Cache::put('public-gallery:featured', true, now()->addMinutes(5));

        $this->library()->withdrawConsent($admin, $liveAsset);

        $this->assertSame('withdrawn', $album->fresh()->publication_state);
        $this->assertNotNull($album->fresh()->withdrawn_at);
        $this->assertNull(Setting::get('website_featured_gallery_album_id'));
        $this->assertFalse(Cache::has('public-gallery:available'));
        $this->assertFalse(Cache::has('public-gallery:featured'));

        $safeLiveAsset = $this->safeAsset($admin);
        $scheduledAsset = $this->safeAsset($admin);
        $replacementAlbum = GalleryAlbum::factory()->create(['publication_state' => 'published']);
        $safeRevision = GalleryAlbumRevision::factory()->create([
            'gallery_album_id' => $replacementAlbum->id,
            'workflow_state' => 'published',
            'published_at' => now(),
        ]);
        $scheduledRevision = GalleryAlbumRevision::factory()->create([
            'gallery_album_id' => $replacementAlbum->id,
            'revision_number' => 2,
            'workflow_state' => 'scheduled',
        ]);
        $replacementAlbum->update([
            'published_revision_id' => $safeRevision->id,
            'published_at' => now(),
            'scheduled_revision_id' => $scheduledRevision->id,
            'scheduled_for' => now()->addDay(),
        ]);
        $this->attachPhoto($safeLiveAsset, $safeRevision, 0);
        $this->attachPhoto($scheduledAsset, $scheduledRevision, 0);

        $this->library()->withdrawConsent($admin, $scheduledAsset);

        $this->assertSame('published', $replacementAlbum->fresh()->publication_state);
        $this->assertSame($safeRevision->id, $replacementAlbum->fresh()->published_revision_id);
        $this->assertNull($replacementAlbum->fresh()->scheduled_revision_id);
        $this->assertNull($replacementAlbum->fresh()->scheduled_for);
        $this->assertSame('approved', $scheduledRevision->fresh()->workflow_state);
    }

    private function user(string $role = 'admin'): User
    {
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    private function library(): MediaLibrary
    {
        return app(MediaLibrary::class);
    }

    private function safeAsset(User $owner): MediaAsset
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
            'title' => 'Gallery photograph',
            'default_alt_text' => 'Students enjoying a school learning activity.',
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

    private function attachPhoto(MediaAsset $asset, GalleryAlbumRevision $revision, int $displayOrder): MediaAttachment
    {
        return $asset->attachments()->create([
            'attachable_type' => 'gallery-album-revision',
            'attachable_id' => $revision->id,
            'placement' => 'photo',
            'display_order' => $displayOrder,
            'alt_text' => 'Students taking part in a school activity.',
        ]);
    }

    private function assertCurrentGalleryMediaBlocksLifecycle(User $actor, MediaAsset $asset, MediaAttachment $attachment): void
    {
        $uses = app(MediaUsage::class)->publishedUses($asset);
        $this->assertNotEmpty($uses);
        $this->assertStringContainsString('gallery-album-revision:'.$attachment->attachable_id, $uses[0]);
        $this->rejects(fn () => $this->library()->draft($actor, $asset), 'gallery-album-revision');
        $this->rejects(fn () => $this->library()->archive($actor, $asset), 'gallery-album-revision');
        $this->rejects(fn () => $this->library()->detach($actor, $attachment), 'Gallery editor');
        $this->assertSame('published', $asset->fresh()->status);
        $this->assertNull($asset->fresh()->archived_at);
    }

    private function rejects(callable $action, string $contains): void
    {
        try {
            $action();
            $this->fail('Expected validation denial.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString($contains, json_encode($exception->errors()));
        }
    }
}
