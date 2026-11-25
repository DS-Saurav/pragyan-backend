@extends('layouts.admin')

@section('title', 'Nepali translation preview')

@section('content')
    @php
        $payload = is_array($payload ?? null) ? $payload : [];
        $title = collect(['title', 'label', 'name', 'hero_headline', 'display_name'])
            ->map(fn (string $key) => trim((string) ($payload[$key] ?? '')))
            ->first(fn (string $value) => $value !== '') ?? 'नेपाली पूर्वावलोकन';
        $summary = collect(['summary', 'public_status_message', 'public_intro', 'hero_intro', 'introduction_markdown', 'description', 'content', 'body_markdown', 'bio_markdown'])
            ->map(fn (string $key) => trim(strip_tags((string) ($payload[$key] ?? ''))))
            ->first(fn (string $value) => $value !== '');
        $body = collect(['body_markdown', 'content', 'introduction_markdown', 'bio_markdown', 'description', 'public_intro', 'home_intro', 'visit_instructions'])
            ->map(fn (string $key) => trim((string) ($payload[$key] ?? '')))
            ->first(fn (string $value) => $value !== '');
        $extraBlocks = collect(['history_markdown', 'mission_markdown', 'vision_markdown', 'facilities_markdown', 'fee_summary'])
            ->map(fn (string $key) => trim((string) ($payload[$key] ?? '')))
            ->filter();
        $lists = collect(['approach_values', 'key_points', 'highlights'])
            ->map(fn (string $key) => $payload[$key] ?? null)
            ->filter(fn (mixed $list): bool => is_array($list) && $list !== []);
        $collections = collect(['grades', 'steps', 'requirements', 'faqs', 'stages'])
            ->map(fn (string $key) => $payload[$key] ?? null)
            ->filter(fn (mixed $rows): bool => is_array($rows) && $rows !== []);
        $media = $revision instanceof \App\Models\PublicContentTranslationRevision
            ? $revision->media->filter(function ($annotation): bool {
                $asset = $annotation->mediaAttachment?->asset;

                return $asset !== null
                    && $asset->status === 'published'
                    && $asset->archived_at === null
                    && in_array($asset->consent_state, ['not_required', 'confirmed'], true)
                    && $asset->consent_withdrawn_at === null
                    && $asset->public_path !== null
                    && ($asset->is_decorative || filled($annotation->alt_text));
            })
            : collect();
    @endphp

    <div class="ui-page">
        <x-ui.page-header title="Nepali translation preview"
            subtitle="Private preview of this revision only. It is not a public page and does not publish the translation.">
            <x-slot name="actions">
                <a href="{{ $backUrl }}" class="ui-btn ui-btn-secondary"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to editor</a>
            </x-slot>
        </x-ui.page-header>

        <div class="ui-alert ui-alert-info" role="status">
            <i class="bi bi-shield-lock" aria-hidden="true"></i>
            <span>English source: <strong>{{ $sourceLabel }}</strong>. Only the Nepali revision below is shown.</span>
        </div>

        <article lang="ne" class="mx-auto mt-6 max-w-4xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm md:p-10">
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-sky-700">नेपाली पूर्वावलोकन</p>
            <h1 class="mt-3 text-3xl font-bold tracking-tight text-slate-900">{{ $title }}</h1>
            @if ($summary)
                <p class="mt-4 text-lg leading-8 text-slate-600">{{ $summary }}</p>
            @endif

            @foreach ($media as $annotation)
                @php($asset = $annotation->mediaAttachment->asset)
                <figure class="mt-6 overflow-hidden rounded-xl bg-slate-100">
                    <img src="{{ route('media.published', $asset->id) }}" alt="{{ $asset->is_decorative ? '' : $annotation->alt_text }}" class="h-auto w-full" decoding="async">
                    @if (filled($annotation->caption))<figcaption class="px-4 py-3 text-sm text-slate-600">{{ $annotation->caption }}</figcaption>@endif
                </figure>
            @endforeach

            @if ($body)
                <div class="prose mt-8 max-w-none">{!! \Illuminate\Support\Str::markdown($body, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
            @endif

            @foreach ($extraBlocks as $block)
                <div class="prose mt-6 max-w-none">{!! \Illuminate\Support\Str::markdown($block, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
            @endforeach

            @foreach ($lists as $list)
                <ul class="mt-6 list-disc space-y-2 pl-6 text-slate-700">
                    @foreach ($list as $item)
                        @if (is_string($item) && trim($item) !== '')<li>{{ $item }}</li>@endif
                    @endforeach
                </ul>
            @endforeach

            @foreach ($collections as $rows)
                <div class="mt-8 grid gap-4">
                    @foreach ($rows as $row)
                        @if (is_array($row))
                            <section class="rounded-xl border border-slate-200 p-5">
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
                            </section>
                        @endif
                    @endforeach
                </div>
            @endforeach
        </article>
    </div>
@endsection
