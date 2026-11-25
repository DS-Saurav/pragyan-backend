@php
    $identity ??= \App\Support\SiteIdentity::publicProfile();
    $footerGroups = \App\Support\PublicNavigation::footerGroups();
    $phoneLink = preg_replace('/[^0-9+]/', '', $identity['phone']);
    $navigationLabels = [
        'Home' => 'public.home',
        'Notices' => 'public.notices',
        'Calendar' => 'public.calendar',
        'Gallery' => 'public.gallery',
        'News' => 'public.news',
        'About' => 'public.about',
        'Leadership and Team' => 'public.leadership_and_team',
        'Discover Pragyan' => 'public.discover_pragyan',
        'Learning' => 'public.learning',
        'Academics' => 'public.academics',
        'School Life' => 'public.school_life',
        'Admissions' => 'public.admissions',
        'Contact' => 'public.contact',
        'Explore' => 'public.explore',
        'Get in touch' => 'public.get_in_touch',
    ];
    $translatedLabel = static fn (string $text): string => __($navigationLabels[$text] ?? $text);
@endphp
<footer class="site-footer">
    <div class="container footer-grid">
        <section class="footer-col footer-identity" aria-label="{{ $identity['name'] }}">
            <p class="footer-brand">{{ $identity['name'] }}</p>
            <p class="footer-tagline">{{ $identity['tagline'] }}</p>
            @if ($identity['address'] !== '')
                <p class="footer-line"><i class="bi bi-geo-alt" aria-hidden="true"></i> {{ $identity['address'] }}</p>
            @endif
            @if ($identity['phone'] !== '')
                <p class="footer-line"><i class="bi bi-telephone" aria-hidden="true"></i>
                    <a href="tel:{{ $phoneLink }}">{{ $identity['phone'] }}</a>
                </p>
            @endif
            @if ($identity['email'] !== '')
                <p class="footer-line"><i class="bi bi-envelope" aria-hidden="true"></i>
                    <a href="mailto:{{ $identity['email'] }}">{{ $identity['email'] }}</a>
                </p>
            @endif
            @if ($identity['office_hours'] !== '')
                <p class="footer-line"><i class="bi bi-clock" aria-hidden="true"></i> {{ $identity['office_hours'] }}</p>
            @endif
            @if ($identity['directions_url'] !== '')
                <p class="footer-line"><i class="bi bi-signpost-2" aria-hidden="true"></i>
                    <a href="{{ $identity['directions_url'] }}" target="_blank" rel="noopener">{{ __('public.directions') }}</a>
                </p>
            @endif
            @if ($identity['website'] !== '')
                <p class="footer-line"><i class="bi bi-globe" aria-hidden="true"></i>
                    <a href="{{ $identity['website'] }}" rel="noopener">{{ parse_url($identity['website'], PHP_URL_HOST) ?: $identity['website'] }}</a>
                </p>
            @elseif ($identity['website_placeholder'] !== '')
                <p class="footer-line"><i class="bi bi-globe" aria-hidden="true"></i>
                    <span>{{ __('public.website_coming_soon') }}: {{ $identity['website_placeholder'] }}</span>
                </p>
            @endif
        </section>

        @foreach ($footerGroups as $group)
            <nav class="footer-col" aria-label="{{ $translatedLabel($group['label']) }}">
                <p class="footer-heading">{{ $translatedLabel($group['label']) }}</p>
                <ul class="footer-links">
                    @foreach ($group['links'] as $link)
                        <li><a href="{{ \App\Support\PublicLocaleUrl::route($link['route']) }}">{{ $translatedLabel($link['label']) }}</a></li>
                    @endforeach
                    @if ($group['label'] === 'Get in touch')
                        <li><a href="{{ route('login') }}">{{ __('public.parent_portal') }}</a></li>
                    @endif
                </ul>
            </nav>
        @endforeach
    </div>

    <div class="footer-bottom">
        <div class="container footer-bottom-inner">
            <p>{{ __('public.copyright', ['year' => date('Y'), 'name' => $identity['name']]) }}</p>
            {{-- Approved policy and social links are intentionally rendered only once the school maintains them. --}}
        </div>
    </div>
</footer>
