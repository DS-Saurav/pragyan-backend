<?php

namespace App\Services\Gallery;

use App\Models\GalleryAlbum;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GalleryVisibility
{
    public const FEATURED_SETTING = 'website_featured_gallery_album_id';

    /** @return Builder<GalleryAlbum> */
    public function displayableQuery(): Builder
    {
        if (! $this->schemaIsReady()) {
            return GalleryAlbum::query()->fromSub(
                DB::query()->selectRaw('1 as id')->whereRaw('1 = 0'),
                'gallery_albums',
            );
        }

        return GalleryAlbum::query()->where('is_active', true);
    }

    public function findDisplayableBySlug(string $slug): ?GalleryAlbum
    {
        if (! $this->schemaIsReady()) {
            return null;
        }

        return $this->displayableQuery()
            ->with(['category', 'coverPhoto', 'photos'])
            ->withCount('photos')
            ->where('slug', $slug)
            ->first();
    }

    public function featured(): ?GalleryAlbum
    {
        if (! $this->schemaIsReady()) {
            return null;
        }

        $featuredId = Setting::get(self::FEATURED_SETTING);
        if (! is_numeric($featuredId)) {
            return null;
        }

        return $this->displayableQuery()
            ->with(['category', 'coverPhoto', 'firstPhoto'])
            ->withCount('photos')
            ->whereKey((int) $featuredId)
            ->first();
    }

    public function hasPublished(): bool
    {
        if (! $this->schemaIsReady()) {
            return false;
        }

        return $this->displayableQuery()->exists();
    }

    public function isDisplayable(GalleryAlbum $album): bool
    {
        return (bool) $album->is_active;
    }

    private function schemaIsReady(): bool
    {
        return Schema::hasTable('gallery_albums')
            && Schema::hasTable('gallery');
    }
}
