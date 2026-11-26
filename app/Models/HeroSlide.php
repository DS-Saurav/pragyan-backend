<?php

namespace App\Models;

use App\Services\MediaStorageService;
use Illuminate\Database\Eloquent\Model;

class HeroSlide extends Model
{
    protected $fillable = [
        'disk',
        'image_path',
        'alt_text',
        'mime_type',
        'file_size',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function toApiArray(): array
    {
        $version = $this->updated_at?->timestamp ?? time();
        $disk = $this->disk ?: 'public';
        $src = app(MediaStorageService::class)->url(
            $disk,
            $this->image_path,
            "/api/hero-slides/{$this->id}/image?v={$version}",
        );

        return [
            'id' => $this->id,
            'disk' => $disk,
            'src' => $src ?? '',
            'alt' => (string) ($this->alt_text ?? ''),
            'sortOrder' => (int) $this->sort_order,
            'isActive' => (bool) $this->is_active,
        ];
    }
}
