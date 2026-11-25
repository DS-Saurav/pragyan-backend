@extends('layouts.admin')

@section('title', 'Private News Preview')

@section('content')
    <div class="ui-page max-w-4xl">
        <x-ui.page-header title="Private story preview" subtitle="This preview is only available to authorized editorial staff.">
            <x-slot name="actions">
                <a href="{{ route('admin.news.edit', $story) }}" class="ui-btn ui-btn-secondary">Return to editor</a>
            </x-slot>
        </x-ui.page-header>

        <article class="ui-card overflow-hidden">
            @if ($cover?->asset?->public_path)
                <img src="{{ route('media.published', $cover->media_asset_id) }}" alt="{{ $cover->alt_text ?: ($cover->asset->default_alt_text ?? '') }}" class="max-h-[28rem] w-full object-cover">
            @endif
            <div class="p-6 sm:p-8">
                <p class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ $revision->category ?: 'School news' }}</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-900">{{ $revision->title }}</h1>
                @if ($revision->summary)
                    <p class="mt-3 text-lg text-slate-600">{{ $revision->summary }}</p>
                @endif
                <div class="prose prose-slate mt-7 max-w-none">{!! $bodyHtml !!}</div>
            </div>
        </article>
    </div>
@endsection
