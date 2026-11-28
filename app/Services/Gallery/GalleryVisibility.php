<?php

namespace App\Services\Gallery;

use App\Models\GalleryAlbum;
use App\Models\GalleryCategory;
use App\Models\GalleryPhoto;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

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

    /**
     * Return active gallery categories with their latest active album photos
     * for dynamic homepage display, plus a combined default photo list.
     *
     * @return array{
     *   categories: array<int, array{id: int, name: string, slug: string, photos: array<int, array<string, mixed>>}>,
     *   photos: array<int, array<string, mixed>>
     * }
     */
    public function homepageGallery(int $perCategory = 5, int $totalLimit = 5): array
    {
        if (! $this->schemaIsReady() || ! Schema::hasTable('gallery_categories')) {
            return ['categories' => [], 'photos' => []];
        }

        $categories = GalleryCategory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $categoryBlocks = [];
        $allPhotos = [];

        foreach ($categories as $category) {
            $photos = GalleryPhoto::query()
                ->with('album:id,slug,title,category_id')
                ->whereHas('album', function (Builder $q) use ($category): void {
                    $q->where('is_active', true)
                        ->where('category_id', $category->id);
                })
                ->orderBy('sort_order')
                ->orderByDesc('id')
                ->limit($perCategory)
                ->get()
                ->map(fn (GalleryPhoto $photo): array => $this->formatHomepagePhoto($photo, $category))
                ->filter(fn (array $item): bool => filled($item['url']))
                ->values()
                ->all();

            if ($photos === []) {
                continue;
            }

            $categoryBlocks[] = [
                'id' => (int) $category->id,
                'name' => (string) $category->name,
                'slug' => (string) $category->slug,
                'photos' => $photos,
            ];

            foreach ($photos as $photo) {
                $allPhotos[] = $photo;
            }
        }

        return [
            'categories' => $categoryBlocks,
            'photos' => array_slice($allPhotos, 0, $totalLimit),
        ];
    }

    /** @return array<string, mixed> */
    private function formatHomepagePhoto(GalleryPhoto $photo, GalleryCategory $category): array
    {
        $path = trim((string) $photo->image_path);

        return [
            'id' => (int) $photo->id,
            'url' => $path !== '' ? Storage::disk('r2')->url($path) : '',
            'alt' => (string) ($photo->alt ?: $photo->caption ?: $photo->album?->title ?: $category->name),
            'album_slug' => $photo->album?->slug,
        ];
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
