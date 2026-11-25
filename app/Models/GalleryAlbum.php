<?php

namespace App\Models;

use Database\Factories\GalleryAlbumFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GalleryAlbum extends Model
{
    /** @use HasFactory<GalleryAlbumFactory> */
    use HasFactory;

    public const PUBLICATION_STATES = [
        'draft' => 'Draft',
        'review' => 'In review',
        'scheduled' => 'Scheduled',
        'published' => 'Published',
        'withdrawn' => 'Withdrawn',
        'archived' => 'Archived',
    ];

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'published_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(GalleryAlbumRevision::class)->orderBy('revision_number')->orderBy('id');
    }

    public function publishedRevision(): BelongsTo
    {
        return $this->belongsTo(GalleryAlbumRevision::class, 'published_revision_id');
    }

    public function scheduledRevision(): BelongsTo
    {
        return $this->belongsTo(GalleryAlbumRevision::class, 'scheduled_revision_id');
    }

    public function formerSlugs(): HasMany
    {
        return $this->hasMany(GalleryAlbumSlug::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
