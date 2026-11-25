<?php

namespace App\Models;

use Database\Factories\PublicTeamProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PublicTeamProfile extends Model
{
    /** @use HasFactory<PublicTeamProfileFactory> */
    use HasFactory;

    public const PUBLICATION_STATES = [
        'draft' => 'Draft',
        'review' => 'In review',
        'published' => 'Published',
        'withdrawn' => 'Withdrawn',
        'archived' => 'Archived',
    ];

    public const TEAM_GROUPS = [
        'leadership' => 'Leadership & coordinators',
        'teaching' => 'Teaching staff',
        'support' => 'Support staff',
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
        return $this->hasMany(PublicTeamProfileRevision::class)
            ->orderBy('revision_number')
            ->orderBy('id');
    }

    public function publishedRevision(): BelongsTo
    {
        return $this->belongsTo(PublicTeamProfileRevision::class, 'published_revision_id');
    }

    public function scheduledRevision(): BelongsTo
    {
        return $this->belongsTo(PublicTeamProfileRevision::class, 'scheduled_revision_id');
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
