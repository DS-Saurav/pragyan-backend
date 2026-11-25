@extends('layouts.public', [
    'title' => $revision?->title ?: 'Academics',
    'metaDescription' => $revision ? $markdown->excerpt((string) $revision->introduction_markdown) : 'Approved learning-stage information from Pragyan Pathashala.',
    'ogImage' => $featureUrl ?? null,
])

@section('content')
    @php
        $hasPublishedStages = $revision !== null && $revision->academicStages->isNotEmpty();
    @endphp

    <section class="editorial-page-hero editorial-page-hero--academics {{ ! empty($featureUrl) ? 'editorial-page-hero--with-photo' : '' }}" aria-labelledby="academicsTitle">
        @if (! empty($featureUrl))
            <figure class="editorial-page-hero__photo" data-academics-hero-photo>
                <img src="{{ $featureUrl }}" alt="{{ $featureAlt ?? '' }}"
                    @if ($featureWidth && $featureHeight) width="{{ $featureWidth }}" height="{{ $featureHeight }}" @endif
                    loading="eager" decoding="async">
            </figure>
        @endif
        <div class="container editorial-page-hero__layout">
            <div class="editorial-page-hero__copy">
                <span class="editorial-page-hero__index">Academics</span>
                <span class="eyebrow">Learning at Pragyan Pathashala</span>
                <h1 id="academicsTitle">{{ $revision?->title ?: 'Academics' }}</h1>
                @if ($revision?->introduction_markdown)
                    @php
                        $heroIntroduction = $markdown->excerpt((string) $revision->introduction_markdown, 400);
                        $firstSentenceEnd = mb_strpos($heroIntroduction, '. ');
                        $heroIntroduction = $firstSentenceEnd === false
                            ? $markdown->excerpt((string) $revision->introduction_markdown, 180)
                            : mb_substr($heroIntroduction, 0, $firstSentenceEnd + 1);
                    @endphp
                    <p>{{ $heroIntroduction }}</p>
                @else
                    <p>Approved learning-stage information will be shared here once the school has published it.</p>
                @endif
            </div>

            @if ($hasPublishedStages)
                <aside class="editorial-page-hero__aside" aria-label="Published learning stages">
                    <span>Published pathway</span>
                    <ol>
                        @foreach ($revision->academicStages as $stage)
                            <li>
                                @if ($stage->stage_label)
                                    <small>{{ $stage->stage_label }}</small>
                                @endif
                                <strong>{{ $stage->title }}</strong>
                            </li>
                        @endforeach
                    </ol>
                </aside>
            @endif
        </div>
    </section>

    <section class="section academic-editorial-section" aria-label="Academic overview">
        <div class="container">
            @if ($revision)
                <article class="academic-editorial">
                    @if ($revision->introduction_markdown)
                        <div class="academic-editorial__introduction prose prose-slate max-w-none prose-headings:font-bold prose-a:text-blue-800">
                            {!! $markdown->toHtml((string) $revision->introduction_markdown) !!}
                        </div>
                    @endif

                    @if ($revision->academicStages->isNotEmpty())
                        <section id="learning-stages" class="academic-pathway scroll-mt-28" aria-labelledby="learningStagesTitle">
                            <span class="eyebrow">Learning stages</span>
                            <h2 id="learningStagesTitle">Approved programmes and stages</h2>
                            <ol class="academic-pathway__list">
                                @foreach ($revision->academicStages as $stage)
                                    <li id="stage-{{ $stage->id }}" class="academic-pathway__item">
                                        <span class="academic-pathway__number" aria-hidden="true">0{{ $loop->iteration }}</span>
                                        <article>
                                            @if ($stage->stage_label)
                                                <p class="academic-pathway__label">{{ $stage->stage_label }}</p>
                                            @endif
                                            <h3>{{ $stage->title }}</h3>
                                            @if ($stage->description_markdown)
                                                <div class="academic-pathway__description prose prose-slate max-w-none prose-a:text-blue-800">
                                                    {!! $markdown->toHtml((string) $stage->description_markdown) !!}
                                                </div>
                                            @endif
                                            @if (is_array($stage->key_points) && count($stage->key_points) > 0)
                                                <ul class="academic-pathway__points">
                                                    @foreach ($stage->key_points as $point)
                                                        <li><i class="bi bi-check2" aria-hidden="true"></i><span>{{ $point }}</span></li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </article>
                                    </li>
                                @endforeach
                            </ol>
                        </section>
                    @endif

                    @if (is_array($revision->approach_values) && count($revision->approach_values) > 0)
                        <section id="beyond-classroom" class="academic-support scroll-mt-28" aria-labelledby="beyondClassroomTitle">
                            <span class="eyebrow">Beyond the classroom</span>
                            <h2 id="beyondClassroomTitle">Activities and learning support</h2>
                            <ul class="academic-support__list">
                                @foreach ($revision->approach_values as $value)
                                    <li>{{ $value }}</li>
                                @endforeach
                            </ul>
                        </section>
                    @endif

                    @if ($revision->facilities_markdown)
                        <section id="learning-spaces" class="academic-spaces scroll-mt-28" aria-labelledby="learningSpacesTitle">
                            <span class="eyebrow">Learning spaces</span>
                            <h2 id="learningSpacesTitle">Facilities available to learners</h2>
                            <div class="academic-spaces__copy prose prose-slate max-w-none prose-a:text-blue-800">
                                {!! $markdown->toHtml((string) $revision->facilities_markdown) !!}
                            </div>
                        </section>
                    @endif
                </article>
            @else
                <div class="content-empty content-empty--academic">
                    <i class="bi bi-mortarboard text-3xl text-blue-800" aria-hidden="true"></i>
                    <h2 class="mt-4 text-2xl font-extrabold tracking-tight text-slate-900">Academic information is being prepared</h2>
                    <p class="mx-auto mt-3 max-w-xl text-slate-600">The school has not yet published approved public learning stages or programme details. The office can help with current admissions and learning questions.</p>
                    <div class="mt-6 flex flex-wrap justify-center gap-3">
                        <a href="{{ route('public.admissions') }}" class="btn btn-primary">Admissions information <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                        <a href="{{ route('public.contact') }}#visit" class="btn btn-outline">Contact the school office</a>
                    </div>
                </div>
            @endif
        </div>
    </section>
@endsection
