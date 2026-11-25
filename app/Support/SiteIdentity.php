<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Single source of truth for the school's public identity. Admin Settings
 * (school_name / tagline / contact fields) win; sensible fallbacks follow.
 * Every public view reads the school through this helper so the public
 * website never contradicts what the administrator saved.
 */
class SiteIdentity
{
    /**
     * Settings that are safe for the public site to read. Keep this allowlist
     * intentionally small so adding an internal setting never exposes it to a
     * public Blade view by accident.
     */
    private const PUBLIC_SETTING_KEYS = [
        'school_name',
        'tagline',
        'logo_url',
        'address',
        'phone',
        'email',
        'website',
        'public_website_placeholder',
        'public_location',
        'public_directions_url',
        'public_office_hours',
        'public_visit_instructions',
    ];

    /**
     * Phase 1 placeholder tokens. These remain separate from the authenticated
     * application's primary_color and accent_color settings until the school
     * approves its public crest and public-brand settings.
     */
    private const BRAND_TOKENS = [
        'primary' => '#19518C',
        'primary-hover' => '#123E6D',
        'navy' => '#122640',
        'surface-blue' => '#EDF5FC',
        'surface-white' => '#FFFFFF',
        'surface-warm' => '#FAF8F4',
        'text' => '#17283B',
        'text-muted' => '#46586A',
        'divider' => '#D7E0E9',
    ];

    /**
     * All settings the public site may consume. Returns [] when the settings
     * table does not exist yet (fresh schema-less render contexts) so public
     * views always degrade to the config/fallback values instead of failing.
     */
    public static function all(): array
    {
        try {
            return Setting::pluck('value', 'key')->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Named public identity data for Blade views and public metadata. This
     * avoids handing the complete settings table to pages rendered for guests.
     *
     * @return array<string, mixed>
     */
    public static function publicProfile(): array
    {
        $settings = self::publicSettings();
        $address = self::value($settings, 'address');
        $websitePlaceholder = self::value($settings, 'public_website_placeholder');

        return [
            'name' => self::value($settings, 'school_name') ?: config('app.name', 'Pragyan Pathashala'),
            'tagline' => self::value($settings, 'tagline') ?: 'Learn. Grow. Succeed.',
            'logo_url' => self::value($settings, 'logo_url'),
            'address' => $address,
            'phone' => self::value($settings, 'phone'),
            'email' => self::value($settings, 'email'),
            'website' => $websitePlaceholder === '' ? self::approvedUrl(self::value($settings, 'website')) : '',
            'website_placeholder' => $websitePlaceholder,
            'location' => self::value($settings, 'public_location') ?: $address,
            'directions_url' => self::approvedUrl(self::value($settings, 'public_directions_url')),
            'office_hours' => self::value($settings, 'public_office_hours'),
            'visit_instructions' => self::value($settings, 'public_visit_instructions'),
            'brand' => self::brand(),
        ];
    }

    /** @return array<string, string> */
    public static function brand(): array
    {
        return self::BRAND_TOKENS;
    }

    /**
     * A safe inline custom-property value for the public wrapper. The CSS file
     * owns component usage while this method gives metadata and the wrapper the
     * same approved token source.
     *
     * @param  array<string, string>  $tokens
     */
    public static function cssVariables(array $tokens = []): string
    {
        $variables = [];

        foreach (self::BRAND_TOKENS as $name => $fallback) {
            $value = $tokens[$name] ?? $fallback;
            $variables[] = '--pp-'.$name.': '.(self::isColour($value) ? $value : $fallback);
        }

        return implode('; ', $variables);
    }

    /** Public logo URL (empty string when unset). */
    public static function logo(): string
    {
        return self::publicProfile()['logo_url'];
    }

    public static function name(): string
    {
        return self::publicProfile()['name'];
    }

    public static function tagline(): string
    {
        return self::publicProfile()['tagline'];
    }

    /** Existing public contact shape, retained for callers outside the shared shell. */
    public static function contact(): array
    {
        $profile = self::publicProfile();

        return [
            'address' => $profile['address'],
            'phone' => $profile['phone'],
            'email' => $profile['email'],
            'website' => $profile['website'],
        ];
    }

    /** @return array<string, string> */
    private static function publicSettings(): array
    {
        try {
            return Setting::query()
                ->whereIn('key', self::PUBLIC_SETTING_KEYS)
                ->pluck('value', 'key')
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /** @param array<string, string> $settings */
    private static function value(array $settings, string $key): string
    {
        return trim((string) ($settings[$key] ?? ''));
    }

    public static function isSafeHttpUrl(string $url): bool
    {
        $parts = parse_url($url);

        return is_array($parts)
            && isset($parts['host'])
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && filter_var($url, FILTER_VALIDATE_URL)
            && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true);
    }

    private static function approvedUrl(string $url): string
    {
        return self::isSafeHttpUrl($url) ? $url : '';
    }

    private static function isColour(mixed $value): bool
    {
        return is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1;
    }
}
