@extends('layouts.public', [
    'title' => $revision?->title ?: 'About',
    'metaDescription' => $revision ? $markdown->excerpt((string) $revision->introduction_markdown) : 'School-approved information is being prepared by Pragyan Pathashala.',
    'ogImage' => $featureUrl ?? null,
])

@section('content')
    <section class="page-hero {{ ! empty($featureUrl) ? 'page-hero--with-photo' : '' }}" aria-labelledby="aboutTitle">
        @if (! empty($featureUrl))
            <figure class="page-hero__photo" data-about-hero-photo>
                <img src="{{ $featureUrl }}" alt="{{ $featureAlt ?? '' }}"
                    @if ($featureWidth && $featureHeight) width="{{ $featureWidth }}" height="{{ $featureHeight }}" @endif
                    loading="eager" decoding="async">
            </figure>
        @endif
        <div class="container">
            <span class="hero-index">About</span>
            <span class="eyebrow">Our school community</span>
            <h1 id="aboutTitle">{{ $revision?->title ?: 'About our school' }}</h1>
            @if ($revision?->introduction_markdown)
                @php
                    $aboutIntroduction = $markdown->excerpt((string) $revision->introduction_markdown, 400);
                    $firstSentenceEnd = mb_strpos($aboutIntroduction, '. ');
                    $aboutIntroduction = $firstSentenceEnd === false
                        ? $markdown->excerpt((string) $revision->introduction_markdown, 180)
                        : mb_substr($aboutIntroduction, 0, $firstSentenceEnd + 1);
                @endphp
                <p>{{ $aboutIntroduction }}</p>
            @else
                <p>School-approved information will appear here once it is ready to share with families.</p>
            @endif
        </div>
    </section>

    <section class="section" aria-label="School profile">
        <div class="container">
            @if ($revision)
                <article class="mx-auto max-w-4xl">
                    @if ($revision->introduction_markdown)
                        <div class="prose prose-slate max-w-none prose-headings:font-bold prose-a:text-blue-800">
                            {!! $markdown->toHtml((string) $revision->introduction_markdown) !!}
                        </div>
                    @endif

                    @if ($revision->history_markdown)
                        <section id="history" class="mt-10 scroll-mt-28" aria-labelledby="historyTitle">
                            <span class="eyebrow">Our story</span>
                            <h2 id="historyTitle" class="mt-2 text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">School introduction and history</h2>
                            <div class="prose prose-slate mt-4 max-w-none prose-headings:font-bold prose-a:text-blue-800">
                                {!! $markdown->toHtml((string) $revision->history_markdown) !!}
                            </div>
                        </section>
                    @endif

                    @if ($revision->mission_markdown || $revision->vision_markdown)
                        <section id="mission-vision" class="mt-10 grid gap-6 md:grid-cols-2" aria-label="Mission and vision">
                            @if ($revision->mission_markdown)
                                <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                                    <span class="eyebrow">Mission</span>
                                    <h2 class="mt-2 text-xl font-extrabold tracking-tight text-slate-900">What guides our work</h2>
                                    <div class="prose prose-slate mt-4 max-w-none prose-a:text-blue-800">
                                        {!! $markdown->toHtml((string) $revision->mission_markdown) !!}
                                    </div>
                                </article>
                            @endif
                            @if ($revision->vision_markdown)
                                <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                                    <span class="eyebrow">Vision</span>
                                    <h2 class="mt-2 text-xl font-extrabold tracking-tight text-slate-900">Where we are heading</h2>
                                    <div class="prose prose-slate mt-4 max-w-none prose-a:text-blue-800">
                                        {!! $markdown->toHtml((string) $revision->vision_markdown) !!}
                                    </div>
                                </article>
                            @endif
                        </section>
                    @endif

                    @if (is_array($revision->approach_values) && count($revision->approach_values) > 0)
                        <section id="approach" class="mt-10 scroll-mt-28" aria-labelledby="approachTitle">
                            <span class="eyebrow">Our approach</span>
                            <h2 id="approachTitle" class="mt-2 text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">What families can expect</h2>
                            <ol class="mt-5 grid gap-3 sm:grid-cols-2">
                                @foreach ($revision->approach_values as $value)
                                    <li class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium leading-6 text-slate-700 shadow-sm">{{ $value }}</li>
                                @endforeach
                            </ol>
                        </section>
                    @endif

                    @if ($revision->facilities_markdown)
                        <section id="facilities" class="mt-10 scroll-mt-28" aria-labelledby="facilitiesTitle">
                            <span class="eyebrow">Facilities</span>
                            <h2 id="facilitiesTitle" class="mt-2 text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">Approved facilities information</h2>
                            <div class="prose prose-slate mt-4 max-w-none prose-a:text-blue-800">
                                {!! $markdown->toHtml((string) $revision->facilities_markdown) !!}
                            </div>
                        </section>
                    @endif
                </article>
            @else
                <div class="mx-auto max-w-2xl rounded-2xl border border-slate-200 bg-white p-7 text-center shadow-sm sm:p-10">
                    <i class="bi bi-journal-text text-3xl text-blue-800" aria-hidden="true"></i>
                    <h2 class="mt-4 text-2xl font-extrabold tracking-tight text-slate-900">Information is being prepared</h2>
                    <p class="mx-auto mt-3 max-w-xl text-slate-600">The school has not yet published its approved introduction, history, or approach. For verified information, please contact the school office.</p>
                    <a href="{{ route('public.contact') }}#visit" class="btn btn-primary mt-6">Contact the school office <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                </div>
            @endif
        </div>
    </section>
@endsection
