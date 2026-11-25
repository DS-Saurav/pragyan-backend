@extends('layouts.public', [
    'title' => $revision->title,
    'metaDescription' => $revision->summary ?: $revision->title,
    'ogImage' => $coverUrl,
])

@section('content')
    <article class="news-story" aria-labelledby="storyTitle">
        <header class="news-story-head">
            <div class="container news-story-head-inner">
                <a href="{{ route('public.news') }}" class="story-back"><i class="bi bi-arrow-left" aria-hidden="true"></i> All news</a>
                <div class="news-story-meta">
                    @if ($revision->category)
                        <span>{{ $revision->category }}</span>
                    @endif
                    @if ($story->published_at)
                        <time datetime="{{ $story->published_at->toDateString() }}">{{ $story->published_at->format('j F Y') }}</time>
                    @endif
                </div>
                <h1 id="storyTitle">{{ $revision->title }}</h1>
                @if ($revision->summary)
                    <p class="news-story-summary">{{ $revision->summary }}</p>
                @endif
            </div>
        </header>

        <div class="container news-story-layout">
            <div class="news-story-content">
                @if ($coverUrl)
                    <figure class="news-story-cover">
                        <img src="{{ $coverUrl }}" alt="{{ $cover?->alt_text ?: ($cover?->asset?->default_alt_text ?? '') }}"
                            @if ($cover?->asset?->width && $cover?->asset?->height) width="{{ $cover->asset->width }}" height="{{ $cover->asset->height }}" @endif
                            decoding="async">
                        @if ($cover?->caption || $revision->public_attribution || $cover?->asset?->photographer_credit)
                            <figcaption>
                                @if ($cover?->caption)<span>{{ $cover->caption }}</span>@endif
                                @if ($revision->public_attribution || $cover?->asset?->photographer_credit)
                                    <span>{{ $revision->public_attribution ?: $cover?->asset?->photographer_credit }}</span>
                                @endif
                            </figcaption>
                        @endif
                    </figure>
                @endif

                <div class="news-story-body prose">{!! $bodyHtml !!}</div>
            </div>
        </div>
    </article>
@endsection
