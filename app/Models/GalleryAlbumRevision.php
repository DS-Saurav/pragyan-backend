<?php

namespace App\Models;

use Database\Factories\GalleryAlbumRevisionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class GalleryAlbumRevision extends Model
{
    /** @use HasFactory<GalleryAlbumRevisionFactory> */
    use HasFactory;

    public const WORKFLOW_STATES = [
        'draft' => 'Draft',
        'review' => 'In review',
        'approved' => 'Approved',
        'scheduled' => 'Scheduled',
        'published' => 'Published',
    ];

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'album_date' => 'date',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function album(): BelongsTo
    {
        return $this->belongsTo(GalleryAlbum::class, 'gallery_album_id');
    }

    public function cover(): MorphOne
    {
        return $this->morphOne(MediaAttachment::class, 'attachable')
            ->where('placement', 'cover')
            ->where('display_order', 0);
    }

    public function photos(): MorphMany
    {
        return $this->morphMany(MediaAttachment::class, 'attachable')
            ->where('placement', 'photo')
            ->orderBy('display_order')
            ->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
