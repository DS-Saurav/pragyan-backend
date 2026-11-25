@extends('layouts.public', [
    'title' => $isArchive ? 'Gallery archive' : 'Gallery',
    'metaDescription' => $isArchive
        ? 'A record of school moments from Pragyan Pathashala.'
        : 'A record of learning, events and school life at Pragyan Pathashala.',
])

@section('content')
    <section class="page-hero" aria-labelledby="galleryTitle">
        <div class="container">
            <span class="hero-index">Gallery</span>
            <span class="eyebrow">School moments</span>
            <h1 id="galleryTitle">{{ $isArchive ? 'Gallery archive' : 'Life at school' }}</h1>
            <p class="mt-4 max-w-2xl text-lg text-slate-600">
                {{ $isArchive
                    ? 'A growing record of events, learning and shared moments at Pragyan Pathashala.'
                    : 'A thoughtful record of events, learning and shared moments at Pragyan Pathashala.' }}
            </p>
        </div>
    </section>

    <section class="section" aria-label="Published gallery albums">
        <div class="container">
            @if (! $isArchive && $albums->isNotEmpty())
                <div class="mb-8 flex justify-end">
                    <a href="{{ route('public.gallery.archive') }}" class="text-link">Browse the archive <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                </div>
            @endif

            <div class="gallery-grid">
                @forelse ($albums as $album)
                    @php
                        $revision = $album->publishedRevision;
                        $cover = $revision?->cover;
                        $coverAsset = $cover?->asset;
                        $photoCount = $revision?->photos?->count() ?? 0;
                    @endphp
                    <article class="gallery-card">
                        @if ($coverAsset?->public_path)
                            <a href="{{ route('public.gallery.show', $album->slug) }}" class="gallery-card-cover" tabindex="-1" aria-hidden="true">
                                <img src="{{ route('media.published', $coverAsset->id) }}"
                                    alt="{{ $cover?->alt_text ?: ($coverAsset->default_alt_text ?? '') }}"
                                    @if ($coverAsset->width && $coverAsset->height) width="{{ $coverAsset->width }}" height="{{ $coverAsset->height }}" @endif
                                    loading="lazy" decoding="async"
                                    style="object-position: {{ $cover?->focal_x ?? 50 }}% {{ $cover?->focal_y ?? 50 }}%;">
                            </a>
                        @endif
                        <div class="gallery-card-copy">
                            <div class="gallery-card-meta">
                                @if ($revision?->category)<span>{{ $revision->category }}</span>@endif
                                @if ($revision?->album_date)<time datetime="{{ $revision->album_date->toDateString() }}">{{ $revision->album_date->format('j M Y') }}</time>@endif
                            </div>
                            <h2><a href="{{ route('public.gallery.show', $album->slug) }}">{{ $revision?->title }}</a></h2>
                            @if ($revision?->summary)<p>{{ $revision->summary }}</p>@endif
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-xs font-semibold text-slate-500">{{ $photoCount }} {{ \Illuminate\Support\Str::plural('photo', $photoCount) }}</span>
                                <a href="{{ route('public.gallery.show', $album->slug) }}" class="text-link">View album <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="news-empty col-span-full">
                        <i class="bi bi-images" aria-hidden="true"></i>
                        <h2>No published albums yet</h2>
                        <p>Approved photographs from {{ \App\Support\SiteIdentity::publicProfile()['name'] }} will appear here when they are ready to share.</p>
                        <a href="{{ route('public.notices') }}" class="text-link">View school notices <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                    </div>
                @endforelse
            </div>

            @if ($albums->hasPages())
                <nav class="news-pagination" aria-label="Gallery pages">{{ $albums->links() }}</nav>
            @endif
        </div>
    </section>
@endsection
