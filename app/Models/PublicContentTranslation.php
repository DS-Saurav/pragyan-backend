<?php

namespace App\Models;

use Database\Factories\PublicContentTranslationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PublicContentTranslation extends Model
{
    /** @use HasFactory<PublicContentTranslationFactory> */
    use HasFactory;

    public const LOCALE_NEPALI = 'ne';

    /** @var array<int, string> */
    public const CONTENT_TYPES = [
        'admission_cycle',
        'gallery_album',
        'news_story',
        'notice',
        'public_page',
        'public_team_profile',
        'school_event',
    ];

    /** @var array<string, string> */
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
        return $this->hasMany(PublicContentTranslationRevision::class)
            ->orderBy('revision_number')
            ->orderBy('id');
    }

    public function publishedRevision(): BelongsTo
    {
        return $this->belongsTo(PublicContentTranslationRevision::class, 'published_revision_id');
    }

    public function scheduledRevision(): BelongsTo
    {
        return $this->belongsTo(PublicContentTranslationRevision::class, 'scheduled_revision_id');
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

    public function auditEvents(): HasMany
    {
        return $this->hasMany(PublicTranslationAuditEvent::class, 'translation_id')
            ->where('translation_type', PublicTranslationAuditEvent::TYPE_CONTENT)
            ->latest('created_at')
            ->latest('id');
    }
}
