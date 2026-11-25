@php
    $identity ??= \App\Support\SiteIdentity::publicProfile();
    $phoneLink = preg_replace('/[^0-9+]/', '', $identity['phone']);
@endphp

<header class="site-header" data-public-header>
    {{-- Utility shortcuts stay secondary to the primary navigation and hero CTA. --}}
    <div class="utility-bar">
        <div class="container utility-bar-inner">
            <div class="utility-contacts">
                @if ($identity['phone'] !== '')
                    <a href="tel:{{ $phoneLink }}" class="utility-link">
                        <i class="bi bi-telephone" aria-hidden="true"></i> {{ $identity['phone'] }}
                    </a>
                @endif
                @if ($identity['email'] !== '')
                    <a href="mailto:{{ $identity['email'] }}" class="utility-link utility-email">
                        <i class="bi bi-envelope" aria-hidden="true"></i> {{ $identity['email'] }}
                    </a>
                @elseif ($identity['location'] !== '')
                    @if ($identity['directions_url'] !== '')
                        <a href="{{ $identity['directions_url'] }}" class="utility-link utility-location" target="_blank" rel="noopener">
                            <i class="bi bi-geo-alt" aria-hidden="true"></i> {{ $identity['location'] }}
                        </a>
                    @else
                        <span class="utility-text utility-location"><i class="bi bi-geo-alt" aria-hidden="true"></i> {{ $identity['location'] }}</span>
                    @endif
                @endif
            </div>
            <div class="utility-actions">
                <a href="{{ route('login') }}" class="utility-link utility-portal">
                    <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> {{ __('public.parent_portal') }}
                </a>
            </div>
        </div>
    </div>

    <div class="container nav-wrap">
        <x-public.brand :identity="$identity" />

        <x-public.nav class="nav nav-desktop" :label="__('public.primary_navigation')" />

        <div class="header-actions">
            <x-public.language-switcher />
            <x-public.auth-actions />
        </div>

        <button type="button" class="nav-toggle" data-mobile-nav-trigger aria-expanded="false"
            aria-controls="public-mobile-navigation" aria-label="{{ __('public.open_navigation_menu') }}"
            data-open-label="{{ __('public.open_navigation_menu') }}"
            data-close-label="{{ __('public.close_navigation_menu') }}">
            <i class="bi bi-list" aria-hidden="true"></i>
            <span>{{ __('public.menu') }}</span>
        </button>

        <div id="public-mobile-navigation" class="mobile-panel" data-mobile-nav hidden>
            <x-public.nav class="nav nav-panel" :label="__('public.mobile_primary_navigation')" mode="mobile" />
            <x-public.language-switcher mode="mobile" />
            <a href="{{ route('login') }}" class="mobile-portal">
                <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> {{ __('public.parent_portal') }}
            </a>
        </div>
    </div>
</header>
