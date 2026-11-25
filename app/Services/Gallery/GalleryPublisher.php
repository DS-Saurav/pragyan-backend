<?php

namespace App\Services\Gallery;

use App\Models\GalleryAlbum;
use App\Models\GalleryAlbumRevision;
use App\Models\GalleryAlbumSlug;
use App\Models\MediaAsset;
use App\Models\MediaAttachment;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GalleryPublisher
{
    public const FEATURED_SETTING = 'website_featured_gallery_album_id';

    /** @var array<int, string> */
    public const RESERVED_SLUGS = ['admin', 'archive', 'create', 'edit', 'feature', 'preview'];

    public const TIMEZONE = 'Asia/Kathmandu';

    public function createDraft(User $actor, array $data): GalleryAlbum
    {
        $this->authorize($actor, 'gallery.author');
        $attributes = $this->draftAttributes($data);

        return DB::transaction(function () use ($actor, $attributes): GalleryAlbum {
            $slug = $this->canonicalSlug($attributes['slug'] ?: $attributes['title']);
            $this->ensureSlugClaimable($slug);

            $album = GalleryAlbum::create([
                'slug' => $slug,
                'publication_state' => 'draft',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $album->revisions()->create($this->revisionAttributes($attributes) + [
                'revision_number' => 1,
                'slug' => $slug,
                'workflow_state' => 'draft',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            return $album->fresh(['revisions']);
        });
    }

    public function saveDraft(User $actor, GalleryAlbum $album, array $data): GalleryAlbumRevision
    {
        $this->authorize($actor, 'gallery.author');
        $attributes = $this->draftAttributes($data);

        return DB::transaction(function () use ($actor, $album, $attributes): GalleryAlbumRevision {
            $lockedAlbum = GalleryAlbum::query()->lockForUpdate()->findOrFail($album->getKey());
            $this->require($lockedAlbum->archived_at === null, 'Restore an archived album before preparing a new revision.');
            $this->require($lockedAlbum->scheduled_revision_id === null, 'Publish, withdraw, or cancel the scheduled revision before preparing another one.');

            $slug = $this->canonicalSlug($attributes['slug'] ?: $attributes['title']);
            $this->ensureSlugClaimable($slug, $lockedAlbum);

            $revision = $lockedAlbum->revisions()
                ->where('workflow_state', 'draft')
                ->orderByDesc('revision_number')
                ->lockForUpdate()
                ->first();

            if ($revision === null) {
                $revision = $lockedAlbum->revisions()->create($this->revisionAttributes($attributes) + [
                    'revision_number' => ((int) $lockedAlbum->revisions()->max('revision_number')) + 1,
                    'slug' => $slug,
                    'workflow_state' => 'draft',
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);
            }

            $revision->fill($this->revisionAttributes($attributes) + [
                'slug' => $slug,
                'workflow_state' => 'draft',
                'updated_by' => $actor->id,
                'submitted_by' => null,
                'submitted_at' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'published_by' => null,
                'published_at' => null,
            ]);
            $revision->save();

            if ($lockedAlbum->published_revision_id === null) {
                $lockedAlbum->fill([
                    'slug' => $slug,
                    'publication_state' => 'draft',
                    'withdrawn_at' => null,
                ]);
            }
            $lockedAlbum->updated_by = $actor->id;
            $lockedAlbum->save();

            return $revision->refresh();
        });
    }

    public function submit(User $actor, GalleryAlbum $album, GalleryAlbumRevision $revision): GalleryAlbumRevision
    {
        $this->authorize($actor, 'gallery.author');

        return DB::transaction(function () use ($actor, $album, $revision): GalleryAlbumRevision {
            $lockedAlbum = GalleryAlbum::query()->lockForUpdate()->findOrFail($album->getKey());
            $lockedRevision = $this->lockedRevision($lockedAlbum, $revision);
            $this->require($lockedAlbum->archived_at === null, 'An archived album cannot enter review.');
            $this->require($lockedRevision->workflow_state === 'draft', 'Only a draft revision can enter review.');

            $lockedRevision->fill([
                'workflow_state' => 'review',
                'submitted_by' => $actor->id,
                'submitted_at' => $this->now(),
                'updated_by' => $actor->id,
            ]);
            $lockedRevision->save();

            if ($lockedAlbum->published_revision_id === null) {
                $lockedAlbum->publication_state = 'review';
            }
            $lockedAlbum->updated_by = $actor->id;
            $lockedAlbum->save();

            return $lockedRevision->refresh();
        });
    }

    public function approve(User $actor, GalleryAlbum $album, GalleryAlbumRevision $revision): GalleryAlbumRevision
    {
        $this->authorize($actor, 'gallery.review');

        return DB::transaction(function () use ($actor, $album, $revision): GalleryAlbumRevision {
            $lockedAlbum = GalleryAlbum::query()->lockForUpdate()->findOrFail($album->getKey());
            $lockedRevision = $this->lockedRevision($lockedAlbum, $revision);
            $this->require($lockedAlbum->archived_at === null, 'An archived album cannot be approved.');
            $this->require($lockedRevision->workflow_state === 'review', 'Only a revision in review can be approved.');
            $this->require(
                $this->isAdministrator($actor)
                    || ($lockedRevision->created_by !== $actor->id && $lockedRevision->submitted_by !== $actor->id),
                'A different authorized reviewer must approve the author’s revision.',
            );

            $lockedRevision->fill([
                'workflow_state' => 'approved',
                'reviewed_by' => $actor->id,
                'reviewed_at' => $this->now(),
                'updated_by' => $actor->id,
            ]);
            $lockedRevision->save();
            $lockedAlbum->updated_by = $actor->id;
            $lockedAlbum->save();

            return $lockedRevision->refresh();
        });
    }

    public function publish(
        User $actor,
        GalleryAlbum $album,
        GalleryAlbumRevision $revision,
        ?CarbonInterface $scheduledFor = null,
    ): GalleryAlbum {
        $this->authorize($actor, 'gallery.publish');

        $published = DB::transaction(function () use ($actor, $album, $revision, $scheduledFor): GalleryAlbum {
            $lockedAlbum = GalleryAlbum::query()->lockForUpdate()->findOrFail($album->getKey());
            $lockedRevision = $this->lockedRevision($lockedAlbum, $revision);
            $this->require($lockedAlbum->archived_at === null, 'Restore an archived album before publishing it.');
            $this->require($lockedRevision->workflow_state === 'approved', 'Only an approved revision can be published or scheduled.');
            $this->assertPhotosArePublicSafe($lockedRevision);

            $when = $scheduledFor === null ? null : $this->atSchoolTime($scheduledFor);
            if ($when !== null && $when->isFuture()) {
                $this->require(
                    $lockedAlbum->scheduled_revision_id === null || $lockedAlbum->scheduled_revision_id === $lockedRevision->id,
                    'This album already has a scheduled revision.',
                );

                $lockedRevision->fill([
                    'workflow_state' => 'scheduled',
                    'published_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);
                $lockedRevision->save();
                $lockedAlbum->fill([
                    'scheduled_revision_id' => $lockedRevision->id,
                    'scheduled_for' => $when,
                    'publication_state' => $lockedAlbum->publication_state === 'published' ? 'published' : 'scheduled',
                    'updated_by' => $actor->id,
                ]);
                $lockedAlbum->save();

                return $lockedAlbum->fresh(['publishedRevision', 'scheduledRevision']);
            }

            $this->require(
                $lockedAlbum->scheduled_revision_id === null || $lockedAlbum->scheduled_revision_id === $lockedRevision->id,
                'This album already has a different scheduled revision.',
            );
            $this->activateRevision($lockedAlbum, $lockedRevision, $actor, $this->now());

            return $lockedAlbum->fresh(['publishedRevision', 'scheduledRevision']);
        });

        self::invalidatePublicCache();

        return $published;
    }

    public function withdraw(User $actor, GalleryAlbum $album): GalleryAlbum
    {
        $this->authorize($actor, 'gallery.publish');

        $withdrawn = DB::transaction(function () use ($actor, $album): GalleryAlbum {
            $lockedAlbum = GalleryAlbum::query()->lockForUpdate()->findOrFail($album->getKey());
            $this->require($lockedAlbum->publication_state === 'published', 'Only a published album can be withdrawn.');
            $this->clearScheduledRevision($lockedAlbum);
            $lockedAlbum->fill([
                'publication_state' => 'withdrawn',
                'withdrawn_at' => $this->now(),
                'updated_by' => $actor->id,
            ]);
            $lockedAlbum->save();
            $this->clearFeaturedAlbum($lockedAlbum);

            return $lockedAlbum->fresh();
        });

        self::invalidatePublicCache();

        return $withdrawn;
    }

    public function archive(User $actor, GalleryAlbum $album): GalleryAlbum
    {
        $this->authorize($actor, 'gallery.publish');

        $archived = DB::transaction(function () use ($actor, $album): GalleryAlbum {
            $lockedAlbum = GalleryAlbum::query()->lockForUpdate()->findOrFail($album->getKey());
            $this->require($lockedAlbum->archived_at === null, 'This album is already archived.');
            $this->clearScheduledRevision($lockedAlbum);
            $lockedAlbum->fill([
                'publication_state' => 'archived',
                'archived_at' => $this->now(),
                'updated_by' => $actor->id,
            ]);
            $lockedAlbum->save();
            $this->clearFeaturedAlbum($lockedAlbum);

            return $lockedAlbum->fresh();
        });

        self::invalidatePublicCache();

        return $archived;
    }

    public function restore(User $actor, GalleryAlbum $album): GalleryAlbum
    {
        $this->authorize($actor, 'gallery.publish');

        $restored = DB::transaction(function () use ($actor, $album): GalleryAlbum {
            $lockedAlbum = GalleryAlbum::query()->lockForUpdate()->findOrFail($album->getKey());
            $this->require($lockedAlbum->archived_at !== null, 'Only an archived album can be restored.');
            $this->clearScheduledRevision($lockedAlbum);
            $lockedAlbum->fill([
                'publication_state' => 'draft',
                'published_revision_id' => null,
                'published_at' => null,
                'withdrawn_at' => null,
                'archived_at' => null,
                'updated_by' => $actor->id,
            ]);
            $lockedAlbum->save();

            return $lockedAlbum->fresh();
        });

        self::invalidatePublicCache();

        return $restored;
    }

    public function setFeatured(User $actor, ?GalleryAlbum $album): void
    {
        $this->authorize($actor, 'gallery.publish');

        DB::transaction(function () use ($album): void {
            if ($album !== null) {
                $lockedAlbum = GalleryAlbum::query()->lockForUpdate()->findOrFail($album->getKey());
                $this->require($this->isDisplayable($lockedAlbum), 'Only a currently published album can be featured.');
                Setting::set(self::FEATURED_SETTING, (string) $lockedAlbum->id);

                return;
            }

            Setting::set(self::FEATURED_SETTING, null);
        });

        self::invalidatePublicCache();
    }

    public function releaseDue(?CarbonInterface $now = null): void
    {
        $time = $this->atSchoolTime($now ?? $this->now());
        $dueAlbumIds = GalleryAlbum::query()
            ->whereNotNull('scheduled_revision_id')
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', $time)
            ->pluck('id');
        $changed = false;

        foreach ($dueAlbumIds as $albumId) {
            $changed = DB::transaction(function () use ($albumId, $time): bool {
                $lockedAlbum = GalleryAlbum::query()->lockForUpdate()->find($albumId);
                if ($lockedAlbum === null
                    || $lockedAlbum->scheduled_revision_id === null
                    || $lockedAlbum->scheduled_for === null
                    || $lockedAlbum->scheduled_for->greaterThan($time)
                    || $lockedAlbum->archived_at !== null
                    || $lockedAlbum->publication_state === 'withdrawn') {
                    if ($lockedAlbum !== null && $lockedAlbum->scheduled_revision_id !== null) {
                        $this->clearScheduledRevision($lockedAlbum);
                        $lockedAlbum->save();
                    }

                    return true;
                }

                $lockedRevision = $lockedAlbum->revisions()
                    ->whereKey($lockedAlbum->scheduled_revision_id)
                    ->lockForUpdate()
                    ->first();
                if ($lockedRevision === null || $lockedRevision->workflow_state !== 'scheduled') {
                    $this->clearScheduledRevision($lockedAlbum);
                    $lockedAlbum->save();

                    return true;
                }

                try {
                    $this->assertPhotosArePublicSafe($lockedRevision);
                } catch (ValidationException) {
                    $lockedRevision->workflow_state = 'approved';
                    $lockedRevision->save();
                    $this->clearScheduledRevision($lockedAlbum);
                    $lockedAlbum->save();

                    return true;
                }

                $this->activateRevision($lockedAlbum, $lockedRevision, null, $time);

                return true;
            }) || $changed;
        }

        if ($changed) {
            self::invalidatePublicCache();
        }
    }

    public static function invalidatePublicCache(): void
    {
        Cache::forget('public-gallery:available');
        Cache::forget('public-gallery:featured');
    }

    private function lockedRevision(GalleryAlbum $album, GalleryAlbumRevision $revision): GalleryAlbumRevision
    {
        return $album->revisions()->whereKey($revision->getKey())->lockForUpdate()->firstOrFail();
    }

    /** @param array{title: string, summary: string, album_date: string, category: ?string} $attributes */
    private function revisionAttributes(array $attributes): array
    {
        return [
            'title' => $attributes['title'],
            'summary' => $attributes['summary'],
            'album_date' => $attributes['album_date'],
            'category' => $attributes['category'],
        ];
    }

    /** @return array{slug: string, title: string, summary: string, album_date: string, category: ?string} */
    private function draftAttributes(array $data): array
    {
        $validated = Validator::make($data, [
            'slug' => ['nullable', 'string', 'max:180'],
            'title' => ['required', 'string', 'max:180'],
            'summary' => ['required', 'string', 'max:500'],
            'album_date' => ['required', 'date_format:Y-m-d'],
            'category' => ['nullable', 'string', 'max:80'],
        ])->validate();

        $attributes = [
            'slug' => trim((string) ($validated['slug'] ?? '')),
            'title' => trim((string) $validated['title']),
            'summary' => trim((string) $validated['summary']),
            'album_date' => (string) $validated['album_date'],
            'category' => $this->nullableString($validated['category'] ?? null),
        ];

        if ($attributes['title'] === '' || $attributes['summary'] === '') {
            throw ValidationException::withMessages([
                'title' => 'An album title and summary are required.',
            ]);
        }

        return $attributes;
    }

    private function canonicalSlug(string $value): string
    {
        $slug = Str::limit(Str::slug(trim($value)), 180, '');

        if ($slug === '' || in_array($slug, self::RESERVED_SLUGS, true)) {
            throw ValidationException::withMessages([
                'slug' => 'Choose a public URL that is not reserved by the Gallery section.',
            ]);
        }

        return $slug;
    }

    private function ensureSlugClaimable(string $slug, ?GalleryAlbum $album = null): void
    {
        $albums = GalleryAlbum::query()->where('slug', $slug);
        if ($album !== null) {
            $albums->whereKey('!=', $album->getKey());
        }
        $formerSlugs = GalleryAlbumSlug::query()->where('slug', $slug);
        if ($album !== null) {
            $formerSlugs->where('gallery_album_id', '!=', $album->getKey());
        }

        if ($albums->exists() || $formerSlugs->exists()) {
            throw ValidationException::withMessages([
                'slug' => 'That public URL is already reserved by another Gallery album.',
            ]);
        }
    }

    private function assertPhotosArePublicSafe(GalleryAlbumRevision $revision): void
    {
        $attachments = MediaAttachment::query()
            ->where('attachable_type', 'gallery-album-revision')
            ->where('attachable_id', $revision->id)
            ->whereIn('placement', ['cover', 'photo'])
            ->orderBy('placement')
            ->orderBy('display_order')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $cover = $attachments->first(static fn (MediaAttachment $attachment): bool => $attachment->placement === 'cover' && $attachment->display_order === 0);
        $photos = $attachments->filter(static fn (MediaAttachment $attachment): bool => $attachment->placement === 'photo')->values();

        $this->require($cover !== null, 'An approved Gallery cover image is required before publication.');
        $this->require($photos->isNotEmpty(), 'At least one ordered Gallery photo is required before publication.');

        foreach ($photos as $index => $photo) {
            $this->require(
                $photo->display_order === $index,
                'Gallery photos must use contiguous display order starting at zero.',
            );
        }

        foreach ($attachments as $attachment) {
            $asset = MediaAsset::query()->lockForUpdate()->findOrFail($attachment->media_asset_id);
            $this->assertAssetIsPublicSafe($asset, $attachment);
        }
    }

    private function assertAssetIsPublicSafe(MediaAsset $asset, MediaAttachment $attachment): void
    {
        $this->require($asset->status === 'published' && $asset->archived_at === null, 'Gallery media must be an active published media asset.');
        $this->require(
            $asset->public_disk === 'media_derivatives' && trim((string) $asset->public_path) !== '',
            'Gallery media needs a published public derivative.',
        );
        $this->require(
            in_array($asset->consent_state, ['not_required', 'confirmed'], true)
                && $asset->consent_withdrawn_at === null,
            'Gallery media does not have consent for public use.',
        );
        $altText = trim((string) ($attachment->alt_text ?: $asset->default_alt_text));
        $this->require((bool) $asset->is_decorative || $altText !== '', 'Each Gallery image needs meaningful public alt text or must be marked decorative.');
    }

    private function activateRevision(
        GalleryAlbum $album,
        GalleryAlbumRevision $revision,
        ?User $actor,
        CarbonInterface $publishedAt,
    ): void {
        $this->ensureSlugClaimable($revision->slug, $album);
        $formerSlug = $album->slug;
        $hadPublishedRevision = $album->published_revision_id !== null;

        if ($hadPublishedRevision && $formerSlug !== $revision->slug) {
            GalleryAlbumSlug::query()->firstOrCreate(
                ['slug' => $formerSlug],
                ['gallery_album_id' => $album->id],
            );
            GalleryAlbumSlug::query()
                ->where('gallery_album_id', $album->id)
                ->where('slug', $revision->slug)
                ->delete();
        }

        $publisherId = $actor?->id ?? $revision->published_by;
        $revision->fill([
            'workflow_state' => 'published',
            'published_by' => $publisherId,
            'published_at' => $publishedAt,
            'updated_by' => $publisherId ?? $revision->updated_by,
        ]);
        $revision->save();
        $album->fill([
            'slug' => $revision->slug,
            'publication_state' => 'published',
            'published_revision_id' => $revision->id,
            'scheduled_revision_id' => null,
            'scheduled_for' => null,
            'published_at' => $publishedAt,
            'withdrawn_at' => null,
            'archived_at' => null,
            'published_by' => $publisherId,
            'updated_by' => $publisherId ?? $album->updated_by,
        ]);
        $album->save();
    }

    private function clearScheduledRevision(GalleryAlbum $album): void
    {
        if ($album->scheduled_revision_id !== null) {
            $revision = $album->revisions()->whereKey($album->scheduled_revision_id)->lockForUpdate()->first();
            if ($revision !== null && $revision->workflow_state === 'scheduled') {
                $revision->workflow_state = 'approved';
                $revision->save();
            }
        }
        $album->scheduled_revision_id = null;
        $album->scheduled_for = null;
        if ($album->publication_state === 'scheduled') {
            $album->publication_state = 'draft';
        }
    }

    private function clearFeaturedAlbum(GalleryAlbum $album): void
    {
        if ((string) Setting::get(self::FEATURED_SETTING) === (string) $album->id) {
            Setting::set(self::FEATURED_SETTING, null);
        }
    }

    private function isDisplayable(GalleryAlbum $album): bool
    {
        return $album->publication_state === 'published'
            && $album->published_revision_id !== null
            && $album->published_at !== null
            && $album->published_at->lessThanOrEqualTo($this->now())
            && $album->archived_at === null;
    }

    private function authorize(User $actor, string $ability): void
    {
        Gate::forUser($actor)->authorize($ability);
    }

    private function isAdministrator(User $actor): bool
    {
        return $actor->hasRole('admin')
            && in_array('*', config('permissions.roles.admin', []), true);
    }

    private function now(): CarbonImmutable
    {
        return CarbonImmutable::now('UTC');
    }

    private function atSchoolTime(CarbonInterface $value): CarbonImmutable
    {
        return CarbonImmutable::instance($value)->utc();
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['gallery' => $message]);
        }
    }
}
