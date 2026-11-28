<?php

namespace App\Http\Controllers;

use App\Http\Resources\PublicGalleryAlbumResource;
use App\Models\GalleryAlbum;
use App\Models\GalleryCategory;
use App\Services\Gallery\GalleryVisibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class PublicGalleryController extends Controller
{
    public function __construct(private GalleryVisibility $galleryVisibility) {}

    public function index(Request $request): JsonResponse
    {
        return $this->listingResponse($request, 9, false);
    }

    public function archive(Request $request): JsonResponse
    {
        return $this->listingResponse($request, 24, true);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $album = $this->galleryVisibility->findDisplayableBySlug($slug);
        abort_unless($album instanceof GalleryAlbum, 404);

        return response()->json([
            'album' => (new PublicGalleryAlbumResource($album))->resolve($request),
        ]);
    }

    private function listingResponse(Request $request, int $perPage, bool $isArchive): JsonResponse
    {
        $validated = $request->validate([
            'category' => ['nullable', 'string', 'max:120'],
        ]);
        $category = isset($validated['category']) ? trim((string) $validated['category']) : '';

        $baseQuery = $this->galleryVisibility->displayableQuery();

        $categories = Schema::hasTable('gallery_categories')
            ? GalleryCategory::query()
                ->where('is_active', true)
                ->whereHas('albums', fn ($query) => $query->where('is_active', true))
                ->orderBy('id')
                ->get(['id', 'name', 'slug'])
            : collect();

        if ($category !== '') {
            $baseQuery->whereHas('category', function ($query) use ($category): void {
                $query->where('is_active', true)
                    ->where(function ($sub) use ($category): void {
                        $sub->where('slug', $category)->orWhere('name', $category);
                    });
            });
        }

        $paginator = $baseQuery
            ->with(['category', 'coverPhoto', 'firstPhoto'])
            ->withCount('photos')
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'albums' => [
                'data' => $paginator->getCollection()
                    ->map(fn (GalleryAlbum $album): array => (new PublicGalleryAlbumResource($album))->resolve($request))
                    ->values()
                    ->all(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'categories' => $categories->pluck('name')->values(),
            'categoryItems' => $categories,
            'activeCategory' => $category !== '' ? $category : null,
            'isArchive' => $isArchive,
        ]);
    }
}
