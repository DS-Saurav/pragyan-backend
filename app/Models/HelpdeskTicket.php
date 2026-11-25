<?php

namespace App\Models;

use App\Models\HelpdeskReply;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HelpdeskTicket extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public const CATEGORIES = [
        'portal' => 'Portal / Login',
        'fees' => 'Fees',
        'academics' => 'Academics',
        'infrastructure' => 'Infrastructure',
        'transport' => 'Transport',
        'other' => 'Other',
    ];

    public const PRIORITIES = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'];

    public const STATUSES = ['open', 'in_progress', 'resolved', 'closed'];

    public const STATUS_LABELS = [
        'open' => 'Open',
        'in_progress' => 'In progress',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
    ];

    /** Hours allowed before an unresolved ticket counts as overdue. */
    public const SLA_HOURS = ['high' => 24, 'medium' => 72, 'low' => 168];

    protected $casts = [
        'last_activity_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function raiser()
    {
        return $this->belongsTo(User::class, 'raised_by');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function replies()
    {
        return $this->hasMany(HelpdeskReply::class)->orderBy('id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['open', 'in_progress'], true);
    }

    public function isOverdue(): bool
    {
        return $this->isActive()
            && $this->created_at->lt(now()->subHours(self::SLA_HOURS[$this->priority] ?? 72));
    }

    public function scopeOverdue($q)
    {
        return $q->whereIn('status', ['open', 'in_progress'])->where(function ($q) {
            foreach (self::SLA_HOURS as $priority => $hours) {
                $q->orWhere(fn ($w) => $w->where('priority', $priority)->where('created_at', '<=', now()->subHours($hours)));
            }
        });
    }

    /** TKT-2026-0001 : restarts every calendar year. Must run inside a transaction. */
    public static function nextNumber(): string
    {
        $prefix = 'TKT-'.now()->year.'-';

        $last = static::withTrashed()
            ->where('ticket_no', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('ticket_no')
            ->value('ticket_no');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
