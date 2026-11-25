@php
    $label ??= __('public.primary_navigation');
    $mode ??= 'desktop';
    $groups = \App\Support\PublicNavigation::groups();
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

<nav {{ $attributes->merge(['aria-label' => $label]) }} @if ($mode === 'desktop') data-public-desktop-nav @endif>
    @foreach ($groups as $group)
        @php
            $links = \App\Support\PublicNavigation::links($group);
            $active = collect($links)->contains(
                fn (array $link): bool => \App\Support\PublicLocaleUrl::routeIs($link['routes']),
            );
            $direct = isset($group['route']);
            $menuId = 'public-nav-'.\Illuminate\Support\Str::slug($group['label']);
            $groupLabel = $translatedLabel($group['label']);
        @endphp

        @if ($mode === 'mobile')
            @if ($direct)
                <a href="{{ \App\Support\PublicLocaleUrl::route($group['route']) }}"
                    class="nav-panel-direct @if ($active) is-active @endif"
                    @if ($active) aria-current="page" @endif>
                    {{ $groupLabel }}
                </a>
            @else
                <section class="nav-panel-group" aria-labelledby="{{ $menuId }}-label">
                    <p id="{{ $menuId }}-label" class="nav-group-label">{{ $groupLabel }}</p>
                    <ul class="nav-panel-links">
                        @foreach ($links as $link)
                            <li>
                                <a href="{{ \App\Support\PublicLocaleUrl::route($link['route']) }}"
                                    @if (\App\Support\PublicLocaleUrl::routeIs($link['routes'])) aria-current="page" @endif>
                                    {{ $translatedLabel($link['label']) }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        @elseif ($direct)
            <a href="{{ \App\Support\PublicLocaleUrl::route($group['route']) }}"
                class="nav-link @if ($active) is-active @endif"
                @if ($active) aria-current="page" @endif>
                {{ $groupLabel }}
            </a>
        @else
            <div class="nav-disclosure @if ($active) is-active @endif">
                <button type="button" class="nav-disclosure-trigger" data-nav-group-trigger
                    aria-expanded="false" aria-controls="{{ $menuId }}" aria-haspopup="true">
                    {{ $groupLabel }}
                    <i class="bi bi-chevron-down" aria-hidden="true"></i>
                </button>
                <ul id="{{ $menuId }}" class="nav-menu" data-nav-group-menu hidden>
                    @foreach ($links as $link)
                        <li>
                            <a href="{{ \App\Support\PublicLocaleUrl::route($link['route']) }}"
                                @if (\App\Support\PublicLocaleUrl::routeIs($link['routes'])) aria-current="page" @endif>
                                {{ $translatedLabel($link['label']) }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endforeach
</nav>
