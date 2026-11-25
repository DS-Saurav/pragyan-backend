@extends('layouts.public', ['title' => 'Our Team', 'metaDescription' => 'Approved public Team profiles from Pragyan Pathashala.'])

@php
    $groupLabels = [
        'leadership' => 'Leadership & coordinators',
        'teaching' => 'Teaching staff',
        'support' => 'Support staff',
    ];
    $groups = collect($groups ?? []);
    $total = $groups->flatten(1)->count();
@endphp

@section('content')
    <section class="page-hero" aria-labelledby="pageTitle">
        <div class="container">
            <span class="hero-index">Team</span>
            <span class="eyebrow">The people behind the learning</span>
            <h1 id="pageTitle">Meet our team</h1>
            <p>Profiles are shared only after the school has approved the information families can see.</p>
        </div>
    </section>

    <section class="section" aria-label="School Team">
        <div class="container">
            @if ($total === 0)
                <div class="mx-auto max-w-2xl rounded-2xl border border-slate-200 bg-white p-7 text-center shadow-sm sm:p-10">
                    <i class="bi bi-people text-3xl text-blue-800" aria-hidden="true"></i>
                    <h2 class="mt-4 text-2xl font-extrabold tracking-tight text-slate-900">Our Team is being prepared</h2>
                    <p class="mx-auto mt-3 max-w-xl text-slate-600">Staff introductions will appear once individual profiles and portrait permissions have been approved.</p>
                    <a href="{{ route('public.contact') }}#visit" class="btn btn-primary mt-6">Contact the school office <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                </div>
            @else
                @foreach ($groupLabels as $groupKey => $groupLabel)
                    @php($people = collect($groups->get($groupKey, [])))
                    @if ($people->isNotEmpty())
                        <section class="mb-12 last:mb-0" aria-labelledby="team-{{ $groupKey }}">
                            <div class="section-head">
                                <div>
                                    <span class="eyebrow">{{ $groupLabel }}</span>
                                    <h2 id="team-{{ $groupKey }}" class="display">{{ $groupLabel }}</h2>
                                </div>
                                <p class="note-line">{{ $people->count() }} {{ \Illuminate\Support\Str::plural('member', $people->count()) }}</p>
                            </div>
                            <div class="team-grid">
                                @foreach ($people as $person)
                                    <article class="team-card">
                                        <div class="photo photo-tall">
                                            @if ($person->portraitUrl)
                                                <img src="{{ $person->portraitUrl }}" alt="{{ $person->portraitAlt }}"
                                                    loading="lazy" decoding="async">
                                            @else
                                                <div class="photo-initials" aria-hidden="true">{{ $person->initials }}</div>
                                            @endif
                                        </div>
                                        <h3>{{ $person->displayName }}</h3>
                                        <p class="team-role">{{ $person->publicRole }}</p>
                                        @if ($person->qualification)
                                            <p class="team-qual">{{ $person->qualification }}</p>
                                        @endif
                                        @if ($person->bioExcerpt !== '')
                                            <div class="team-bio prose prose-slate max-w-none prose-p:my-0 prose-a:text-blue-800">{!! $person->bioHtml !!}</div>
                                        @endif
                                    </article>
                                @endforeach
                            </div>
                        </section>
                    @endif
                @endforeach
            @endif
        </div>
    </section>
@endsection
