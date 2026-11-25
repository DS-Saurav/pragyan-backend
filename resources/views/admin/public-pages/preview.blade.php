@extends('layouts.admin')

@section('title', 'Private Public Page Preview')

@section('content')
    <div class="ui-page max-w-5xl">
        <x-ui.page-header title="Private {{ $page->type === 'academics' ? 'Academics' : 'About' }} preview"
            subtitle="Only authorized editorial staff can open this unpublished revision.">
            <x-slot name="actions"><a href="{{ route('admin.public-pages.edit', $page) }}" class="ui-btn ui-btn-secondary">Return to editor</a></x-slot>
        </x-ui.page-header>

        <article class="ui-card overflow-hidden">
            @if ($feature?->asset?->public_path)
                <img src="{{ route('media.published', $feature->media_asset_id) }}" alt="{{ $feature->alt_text ?: ($feature->asset->default_alt_text ?? '') }}" class="max-h-[30rem] w-full object-cover" style="object-position: {{ $feature->focal_x ?? 50 }}% {{ $feature->focal_y ?? 50 }}%;">
            @endif
            <div class="p-6 sm:p-8">
                <p class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ $page->type === 'academics' ? 'Academics' : 'About' }} • Revision {{ $revision->revision_number }}</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-900">{{ $revision->title }}</h1>
                <div class="prose prose-slate mt-7 max-w-none">{!! $markdown->toHtml((string) $revision->introduction_markdown) !!}</div>
                @if ($page->type === 'academics')
                    <div class="mt-8 space-y-4">
                        @foreach ($revision->academicStages as $stage)
                            <section class="rounded-xl border border-slate-200 p-5"><h2 class="text-lg font-bold text-slate-900">{{ $stage->title }}</h2>@if ($stage->stage_label)<p class="mt-1 text-sm text-slate-500">{{ $stage->stage_label }}</p>@endif @if ($stage->description_markdown)<div class="prose prose-slate mt-3 max-w-none">{!! $markdown->toHtml((string) $stage->description_markdown) !!}</div>@endif</section>
                        @endforeach
                    </div>
                @else
                    @foreach (['history_markdown' => 'School introduction and history', 'mission_markdown' => 'Mission', 'vision_markdown' => 'Vision', 'facilities_markdown' => 'Facilities'] as $field => $heading)
                        @if ($revision->{$field})<section class="mt-8"><h2 class="text-xl font-bold text-slate-900">{{ $heading }}</h2><div class="prose prose-slate mt-3 max-w-none">{!! $markdown->toHtml((string) $revision->{$field}) !!}</div></section>@endif
                    @endforeach
                @endif
            </div>
        </article>
    </div>
@endsection
