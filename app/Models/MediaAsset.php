<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class MediaAsset extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['file_size' => 'integer', 'width' => 'integer', 'height' => 'integer',
            'is_decorative' => 'boolean',
            'submitted_at' => 'datetime', 'reviewed_at' => 'datetime',
            'published_at' => 'datetime', 'archived_at' => 'datetime',
            'consent_confirmed_at' => 'datetime', 'consent_withdrawn_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Media originals must be archived, not deleted.'));
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MediaAttachment::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function consentConfirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consent_confirmed_by');
    }

    public function consentWithdrawer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consent_withdrawn_by');
    }
}
