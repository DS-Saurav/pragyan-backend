<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GalleryAlbum;
use App\Models\GalleryCategory;
use App\Models\GalleryPhoto;
use App\Models\Setting;
use App\Services\Gallery\GalleryVisibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class GalleryAlbumController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorizeAny(['gallery.author', 'gallery.review', 'gallery.publish']);

        $albums = GalleryAlbum::query()
            ->with(['category', 'coverPhoto', 'firstPhoto'])
            ->withCount('photos')
            ->orderByDesc('id')
            ->paginate(30)
            ->through(fn (GalleryAlbum $album): array => $this->serializeAlbum($album));

        return response()->json([
            'albums' => $albums,
            'categories' => GalleryCategory::query()->orderBy('id')->get(),
            'featuredAlbumId' => (int) Setting::get(GalleryVisibility::FEATURED_SETTING),
        ]);
    }

    public function show(GalleryAlbum $galleryAlbum): JsonResponse
    {
        $this->authorizeAny(['gallery.author', 'gallery.review', 'gallery.publish']);
        $galleryAlbum->load(['category', 'coverPhoto', 'photos'])->loadCount('photos');

        return response()->json([
            'album' => $this->serializeAlbum($galleryAlbum, true),
            'categories' => GalleryCategory::query()->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('gallery.author');

        $data = $this->validatedAlbum($request);
        $slug = $this->uniqueAlbumSlug($data['slug'] ?? $data['title']);

        $album = GalleryAlbum::create([
            'category_id' => $data['category_id'] ?? null,
            'title' => trim($data['title']),
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'cover_image_id' => null,
            'event_date' => $data['event_date'] ?? null,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
        ]);

        $album->load('category')->loadCount('photos');

        return response()->json([
            'message' => 'Gallery album created.',
            'album' => $this->serializeAlbum($album),
        ], 201);
    }

    public function update(Request $request, GalleryAlbum $galleryAlbum): JsonResponse
    {
        Gate::authorize('gallery.author');

        $data = $this->validatedAlbum($request, $galleryAlbum);
        $slug = $this->uniqueAlbumSlug($data['slug'] ?? $data['title'], $galleryAlbum);

        $galleryAlbum->update([
            'category_id' => array_key_exists('category_id', $data) ? $data['category_id'] : $galleryAlbum->category_id,
            'title' => trim($data['title']),
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'event_date' => $data['event_date'] ?? null,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $galleryAlbum->is_active,
        ]);

        if (! $galleryAlbum->is_active && (string) Setting::get(GalleryVisibility::FEATURED_SETTING) === (string) $galleryAlbum->id) {
            Setting::set(GalleryVisibility::FEATURED_SETTING, null);
        }

        $galleryAlbum->load(['category', 'coverPhoto', 'photos'])->loadCount('photos');

        return response()->json([
            'message' => 'Gallery album updated.',
            'album' => $this->serializeAlbum($galleryAlbum, true),
        ]);
    }

    public function destroy(GalleryAlbum $galleryAlbum): JsonResponse
    {
        Gate::authorize('gallery.publish');

        $galleryAlbum->load('photos');

        DB::transaction(function () use ($galleryAlbum): void {
            if ((string) Setting::get(GalleryVisibility::FEATURED_SETTING) === (string) $galleryAlbum->id) {
                Setting::set(GalleryVisibility::FEATURED_SETTING, null);
            }

            if ($galleryAlbum->cover_image_id !== null) {
                $galleryAlbum->update(['cover_image_id' => null]);
            }

            foreach ($galleryAlbum->photos as $photo) {
                if ($photo->image_path && Storage::disk('r2')->exists($photo->image_path)) {
                    Storage::disk('r2')->delete($photo->image_path);
                }
            }

            $galleryAlbum->delete();
        });

        return response()->json(['message' => 'Gallery album deleted.']);
    }

    public function setCover(GalleryAlbum $galleryAlbum, GalleryPhoto $photo): JsonResponse
    {
        Gate::authorize('gallery.author');
        abort_unless($photo->album_id === $galleryAlbum->id, 404);

        $galleryAlbum->update(['cover_image_id' => $photo->id]);
        $galleryAlbum->load(['category', 'coverPhoto', 'photos'])->loadCount('photos');

        return response()->json([
            'message' => 'Album cover image updated.',
            'album' => $this->serializeAlbum($galleryAlbum, true),
        ]);
    }

    public function addPhotos(Request $request, GalleryAlbum $galleryAlbum): JsonResponse
    {
        Gate::authorize('gallery.author');

        $request->validate([
            'images' => ['required_without:image', 'array', 'min:1'],
            'images.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:8192'],
            'image' => ['required_without:images', 'file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:8192'],
            'alt' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:500'],
        ]);

        $files = $request->hasFile('images')
            ? $request->file('images')
            : [$request->file('image')];

        $alt = trim((string) $request->input('alt', ''));
        $caption = trim((string) $request->input('caption', ''));

        $created = DB::transaction(function () use ($galleryAlbum, $files, $alt, $caption): array {
            $nextOrder = ((int) $galleryAlbum->photos()->max('sort_order')) + 1;
            if ($galleryAlbum->photos()->count() === 0) {
                $nextOrder = 0;
            }

            $items = [];
            foreach ($files as $file) {
                $path = $file->store('gallery/photos', 'r2');
                $items[] = $galleryAlbum->photos()->create([
                    'image_path' => $path,
                    'alt' => $alt !== '' ? $alt : $galleryAlbum->title,
                    'caption' => $caption !== '' ? $caption : null,
                    'sort_order' => $nextOrder++,
                ]);
            }

            return $items;
        });

        return response()->json([
            'message' => count($created) === 1 ? 'Photo added to album.' : count($created).' photos added to album.',
            'photos' => array_map(fn (GalleryPhoto $p): array => $this->serializePhoto($p), $created),
        ], 201);
    }

    public function updatePhoto(Request $request, GalleryAlbum $galleryAlbum, GalleryPhoto $photo): JsonResponse
    {
        Gate::authorize('gallery.author');
        abort_unless($photo->album_id === $galleryAlbum->id, 404);

        $data = $request->validate([
            'alt' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:500'],
        ]);

        $photo->update([
            'alt' => array_key_exists('alt', $data) ? trim((string) $data['alt']) : $photo->alt,
            'caption' => array_key_exists('caption', $data)
                ? (trim((string) $data['caption']) !== '' ? trim((string) $data['caption']) : null)
                : $photo->caption,
        ]);

        return response()->json([
            'message' => 'Photo updated.',
            'photo' => $this->serializePhoto($photo->fresh()),
        ]);
    }

    public function removePhoto(GalleryAlbum $galleryAlbum, GalleryPhoto $photo): JsonResponse
    {
        Gate::authorize('gallery.author');
        abort_unless($photo->album_id === $galleryAlbum->id, 404);

        DB::transaction(function () use ($galleryAlbum, $photo): void {
            if ($photo->image_path && Storage::disk('r2')->exists($photo->image_path)) {
                Storage::disk('r2')->delete($photo->image_path);
            }
            $photo->delete();

            $remaining = $galleryAlbum->photos()->orderBy('sort_order')->orderBy('id')->get();
            foreach ($remaining as $index => $item) {
                if ($item->sort_order !== $index) {
                    $item->update(['sort_order' => $index]);
                }
            }
        });

        return response()->json(['message' => 'Photo removed from album.']);
    }

    public function reorderPhotos(Request $request, GalleryAlbum $galleryAlbum): JsonResponse
    {
        Gate::authorize('gallery.author');

        $validated = $request->validate([
            'photo_ids' => ['required', 'array', 'min:1'],
            'photo_ids.*' => ['integer', 'distinct'],
        ]);

        $photoIds = array_map('intval', $validated['photo_ids']);

        DB::transaction(function () use ($galleryAlbum, $photoIds): void {
            $currentIds = $galleryAlbum->photos()->pluck('id')->map('intval')->sort()->values()->all();
            $requestedIds = collect($photoIds)->sort()->values()->all();
            abort_unless($currentIds === $requestedIds, 422, 'Photo order must include every photo in this album exactly once.');

            foreach ($photoIds as $order => $photoId) {
                GalleryPhoto::query()
                    ->where('album_id', $galleryAlbum->id)
                    ->whereKey($photoId)
                    ->update(['sort_order' => $order]);
            }
        });

        return response()->json(['message' => 'Photo order saved.']);
    }

    public function feature(GalleryAlbum $galleryAlbum): JsonResponse
    {
        Gate::authorize('gallery.publish');
        abort_unless($galleryAlbum->is_active, 422, 'Only an active album can be featured on the homepage.');
        Setting::set(GalleryVisibility::FEATURED_SETTING, (string) $galleryAlbum->id);

        return response()->json(['message' => 'Album featured on the homepage.']);
    }

    public function unfeature(GalleryAlbum $galleryAlbum): JsonResponse
    {
        Gate::authorize('gallery.publish');
        Setting::set(GalleryVisibility::FEATURED_SETTING, null);

        return response()->json(['message' => 'Homepage featured album removed.']);
    }

    // -------------------------------------------------------------------------
    // Categories
    // -------------------------------------------------------------------------

    public function categories(): JsonResponse
    {
        $this->authorizeAny(['gallery.author', 'gallery.review', 'gallery.publish']);

        return response()->json([
            'categories' => GalleryCategory::query()
                ->withCount('albums')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        Gate::authorize('gallery.author');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $slug = $this->uniqueCategorySlug($data['slug'] ?? $data['name']);

        $category = GalleryCategory::create([
            'name' => trim($data['name']),
            'slug' => $slug,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
        ]);

        return response()->json([
            'message' => 'Category created.',
            'category' => $category,
        ], 201);
    }

    public function updateCategory(Request $request, GalleryCategory $category): JsonResponse
    {
        Gate::authorize('gallery.author');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $slug = $this->uniqueCategorySlug($data['slug'] ?? $data['name'], $category);

        $category->update([
            'name' => trim($data['name']),
            'slug' => $slug,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $category->is_active,
        ]);

        return response()->json([
            'message' => 'Category updated.',
            'category' => $category->fresh(),
        ]);
    }

    public function destroyCategory(GalleryCategory $category): JsonResponse
    {
        Gate::authorize('gallery.publish');
        $category->delete();

        return response()->json(['message' => 'Category deleted.']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function validatedAlbum(Request $request, ?GalleryAlbum $album = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['nullable', 'integer', Rule::exists('gallery_categories', 'id')],
            'event_date' => ['nullable', 'date_format:Y-m-d'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function uniqueAlbumSlug(string $source, ?GalleryAlbum $ignore = null): string
    {
        $base = Str::limit(Str::slug(trim($source)), 240, '') ?: 'album';
        $slug = $base;
        $suffix = 2;

        while (
            GalleryAlbum::query()
                ->where('slug', $slug)
                ->when($ignore !== null, fn ($q) => $q->whereKeyNot($ignore->id))
                ->exists()
        ) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function uniqueCategorySlug(string $source, ?GalleryCategory $ignore = null): string
    {
        $base = Str::limit(Str::slug(trim($source)), 110, '') ?: 'category';
        $slug = $base;
        $suffix = 2;

        while (
            GalleryCategory::query()
                ->where('slug', $slug)
                ->when($ignore !== null, fn ($q) => $q->whereKeyNot($ignore->id))
                ->exists()
        ) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /** @return array<string, mixed> */
    private function serializeAlbum(GalleryAlbum $album, bool $includePhotos = false): array
    {
        $coverPhoto = $album->relationLoaded('coverPhoto') ? $album->coverPhoto : null;
        if ($coverPhoto === null && $album->cover_image_id !== null && $album->relationLoaded('photos')) {
            $coverPhoto = $album->photos->firstWhere('id', $album->cover_image_id);
        }
        if ($coverPhoto === null) {
            $coverPhoto = $album->relationLoaded('firstPhoto')
                ? $album->firstPhoto
                : ($album->relationLoaded('photos') ? $album->photos->first() : null);
        }

        $coverPath = $coverPhoto !== null ? trim((string) $coverPhoto->image_path) : '';

        $data = [
            'id' => $album->id,
            'category_id' => $album->category_id,
            'category' => $album->category,
            'title' => $album->title,
            'slug' => $album->slug,
            'description' => $album->description,
            'cover_image_id' => $album->cover_image_id,
            'cover_url' => $coverPath !== '' ? Storage::disk('r2')->url($coverPath) : null,
            'event_date' => $album->event_date?->format('Y-m-d'),
            'is_active' => (bool) $album->is_active,
            'photos_count' => (int) ($album->photos_count ?? ($album->relationLoaded('photos') ? $album->photos->count() : 0)),
            'created_at' => $album->created_at?->toIso8601String(),
            'updated_at' => $album->updated_at?->toIso8601String(),
        ];

        if ($includePhotos && $album->relationLoaded('photos')) {
            $data['photos'] = $album->photos->map(fn (GalleryPhoto $p): array => $this->serializePhoto($p))->values()->all();
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function serializePhoto(GalleryPhoto $photo): array
    {
        $path = trim((string) $photo->image_path);

        return [
            'id' => $photo->id,
            'album_id' => $photo->album_id,
            'alt' => $photo->alt,
            'caption' => $photo->caption,
            'image_path' => $photo->image_path,
            'url' => $path !== '' ? Storage::disk('r2')->url($path) : null,
            'sort_order' => (int) $photo->sort_order,
        ];
    }

    /** @param array<int, string> $abilities */
    private function authorizeAny(array $abilities): void
    {
        foreach ($abilities as $ability) {
            if (Gate::allows($ability)) {
                return;
            }
        }

        abort(403);
    }
}
