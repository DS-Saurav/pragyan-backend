@extends('layouts.public', [
    'title' => $title,
    'metaDescription' => $metaDescription ?? $title,
])

@section('content')
    <section class="page-hero" aria-labelledby="neListingTitle">
        <div class="container">
            <span class="hero-index" lang="ne">नेपाली</span>
            <span class="eyebrow">{{ __('public.published_content') }}</span>
            <h1 id="neListingTitle">{{ $title }}</h1>
        </div>
    </section>

    <section class="section" aria-label="{{ $title }}">
        <div class="container">
            <div class="news-grid">
                @foreach ($items as $item)
                    <article class="news-card">
                        @if ($item['url'] && $item['media']->isNotEmpty())
                            @php($image = $item['media']->first())
                            <a href="{{ $item['url'] }}" class="news-card-image" tabindex="-1" aria-hidden="true">
                                <img src="{{ $image['url'] }}" alt="{{ $image['alt'] }}" loading="lazy" decoding="async">
                            </a>
                        @endif
                        <div class="news-card-copy">
                            <h2>
                                @if ($item['url'])
                                    <a href="{{ $item['url'] }}">{{ $item['title'] }}</a>
                                @else
                                    {{ $item['title'] }}
                                @endif
                            </h2>
                            @if ($item['summary'] !== '')
                                <p>{{ $item['summary'] }}</p>
                            @endif
                            @if ($item['url'])
                                <a href="{{ $item['url'] }}" class="text-link">{{ __('public.view_details') }} <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
            @if ($isArchive ?? false)
                <p class="mt-8 text-center text-sm text-slate-600"><a href="{{ $englishUrl }}" class="text-link" lang="en">{{ __('public.unavailable.view_english') }}</a></p>
            @endif
        </div>
    </section>
@endsection
