@extends('layouts.public', [
    'title' => $isArchive ? 'Notice archive' : 'Notice Board',
    'metaDescription' => 'Official school notices and family updates from Pragyan Pathashala.',
])

@section('content')
    <section class="page-hero" aria-labelledby="noticeTitle">
        <div class="container">
            <span class="hero-index">Notices</span>
            <span class="eyebrow">{{ $isArchive ? 'Published notice archive' : 'School updates' }}</span>
            <h1 id="noticeTitle">{{ $isArchive ? 'Notice archive' : 'Notice Board' }}</h1>
            <p>{{ $isArchive ? 'Previously published notices from the school.' : 'Official announcements shared by the school for families and the wider community.' }}</p>
        </div>
    </section>

    <section class="section notice-listing" aria-label="{{ $isArchive ? 'Notice archive' : 'Published notices' }}">
        <div class="container">
            <div class="section-head split-head">
                <div class="head-copy">
                    <span class="eyebrow">{{ $isArchive ? 'Archive' : 'From the school office' }}</span>
                    <h2>{{ $isArchive ? 'Published notices' : 'Useful information for families' }}</h2>
                </div>
                @if (! $isArchive)
                    <a href="{{ route('public.notices.archive') }}" class="text-link">Browse the archive <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                @endif
            </div>

            @if ($notices->isEmpty())
                <div class="news-empty">
                    <i class="bi bi-megaphone" aria-hidden="true"></i>
                    <h2>No notices yet</h2>
                    <p>Published announcements will appear here when the school has an update to share.</p>
                    <a href="{{ route('public.calendar') }}" class="text-link">View the school calendar <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                </div>
            @else
                <div class="notice-grid">
                    @foreach ($notices as $notice)
                        @php
                            $revision = $notice->publishedRevision;
                            $title = $revision?->title ?? $notice->title;
                            $content = $revision?->content ?? $notice->content;
                            $noticeDate = $revision?->date ?? $notice->date;
                            $date = $noticeDate ? \Illuminate\Support\Carbon::parse($noticeDate, 'Asia/Kathmandu') : null;
                            $target = $notice->slug ? route('public.notices.show', ['slug' => $notice->slug]) : null;
                        @endphp
                        <article class="notice-card">
                            <div class="notice-card-copy">
                                <p class="card-kicker">
                                    @if ($notice->is_urgent)
                                        <span class="card-date text-rose-700">Urgent notice</span>
                                    @else
                                        <span class="card-date">School notice</span>
                                    @endif
                                    @if ($date)
                                        <time datetime="{{ $date->toDateString() }}">{{ $date->format('j M Y') }}</time>
                                    @endif
                                </p>
                                <h2>
                                    @if ($target)
                                        <a href="{{ $target }}">{{ $title }}</a>
                                    @else
                                        {{ $title }}
                                    @endif
                                </h2>
                                @if ($content)
                                    <p>{{ \Illuminate\Support\Str::limit(\Illuminate\Support\Str::squish(strip_tags($content)), 220) }}</p>
                                @endif
                                @if ($target)
                                    <a href="{{ $target }}" class="text-link">Read notice <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif

            @if ($notices->hasPages())
                <nav class="news-pagination" aria-label="Notice pages">{{ $notices->links() }}</nav>
            @endif
        </div>
    </section>
@endsection
