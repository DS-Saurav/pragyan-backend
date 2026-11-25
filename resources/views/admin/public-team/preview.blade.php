@extends('layouts.admin')

@section('title', 'Private Team Profile Preview')

@section('content')
    <div class="ui-page max-w-4xl">
        <x-ui.page-header title="Private Team profile preview" subtitle="This preview is available only to authorized editorial staff.">
            <x-slot name="actions"><a href="{{ route('admin.public-team.edit', $profile) }}" class="ui-btn ui-btn-secondary">Return to editor</a></x-slot>
        </x-ui.page-header>

        <article class="ui-card overflow-hidden">
            <div class="grid md:grid-cols-[15rem_minmax(0,1fr)]">
                <div class="bg-slate-100">
                    @if ($portrait?->asset?->public_path)
                        <img src="{{ route('media.published', $portrait->media_asset_id) }}" alt="{{ $portrait->alt_text ?: ($portrait->asset->default_alt_text ?? '') }}" class="h-full min-h-64 w-full object-cover" style="object-position: {{ $portrait->focal_x ?? 50 }}% {{ $portrait->focal_y ?? 50 }}%;">
                    @else
                        <div class="flex min-h-64 items-center justify-center text-5xl font-black text-slate-400" aria-hidden="true">{{ collect(explode(' ', (string) $revision->display_name))->filter()->take(2)->map(fn ($name) => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($name, 0, 1)))->implode('') ?: '?' }}</div>
                    @endif
                </div>
                <div class="p-6 sm:p-8">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ $revision->team_group }} • Revision {{ $revision->revision_number }}</p>
                    <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-900">{{ $revision->display_name }}</h1>
                    <p class="mt-2 text-lg font-medium text-blue-800">{{ $revision->public_role }}</p>
                    @if ($revision->qualification)<p class="mt-2 text-sm text-slate-600">{{ $revision->qualification }}</p>@endif
                    @if ($revision->bio_markdown)<div class="prose prose-slate mt-6 max-w-none">{!! $markdown->toHtml((string) $revision->bio_markdown) !!}</div>@endif
                </div>
            </div>
        </article>
    </div>
@endsection
