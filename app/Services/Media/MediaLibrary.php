<?php

namespace App\Services\Media;

use App\Models\MediaAsset;
use App\Models\MediaAttachment;
use App\Models\NewsStoryRevision;
use App\Models\PublicPageRevision;
use App\Models\PublicTeamProfileRevision;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class MediaLibrary
{
    public const CATEGORIES = ['homepage', 'academics', 'science', 'computer-education', 'robotics', 'library',
        'leadership', 'teams', 'clubs', 'sports', 'campus', 'news', 'events', 'gallery', 'admissions', 'publications'];

    public const MAX_UPLOAD_KB = 8192;

    public const MAX_PIXELS = 24000000;

    // News covers are only changed through replaceNewsStoryRevisionCover(),
    // which enforces the draft-revision and news.author boundaries.
    private const GENERIC_ATTACHABLE_TYPES = ['homepage-hero', 'public-event'];

    private function authorize(User $actor, string $ability): void
    {
        Gate::forUser($actor)->authorize($ability);
        app(MediaSchema::class)->ensureReady();
    }

    private function metadata(array $data): array
    {
        return Validator::make($data, [
            'title' => 'nullable|string|max:255', 'description' => 'nullable|string|max:10000',
            'default_alt_text' => 'nullable|string|max:255',
            'photographer_credit' => 'nullable|string|max:255',
            'is_decorative' => 'nullable|boolean',
            'category' => ['nullable', Rule::in(self::CATEGORIES)],
        ])->validate();
    }

    public function upload(User $actor, UploadedFile $file, array $metadata = []): MediaAsset
    {
        $this->authorize($actor, 'media.upload');
        // Own the file/DB boundary, so an outer rollback cannot orphan the file.
        $this->require(DB::transactionLevel() === 0, 'Uploads must own their database transaction.');
        $metadata = $this->metadata($metadata);
        Validator::make(['image' => $file], ['image' => 'required|file|image|mimes:jpg,jpeg,png,webp,gif|max:8192'])->validate();
        $bytes = file_get_contents($file->getRealPath());
        $info = $this->imageInfo($bytes);
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$info['mime']];
        $filename = Str::uuid().'.'.$ext;
        $path = 'originals/'.$filename;
        try {
            $this->require(Storage::disk('media_originals')->put($path, $bytes), 'The original could not be stored.');

            return DB::transaction(fn () => MediaAsset::create($metadata + [
                'disk' => 'media_originals', 'original_path' => $path,
                'public_disk' => 'media_derivatives', 'original_filename' => basename($file->getClientOriginalName()),
                'storage_filename' => $filename, 'mime_type' => $info['mime'], 'file_size' => strlen($bytes),
                'width' => $info[0], 'height' => $info[1], 'checksum' => hash('sha256', $bytes),
                'uploaded_by' => $actor->id, 'status' => 'draft',
            ]));
        } catch (Throwable $e) {
            Storage::disk('media_originals')->delete($path);
            throw $e;
        }
    }

    public function updateMetadata(User $actor, MediaAsset $asset, array $metadata): MediaAsset
    {
        $this->authorize($actor, 'media.upload');
        $metadata = $this->metadata($metadata);

        return $this->change($asset, function ($locked) use ($metadata) {
            $this->require($locked->archived_at === null && $locked->status === 'draft', 'Only active drafts may be edited; return to Draft first.');
            $locked->fill($metadata);
        });
    }

    public function submit(User $actor, MediaAsset $asset): MediaAsset
    {
        $this->authorize($actor, 'media.upload');

        return $this->change($asset, function ($locked) {
            $this->require($locked->status === 'draft' && $locked->archived_at === null, 'Only an active Draft may enter Review.');
            $locked->fill(['status' => 'review', 'submitted_at' => now(), 'reviewed_by' => null, 'reviewed_at' => null]);
        });
    }

    public function approve(User $actor, MediaAsset $asset): MediaAsset
    {
        $this->authorize($actor, 'media.review');

        return $this->change($asset, function ($locked) use ($actor) {
            $this->reviewer($actor, $locked);
            $locked->fill(['reviewed_by' => $actor->id, 'reviewed_at' => now()]);
        });
    }

    public function publish(User $actor, MediaAsset $asset): MediaAsset
    {
        $this->authorize($actor, 'media.publish');
        $this->require(DB::transactionLevel() === 0, 'Publication must own its database transaction.');
        $written = null;
        try {
            return $this->change($asset, function ($locked) use ($actor, &$written) {
                $this->require($locked->status === 'review' && $locked->archived_at === null, 'Only an active Review asset may be published.');
                $this->require($locked->reviewed_at !== null && $locked->reviewed_by !== null, 'A reviewer must approve the asset first.');
                $this->require($locked->reviewed_by !== $locked->uploaded_by || $this->admin($actor), 'The uploader cannot be their own reviewer.');
                abort_if(
                    $locked->uploaded_by === $actor->id && ! $this->admin($actor),
                    403,
                    'A different authorized publisher must publish this asset.',
                );
                $this->requirePublicMetadata($locked);
                $source = app(MediaFiles::class)->originalPath($locked);
                $bytes = Storage::disk('media_originals')->get($source);
                $this->imageInfo($bytes);
                $this->require(hash_equals($locked->checksum, hash('sha256', $bytes)), 'The original changed after upload; publication is blocked.');
                $image = @imagecreatefromstring($bytes);
                $this->require($image !== false, 'The image could not be processed.');
                $scale = min(1, 1920 / imagesx($image), 1080 / imagesy($image));
                $out = imagecreatetruecolor(max(1, (int) round(imagesx($image) * $scale)), max(1, (int) round(imagesy($image) * $scale)));
                try {
                    imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
                    imagecopyresampled($out, $image, 0, 0, 0, 0, imagesx($out), imagesy($out), imagesx($image), imagesy($image));
                    ob_start();
                    $encoded = imagejpeg($out, null, 85);
                    $jpeg = ob_get_clean();
                    $this->require($encoded && is_string($jpeg) && $jpeg !== '', 'The derivative could not be generated.');
                } finally {
                    imagedestroy($out);
                    imagedestroy($image);
                }
                // Reuse the asset's stable derivative path after re-review. No new file leaks.
                $path = $locked->public_path ?? 'published/'.Str::uuid().'.jpg';
                if ($locked->public_path !== null) {
                    app(MediaFiles::class)->publicPath($locked);
                }
                $written = $locked->public_path === null ? $path : null;
                $this->require(Storage::disk('media_derivatives')->put($path, $jpeg), 'The derivative could not be stored.');
                $locked->fill(['status' => 'published', 'public_path' => $path,
                    'published_by' => $actor->id, 'published_at' => now()]);
            });
        } catch (Throwable $e) {
            if ($written !== null) {
                Storage::disk('media_derivatives')->delete($written);
            }
            throw $e;
        }
    }

    public function draft(User $actor, MediaAsset $asset): MediaAsset
    {
        $this->authorize($actor, 'media.publish');

        return $this->change($asset, function ($locked) {
            $this->require($locked->archived_at === null, 'Restore archived assets first.');
            app(MediaUsage::class)->requireUnusedByPublishedContent($locked);
            $locked->fill(['status' => 'draft', 'submitted_at' => null, 'reviewed_by' => null,
                'reviewed_at' => null, 'published_by' => null, 'published_at' => null]);
        });
    }

    public function archive(User $actor, MediaAsset $asset): MediaAsset
    {
        $this->authorize($actor, 'media.publish');

        return $this->change($asset, function ($locked) {
            app(MediaUsage::class)->requireUnusedByPublishedContent($locked);
            $locked->archived_at = now();
        });
    }

    public function restore(User $actor, MediaAsset $asset): MediaAsset
    {
        $this->authorize($actor, 'media.publish');

        return $this->change($asset, function ($locked) {
            $this->require($locked->archived_at !== null, 'The asset is not archived.');
            $locked->fill(['archived_at' => null, 'status' => 'draft', 'submitted_at' => null,
                'reviewed_by' => null, 'reviewed_at' => null, 'published_by' => null, 'published_at' => null]);
        });
    }

    public function attach(User $actor, MediaAsset $asset, array $data): MediaAttachment
    {
        $this->authorize($actor, 'media.publish');
        $owner = Validator::make($data, [
            'attachable_type' => ['required', Rule::in(self::GENERIC_ATTACHABLE_TYPES)],
            'attachable_id' => 'required|integer|min:1',
        ])->validate();
        $data = $this->attachmentMetadata($data, true) + [
            'attachable_type' => $owner['attachable_type'], 'attachable_id' => (int) $owner['attachable_id'],
        ];

        return DB::transaction(function () use ($asset, $data) {
            $locked = MediaAsset::whereKey($asset->id)->lockForUpdate()->firstOrFail();
            $this->require($locked->status === 'published' && $locked->archived_at === null, 'Only active published assets may be selected.');
            $this->requirePublicMetadata($locked);
            $this->requireAvailableSlot($data);

            return $locked->attachments()->create($data);
        });
    }

    public function updateAttachment(User $actor, MediaAttachment $attachment, array $data): MediaAttachment
    {
        $this->authorize($actor, 'media.publish');
        $data = $this->attachmentMetadata($data, false);

        return DB::transaction(function () use ($attachment, $data) {
            // Same asset -> attachment -> owner lock order as other attachment operations.
            $asset = MediaAsset::whereKey($attachment->media_asset_id)->lockForUpdate()->firstOrFail();
            $locked = $asset->attachments()->whereKey($attachment->id)->lockForUpdate()->firstOrFail();
            $this->requireNotEditorialRevisionAttachment($locked);
            $this->require($asset->status === 'published' && $asset->archived_at === null, 'Only active published asset attachments may be edited.');
            $this->requirePublicMetadata($asset);
            $locked->fill($data);
            $locked->placement = $this->canonicalPlacement($locked->placement);
            $this->requireAvailableSlot([
                'attachable_type' => $locked->attachable_type, 'attachable_id' => $locked->attachable_id,
                'placement' => $locked->placement, 'display_order' => $locked->display_order,
            ], $locked->id);
            $locked->save();

            return $locked->refresh();
        });
    }

    private function attachmentMetadata(array $data, bool $creating): array
    {
        // Explicit presence distinguishes omitted values from null/blank on direct calls.
        foreach (['focal_x', 'focal_y'] as $coordinate) {
            if (array_key_exists($coordinate, $data)) {
                $data[$coordinate] = $this->focalCoordinate($data[$coordinate], $coordinate);
            } elseif ($creating) {
                $data[$coordinate] = '50.00';
            }
        }
        if (array_key_exists('placement', $data) && is_string($data['placement'])) {
            $data['placement'] = $this->canonicalPlacement($data['placement']);
        }
        $presence = $creating ? 'required' : 'sometimes|required';

        return Validator::make($data, [
            'placement' => ['bail', ...explode('|', $presence), 'string', 'max:60', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/'],
            'display_order' => $presence.'|integer|min:0',
            'caption' => 'nullable|string|max:10000', 'alt_text' => 'nullable|string|max:255',
            'focal_x' => 'sometimes|required', 'focal_y' => 'sometimes|required',
        ])->validate();
    }

    private function focalCoordinate(mixed $value, string $field): string
    {
        if (is_int($value) && $value >= 0 && $value <= 100) {
            return $value.'.00';
        }
        if (is_float($value) && is_finite($value) && $value >= 0 && $value <= 100) {
            $decimal = sprintf('%.2F', $value);
            // Round-trip equality rejects precision loss, including adjacent float values.
            if ((float) $decimal === $value) {
                return $value == 0 ? '0.00' : $decimal;
            }
        }
        if (is_string($value) && preg_match('/\A(?:0|[1-9][0-9]?|100)(?:\.[0-9]{1,2})?\z/', $value)
            && (float) $value <= 100) {
            $parts = explode('.', $value);

            return $parts[0].'.'.str_pad($parts[1] ?? '', 2, '0');
        }

        throw ValidationException::withMessages([
            $field => 'Use a number from 0 to 100 with at most two decimal places; blank, null and exponent values are not allowed.',
        ]);
    }

    private function canonicalPlacement(string $placement): string
    {
        // ASCII-only conversion: no Unicode transliteration or invalid-character removal.
        return strtr(trim($placement, " \t\n\r\x0B\x0C"), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    private function requireAvailableSlot(array $slot, ?int $exceptId = null): void
    {
        $owner = app(MediaUsage::class)->owner($slot['attachable_type'], (int) $slot['attachable_id']);
        // Serialize competing slots across different assets for the same owner on MySQL.
        $owner->newQuery()->whereKey($owner->getKey())->lockForUpdate()->firstOrFail();
        $query = MediaAttachment::query()->where('attachable_type', $slot['attachable_type'])
            ->where('attachable_id', $owner->getKey())->where('placement', $slot['placement'])
            ->where('display_order', (int) $slot['display_order']);
        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }
        // Locking read observes the current slot after waiting for an owner lock.
        if ($query->lockForUpdate()->first() !== null) {
            throw ValidationException::withMessages(['placement' => 'This owner placement and display-order slot is already occupied.']);
        }
    }

    public function detach(User $actor, MediaAttachment $attachment): void
    {
        $this->authorize($actor, 'media.publish');
        DB::transaction(function () use ($attachment) {
            $asset = MediaAsset::whereKey($attachment->media_asset_id)->lockForUpdate()->firstOrFail();
            $locked = $asset->attachments()->whereKey($attachment->id)->lockForUpdate()->firstOrFail();
            $this->requireNotEditorialRevisionAttachment($locked);
            app(MediaUsage::class)->requireAttachmentMayBeDetached($locked);
            $locked->delete();
        });
    }

    /**
     * A News author can select a reviewed public asset for a draft revision
     * without acquiring any of the broader Media Library permissions.
     */
    public function replaceNewsStoryRevisionCover(User $actor, NewsStoryRevision $revision, MediaAsset $asset, array $metadata = []): MediaAttachment
    {
        Gate::forUser($actor)->authorize('news.author');
        app(MediaSchema::class)->ensureReady();
        $metadata = $this->attachmentMetadata(array_replace($metadata, [
            'placement' => 'cover',
            'display_order' => 0,
        ]), true);

        return DB::transaction(function () use ($revision, $asset, $metadata) {
            $lockedRevision = NewsStoryRevision::query()->whereKey($revision->id)->lockForUpdate()->firstOrFail();
            $this->require($lockedRevision->workflow_state === 'draft', 'Only a draft News revision may change its cover image.');
            $lockedAsset = MediaAsset::whereKey($asset->id)->lockForUpdate()->firstOrFail();
            $this->require($lockedAsset->status === 'published' && $lockedAsset->archived_at === null, 'Only active published assets may be selected.');
            $this->requirePublicMetadata($lockedAsset);

            $existing = MediaAttachment::query()
                ->where('attachable_type', 'news-story-revision')
                ->where('attachable_id', $lockedRevision->getKey())
                ->where('placement', 'cover')
                ->where('display_order', 0)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $existing->fill($metadata + ['media_asset_id' => $lockedAsset->getKey()]);
                $existing->save();

                return $existing->refresh();
            }

            return $lockedAsset->attachments()->create($metadata + [
                'attachable_type' => 'news-story-revision',
                'attachable_id' => $lockedRevision->getKey(),
            ]);
        });
    }

    /**
     * A Gallery author can select a public-safe cover for a draft album
     * revision without gaining broad Media Library publication permissions.
     */
    public function replaceGalleryAlbumRevisionCover(User $actor, GalleryAlbumRevision $revision, MediaAsset $asset, array $metadata = []): MediaAttachment
    {
        Gate::forUser($actor)->authorize('gallery.author');
        app(MediaSchema::class)->ensureReady();
        $metadata = $this->attachmentMetadata(array_replace($metadata, [
            'placement' => 'cover',
            'display_order' => 0,
        ]), true);

        return DB::transaction(function () use ($revision, $asset, $metadata): MediaAttachment {
            $lockedRevision = GalleryAlbumRevision::query()->whereKey($revision->id)->lockForUpdate()->firstOrFail();
            $this->require($lockedRevision->workflow_state === 'draft', 'Only a draft Gallery revision may change its cover image.');
            $lockedAsset = MediaAsset::whereKey($asset->id)->lockForUpdate()->firstOrFail();
            $this->require($lockedAsset->status === 'published' && $lockedAsset->archived_at === null, 'Only active published assets may be selected.');
            $this->requirePublicMetadata($lockedAsset);

            $existing = MediaAttachment::query()
                ->where('attachable_type', 'gallery-album-revision')
                ->where('attachable_id', $lockedRevision->getKey())
                ->where('placement', 'cover')
                ->where('display_order', 0)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $existing->fill($metadata + ['media_asset_id' => $lockedAsset->getKey()]);
                $existing->save();

                return $existing->refresh();
            }

            return $lockedAsset->attachments()->create($metadata + [
                'attachable_type' => 'gallery-album-revision',
                'attachable_id' => $lockedRevision->getKey(),
            ]);
        });
    }

    /**
     * A public-page author can select one consent-safe feature image for a
     * draft, typed page revision without gaining broad Media Library access.
     */
    public function replacePublicPageRevisionFeature(User $actor, PublicPageRevision $revision, MediaAsset $asset, array $metadata = []): MediaAttachment
    {
        Gate::forUser($actor)->authorize('public_pages.author');
        app(MediaSchema::class)->ensureReady();
        $metadata = $this->attachmentMetadata(array_replace($metadata, [
            'placement' => 'feature',
            'display_order' => 0,
        ]), true);

        return DB::transaction(function () use ($revision, $asset, $metadata): MediaAttachment {
            $lockedRevision = PublicPageRevision::query()->whereKey($revision->id)->lockForUpdate()->firstOrFail();
            $this->require($lockedRevision->workflow_state === 'draft', 'Only a draft Public Page revision may change its feature image.');
            $lockedAsset = MediaAsset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();
            $this->require($lockedAsset->status === 'published' && $lockedAsset->archived_at === null, 'Only active published assets may be selected.');
            $this->requirePublicMetadata($lockedAsset);

            $existing = MediaAttachment::query()
                ->where('attachable_type', 'public-page-revision')
                ->where('attachable_id', $lockedRevision->getKey())
                ->where('placement', 'feature')
                ->where('display_order', 0)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $existing->fill($metadata + ['media_asset_id' => $lockedAsset->getKey()]);
                $existing->save();

                return $existing->refresh();
            }

            return $lockedAsset->attachments()->create($metadata + [
                'attachable_type' => 'public-page-revision',
                'attachable_id' => $lockedRevision->getKey(),
            ]);
        });
    }

    public function clearPublicPageRevisionFeature(User $actor, PublicPageRevision $revision): void
    {
        Gate::forUser($actor)->authorize('public_pages.author');
        app(MediaSchema::class)->ensureReady();

        DB::transaction(function () use ($revision): void {
            $lockedRevision = PublicPageRevision::query()->whereKey($revision->id)->lockForUpdate()->firstOrFail();
            $this->require($lockedRevision->workflow_state === 'draft', 'Only a draft Public Page revision may remove its feature image.');
            MediaAttachment::query()
                ->where('attachable_type', 'public-page-revision')
                ->where('attachable_id', $lockedRevision->getKey())
                ->where('placement', 'feature')
                ->where('display_order', 0)
                ->lockForUpdate()
                ->first()?->delete();
        });
    }

    /**
     * A public-team author can select one consent-safe portrait for a draft
     * profile revision without exposing a legacy staff photo path.
     */
    public function replacePublicTeamProfileRevisionPortrait(User $actor, PublicTeamProfileRevision $revision, MediaAsset $asset, array $metadata = []): MediaAttachment
    {
        Gate::forUser($actor)->authorize('public_team.author');
        app(MediaSchema::class)->ensureReady();
        $metadata = $this->attachmentMetadata(array_replace($metadata, [
            'placement' => 'portrait',
            'display_order' => 0,
        ]), true);

        return DB::transaction(function () use ($revision, $asset, $metadata): MediaAttachment {
            $lockedRevision = PublicTeamProfileRevision::query()->whereKey($revision->id)->lockForUpdate()->firstOrFail();
            $this->require($lockedRevision->workflow_state === 'draft', 'Only a draft Team Profile revision may change its portrait.');
            $lockedAsset = MediaAsset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();
            $this->require($lockedAsset->status === 'published' && $lockedAsset->archived_at === null, 'Only active published assets may be selected.');
            $this->requirePublicMetadata($lockedAsset);

            $existing = MediaAttachment::query()
                ->where('attachable_type', 'public-team-profile-revision')
                ->where('attachable_id', $lockedRevision->getKey())
                ->where('placement', 'portrait')
                ->where('display_order', 0)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $existing->fill($metadata + ['media_asset_id' => $lockedAsset->getKey()]);
                $existing->save();

                return $existing->refresh();
            }

            return $lockedAsset->attachments()->create($metadata + [
                'attachable_type' => 'public-team-profile-revision',
                'attachable_id' => $lockedRevision->getKey(),
            ]);
        });
    }

    public function clearPublicTeamProfileRevisionPortrait(User $actor, PublicTeamProfileRevision $revision): void
    {
        Gate::forUser($actor)->authorize('public_team.author');
        app(MediaSchema::class)->ensureReady();

        DB::transaction(function () use ($revision): void {
            $lockedRevision = PublicTeamProfileRevision::query()->whereKey($revision->id)->lockForUpdate()->firstOrFail();
            $this->require($lockedRevision->workflow_state === 'draft', 'Only a draft Team Profile revision may remove its portrait.');
            MediaAttachment::query()
                ->where('attachable_type', 'public-team-profile-revision')
                ->where('attachable_id', $lockedRevision->getKey())
                ->where('placement', 'portrait')
                ->where('display_order', 0)
                ->lockForUpdate()
                ->first()?->delete();
        });
    }

    /**
     * Add a public-safe image to a draft Gallery revision. New photos append
     * by default; callers can provide a free slot before using the dedicated
     * reordering action.
     */
    public function addGalleryAlbumRevisionPhoto(User $actor, GalleryAlbumRevision $revision, MediaAsset $asset, array $metadata = []): MediaAttachment
    {
        Gate::forUser($actor)->authorize('gallery.author');
        app(MediaSchema::class)->ensureReady();

        return DB::transaction(function () use ($revision, $asset, $metadata): MediaAttachment {
            $lockedRevision = GalleryAlbumRevision::query()->whereKey($revision->id)->lockForUpdate()->firstOrFail();
            $this->require($lockedRevision->workflow_state === 'draft', 'Only a draft Gallery revision may change its photos.');
            $lockedAsset = MediaAsset::whereKey($asset->id)->lockForUpdate()->firstOrFail();
            $this->require($lockedAsset->status === 'published' && $lockedAsset->archived_at === null, 'Only active published assets may be selected.');
            $this->requirePublicMetadata($lockedAsset);
            $lastOrder = MediaAttachment::query()
                ->where('attachable_type', 'gallery-album-revision')
                ->where('attachable_id', $lockedRevision->getKey())
                ->where('placement', 'photo')
                ->lockForUpdate()
                ->max('display_order');
            $nextOrder = $lastOrder === null ? 0 : ((int) $lastOrder) + 1;
            $photoMetadata = $this->attachmentMetadata(array_replace($metadata, [
                'placement' => 'photo',
                'display_order' => $metadata['display_order'] ?? $nextOrder,
            ]), true);
            $this->requireAvailableSlot([
                'attachable_type' => 'gallery-album-revision',
                'attachable_id' => $lockedRevision->getKey(),
                'placement' => 'photo',
                'display_order' => $photoMetadata['display_order'],
            ]);

            return $lockedAsset->attachments()->create($photoMetadata + [
                'attachable_type' => 'gallery-album-revision',
                'attachable_id' => $lockedRevision->getKey(),
            ]);
        });
    }

    /**
     * Replace the asset or accessibility metadata for one draft Gallery photo.
     */
    public function replaceGalleryAlbumRevisionPhoto(
        User $actor,
        GalleryAlbumRevision $revision,
        MediaAttachment $photo,
        MediaAsset $asset,
        array $metadata = [],
    ): MediaAttachment {
        Gate::forUser($actor)->authorize('gallery.author');
        app(MediaSchema::class)->ensureReady();
        $metadata = $this->attachmentMetadata($metadata, false);
        unset($metadata['placement'], $metadata['display_order']);

        return DB::transaction(function () use ($revision, $photo, $asset, $metadata): MediaAttachment {
            $lockedRevision = GalleryAlbumRevision::query()->whereKey($revision->id)->lockForUpdate()->firstOrFail();
            $this->require($lockedRevision->workflow_state === 'draft', 'Only a draft Gallery revision may change its photos.');
            $lockedPhoto = MediaAttachment::query()
                ->whereKey($photo->id)
                ->where('attachable_type', 'gallery-album-revision')
                ->where('attachable_id', $lockedRevision->getKey())
                ->where('placement', 'photo')
                ->lockForUpdate()
                ->firstOrFail();
            $lockedAsset = MediaAsset::whereKey($asset->id)->lockForUpdate()->firstOrFail();
            $this->require($lockedAsset->status === 'published' && $lockedAsset->archived_at === null, 'Only active published assets may be selected.');
            $this->requirePublicMetadata($lockedAsset);
            $lockedPhoto->fill($metadata + ['media_asset_id' => $lockedAsset->getKey()]);
            $lockedPhoto->save();

            return $lockedPhoto->refresh();
        });
    }

    /**
     * Update caption, alt text, or focal point without replacing a Gallery
     * image. Ordering stays in the dedicated reorder operation.
     */
    public function updateGalleryAlbumRevisionPhoto(User $actor, GalleryAlbumRevision $revision, MediaAttachment $photo, array $metadata): MediaAttachment
    {
        Gate::forUser($actor)->authorize('gallery.author');
        app(MediaSchema::class)->ensureReady();
        $metadata = $this->attachmentMetadata($metadata, false);
        unset($metadata['placement'], $metadata['display_order']);

        return DB::transaction(function () use ($revision, $photo, $metadata): MediaAttachment {
            $lockedRevision = GalleryAlbumRevision::query()->whereKey($revision->id)->lockForUpdate()->firstOrFail();
            $this->require($lockedRevision->workflow_state === 'draft', 'Only a draft Gallery revision may change its photos.');
            $lockedPhoto = MediaAttachment::query()
                ->whereKey($photo->id)
                ->where('attachable_type', 'gallery-album-revision')
                ->where('attachable_id', $lockedRevision->getKey())
                ->where('placement', 'photo')
                ->lockForUpdate()
                ->firstOrFail();
            $lockedAsset = MediaAsset::query()->whereKey($lockedPhoto->media_asset_id)->lockForUpdate()->firstOrFail();
            $this->require($lockedAsset->status === 'published' && $lockedAsset->archived_at === null, 'Only active published assets may be selected.');
            $this->requirePublicMetadata($lockedAsset);
            $lockedPhoto->fill($metadata);
            $lockedPhoto->save();

            return $lockedPhoto->refresh();
        });
    }

    /**
     * Persist the exact public photo sequence for a draft Gallery revision.
     * A temporary offset avoids collisions with the attachment-slot index.
     *
     * @param  array<int, int|string>  $photoIds
     */
    public function reorderGalleryAlbumRevisionPhotos(User $actor, GalleryAlbumRevision $revision, array $photoIds): void
    {
        Gate::forUser($actor)->authorize('gallery.author');
        app(MediaSchema::class)->ensureReady();
        $validated = Validator::make(['photo_ids' => $photoIds], [
            'photo_ids' => ['required', 'array', 'min:1'],
            'photo_ids.*' => ['integer', 'distinct'],
        ])->validate();
        $photoIds = array_map(static fn (mixed $id): int => (int) $id, $validated['photo_ids']);

        DB::transaction(function () use ($revision, $photoIds): void {
            $lockedRevision = GalleryAlbumRevision::query()->whereKey($revision->id)->lockForUpdate()->firstOrFail();
            $this->require($lockedRevision->workflow_state === 'draft', 'Only a draft Gallery revision may change its photo order.');
            $photos = MediaAttachment::query()
                ->where('attachable_type', 'gallery-album-revision')
                ->where('attachable_id', $lockedRevision->getKey())
                ->where('placement', 'photo')
                ->lockForUpdate()
                ->get();
            $currentIds = $photos->pluck('id')->map(static fn (mixed $id): int => (int) $id)->sort()->values()->all();
            $requestedIds = collect($photoIds)->sort()->values()->all();
            $this->require($currentIds === $requestedIds, 'The Gallery photo order must include every current photo exactly once.');
            $offset = ((int) $photos->max('display_order')) + count($photoIds) + 1;
            MediaAttachment::query()->whereIn('id', $photoIds)->update([
                'display_order' => DB::raw('display_order + '.$offset),
            ]);

            foreach ($photoIds as $order => $photoId) {
                MediaAttachment::query()->whereKey($photoId)->update(['display_order' => $order]);
            }
        });
    }

    /**
     * Remove one image from a draft Gallery revision. Publication validation
     * prevents the last image from being released as an empty public album.
     */
    public function removeGalleryAlbumRevisionPhoto(User $actor, GalleryAlbumRevision $revision, MediaAttachment $photo): void
    {
        Gate::forUser($actor)->authorize('gallery.author');
        app(MediaSchema::class)->ensureReady();

        DB::transaction(function () use ($revision, $photo): void {
            $lockedRevision = GalleryAlbumRevision::query()->whereKey($revision->id)->lockForUpdate()->firstOrFail();
            $this->require($lockedRevision->workflow_state === 'draft', 'Only a draft Gallery revision may change its photos.');
            $lockedPhoto = MediaAttachment::query()
                ->whereKey($photo->id)
                ->where('attachable_type', 'gallery-album-revision')
                ->where('attachable_id', $lockedRevision->getKey())
                ->where('placement', 'photo')
                ->lockForUpdate()
                ->firstOrFail();
            $lockedPhoto->delete();

            $remainingPhotos = MediaAttachment::query()
                ->where('attachable_type', 'gallery-album-revision')
                ->where('attachable_id', $lockedRevision->getKey())
                ->where('placement', 'photo')
                ->orderBy('display_order')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            foreach ($remainingPhotos as $displayOrder => $remainingPhoto) {
                if ($remainingPhoto->display_order !== $displayOrder) {
                    $remainingPhoto->display_order = $displayOrder;
                    $remainingPhoto->save();
                }
            }
        });
    }

    public function confirmConsent(User $actor, MediaAsset $asset): MediaAsset
    {
        $this->authorize($actor, 'media.review');

        return $this->change($asset, function (MediaAsset $locked) use ($actor) {
            $this->require($locked->consent_state !== 'withdrawn' && $locked->consent_withdrawn_at === null, 'Withdrawn consent cannot be reactivated; upload a newly approved asset instead.');
            $locked->fill([
                'consent_state' => 'confirmed',
                'consent_confirmed_by' => $actor->id,
                'consent_confirmed_at' => now(),
            ]);
        });
    }

    public function markConsentNotRequired(User $actor, MediaAsset $asset): MediaAsset
    {
        $this->authorize($actor, 'media.review');

        return $this->change($asset, function (MediaAsset $locked) {
            $this->require($locked->consent_state !== 'withdrawn' && $locked->consent_withdrawn_at === null, 'Withdrawn consent cannot be reactivated; upload a newly approved asset instead.');
            $locked->consent_state = 'not_required';
        });
    }

    public function withdrawConsent(User $actor, MediaAsset $asset): MediaAsset
    {
        $this->authorize($actor, 'media.publish');

        return DB::transaction(function () use ($actor, $asset) {
            $locked = MediaAsset::whereKey($asset->id)->lockForUpdate()->firstOrFail();
            app(MediaUsage::class)->withdrawCurrentPublicNewsStories($locked, $actor);
            $locked->fill([
                'consent_state' => 'withdrawn',
                'consent_withdrawn_by' => $actor->id,
                'consent_withdrawn_at' => now(),
            ]);
            $locked->save();

            return $locked->refresh();
        });
    }

    private function change(MediaAsset $asset, callable $change): MediaAsset
    {
        return DB::transaction(function () use ($asset, $change) {
            $locked = MediaAsset::whereKey($asset->id)->lockForUpdate()->firstOrFail();
            $change($locked);
            $locked->save();

            return $locked->refresh();
        });
    }

    private function reviewer(User $actor, MediaAsset $asset): void
    {
        $this->require($asset->status === 'review' && $asset->archived_at === null, 'Only an active Review asset may be approved/published.');
        abort_if($asset->uploaded_by === $actor->id && ! $this->admin($actor), 403, 'A different authorized reviewer must approve and publish this asset.');
    }

    private function requireNotEditorialRevisionAttachment(MediaAttachment $attachment): void
    {
        $message = match ($attachment->attachable_type) {
            'news-story-revision' => 'Use the News editor to change a story cover image.',
            'gallery-album-revision' => 'Use the Gallery editor to change album media.',
            'public-page-revision' => 'Use the Public Pages editor to change a page feature image.',
            'public-team-profile-revision' => 'Use the Public Team editor to change a profile portrait.',
            default => null,
        };

        $this->require($message === null, $message ?? '');
    }

    private function requirePublicMetadata(MediaAsset $asset): void
    {
        $this->require(
            in_array($asset->consent_state, ['not_required', 'confirmed'], true) && $asset->consent_withdrawn_at === null,
            'Consent has been withdrawn; this asset cannot be used publicly.',
        );
        $this->require($asset->is_decorative || trim((string) $asset->default_alt_text) !== '', 'Meaningful public alt text is required unless the asset is explicitly decorative.');
    }

    private function admin(User $actor): bool
    {
        // Same existing admin role as Gate::before, plus its configured wildcard.
        return $actor->hasRole('admin') && in_array('*', config('permissions.roles.admin', []), true);
    }

    private function imageInfo(string $bytes): array
    {
        $this->require(function_exists('imagecreatefromstring'), 'GD image processing is unavailable.');
        $info = @getimagesizefromstring($bytes);
        $this->require($info !== false && in_array($info['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true), 'Use a valid JPEG, PNG, WebP or GIF image.');
        $this->require($info[0] * $info[1] <= self::MAX_PIXELS, 'The image exceeds the processing dimension limit.');
        $decoded = @imagecreatefromstring($bytes);
        $this->require($decoded !== false, 'The image could not be decoded.');
        imagedestroy($decoded);

        return $info;
    }

    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['media' => $message]);
        }
    }
}
