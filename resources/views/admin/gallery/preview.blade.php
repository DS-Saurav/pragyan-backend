@extends('layouts.admin')

@section('title', 'Private Gallery Preview')

@section('content')
    <div class="ui-page max-w-5xl">
        <x-ui.page-header title="Private album preview" subtitle="Only authorised Gallery staff can open this pre-publication view.">
            <x-slot name="actions">
                <a href="{{ route('admin.gallery.edit', $album) }}" class="ui-btn ui-btn-secondary">Return to editor</a>
            </x-slot>
        </x-ui.page-header>

        <article class="ui-card overflow-hidden">
            @if ($cover?->asset?->public_path)
                <img src="{{ route('media.published', $cover->media_asset_id) }}" alt="{{ $cover->alt_text ?: ($cover->asset->default_alt_text ?? '') }}" class="max-h-[30rem] w-full object-cover" style="object-position: {{ $cover->focal_x ?? 50 }}% {{ $cover->focal_y ?? 50 }}%;">
            @endif
            <div class="p-6 sm:p-8">
                <p class="text-xs font-bold uppercase tracking-wider text-blue-700">
                    {{ $revision->category ?: 'School Gallery' }}
                    @if ($revision->album_date)
                        <span class="font-normal text-slate-500"> · {{ $revision->album_date->format('j F Y') }}</span>
                    @endif
                </p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-900">{{ $revision->title ?: 'Untitled Gallery album' }}</h1>
                @if ($revision->summary)
                    <p class="mt-3 max-w-3xl text-lg leading-8 text-slate-600">{{ $revision->summary }}</p>
                @endif
            </div>

            <div class="grid gap-4 bg-slate-50 p-5 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($photos as $photo)
                    <figure class="overflow-hidden rounded-lg bg-white shadow-sm">
                        @if ($photo->asset?->public_path)
                            <img src="{{ route('media.published', $photo->media_asset_id) }}" alt="{{ $photo->alt_text ?: ($photo->asset->default_alt_text ?? '') }}" class="aspect-[4/3] w-full object-cover" style="object-position: {{ $photo->focal_x ?? 50 }}% {{ $photo->focal_y ?? 50 }}%;">
                        @endif
                        @if ($photo->caption)
                            <figcaption class="p-3 text-sm leading-6 text-slate-600">{{ $photo->caption }}</figcaption>
                        @endif
                    </figure>
                @empty
                    <p class="p-4 text-sm text-slate-600">No photographs have been selected for this private revision yet.</p>
                @endforelse
            </div>
        </article>
    </div>
@endsection
