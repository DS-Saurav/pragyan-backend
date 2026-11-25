<?php

namespace App\Models;

use Database\Factories\PublicContentTranslationRevisionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PublicContentTranslationRevision extends Model
{
    /** @use HasFactory<PublicContentTranslationRevisionFactory> */
    use HasFactory;

    /** @var array<string, string> */
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
            'payload' => 'array',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function translation(): BelongsTo
    {
        return $this->belongsTo(PublicContentTranslation::class, 'public_content_translation_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(PublicContentTranslationMedia::class)
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
