<?php

namespace App\Services\Media;

use App\Models\MediaAsset;
use App\Models\MediaAttachment;
use App\Models\PublicPhotoSlot;
use App\Models\PublicPageRevision;
use App\Models\PublicTeamProfileRevision;
use App\Models\SchoolEvent;
use App\Models\Setting;
use App\Models\User;
use App\Services\Gallery\GalleryPublisher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class MediaUsage
{
    // Stable aliases only; no Student/User/Staff/Document owners are allowed.
    // A class-string is intentionally safe before the News model is deployed.
    public const MORPH_MAP = [
        'homepage-hero' => Setting::class,
        'public-event' => SchoolEvent::class,
        'news-story-revision' => 'App\\Models\\NewsStoryRevision',
        'gallery-album-revision' => 'App\\Models\\GalleryAlbumRevision',
        'public-page-revision' => PublicPageRevision::class,
        'public-team-profile-revision' => PublicTeamProfileRevision::class,
    ];

    public function owner(string $alias, int $id): Model
    {
        $class = self::MORPH_MAP[$alias] ?? null;
        if ($class === null || ! class_exists($class)) {
            throw ValidationException::withMessages(['attachable_type' => 'Unsupported public media owner.']);
        }
        $owner = $class::findOrFail($id);
        if ($owner instanceof Setting && $owner->key !== 'website_hero_images') {
            throw ValidationException::withMessages(['attachable_id' => 'Only the homepage hero setting can own media.']);
        }

        return $owner;
    }

    public function publishedUses(MediaAsset $asset): array
    {
        $uses = [];
        foreach ($asset->attachments()->get() as $attachment) {
            if ($this->attachmentIsRequiredByPublishedContent($attachment)) {
                $uses[] = $attachment->attachable_type.':'.$attachment->attachable_id.' / '.$attachment->placement.' #'.$attachment->display_order;
            }
        }
        if (Schema::hasTable('public_photo_slots')) {
            foreach (PublicPhotoSlot::query()->where('media_asset_id', $asset->getKey())->pluck('slot_key') as $slot) {
                $uses[] = 'public photo slot: '.$slot;
            }
        }
        // Until D2-C switches rendering, the legacy JSON is also authoritative live use.
        $json = Setting::get('website_hero_images');
        if ($json !== null && $json !== '') {
            $entries = json_decode($json, true);
            if (! is_array($entries) || ! array_is_list($entries)) {
                throw ValidationException::withMessages(['media' => 'Cannot verify homepage hero references; repair the hero list first.']);
            }
            foreach ($entries as $entry) {
                try {
                    $bytes = app(MediaFiles::class)->legacyBytes((string) ($entry['path'] ?? ''));
                    if (hash('sha256', $bytes) === $asset->checksum) {
                        $uses[] = 'legacy homepage hero: '.$entry['path'];
                    }
                } catch (ValidationException) {
                    // A broken live reference cannot be proven unrelated to this asset.
                    throw ValidationException::withMessages(['media' => 'Cannot verify a legacy homepage image; reconcile it before changing publication.']);
                }
            }
        }

        return $uses;
    }

    public function requireAttachmentMayBeDetached(MediaAttachment $attachment): void
    {
        if (in_array($attachment->attachable_type, [
            'news-story-revision',
            'gallery-album-revision',
            'public-page-revision',
            'public-team-profile-revision',
        ], true)
            && $this->attachmentIsRequiredByPublishedContent($attachment)) {
            throw ValidationException::withMessages([
                'media' => 'This media attachment is required by published content and cannot be detached.',
            ]);
        }
    }

    /**
     * Withdraw a current News publication when its cover loses consent, or
     * cancel an unsafe scheduled replacement while retaining an older safe
     * publication. This is called while the MediaAsset row is already locked
     * by MediaLibrary. Other public placements remain untouched and are
     * fail-closed by the derivative endpoint once the asset is withdrawn.
     *
     * @return array<int, int>
     */
    public function withdrawCurrentPublicNewsStories(MediaAsset $asset, User $actor): array
    {
        $revisionIds = MediaAttachment::query()
            ->where('media_asset_id', $asset->getKey())
            ->where('attachable_type', 'news-story-revision')
            ->lockForUpdate()
            ->pluck('attachable_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        if ($revisionIds->isEmpty() || ! class_exists('App\\Models\\NewsStory')) {
            return [];
        }

        $storyClass = 'App\\Models\\NewsStory';
        $revisionIdList = $revisionIds->all();
        $stories = $storyClass::query()
            ->where(function ($query) use ($revisionIdList): void {
                $query->whereIn('published_revision_id', $revisionIdList)
                    ->orWhereIn('scheduled_revision_id', $revisionIdList);
            })
            ->lockForUpdate()
            ->get();

        $changedStoryIds = [];
        $withdrawnStoryIds = [];
        foreach ($stories as $story) {
            $usesPublishedRevision = $story->getAttribute('published_revision_id') !== null
                && in_array((int) $story->getAttribute('published_revision_id'), $revisionIdList, true);
            $usesScheduledRevision = $story->getAttribute('scheduled_revision_id') !== null
                && in_array((int) $story->getAttribute('scheduled_revision_id'), $revisionIdList, true);

            if ($usesPublishedRevision && $story->getAttribute('publication_state') === 'published') {
                $this->cancelScheduledRevision($story);
                $story->forceFill([
                    'publication_state' => 'withdrawn',
                    'withdrawn_at' => now(),
                    'updated_by' => $actor->id,
                ])->save();
                $withdrawnStoryIds[] = (int) $story->getKey();
                $changedStoryIds[] = (int) $story->getKey();

                continue;
            }

            if ($usesScheduledRevision) {
                // Keep an older live revision online, but cancel the unsafe
                // queued replacement before its publication boundary.
                $this->cancelScheduledRevision($story);
                $story->updated_by = $actor->id;
                $story->save();
                $changedStoryIds[] = (int) $story->getKey();
            }
        }

        if ($changedStoryIds !== []) {
            $featured = Setting::get('website_featured_news_story_id');
            if (in_array((string) $featured, array_map('strval', $withdrawnStoryIds), true)) {
                Setting::set('website_featured_news_story_id', null);
            }
            Cache::forget('public-news:available');
            Cache::forget('public-news:featured');
        }

        return $changedStoryIds;
    }

    /**
     * Withdraw Gallery albums whose live revision uses a consent-withdrawn
     * asset, or cancel only an unsafe scheduled replacement while retaining
     * the older published album.
     *
     * @return array<int, int>
     */
    public function withdrawCurrentPublicGalleryAlbums(MediaAsset $asset, User $actor): array
    {
        $revisionIds = MediaAttachment::query()
            ->where('media_asset_id', $asset->getKey())
            ->where('attachable_type', 'gallery-album-revision')
            ->lockForUpdate()
            ->pluck('attachable_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        if ($revisionIds->isEmpty() || ! class_exists('App\\Models\\GalleryAlbum')) {
            return [];
        }

        $albumClass = 'App\\Models\\GalleryAlbum';
        $revisionIdList = $revisionIds->all();
        $albums = $albumClass::query()
            ->where(function ($query) use ($revisionIdList): void {
                $query->whereIn('published_revision_id', $revisionIdList)
                    ->orWhereIn('scheduled_revision_id', $revisionIdList);
            })
            ->lockForUpdate()
            ->get();
        $changedAlbumIds = [];
        $withdrawnAlbumIds = [];

        foreach ($albums as $album) {
            $usesPublishedRevision = $album->getAttribute('published_revision_id') !== null
                && in_array((int) $album->getAttribute('published_revision_id'), $revisionIdList, true);
            $usesScheduledRevision = $album->getAttribute('scheduled_revision_id') !== null
                && in_array((int) $album->getAttribute('scheduled_revision_id'), $revisionIdList, true);

            if ($usesPublishedRevision && $album->getAttribute('publication_state') === 'published') {
                $this->cancelScheduledGalleryRevision($album);
                $album->forceFill([
                    'publication_state' => 'withdrawn',
                    'withdrawn_at' => now(),
                    'updated_by' => $actor->id,
                ])->save();
                $withdrawnAlbumIds[] = (int) $album->getKey();
                $changedAlbumIds[] = (int) $album->getKey();

                continue;
            }

            if ($usesScheduledRevision) {
                $this->cancelScheduledGalleryRevision($album);
                $album->updated_by = $actor->id;
                $album->save();
                $changedAlbumIds[] = (int) $album->getKey();
            }
        }

        if ($changedAlbumIds !== []) {
            $featured = Setting::get('website_featured_gallery_album_id');
            if (in_array((string) $featured, array_map('strval', $withdrawnAlbumIds), true)) {
                Setting::set('website_featured_gallery_album_id', null);
            }
            GalleryPublisher::invalidatePublicCache();
        }

        return $changedAlbumIds;
    }

    public function requireUnusedByPublishedContent(MediaAsset $asset): void
    {
        $uses = $this->publishedUses($asset);
        if ($uses !== []) {
            throw ValidationException::withMessages(['media' => 'This asset is required by published content: '.implode('; ', $uses)]);
        }
    }

    private function attachmentIsRequiredByPublishedContent(MediaAttachment $attachment): bool
    {
        // Unknown/deleted owners fail closed, rather than risking a broken public page.
        try {
            $owner = $this->owner($attachment->attachable_type, $attachment->attachable_id);

            return match ($attachment->attachable_type) {
                'news-story-revision' => $this->isCurrentPublicOrScheduledNewsRevision($owner),
                'gallery-album-revision' => $this->isCurrentPublicOrScheduledGalleryRevision($owner),
                'public-page-revision' => $this->isCurrentPublicOrScheduledPublicPageRevision($owner),
                'public-team-profile-revision' => $this->isCurrentPublicOrScheduledPublicTeamProfileRevision($owner),
                'homepage-hero' => true,
                default => (bool) $owner->is_public,
            };
        } catch (\Throwable) {
            return true;
        }
    }

    private function cancelScheduledRevision(Model $story): void
    {
        $scheduledRevisionId = $story->getAttribute('scheduled_revision_id');
        if ($scheduledRevisionId !== null && class_exists('App\\Models\\NewsStoryRevision')) {
            $revisionClass = 'App\\Models\\NewsStoryRevision';
            $revision = $revisionClass::query()->whereKey($scheduledRevisionId)->lockForUpdate()->first();
            if ($revision !== null && $revision->workflow_state === 'scheduled') {
                $revision->workflow_state = 'approved';
                $revision->save();
            }
        }
        $story->scheduled_revision_id = null;
        $story->scheduled_for = null;
    }

    private function cancelScheduledGalleryRevision(Model $album): void
    {
        $scheduledRevisionId = $album->getAttribute('scheduled_revision_id');
        if ($scheduledRevisionId !== null && class_exists('App\\Models\\GalleryAlbumRevision')) {
            $revisionClass = 'App\\Models\\GalleryAlbumRevision';
            $revision = $revisionClass::query()->whereKey($scheduledRevisionId)->lockForUpdate()->first();
            if ($revision !== null && $revision->workflow_state === 'scheduled') {
                $revision->workflow_state = 'approved';
                $revision->save();
            }
        }
        $album->scheduled_revision_id = null;
        $album->scheduled_for = null;
    }

    private function isCurrentPublicOrScheduledNewsRevision(Model $revision): bool
    {
        // A missing/unreadable relation is deliberately treated as live by the
        // caller's fail-closed catch block.
        if (! method_exists($revision, 'story')) {
            return true;
        }
        $story = $revision->story;
        if (! $story instanceof Model) {
            return true;
        }
        $revisionId = (int) $revision->getKey();
        $isPublishedRevision = (int) $story->getAttribute('published_revision_id') === $revisionId;
        $isScheduledRevision = (int) $story->getAttribute('scheduled_revision_id') === $revisionId;
        $state = (string) $story->getAttribute('publication_state');
        if ($isPublishedRevision && $state === 'published') {
            return true;
        }

        // A revision selected for a future release is still a live editorial
        // dependency, even though the public resolver will not show it yet.
        // Treat an inconsistent withdrawn/archived schedule as live too: a
        // malformed schedule must not make consent-sensitive media reusable.
        return ($isScheduledRevision || $isPublishedRevision)
            && $story->getAttribute('scheduled_for') !== null;
    }

    private function isCurrentPublicOrScheduledGalleryRevision(Model $revision): bool
    {
        if (! method_exists($revision, 'album')) {
            return true;
        }
        $album = $revision->album;
        if (! $album instanceof Model) {
            return true;
        }
        $revisionId = (int) $revision->getKey();
        $isPublishedRevision = (int) $album->getAttribute('published_revision_id') === $revisionId;
        $isScheduledRevision = (int) $album->getAttribute('scheduled_revision_id') === $revisionId;

        if ($isPublishedRevision && $album->getAttribute('publication_state') === 'published') {
            return true;
        }

        return $isScheduledRevision && $album->getAttribute('scheduled_for') !== null;
    }

    private function isCurrentPublicOrScheduledPublicPageRevision(Model $revision): bool
    {
        if (! method_exists($revision, 'page')) {
            return true;
        }
        $page = $revision->page;
        if (! $page instanceof Model) {
            return true;
        }

        $revisionId = (int) $revision->getKey();
        $isPublishedRevision = (int) $page->getAttribute('published_revision_id') === $revisionId;
        $isScheduledRevision = (int) $page->getAttribute('scheduled_revision_id') === $revisionId;

        if ($isPublishedRevision && $page->getAttribute('publication_state') === 'published') {
            return true;
        }

        return $isScheduledRevision && $page->getAttribute('scheduled_for') !== null;
    }

    private function isCurrentPublicOrScheduledPublicTeamProfileRevision(Model $revision): bool
    {
        if (! method_exists($revision, 'profile')) {
            return true;
        }
        $profile = $revision->profile;
        if (! $profile instanceof Model) {
            return true;
        }

        $revisionId = (int) $revision->getKey();
        $isPublishedRevision = (int) $profile->getAttribute('published_revision_id') === $revisionId;
        $isScheduledRevision = (int) $profile->getAttribute('scheduled_revision_id') === $revisionId;

        if ($isPublishedRevision && $profile->getAttribute('publication_state') === 'published') {
            return true;
        }

        return $isScheduledRevision && $profile->getAttribute('scheduled_for') !== null;
    }
}
