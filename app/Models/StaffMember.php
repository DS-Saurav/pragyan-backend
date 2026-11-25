<?php

namespace App\Models;

use App\Support\Photo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class StaffMember extends Model
{
    public const TYPES = ['teaching' => 'Teaching', 'non_teaching' => 'Non-teaching'];

    public const GROUPS = [
        'leadership' => 'Leadership & coordinators',
        'teaching' => 'Teaching staff',
        'support' => 'Support staff',
    ];

    protected $fillable = [
        'teacher_id', 'user_id', 'name', 'designation', 'type', 'phone', 'email', 'gender', 'joined_on', 'basic_salary',
        'bank_name', 'bank_account', 'pan_no', 'status', 'qualification', 'bio', 'photo',
        'show_on_website', 'team_group', 'display_order',
    ];

    protected $casts = [
        'joined_on' => 'date',
        'basic_salary' => 'float',
        'show_on_website' => 'boolean',
        'display_order' => 'integer',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function components(): BelongsToMany
    {
        return $this->belongsToMany(SalaryComponent::class, 'staff_salary_components')
            ->withPivot('value')
            ->withTimestamps();
    }

    public function slips(): HasMany
    {
        return $this->hasMany(SalarySlip::class);
    }

    /** Staff shown on the public "Our Team" page. */
    public function scopeWebsite(Builder $query): Builder
    {
        return $query->where('show_on_website', true)->where('status', 'active');
    }

    public function photoUrl(): ?string
    {
        return Photo::url($this->photo);
    }

    public function initials(): string
    {
        return collect(explode(' ', trim((string) $this->name)))
            ->filter()->take(2)
            ->map(fn ($w) => Str::upper(Str::substr($w, 0, 1)))
            ->implode('') ?: '?';
    }

    /** The recurring pay lines of this person's salary structure, as amounts. */
    public function defaultLines(): array
    {
        $lines = [];

        foreach ($this->components as $c) {
            if (! $c->is_active) {
                continue;
            }

            $value = (float) $c->pivot->value;
            $amount = $c->calc === 'percent' ? round($this->basic_salary * $value / 100, 2) : $value;

            if ($amount > 0) {
                $lines[] = ['type' => $c->type, 'name' => $c->name, 'amount' => $amount];
            }
        }

        return $lines;
    }
}
