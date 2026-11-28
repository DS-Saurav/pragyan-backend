<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin \App\Models\GalleryPhoto
 */
class PublicGalleryPhotoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $path = trim((string) $this->image_path);

        return [
            'id' => $this->id,
            'url' => $path !== '' ? Storage::disk('r2')->url($path) : null,
            'alt' => (string) ($this->alt ?? ''),
            'caption' => $this->caption,
            'sort_order' => (int) $this->sort_order,
        ];
    }
}
