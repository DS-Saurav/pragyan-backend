@php
    $notice = app(\App\Services\Notices\NoticeVisibility::class)->urgent();
    $revision = $notice?->publishedRevision;
    $title = $revision?->title ?? $notice?->title;
    $content = $revision?->content ?? $notice?->content;
    $noticeDate = $revision?->date ?? $notice?->date;
    $date = $noticeDate ? \Illuminate\Support\Carbon::parse($noticeDate, 'Asia/Kathmandu') : null;
    $context = $content ? app(\App\Services\Notices\NoticeMarkdown::class)->excerpt((string) $content, 150) : null;
@endphp

@if ($notice && $title && $notice->slug)
    <section class="urgent-notice" aria-label="Urgent school notice">
        <div class="container urgent-notice-inner">
            <div class="urgent-notice-icon" aria-hidden="true"><i class="bi bi-exclamation-diamond-fill"></i></div>
            <div class="urgent-notice-copy">
                <p class="urgent-notice-label">Urgent school notice</p>
                <h2>{{ $title }}</h2>
                @if ($context)
                    <p>{{ $context }}</p>
                @endif
                @if ($date)
                    <time datetime="{{ $date->toDateString() }}">Published {{ $date->format('j M Y') }}</time>
                @endif
            </div>
            <a href="{{ route('public.notices.show', ['slug' => $notice->slug]) }}" class="urgent-notice-link">
                Read notice <i class="bi bi-arrow-right" aria-hidden="true"></i>
            </a>
        </div>
    </section>
@endif
