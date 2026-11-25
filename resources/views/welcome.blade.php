@extends('layouts.public', ['metaDescription' => $homeMetaDescription])

@section('content')
    <x-public.hero
        :identity="$identity"
        :photo="$homeHeroPhoto"
        :headline="$heroHeadline"
        :intro="$heroIntro"
        :learningRange="$academicsPreview['learning_range'] ?? null"
        :admissionCycle="$admissionCycle"
        ctaText="Admissions"
        :ctaUrl="route('public.admissions').'#current-status'"
        cta2Text="Plan a visit"
        :cta2Url="route('public.contact').'#visit'"
    />

    <x-public.urgent-notice />

    <x-public.family-updates :cycle="$admissionCycle" :next-event="$nextEvent" :latest-notice="$latestNotice" />

    @if ($homeIntro !== '' || count($highlights) > 0)
        <section class="section home-editorial-intro" aria-labelledby="homeIntroTitle">
            <div class="container">
                <div class="home-editorial-intro__layout">
                    @if ($homeIntro !== '')
                        <div class="home-editorial-intro__copy">
                            <span class="eyebrow">Who we are</span>
                            <h2 id="homeIntroTitle">A school where learning is personal</h2>
                            <p class="lede">{{ $homeIntro }}</p>
                        </div>
                    @endif
                    @if (count($highlights) > 0)
                        <ul class="home-editorial-intro__highlights" aria-label="Learning highlights">
                            @foreach ($highlights as $line)
                                <li><i class="bi bi-check2-circle" aria-hidden="true"></i> {{ $line }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </section>
    @endif

    @if ($academicsPreview !== null)
        <section class="section home-learning-journey" aria-labelledby="academicsPreviewTitle" data-home-academics-preview>
            <div class="container">
                @if ($homeLearningPhoto)
                    <figure class="home-learning-journey__photo" data-home-learning-photo>
                        <img src="{{ $homeLearningPhoto['url'] }}" alt="{{ $homeLearningPhoto['alt'] }}"
                            width="{{ $homeLearningPhoto['width'] }}" height="{{ $homeLearningPhoto['height'] }}"
                            style="object-position: {{ $homeLearningPhoto['focal_x'] }}% {{ $homeLearningPhoto['focal_y'] }}%;"
                            loading="lazy" decoding="async">
                        @if ($homeLearningPhoto['caption'] !== '')
                            <figcaption>{{ $homeLearningPhoto['caption'] }}</figcaption>
                        @endif
                    </figure>
                @endif
                <div class="home-learning-journey__head">
                    <div>
                        <span class="eyebrow">Learning</span>
                        <h2 id="academicsPreviewTitle">{{ $academicsPreview['title'] }}</h2>
                        @if ($academicsPreview['introduction'] !== '')
                            <p class="lede">{{ $academicsPreview['introduction'] }}</p>
                        @endif
                    </div>
                    <a href="{{ route('public.academics') }}" class="btn btn-outline">
                        Explore academics <i class="bi bi-arrow-right" aria-hidden="true"></i>
                    </a>
                </div>

                @if ($academicsPreview['stages'] !== [])
                    <ol class="home-learning-journey__stages" aria-label="Published learning stages">
                        @foreach ($academicsPreview['stages'] as $stage)
                            <li>
                                <article class="home-learning-journey__stage">
                                    <span class="home-learning-journey__number" aria-hidden="true">0{{ $loop->iteration }}</span>
                                    @if ($stage['label'] !== '')
                                        <p class="home-learning-journey__label">{{ $stage['label'] }}</p>
                                    @endif
                                    <h3>{{ $stage['title'] }}</h3>
                                    @if ($stage['description'] !== '')
                                        <p>{{ $stage['description'] }}</p>
                                    @endif
                                </article>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        </section>
    @endif

    @if ($featuredStory && $featuredStory->publishedRevision)
        @php
            $featuredRevision = $featuredStory->publishedRevision;
            $featuredCover = $featuredRevision->cover;
            $featuredAsset = $featuredCover?->asset;
        @endphp
        <section class="section home-featured-story" aria-labelledby="featuredStoryTitle">
            <div class="container">
                <div class="featured-story-card">
                    @if ($featuredAsset?->public_path)
                        <a href="{{ route('public.news.show', $featuredStory->slug) }}" class="featured-story-image" tabindex="-1" aria-hidden="true">
                            <img src="{{ route('media.published', $featuredAsset->id) }}"
                                alt="{{ $featuredCover?->alt_text ?: ($featuredAsset->default_alt_text ?? '') }}"
                                loading="lazy" decoding="async">
                        </a>
                    @endif
                    <div class="featured-story-copy">
                        <span class="eyebrow">Featured story</span>
                        @if ($featuredStory->published_at)
                            <time datetime="{{ $featuredStory->published_at->toDateString() }}">{{ $featuredStory->published_at->format('j M Y') }}</time>
                        @endif
                        <h2 id="featuredStoryTitle">{{ $featuredRevision->title }}</h2>
                        @if ($featuredRevision->summary)
                            <p>{{ $featuredRevision->summary }}</p>
                        @endif
                        <a href="{{ route('public.news.show', $featuredStory->slug) }}" class="btn btn-outline">Read the story <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                    </div>
                </div>
            </div>
        </section>
    @endif

    @if ($featuredAlbum && $featuredAlbum->publishedRevision)
        @php
            $featuredAlbumRevision = $featuredAlbum->publishedRevision;
            $featuredAlbumCover = $featuredAlbumRevision->cover;
            $featuredAlbumAsset = $featuredAlbumCover?->asset;
        @endphp
        <section class="section home-featured-gallery" aria-labelledby="featuredGalleryTitle">
            <div class="container">
                <div class="gallery-featured-card">
                    @if ($featuredAlbumAsset?->public_path)
                        <a href="{{ route('public.gallery.show', $featuredAlbum->slug) }}" class="gallery-featured-image" tabindex="-1" aria-hidden="true">
                            <img src="{{ route('media.published', $featuredAlbumAsset->id) }}"
                                alt="{{ $featuredAlbumCover?->alt_text ?: ($featuredAlbumAsset->default_alt_text ?? '') }}"
                                loading="lazy" decoding="async"
                                style="object-position: {{ $featuredAlbumCover?->focal_x ?? 50 }}% {{ $featuredAlbumCover?->focal_y ?? 50 }}%;">
                        </a>
                    @endif
                    <div class="gallery-featured-copy">
                        <span class="eyebrow">Featured gallery</span>
                        @if ($featuredAlbumRevision->album_date)
                            <time datetime="{{ $featuredAlbumRevision->album_date->toDateString() }}">{{ $featuredAlbumRevision->album_date->format('j M Y') }}</time>
                        @endif
                        <h2 id="featuredGalleryTitle">{{ $featuredAlbumRevision->title }}</h2>
                        @if ($featuredAlbumRevision->summary)
                            <p>{{ $featuredAlbumRevision->summary }}</p>
                        @endif
                        <a href="{{ route('public.gallery.show', $featuredAlbum->slug) }}" class="btn btn-outline">View the album <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                    </div>
                </div>
            </div>
        </section>
    @endif

    @if ($latestNotice || $nextEvent)
        <section class="section home-update-ledger" aria-labelledby="schoolUpdatesTitle">
            <div class="container">
                <div class="home-update-ledger__head">
                    <div class="head-copy">
                        <span class="eyebrow">School updates</span>
                        <h2 id="schoolUpdatesTitle">Dates and notices from the school office</h2>
                    </div>
                </div>
                <div class="home-update-ledger__list">
                    @if ($latestNotice)
                        <article class="home-update-ledger__item">
                            <time class="home-update-ledger__date" datetime="{{ $latestNotice->date }}">
                                <span>{{ \Illuminate\Support\Carbon::parse($latestNotice->date)->format('M') }}</span>
                                <strong>{{ \Illuminate\Support\Carbon::parse($latestNotice->date)->format('j') }}</strong>
                                <small>{{ \Illuminate\Support\Carbon::parse($latestNotice->date)->format('Y') }}</small>
                            </time>
                            <div class="home-update-ledger__copy">
                                <span class="home-update-ledger__type">Latest notice</span>
                                <h3>{{ $latestNotice->title }}</h3>
                                @if ($latestNotice->content)
                                    <p>{{ \Illuminate\Support\Str::limit(strip_tags($latestNotice->content), 180) }}</p>
                                @endif
                            </div>
                            <a href="{{ $latestNotice->slug ? route('public.notices.show', $latestNotice->slug) : route('public.notices') }}" class="text-link">View notice <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                        </article>
                    @endif
                    @if ($nextEvent)
                        <article class="home-update-ledger__item">
                            <time class="home-update-ledger__date" datetime="{{ $nextEvent->date }}">
                                <span>{{ \Illuminate\Support\Carbon::parse($nextEvent->date)->format('M') }}</span>
                                <strong>{{ \Illuminate\Support\Carbon::parse($nextEvent->date)->format('j') }}</strong>
                                <small>{{ \Illuminate\Support\Carbon::parse($nextEvent->date)->format('Y') }}</small>
                            </time>
                            <div class="home-update-ledger__copy">
                                <span class="home-update-ledger__type">Upcoming date</span>
                                <h3>{{ $nextEvent->title }}</h3>
                                @if ($nextEvent->description)
                                    <p>{{ \Illuminate\Support\Str::limit(strip_tags($nextEvent->description), 180) }}</p>
                                @endif
                            </div>
                            <a href="{{ route('public.calendar', ['month' => \Illuminate\Support\Carbon::parse($nextEvent->date)->format('Y-m')]) }}" class="text-link">View calendar <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                        </article>
                    @endif
                </div>
            </div>
        </section>
    @endif

    @if ($leadershipProfile !== null && $leadershipProfile->bioExcerpt !== '')
        <section class="principal-band" aria-labelledby="principalTitle">
            <div class="container principal-band-inner">
                <div class="principal-photo">
                    @if ($leadershipProfile->portraitUrl !== null)
                        <img src="{{ $leadershipProfile->portraitUrl }}" alt="{{ $leadershipProfile->portraitAlt }}" loading="lazy" decoding="async">
                    @else
                        <span class="principal-initials" aria-hidden="true">{{ $leadershipProfile->initials }}</span>
                    @endif
                </div>
                <div class="principal-copy">
                    <span class="eyebrow">Welcome</span>
                    <h2 id="principalTitle">A word from our {{ $leadershipProfile->publicRole }}</h2>
                    <div class="principal-quote">{!! $leadershipProfile->bioHtml !!}</div>
                    <p class="principal-name">
                        <strong>{{ $leadershipProfile->displayName }}</strong>
                        <span> — {{ $leadershipProfile->publicRole }}</span>
                    </p>
                    <a href="{{ route('public.team') }}" class="btn btn-outline btn-sm">Meet the team <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                </div>
            </div>
        </section>
    @endif

    <section class="section home-visit home-visit--editorial" aria-labelledby="homeVisitTitle">
        <div class="container">
            <div class="visit-panel">
                <div>
                    <span class="eyebrow">Plan a visit</span>
                    <h2 id="homeVisitTitle">See whether {{ $identity['name'] }} feels right for your family.</h2>
                    <p>Arrange a visit with the school office to ask questions, understand current admissions information and get to know the school community.</p>
                </div>
                <div class="visit-panel-actions">
                    <a href="{{ route('public.contact') }}#visit" class="btn btn-primary">Plan a visit <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                    @if ($identity['phone'] !== '')
                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $identity['phone']) }}" class="text-link">Call the school <i class="bi bi-telephone" aria-hidden="true"></i></a>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endsection
