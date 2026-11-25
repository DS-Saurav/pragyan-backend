@extends('layouts.public', [
    'title' => $revision->title,
    'metaDescription' => $revision->summary ?: $revision->title,
    'ogImage' => $coverUrl,
])

@section('content')
    @php
        $photos = $revision->photos->values();

        if ($cover && ! $photos->contains(fn ($photo) => $photo->media_asset_id === $cover->media_asset_id)) {
            $photos->prepend($cover);
        }
    @endphp

    <article class="gallery-story" aria-labelledby="galleryAlbumTitle">
        <header class="gallery-story-head">
            <div class="container gallery-story-head-inner">
                <a href="{{ route('public.gallery') }}" class="story-back"><i class="bi bi-arrow-left" aria-hidden="true"></i> All gallery albums</a>
                <div class="gallery-story-meta mt-6">
                    @if ($revision->category)<span>{{ $revision->category }}</span>@endif
                    @if ($revision->album_date)<time datetime="{{ $revision->album_date->toDateString() }}">{{ $revision->album_date->format('j F Y') }}</time>@endif
                    <span>{{ $photos->count() }} {{ \Illuminate\Support\Str::plural('photo', $photos->count()) }}</span>
                </div>
                <h1 id="galleryAlbumTitle">{{ $revision->title }}</h1>
                @if ($revision->summary)<p class="gallery-story-summary">{{ $revision->summary }}</p>@endif
            </div>
        </header>

        <div class="container gallery-story-layout">
            <div class="gallery-photos" aria-label="Photographs from {{ $revision->title }}">
                @foreach ($photos as $photo)
                    @php($asset = $photo->asset)
                    @if ($asset?->public_path)
                        <figure class="gallery-photo">
                            <img src="{{ route('media.published', $asset->id) }}"
                                alt="{{ $photo->alt_text ?: ($asset->default_alt_text ?? '') }}"
                                @if ($asset->width && $asset->height) width="{{ $asset->width }}" height="{{ $asset->height }}" @endif
                                loading="{{ $loop->first ? 'eager' : 'lazy' }}" decoding="async"
                                style="object-position: {{ $photo->focal_x ?? 50 }}% {{ $photo->focal_y ?? 50 }}%;">
                            @if ($photo->caption)<figcaption>{{ $photo->caption }}</figcaption>@endif
                        </figure>
                    @endif
                @endforeach
            </div>
        </div>
    </article>
@endsection
