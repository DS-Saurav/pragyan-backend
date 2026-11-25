<?php

namespace App\Models;

use Database\Factories\AdmissionCycleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdmissionCycle extends Model
{
    /** @use HasFactory<AdmissionCycleFactory> */
    use HasFactory;

    public const PUBLIC_STATUSES = [
        'open' => 'Open',
        'closing_soon' => 'Closing soon',
        'closed' => 'Closed',
        'contact_us' => 'Contact us',
    ];

    public const PUBLICATION_STATES = [
        'draft' => 'Draft',
        'published' => 'Published',
        'withdrawn' => 'Withdrawn',
        'archived' => 'Archived',
    ];

    protected $fillable = [
        'label',
        'public_status',
        'public_intro',
        'display_starts_on',
        'display_ends_on',
        'publication_state',
        'published_at',
        'public_phone',
        'public_email',
        'primary_action_label',
        'primary_action_url',
        'fee_summary',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'display_starts_on' => 'date',
            'display_ends_on' => 'date',
            'published_at' => 'datetime',
        ];
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

    public function grades(): HasMany
    {
        return $this->hasMany(AdmissionCycleGrade::class)->orderBy('sort_order')->orderBy('id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(AdmissionCycleStep::class)->orderBy('sort_order')->orderBy('id');
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(AdmissionCycleRequirement::class)->orderBy('sort_order')->orderBy('id');
    }

    public function faqs(): HasMany
    {
        return $this->hasMany(AdmissionCycleFaq::class)->orderBy('sort_order')->orderBy('id');
    }

    public static function isValidPublicActionUrl(?string $value): bool
    {
        $url = trim((string) $value);

        if ($url === '') {
            return true;
        }

        if (str_starts_with($url, '/')) {
            return ! str_starts_with($url, '//') && ! str_contains($url, '\\');
        }

        if (str_starts_with(strtolower($url), 'mailto:')) {
            $address = explode('?', substr($url, strlen('mailto:')), 2)[0];

            return filter_var($address, FILTER_VALIDATE_EMAIL) !== false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true)
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    public function safePrimaryActionUrl(): ?string
    {
        $url = trim((string) $this->primary_action_url);

        return self::isValidPublicActionUrl($url) && $url !== '' ? $url : null;
    }
}
