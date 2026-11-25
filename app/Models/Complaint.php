<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Complaint extends Model
{
    use SoftDeletes;

    protected $table = 'school_complaints';

    protected $guarded = [];

    public const AGAINST = [
        'transport' => 'Transport',
        'canteen' => 'Canteen',
        'teacher' => 'Teacher / Staff',
        'academics' => 'Academics',
        'facilities' => 'Facilities / Cleanliness',
        'student' => 'Student behaviour',
        'administration' => 'Administration',
        'other' => 'Other',
    ];

    public const SEVERITIES = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'];

    public const STATUSES = ['open', 'investigating', 'resolved', 'dismissed'];

    public const STATUS_LABELS = [
        'open' => 'Open',
        'investigating' => 'Investigating',
        'resolved' => 'Resolved',
        'dismissed' => 'Dismissed',
    ];

    /** Hours allowed before an unresolved complaint counts as overdue. */
    public const SLA_HOURS = ['high' => 48, 'medium' => 168, 'low' => 336];

    protected $casts = [
        'is_anonymous' => 'boolean',
        'is_escalated' => 'boolean',
        'incident_date' => 'date',
        'escalated_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function filer() { return $this->belongsTo(User::class, 'filed_by'); }
    public function assignee() { return $this->belongsTo(User::class, 'assigned_to'); }
    public function updates() { return $this->hasMany(ComplaintUpdate::class, 'complaint_id')->orderBy('id'); }

    public function isActive(): bool
    {
        return in_array($this->status, ['open', 'investigating'], true);
    }

    public function isOverdue(): bool
    {
        return $this->isActive()
            && $this->created_at->lt(now()->subHours(self::SLA_HOURS[$this->severity] ?? 168));
    }

    public function scopeOverdue($q)
    {
        return $q->whereIn('status', ['open', 'investigating'])->where(function ($q) {
            foreach (self::SLA_HOURS as $severity => $hours) {
                $q->orWhere(fn ($w) => $w->where('severity', $severity)->where('created_at', '<=', now()->subHours($hours)));
            }
        });
    }

    /** Anonymous complaints hide the filer from everyone except the filer. */
    public function hidesFilerFrom(?int $viewerId): bool
    {
        return $this->is_anonymous && $this->filed_by !== $viewerId;
    }

    public function filerName(): string
    {
        if ($this->hidesFilerFrom(auth()->id())) {
            return 'Anonymous';
        }

        $u = $this->filer;

        return $u ? ($u->name ?? $u->username ?? ('User #'.$u->id)) : '—';
    }

    /** CMP-2026-0001 : restarts every calendar year. Must run inside a transaction. */
    public static function nextNumber(): string
    {
        $prefix = 'CMP-'.now()->year.'-';

        $last = static::withTrashed()
            ->where('complaint_no', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('complaint_no')
            ->value('complaint_no');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
