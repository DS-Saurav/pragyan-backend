<?php

/**
 * Homepage editorial hero with a consent-safe Media Library photo when selected.
 *
 * Deliberately does not render legacy public-storage hero imagery. The
 * approved Media Library cutover remains a separate, consent-gated step.
 *
 * @param  array<string, mixed>  $identity
 * @param  array<string, mixed>|null  $photo
 * @param  string|null  $headline
 * @param  string|null  $intro
 * @param  string|null  $learningRange
 * @param  \App\Models\AdmissionCycle|null  $admissionCycle
 * @param  string|null  $ctaText
 * @param  string|null  $ctaUrl
 * @param  string|null  $cta2Text
 * @param  string|null  $cta2Url
 */
$identity ??= \App\Support\SiteIdentity::publicProfile();
$photo ??= null;
$headline = trim((string) ($headline ?? '')) ?: $identity['name'];
$intro = trim((string) ($intro ?? '')) ?: $identity['tagline'];
$learningRange = trim((string) ($learningRange ?? ''));
$ctaText = trim((string) ($ctaText ?? '')) ?: 'Admissions';
$ctaUrl ??= route('public.admissions').'#current-status';
$cta2Text = trim((string) ($cta2Text ?? '')) ?: 'Plan a visit';
$cta2Url ??= route('public.contact').'#visit';
$admissionLabel = $admissionCycle?->label ?: 'Admissions information';
$admissionStatus = $admissionCycle
    ? (\App\Models\AdmissionCycle::PUBLIC_STATUSES[$admissionCycle->public_status] ?? 'Contact the school')
    : 'Contact the school';
?>

<section class="home-text-hero {{ $photo ? 'home-text-hero--photo' : '' }}" aria-labelledby="homeHeroTitle" data-home-text-hero>
    @if ($photo)
        <figure class="home-text-hero__photo" data-home-hero-photo>
            <img src="{{ $photo['url'] }}" alt="{{ $photo['alt'] }}" width="{{ $photo['width'] }}" height="{{ $photo['height'] }}"
                style="object-position: {{ $photo['focal_x'] }}% {{ $photo['focal_y'] }}%;"
                loading="eager" fetchpriority="high" decoding="async">
            @if ($photo['caption'] !== '')
                <figcaption>{{ $photo['caption'] }}</figcaption>
            @endif
        </figure>
    @else
        <div class="home-text-hero__art" aria-hidden="true"></div>
    @endif

    <div class="container home-text-hero__layout">
        <div class="home-text-hero__copy">
            <p class="home-text-hero__kicker">{{ $identity['tagline'] }}</p>
            <h1 id="homeHeroTitle">{{ $headline }}</h1>
            @if ($intro !== '')
                <p class="home-text-hero__intro">{{ $intro }}</p>
            @endif
            @if ($learningRange !== '')
                <p class="home-text-hero__range">
                    <span>Learning journey</span>
                    {{ $learningRange }}
                </p>
            @endif
            <div class="home-text-hero__actions">
                @if (trim((string) $ctaText) !== '')
                    <a href="{{ $ctaUrl ?: route('public.admissions') }}" class="btn btn-accent">
                        {{ $ctaText }} <i class="bi bi-arrow-right" aria-hidden="true"></i>
                    </a>
                @endif
                @if (trim((string) $cta2Text) !== '')
                    <a href="{{ $cta2Url ?: route('public.contact').'#visit' }}" class="btn btn-ghost">{{ $cta2Text }}</a>
                @endif
            </div>
        </div>

        <aside class="home-text-hero__status" aria-label="Current admissions status">
            <span class="home-text-hero__status-label">For families</span>
            <p>Admissions</p>
            <strong>{{ $admissionLabel }}</strong>
            <span class="home-text-hero__status-value">{{ $admissionStatus }}</span>
            <a href="{{ route('public.admissions') }}#current-status" class="home-text-hero__status-link">
                View current status <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
            </a>
        </aside>
    </div>
</section>
