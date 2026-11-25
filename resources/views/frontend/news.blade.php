@extends('layouts.public', [
    'title' => $isArchive ? 'News archive' : 'News & Updates',
    'metaDescription' => 'News and updates from Pragyan Pathashala',
])

@section('content')
    @php
        $identity = \App\Support\SiteIdentity::publicProfile();
    @endphp

    <section class="page-hero" aria-labelledby="newsTitle">
        <div class="container">
            <span class="hero-index">News</span>
            <span class="eyebrow">{{ $isArchive ? 'Published story archive' : 'Latest news and updates' }}</span>
            <h1 id="newsTitle">{{ $isArchive ? 'News archive' : $identity['name'] }}</h1>
        </div>
    </section>

    <section class="section news-listing" aria-label="{{ $isArchive ? 'News archive' : 'News stories' }}">
        <div class="container">
            <div class="section-head split-head">
                <div class="head-copy">
                    <span class="eyebrow">{{ $isArchive ? 'Archive' : 'From the school' }}</span>
                    <h2>{{ $isArchive ? 'Previously published stories' : 'News from our community' }}</h2>
                </div>
                @if (! $isArchive)
                    <a href="{{ route('public.news.archive') }}" class="text-link">Browse the archive <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                @endif
            </div>

            @forelse ($stories as $story)
                @php
                    $revision = $story->publishedRevision;
                    $cover = $revision?->cover;
                    $coverAsset = $cover?->asset;
                    $coverAlt = $cover?->alt_text ?: ($coverAsset?->default_alt_text ?? '');
                @endphp
                @if ($loop->first)
                    <div class="news-grid">
                @endif
                    <article class="news-card">
                        @if ($coverAsset?->public_path)
                            <a href="{{ route('public.news.show', $story->slug) }}" class="news-card-image" tabindex="-1" aria-hidden="true">
                                <img src="{{ route('media.published', $coverAsset->id) }}" alt="{{ $coverAlt }}"
                                    @if ($coverAsset->width && $coverAsset->height) width="{{ $coverAsset->width }}" height="{{ $coverAsset->height }}" @endif
                                    loading="lazy" decoding="async">
                            </a>
                        @endif
                        <div class="news-card-copy">
                            <p class="card-kicker">
                                @if ($revision?->category)
                                    <span class="card-date">{{ $revision->category }}</span>
                                @else
                                    <span class="card-date">School news</span>
                                @endif
                                @if ($story->published_at)
                                    <time datetime="{{ $story->published_at->toDateString() }}">{{ $story->published_at->format('j M Y') }}</time>
                                @endif
                            </p>
                            <h3><a href="{{ route('public.news.show', $story->slug) }}">{{ $revision?->title }}</a></h3>
                            @if ($revision?->summary)
                                <p>{{ $revision->summary }}</p>
                            @endif
                            <a href="{{ route('public.news.show', $story->slug) }}" class="text-link">Read story <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                        </div>
                    </article>
                @if ($loop->last)
                    </div>
                @endif
            @empty
                <div class="news-empty">
                    <i class="bi bi-newspaper" aria-hidden="true"></i>
                    <h2>No published stories yet</h2>
                    <p>News and updates from {{ $identity['name'] }} will appear here once they are ready to share.</p>
                    <a href="{{ route('public.notices') }}" class="text-link">View school notices <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                </div>
            @endforelse

            @if ($stories->hasPages())
                <nav class="news-pagination" aria-label="News pages">{{ $stories->links() }}</nav>
            @endif
        </div>
    </section>
@endsection
