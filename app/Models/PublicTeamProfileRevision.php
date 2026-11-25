<?php

namespace App\Models;

use Database\Factories\PublicTeamProfileRevisionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class PublicTeamProfileRevision extends Model
{
    /** @use HasFactory<PublicTeamProfileRevisionFactory> */
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
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
            'display_order' => 'integer',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(PublicTeamProfile::class, 'public_team_profile_id');
    }

    /**
     * This relationship is used only inside the private editorial workflow to
     * verify employment eligibility. It is never passed to a public view.
     */
    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }

    public function portrait(): MorphOne
    {
        return $this->morphOne(MediaAttachment::class, 'attachable')
            ->where('placement', 'portrait')
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
