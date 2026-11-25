@extends('layouts.admin')

@section('title', 'Private Notice Preview')

@section('content')
    <div class="ui-page max-w-4xl">
        <x-ui.page-header title="Private notice preview" subtitle="Only authorized editorial staff can view this unpublished revision.">
            <x-slot name="actions">
                <a href="{{ route('admin.notices.edit', $notice) }}" class="ui-btn ui-btn-secondary">Return to editor</a>
            </x-slot>
        </x-ui.page-header>

        <article class="ui-card p-6 sm:p-8" aria-labelledby="noticePreviewTitle">
            <div class="flex flex-wrap items-center gap-3 text-xs font-bold uppercase tracking-wider text-slate-500">
                <span>{{ $revision->audience === 'all' ? 'Public family notice' : ucfirst((string) $revision->audience) }}</span>
                @if ($revision->is_urgent)
                    <span class="text-rose-700">Urgent</span>
                @endif
                @if ($revision->date)
                    <time datetime="{{ $revision->date instanceof \Carbon\CarbonInterface ? $revision->date->toDateString() : $revision->date }}">
                        {{ \Illuminate\Support\Carbon::parse($revision->date, 'Asia/Kathmandu')->format('j F Y') }}
                    </time>
                @endif
            </div>
            <h1 id="noticePreviewTitle" class="mt-3 text-3xl font-bold tracking-tight text-slate-900">{{ $revision->title }}</h1>
            <div class="prose prose-slate mt-7 max-w-none break-words">{!! $bodyHtml !!}</div>
        </article>
    </div>
@endsection
