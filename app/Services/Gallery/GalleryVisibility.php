<?php

namespace App\Services\Gallery;

use App\Models\GalleryAlbum;
use App\Models\GalleryAlbumSlug;
use App\Models\Setting;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GalleryVisibility
{
    /** @return Builder<GalleryAlbum> */
    public function displayableQuery(?CarbonInterface $now = null): Builder
    {
        if (! $this->schemaIsReady()) {
            return GalleryAlbum::query()->fromSub(
                DB::query()->selectRaw('1 as id')->whereRaw('1 = 0'),
                'gallery_albums',
            );
        }

        return $this->queryAt($this->atSchoolTime($now));
    }

    /**
     * @return array{album: ?GalleryAlbum, redirect: ?GalleryAlbum}
     */
    public function findDisplayableSlug(string $slug, ?CarbonInterface $now = null): array
    {
        if (! $this->schemaIsReady()) {
            return ['album' => null, 'redirect' => null];
        }

        $album = $this->displayableQuery($now)
            ->with(['publishedRevision.cover.asset', 'publishedRevision.photos.asset'])
            ->where('slug', $slug)
            ->first();
        if ($album !== null) {
            return ['album' => $album, 'redirect' => null];
        }

        $formerSlug = GalleryAlbumSlug::query()
            ->where('slug', $slug)
            ->with(['album.publishedRevision.cover.asset', 'album.publishedRevision.photos.asset'])
            ->first();
        if ($formerSlug === null || $formerSlug->album === null || ! $this->isDisplayable($formerSlug->album, $now)) {
            return ['album' => null, 'redirect' => null];
        }

        return ['album' => $formerSlug->album, 'redirect' => $formerSlug->album];
    }

    public function featured(?CarbonInterface $now = null): ?GalleryAlbum
    {
        if (! $this->schemaIsReady()) {
            return null;
        }

        return $this->featuredAt($this->atSchoolTime($now));
    }

    public function hasPublished(?CarbonInterface $now = null): bool
    {
        if (! $this->schemaIsReady()) {
            return false;
        }

        return $this->queryAt($this->atSchoolTime($now))->exists();
    }

    public function isDisplayable(GalleryAlbum $album, ?CarbonInterface $now = null): bool
    {
        $time = $this->atSchoolTime($now);

        return $album->publication_state === 'published'
            && $album->published_revision_id !== null
            && $album->published_at !== null
            && $album->published_at->lessThanOrEqualTo($time)
            && $album->archived_at === null;
    }

    /** @return Builder<GalleryAlbum> */
    private function queryAt(CarbonImmutable $time): Builder
    {
        return GalleryAlbum::query()
            ->where('publication_state', 'published')
            ->whereNotNull('published_revision_id')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', $time)
            ->whereNull('archived_at');
    }

    private function featuredAt(CarbonImmutable $time): ?GalleryAlbum
    {
        $featuredId = Setting::get(GalleryPublisher::FEATURED_SETTING);
        if (! is_numeric($featuredId)) {
            return null;
        }

        return $this->queryAt($time)
            ->with(['publishedRevision.cover.asset', 'publishedRevision.photos.asset'])
            ->whereKey((int) $featuredId)
            ->first();
    }

    private function atSchoolTime(?CarbonInterface $value): CarbonImmutable
    {
        return $value === null
            ? CarbonImmutable::now('UTC')
            : CarbonImmutable::instance($value)->utc();
    }

    private function schemaIsReady(): bool
    {
        return Schema::hasTable('gallery_albums')
            && Schema::hasTable('gallery_album_revisions')
            && Schema::hasTable('gallery_album_slugs');
    }
}
