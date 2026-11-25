<?php

namespace App\Models;

use Database\Factories\GalleryAlbumSlugFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GalleryAlbumSlug extends Model
{
    /** @use HasFactory<GalleryAlbumSlugFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function album(): BelongsTo
    {
        return $this->belongsTo(GalleryAlbum::class, 'gallery_album_id');
    }
}
