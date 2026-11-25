@extends('layouts.public', [
    'title' => $title,
    'metaDescription' => $metaDescription ?? $title,
])

@section('content')
    @php
        $textBlocks = collect([
            $payload['history_markdown'] ?? null,
            $payload['mission_markdown'] ?? null,
            $payload['vision_markdown'] ?? null,
            $payload['facilities_markdown'] ?? null,
            $payload['fee_summary'] ?? null,
        ])->filter(fn ($value) => is_string($value) && trim($value) !== '')->values();
        $lists = collect([
            $payload['approach_values'] ?? null,
            $payload['key_points'] ?? null,
            $payload['highlights'] ?? null,
        ])->filter(fn ($value) => is_array($value) && $value !== [])->values();
        $rows = collect([
            $payload['grades'] ?? null,
            $payload['steps'] ?? null,
            $payload['requirements'] ?? null,
            $payload['faqs'] ?? null,
            $payload['stages'] ?? null,
        ])->filter(fn ($value) => is_array($value) && $value !== [])->values();
        $headerPhoto = ! empty($photoHero) ? $media->first() : null;
    @endphp

    <article class="news-story" aria-labelledby="neContentTitle">
        <header class="news-story-head {{ $headerPhoto ? 'news-story-head--with-photo' : '' }}">
            @if ($headerPhoto)
                <figure class="news-story-head__photo">
                    <img src="{{ $headerPhoto['url'] }}" alt="{{ $headerPhoto['alt'] }}" loading="eager" decoding="async">
                </figure>
            @endif
            <div class="container news-story-head-inner">
                <a href="{{ $englishUrl }}" class="story-back" lang="en"><i class="bi bi-arrow-left" aria-hidden="true"></i> {{ __('public.unavailable.view_english') }}</a>
                <h1 id="neContentTitle">{{ $title }}</h1>
                @if (($payload['summary'] ?? '') !== '')
                    <p class="news-story-summary">{{ $payload['summary'] }}</p>
                @endif
                @if (($payload['public_status_message'] ?? '') !== '')
                    <p class="news-story-summary">{{ $payload['public_status_message'] }}</p>
                @endif
            </div>
        </header>

        <div class="container news-story-layout">
            <div class="news-story-content">
                @foreach ($headerPhoto ? $media->skip(1) : $media as $image)
                    <figure class="news-story-cover">
                        <img src="{{ $image['url'] }}" alt="{{ $image['alt'] }}" decoding="async">
                        @if ($image['caption'])<figcaption>{{ $image['caption'] }}</figcaption>@endif
                    </figure>
                @endforeach

                @if ($bodyHtml->toHtml() !== '')
                    <div class="news-story-body prose">{!! $bodyHtml !!}</div>
                @endif

                @foreach ($textBlocks as $block)
                    <div class="news-story-body prose mt-6">{!! \Illuminate\Support\Str::markdown($block, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
                @endforeach

                @foreach ($lists as $list)
                    <ul class="mt-6 list-disc space-y-2 pl-6 text-slate-700">
                        @foreach ($list as $item)
                            @if (is_string($item) && trim($item) !== '')<li>{{ $item }}</li>@endif
                        @endforeach
                    </ul>
                @endforeach

                @foreach ($rows as $collection)
                    <div class="mt-8 grid gap-4">
                        @foreach ($collection as $row)
                            @if (is_array($row))
                                <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                                    @if (filled($row['stage_label'] ?? null) && filled($row['title'] ?? null))
                                        <p class="text-xs font-bold uppercase tracking-[0.14em] text-blue-800">{{ $row['stage_label'] }}</p>
                                    @endif
                                    @foreach (['title', 'label', 'question', 'stage_label'] as $key)
                                        @if (filled($row[$key] ?? null))
                                            <h2 class="text-lg font-bold text-slate-900">{{ $row[$key] }}</h2>
                                            @break
                                        @endif
                                    @endforeach
                                    @foreach (['body', 'detail', 'answer', 'description_markdown'] as $key)
                                        @if (filled($row[$key] ?? null))
                                            <div class="prose mt-2 max-w-none">{!! \Illuminate\Support\Str::markdown((string) $row[$key], ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
                                        @endif
                                    @endforeach
                                    @if (is_array($row['key_points'] ?? null) && $row['key_points'] !== [])
                                        <ul class="mt-3 list-disc space-y-1 pl-6 text-slate-700">
                                            @foreach ($row['key_points'] as $point)<li>{{ $point }}</li>@endforeach
                                        </ul>
                                    @endif
                                </section>
                            @endif
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    </article>
@endsection
