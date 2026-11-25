@props(['mode' => 'desktop'])

@php
    $localeUrl = \App\Support\PublicLocaleUrl::class;
    $locales = [
        'en' => __('public.english'),
        'ne' => __('public.nepali'),
    ];
@endphp

<nav {{ $attributes->class(['public-language-switcher', "public-language-switcher-{$mode}"]) }}
    aria-label="{{ __('public.language') }}">
    @foreach ($locales as $locale => $label)
        @php
            $isCurrent = $localeUrl::isCurrent($locale);
            $accessibleLabel = $isCurrent
                ? __('public.current_language').': '.$label
                : __('public.switch_to', ['language' => $label]);
        @endphp
        <a href="{{ $localeUrl::switchUrl($locale) }}" lang="{{ $locale }}" hreflang="{{ $locale }}"
            aria-label="{{ $accessibleLabel }}" @if ($isCurrent) aria-current="true" @endif>
            {{ $label }}
        </a>
    @endforeach
</nav>
