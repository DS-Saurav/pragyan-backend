<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @php
        $identity = \App\Support\SiteIdentity::publicProfile();
        $localizedAvailable ??= app()->getLocale() !== 'ne';
        $localizedIdentity = null;
        if (app()->getLocale() === 'ne') {
            $localizedIdentity = app(\App\Services\PublicTranslations\PublicTranslationVisibility::class)->siteRevision('identity');
            if ($localizedIdentity !== null) {
                foreach (['name', 'tagline', 'address', 'location', 'office_hours', 'visit_instructions'] as $field) {
                    if (filled($localizedIdentity->payload[$field] ?? null)) {
                        $identity[$field] = $localizedIdentity->payload[$field];
                    }
                }
            } else {
                // Do not present unapproved English editorial identity copy as Nepali.
                $identity['name'] = __('public.school');
                foreach (['tagline', 'address', 'location', 'office_hours', 'visit_instructions'] as $field) {
                    $identity[$field] = '';
                }
            }
        }
        $brand = $identity['brand'];
        $siteName = $identity['name'];
        $pageTitle = isset($title) ? $title.' - '.$siteName : $siteName;
        $shareTitle = isset($title) ? $title : $siteName;
        $metaDesc = str_replace(
            'Pragyan Pathashala',
            $siteName,
            $metaDescription ?? $identity['tagline'],
        );
        $canonical = \App\Support\PublicSitemap::canonicalUrl(request()->path());
        $shareImage = \App\Support\PublicSitemap::canonicalMediaUrl($ogImage ?? null);
        $organizationSchema = \App\Support\PublicSitemap::organizationSchema();
        if (app()->getLocale() === 'ne') {
            $organizationSchema['name'] = $siteName;
            // Never inherit English address copy in a Nepali representation.
            unset($organizationSchema['address']);
            foreach (['email', 'phone', 'address'] as $field) {
                if (filled($identity[$field] ?? null)) {
                    $organizationSchema[$field === 'phone' ? 'telephone' : $field] = $identity[$field];
                }
            }
        }
        $isArchive = request()->routeIs([
            'public.news.archive',
            'public.gallery.archive',
            'public.notices.archive',
            'public.ne.news.archive',
            'public.ne.gallery.archive',
            'public.ne.notices.archive',
        ]);
        $noIndex = $isArchive || ! $localizedAvailable;
        $alternateLocaleUrl = app(\App\Support\PublicLocaleMetadata::class)->alternateUrl($localizedAvailable);
    @endphp

    <meta name="description" content="{{ \Illuminate\Support\Str::limit($metaDesc, 160) }}">
    <meta name="theme-color" content="{{ $brand['primary'] }}">
    @if ($noIndex)
        <meta name="robots" content="noindex, follow">
    @endif

    <title>{{ $pageTitle }}</title>

    <link rel="canonical" href="{{ $canonical }}">
    @if ($alternateLocaleUrl)
        <link rel="alternate" hreflang="en" href="{{ app()->getLocale() === 'en' ? $canonical : $alternateLocaleUrl }}">
        <link rel="alternate" hreflang="ne" href="{{ app()->getLocale() === 'ne' ? $canonical : $alternateLocaleUrl }}">
        <link rel="alternate" hreflang="x-default" href="{{ app()->getLocale() === 'en' ? $canonical : $alternateLocaleUrl }}">
    @endif
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="{{ $shareTitle }}">
    <meta property="og:description" content="{{ \Illuminate\Support\Str::limit($metaDesc, 200) }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta name="twitter:card" content="{{ $shareImage ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $shareTitle }}">
    <meta name="twitter:description" content="{{ \Illuminate\Support\Str::limit($metaDesc, 200) }}">
    @if ($shareImage)
        <meta property="og:image" content="{{ $shareImage }}">
        <meta name="twitter:image" content="{{ $shareImage }}">
    @endif
    <script type="application/ld+json">{!! json_encode($organizationSchema, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) !!}</script>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800|fraunces:400,500,600,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    @vite(['resources/css/app.css', 'resources/css/public.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="pp-site public-body flex min-h-screen flex-col antialiased"
    style="{{ \App\Support\SiteIdentity::cssVariables($brand) }}">
    <a href="#main-content" class="public-skip-link">
        {{ __('public.skip_to_content') }}
    </a>

    <x-public.header :identity="$identity" />

    @unless (\App\Support\PublicLocaleUrl::routeIs('home'))
        <x-public.urgent-notice />
    @endunless

    <main id="main-content" tabindex="-1" class="flex-1">
        @yield('content')
    </main>

    <x-public.footer :identity="$identity" />

    @stack('scripts')
</body>
</html>
