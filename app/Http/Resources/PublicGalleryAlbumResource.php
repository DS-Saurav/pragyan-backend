<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin \App\Models\GalleryAlbum
 */
class PublicGalleryAlbumResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $coverPath = trim((string) $this->cover_image);
        $fallbackPhoto = $coverPath === ''
            ? ($this->relationLoaded('firstPhoto')
                ? $this->firstPhoto
                : ($this->relationLoaded('photos') ? $this->photos->first() : null))
            : null;

        if ($coverPath !== '') {
            $cover = [
                'url' => Storage::disk('r2')->url($coverPath),
                'alt' => $this->title,
            ];
        } elseif ($fallbackPhoto !== null && trim((string) $fallbackPhoto->image_path) !== '') {
            $cover = [
                'url' => Storage::disk('r2')->url(trim((string) $fallbackPhoto->image_path)),
                'alt' => trim((string) $fallbackPhoto->alt) !== '' ? $fallbackPhoto->alt : $this->title,
            ];
        } else {
            $cover = null;
        }

        $photosCount = (int) ($this->photos_count ?? ($this->relationLoaded('photos') ? $this->photos->count() : 0));

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
            'event_date' => $this->event_date?->format('Y-m-d'),
            'category' => $this->category?->name,
            'category_slug' => $this->category?->slug,
            'photos_count' => $photosCount,
            'cover' => $cover,
            'photos' => $this->when(
                $this->relationLoaded('photos'),
                fn (): array => PublicGalleryPhotoResource::collection($this->photos)->resolve($request),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
