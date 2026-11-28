<?php

namespace App\Http\Controllers;

use App\Http\Resources\PublicGalleryAlbumResource;
use App\Models\HeroSlide;
use App\Services\Gallery\GalleryVisibility;
use Illuminate\Http\JsonResponse;

class PublicHomepageController extends Controller
{
    public function __construct(
        private GalleryVisibility $galleryVisibility,
    ) {}

    public function index(): JsonResponse
    {
        $featuredAlbum = $this->galleryVisibility->featured();
        $homepageGallery = $this->galleryVisibility->homepageGallery();

        return response()->json([
            'heroSlides' => HeroSlide::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (HeroSlide $slide): array => [
                    'id' => $slide->id,
                    'src' => $slide->toApiArray()['src'],
                    'alt' => $slide->alt ?? '',
                ])
                ->values(),
            'featuredAlbum' => $featuredAlbum !== null
                ? (new PublicGalleryAlbumResource(
                    $featuredAlbum->load(['category', 'coverPhoto', 'firstPhoto'])->loadCount('photos'),
                ))->resolve(request())
                : null,
            'homeGalleryCategories' => $homepageGallery['categories'],
            'homeGalleryPhotos' => $homepageGallery['photos'],
        ]);
    }
}
