<?php

namespace App\Models;

use Database\Factories\NoticeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Notice extends Model
{
    /** @use HasFactory<NoticeFactory> */
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
            'date' => 'date',
            'scheduled_for' => 'datetime',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'archived_at' => 'datetime',
            'is_urgent' => 'boolean',
        ];
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(NoticeRevision::class)->orderBy('revision_number')->orderBy('id');
    }

    public function publishedRevision(): BelongsTo
    {
        return $this->belongsTo(NoticeRevision::class, 'published_revision_id');
    }

    public function scheduledRevision(): BelongsTo
    {
        return $this->belongsTo(NoticeRevision::class, 'scheduled_revision_id');
    }

    public function formerSlugs(): HasMany
    {
        return $this->hasMany(NoticeSlug::class);
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
