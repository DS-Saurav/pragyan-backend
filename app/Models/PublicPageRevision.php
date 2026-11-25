<?php

namespace App\Models;

use Database\Factories\PublicPageRevisionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class PublicPageRevision extends Model
{
    /** @var array<string, string> */
    public const WORKFLOW_STATES = [
        'draft' => 'Draft',
        'review' => 'In review',
        'approved' => 'Approved',
        'scheduled' => 'Scheduled',
        'published' => 'Published',
    ];

    /** @use HasFactory<PublicPageRevisionFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'approach_values' => 'array',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(PublicPage::class, 'public_page_id');
    }

    public function academicStages(): HasMany
    {
        return $this->hasMany(PublicAcademicStage::class)->orderBy('display_order')->orderBy('id');
    }

    public function featureImage(): MorphOne
    {
        return $this->morphOne(MediaAttachment::class, 'attachable')
            ->where('placement', 'feature')
            ->where('display_order', 0);
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
