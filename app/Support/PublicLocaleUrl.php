<?php

namespace App\Support;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;

/**
 * Builds public links without coupling public Blade components to a URL prefix.
 *
 * English remains the canonical, unprefixed public site. Nepali equivalents use
 * the explicit `public.ne.*` route family registered by the public locale routes.
 */
final class PublicLocaleUrl
{
    public const ENGLISH = 'en';

    public const NEPALI = 'ne';

    /** @var array<int, string> */
    private const SUPPORTED_LOCALES = [self::ENGLISH, self::NEPALI];

    /** @var array<int, string> */
    private const LOCALIZED_ROUTES = [
        'home',
        'public.about',
        'public.academics',
        'public.admissions',
        'public.calendar',
        'public.contact',
        'public.gallery',
        'public.gallery.archive',
        'public.gallery.show',
        'public.news',
        'public.news.archive',
        'public.news.show',
        'public.notices',
        'public.notices.archive',
        'public.notices.show',
        'public.team',
    ];

    /** @return array<int, string> */
    public static function supported(): array
    {
        return self::SUPPORTED_LOCALES;
    }

    public static function current(): string
    {
        return app()->getLocale() === self::NEPALI ? self::NEPALI : self::ENGLISH;
    }

    public static function isCurrent(string $locale): bool
    {
        self::assertSupported($locale);

        return self::current() === $locale;
    }

    /**
     * Resolve a public route name for a specific locale.
     *
     * Unknown route names intentionally pass through unchanged. This keeps links
     * such as login and media routes outside the locale-only public route family.
     */
    public static function routeName(string $name, ?string $locale = null): string
    {
        $locale ??= self::current();
        self::assertSupported($locale);

        $canonicalName = self::canonicalRouteName($name);

        if (! self::isLocalizedRoute($canonicalName) || $locale === self::ENGLISH) {
            return $canonicalName;
        }

        if ($canonicalName === 'home') {
            return 'public.ne.home';
        }

        return 'public.ne.'.substr($canonicalName, strlen('public.'));
    }

    /**
     * Generate a public URL in the requested locale.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function route(string $name, array $parameters = [], ?string $locale = null, bool $absolute = true): string
    {
        return \route(self::routeName($name, $locale), $parameters, $absolute);
    }

    /**
     * Determine whether the current route matches the locale-aware name(s).
     *
     * @param  array<int, string>|string  $names
     */
    public static function routeIs(array|string $names, ?string $locale = null): bool
    {
        $locale ??= self::current();
        self::assertSupported($locale);

        $routeNames = is_array($names) ? $names : [$names];

        return request()->routeIs(array_map(
            fn (string $name): string => self::routeName($name, $locale),
            $routeNames,
        ));
    }

    /**
     * Build the equivalent public URL for the active route.
     *
     * A route outside the localized public site deliberately falls back to the
     * selected locale home page instead of exposing an arbitrary internal URL.
     */
    public static function counterpartUrl(string $locale): string
    {
        self::assertSupported($locale);

        $currentRoute = request()->route();

        if (! $currentRoute instanceof RoutingRoute || $currentRoute->getName() === null) {
            return self::route('home', [], $locale);
        }

        $routeName = self::canonicalRouteName($currentRoute->getName());

        if (! self::isLocalizedRoute($routeName)) {
            return self::route('home', [], $locale);
        }

        $parameters = $currentRoute->parameters();
        unset($parameters['locale']);

        foreach (request()->query() as $name => $value) {
            if (! array_key_exists($name, $parameters) && $name !== 'locale') {
                $parameters[$name] = $value;
            }
        }

        return self::route($routeName, $parameters, $locale);
    }

    /**
     * Return the local path and query string for a safe explicit locale switch.
     */
    public static function counterpartPath(string $locale): string
    {
        $parts = parse_url(self::counterpartUrl($locale));
        $path = $parts['path'] ?? '/';

        if (isset($parts['query']) && $parts['query'] !== '') {
            $path .= '?'.$parts['query'];
        }

        return $path;
    }

    /**
     * Build an explicit language-choice link. Before the switch endpoint is
     * registered, this safely remains a normal locale counterpart link.
     */
    public static function switchUrl(string $locale, ?string $target = null): string
    {
        self::assertSupported($locale);

        $target ??= self::counterpartPath($locale);
        self::assertLocalPath($target);

        if (! Route::has('public.locale.switch')) {
            return self::counterpartUrl($locale);
        }

        return \route('public.locale.switch', [
            'locale' => $locale,
            'to' => $target,
        ]);
    }

    public static function switcherUrl(string $locale): string
    {
        return self::switchUrl($locale);
    }

    private static function canonicalRouteName(string $name): string
    {
        if ($name === 'public.ne.home') {
            return 'home';
        }

        if (str_starts_with($name, 'public.ne.')) {
            return 'public.'.substr($name, strlen('public.ne.'));
        }

        return $name;
    }

    private static function isLocalizedRoute(string $name): bool
    {
        return in_array($name, self::LOCALIZED_ROUTES, true);
    }

    private static function assertSupported(string $locale): void
    {
        if (! in_array($locale, self::SUPPORTED_LOCALES, true)) {
            throw new InvalidArgumentException("Unsupported public locale [{$locale}].");
        }
    }

    private static function assertLocalPath(string $target): void
    {
        $parts = parse_url($target);

        if (
            $parts === false
            || ! str_starts_with($target, '/')
            || str_starts_with($target, '//')
            || str_contains($target, '\\')
            || str_contains($target, "\r")
            || str_contains($target, "\n")
            || isset($parts['scheme'])
            || isset($parts['host'])
        ) {
            throw new InvalidArgumentException('The locale switch target must be a local path.');
        }
    }
}
