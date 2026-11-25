@extends('layouts.public', [
    'title' => __('public.contact'),
    'metaDescription' => 'प्रज्ञा पाठशालाको कार्यालय सम्पर्क र भ्रमणसम्बन्धी जानकारी।',
])

@section('content')
    @php
        $address = trim((string) ($payload['address'] ?? ''));
        $location = trim((string) ($payload['location'] ?? ''));
        $hours = trim((string) ($payload['office_hours'] ?? ''));
        $guidance = trim((string) ($payload['visit_instructions'] ?? ''));
        $phone = trim((string) ($identity['phone'] ?? ''));
        $email = trim((string) ($identity['email'] ?? ''));
        $directions = trim((string) ($identity['directions_url'] ?? ''));
        $websitePlaceholder = trim((string) ($identity['website_placeholder'] ?? ''));
        $hasDetails = $address !== '' || $location !== '' || $hours !== '' || $guidance !== '' || $phone !== '' || $email !== '' || $directions !== '' || $websitePlaceholder !== '';
    @endphp

    <section class="page-hero {{ ($visitPhoto ?? null) ? 'page-hero--with-photo' : '' }}" aria-labelledby="contactTitle">
        @if ($visitPhoto ?? null)
            <figure class="page-hero__photo" data-contact-visit-photo>
                <img src="{{ $visitPhoto['url'] }}" alt="{{ $visitPhoto['alt'] }}" width="{{ $visitPhoto['width'] }}" height="{{ $visitPhoto['height'] }}"
                    style="object-position: {{ $visitPhoto['focal_x'] }}% {{ $visitPhoto['focal_y'] }}%;" loading="eager" decoding="async">
            </figure>
        @endif
        <div class="container">
            <span class="hero-index">{{ __('public.contact') }}</span>
            <span class="eyebrow">{{ $payload['name'] ?? __('public.school') }}</span>
            <h1 id="contactTitle">सम्पर्क र विद्यालय भ्रमण</h1>
            @if (filled($payload['tagline'] ?? null))<p>{{ $payload['tagline'] }}</p>@endif
        </div>
    </section>

    <section id="visit" class="section visit-section" aria-labelledby="visitTitle">
        <div class="container">
            <div class="visit-panel visit-panel-contact">
                <div>
                    <span class="eyebrow">विद्यालय भ्रमण</span>
                    <h2 id="visitTitle">भ्रमण मिलाउन विद्यालय कार्यालयमा सम्पर्क गर्नुहोस्।</h2>
                </div>

                @if ($hasDetails)
                    <dl class="visit-details">
                        @if ($address !== '')
                            <div><dt><i class="bi bi-geo-alt" aria-hidden="true"></i> ठेगाना</dt><dd>{{ $address }}</dd></div>
                        @endif
                        @if ($location !== '' && $location !== $address)
                            <div><dt><i class="bi bi-pin-map" aria-hidden="true"></i> स्थान</dt><dd>{{ $location }}</dd></div>
                        @endif
                        @if ($hours !== '')
                            <div><dt><i class="bi bi-clock" aria-hidden="true"></i> कार्यालय समय</dt><dd>{{ $hours }}</dd></div>
                        @endif
                        @if ($phone !== '')
                            <div><dt><i class="bi bi-telephone" aria-hidden="true"></i> फोन</dt><dd><a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}">{{ $phone }}</a></dd></div>
                        @endif
                        @if ($email !== '')
                            <div><dt><i class="bi bi-envelope" aria-hidden="true"></i> इमेल</dt><dd><a href="mailto:{{ $email }}">{{ $email }}</a></dd></div>
                        @endif
                        @if ($websitePlaceholder !== '')
                            <div><dt><i class="bi bi-globe" aria-hidden="true"></i> {{ __('public.website_coming_soon') }}</dt><dd>{{ $websitePlaceholder }}</dd></div>
                        @endif
                        @if ($directions !== '')
                            <div><dt><i class="bi bi-signpost-2" aria-hidden="true"></i> {{ __('public.directions') }}</dt><dd><a href="{{ $directions }}" target="_blank" rel="noopener">नक्सामा हेर्नुहोस्</a></dd></div>
                        @endif
                        @if ($guidance !== '')
                            <div><dt><i class="bi bi-info-circle" aria-hidden="true"></i> भ्रमण जानकारी</dt><dd>{!! nl2br(e($guidance)) !!}</dd></div>
                        @endif
                    </dl>
                @else
                    <p class="visit-details-empty">स्वीकृत सम्पर्क जानकारी तयार हुँदैछ।</p>
                @endif

                <div class="visit-panel-actions">
                    <a href="{{ route('public.ne.admissions') }}" class="btn btn-primary">भर्नासम्बन्धी जानकारी <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                </div>
            </div>
        </div>
    </section>
@endsection
