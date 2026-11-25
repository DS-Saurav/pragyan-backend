<?php

namespace App\Models;

use Database\Factories\PublicSiteTranslationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PublicSiteTranslation extends Model
{
    /** @use HasFactory<PublicSiteTranslationFactory> */
    use HasFactory;

    public const LOCALE_NEPALI = 'ne';

    public const SCOPE_HOMEPAGE = 'homepage';

    public const SCOPE_IDENTITY = 'identity';

    /** @var array<int, string> */
    public const SCOPES = [self::SCOPE_HOMEPAGE, self::SCOPE_IDENTITY];

    /** @var array<string, string> */
    public const PUBLICATION_STATES = PublicContentTranslation::PUBLICATION_STATES;

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
        return $this->hasMany(PublicSiteTranslationRevision::class)
            ->orderBy('revision_number')
            ->orderBy('id');
    }

    public function publishedRevision(): BelongsTo
    {
        return $this->belongsTo(PublicSiteTranslationRevision::class, 'published_revision_id');
    }

    public function scheduledRevision(): BelongsTo
    {
        return $this->belongsTo(PublicSiteTranslationRevision::class, 'scheduled_revision_id');
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
            ->where('translation_type', PublicTranslationAuditEvent::TYPE_SITE)
            ->latest('created_at')
            ->latest('id');
    }
}
