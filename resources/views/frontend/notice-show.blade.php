@extends('layouts.public', [
    'title' => $revision?->title ?? $notice->title,
    'metaDescription' => $metaDescription,
])

@section('content')
    @php
        $title = $revision?->title ?? $notice->title;
        $content = $revision?->content ?? $notice->content;
        $noticeDate = $revision?->date ?? $notice->date;
        $date = $noticeDate ? \Illuminate\Support\Carbon::parse($noticeDate, 'Asia/Kathmandu') : null;
        $lastUpdated = $notice->published_at ?? $notice->updated_at;
    @endphp

    <article class="notice-story" aria-labelledby="noticeStoryTitle">
        <header class="news-story-head">
            <div class="container news-story-head-inner">
                <a href="{{ route('public.notices') }}" class="story-back"><i class="bi bi-arrow-left" aria-hidden="true"></i> All notices</a>
                <div class="news-story-meta">
                    @if ($notice->is_urgent)
                        <span class="text-rose-700">Urgent notice</span>
                    @endif
                    @if ($date)
                        <time datetime="{{ $date->toDateString() }}">{{ $date->format('j F Y') }}</time>
                    @endif
                </div>
                <h1 id="noticeStoryTitle">{{ $title }}</h1>
                @if ($lastUpdated)
                    <p class="mt-3 text-sm text-slate-600">Last updated <time datetime="{{ $lastUpdated->toDateString() }}">{{ $lastUpdated->timezone('Asia/Kathmandu')->format('j F Y, g:i A') }}</time></p>
                @endif
            </div>
        </header>

        <div class="container news-story-layout">
            <div class="news-story-content">
                @if ($isLegacy)
                    <div class="prose whitespace-pre-line break-words">{{ $content }}</div>
                @else
                    <div class="prose prose-slate max-w-none break-words">{!! $bodyHtml !!}</div>
                @endif
            </div>
        </div>
    </article>
@endsection
