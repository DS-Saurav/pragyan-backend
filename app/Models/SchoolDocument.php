<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SchoolDocument extends Model
{
    use SoftDeletes;

    protected $table = 'school_documents';

    protected $guarded = [];

    public const CATEGORIES = [
        'general' => 'General',
        'academic' => 'Academic',
        'exams' => 'Exams',
        'policy' => 'Policy',
        'forms' => 'Forms',
        'finance' => 'Finance',
        'hr' => 'HR',
    ];

    /** Who can see a document. "staff" means everyone who is not a student or a parent (teachers included). */
    public const AUDIENCES = [
        'all' => 'Everyone',
        'students' => 'Students',
        'parents' => 'Parents',
        'teachers' => 'Teachers',
        'staff' => 'All staff',
    ];

    public const TABS = ['all', 'active', 'draft', 'expiring', 'expired'];

    public const EXPIRING_DAYS = 30;

    protected $casts = [
        'is_published' => 'boolean',
        'effective_date' => 'date',
        'expires_on' => 'date',
        'last_downloaded_at' => 'datetime',
    ];

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /* ---------------------------- scopes ---------------------------- */

    /** Published, already effective and not expired. */
    public function scopeActive($q)
    {
        return $q->where('is_published', true)
            ->where(fn ($w) => $w->whereNull('effective_date')->orWhere('effective_date', '<=', today()))
            ->where(fn ($w) => $w->whereNull('expires_on')->orWhere('expires_on', '>=', today()));
    }

    public function scopeExpired($q)
    {
        return $q->whereNotNull('expires_on')->where('expires_on', '<', today());
    }

    public function scopeExpiring($q)
    {
        return $q->where('is_published', true)
            ->whereBetween('expires_on', [today(), today()->addDays(self::EXPIRING_DAYS)]);
    }

    /**
     * What a non-manager is allowed to see: active documents whose audience matches one of their roles.
     * $roles are lower-case role names from the user_roles table.
     */
    public function scopeForRoles($q, array $roles)
    {
        $audiences = ['all'];

        if (in_array('student', $roles, true)) {
            $audiences[] = 'students';
        }
        if (in_array('parent', $roles, true)) {
            $audiences[] = 'parents';
        }
        if (in_array('teacher', $roles, true)) {
            $audiences[] = 'teachers';
        }
        if (array_diff($roles, ['student', 'parent'])) {
            $audiences[] = 'staff';
        }

        return $q->active()->whereIn('audience', array_unique($audiences));
    }

    /* ---------------------------- helpers ---------------------------- */

    /** draft | scheduled | expired | expiring | active */
    public function statusKey(): string
    {
        if (! $this->is_published) {
            return 'draft';
        }
        if ($this->expires_on && $this->expires_on->lt(today())) {
            return 'expired';
        }
        if ($this->effective_date && $this->effective_date->gt(today())) {
            return 'scheduled';
        }
        if ($this->expires_on && $this->expires_on->lte(today()->addDays(self::EXPIRING_DAYS))) {
            return 'expiring';
        }

        return 'active';
    }

    public function isPreviewable(): bool
    {
        return $this->file_path && ($this->mime === 'application/pdf' || str_starts_with((string) $this->mime, 'image/'));
    }

    public function sizeLabel(): string
    {
        $b = (int) $this->file_size;

        return match (true) {
            $b >= 1048576 => round($b / 1048576, 1).' MB',
            $b >= 1024 => round($b / 1024).' KB',
            default => $b.' B',
        };
    }

    /** A clean file name for the download, e.g. staff-leave-policy-v2.pdf */
    public function downloadName(): string
    {
        $ext = pathinfo((string) $this->file_name, PATHINFO_EXTENSION);

        return Str::slug($this->title).'-v'.$this->version.($ext ? '.'.strtolower($ext) : '');
    }

    public function shownDate()
    {
        return $this->effective_date ?? $this->created_at;
    }
}
