@php
    $identity ??= \App\Support\SiteIdentity::publicProfile();
    $siteName = $identity['name'];
    $logoUrl = $identity['logo_url'];
    $location = $identity['location'];
    $tagline = $identity['tagline'];
    $initials = collect(explode(' ', trim($siteName)))
        ->map(fn ($word) => Str::upper(Str::substr($word, 0, 1)))
        ->take(2)
        ->implode('');
@endphp
<a href="{{ \App\Support\PublicLocaleUrl::route('home') }}" class="brand">
    <span class="brand-mark" aria-hidden="true">
        @if ($logoUrl !== '')
            <img src="{{ $logoUrl }}" alt="" width="44" height="44" class="brand-logo">
        @else
            {{ $initials }}
        @endif
    </span>
    <span class="brand-copy">
        <span class="brand-name">{{ $siteName }}</span>
        @if ($location !== '')
            <span class="brand-location">{{ $location }}</span>
        @elseif ($tagline !== '')
            <span class="brand-tag">{{ $tagline }}</span>
        @endif
    </span>
</a>
