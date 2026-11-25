@extends('layouts.public', ['title' => 'Contact', 'metaDescription' => 'Contact Pragyan Pathashala — address, phone and email for the school office.'])

@section('content')
    @php
        $identity ??= \App\Support\SiteIdentity::publicProfile();
        $hasVisitDetails = $identity['address'] !== '' || $identity['office_hours'] !== '' || $identity['phone'] !== '' || $identity['email'] !== '' || $identity['location'] !== '' || $identity['directions_url'] !== '' || $identity['visit_instructions'] !== '' || $identity['website_placeholder'] !== '';
        $showSeparateLocation = $identity['location'] !== '' && $identity['location'] !== $identity['address'];
    @endphp

    <section class="editorial-page-hero editorial-page-hero--contact {{ $visitPhoto ? 'editorial-page-hero--with-photo' : '' }}" aria-labelledby="contactTitle">
        @if ($visitPhoto)
            <figure class="editorial-page-hero__photo" data-contact-visit-photo>
                <img src="{{ $visitPhoto['url'] }}" alt="{{ $visitPhoto['alt'] }}" width="{{ $visitPhoto['width'] }}" height="{{ $visitPhoto['height'] }}"
                    style="object-position: {{ $visitPhoto['focal_x'] }}% {{ $visitPhoto['focal_y'] }}%;" loading="eager" decoding="async">
                @if ($visitPhoto['caption'] !== '')
                    <figcaption>{{ $visitPhoto['caption'] }}</figcaption>
                @endif
            </figure>
        @endif
        <div class="container editorial-page-hero__layout">
            <div class="editorial-page-hero__copy">
                <span class="hero-index">Contact</span>
                <span class="eyebrow">Get in touch</span>
                <h1 id="contactTitle">Contact our school office</h1>
                <p>Arrange a visit, ask about current admissions information, or connect with the school office.</p>
            </div>
            <aside class="editorial-page-hero__aside editorial-page-hero__aside--contact" aria-label="School office details">
                <span class="editorial-page-hero__aside-label">School office</span>
                @if ($identity['phone'] !== '')
                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $identity['phone']) }}">{{ $identity['phone'] }}</a>
                @endif
                @if ($identity['office_hours'] !== '')
                    <p>{{ $identity['office_hours'] }}</p>
                @endif
                @if ($identity['address'] !== '')
                    <p>{{ $identity['address'] }}</p>
                @endif
                <a href="#visit" class="editorial-page-hero__aside-link">Plan a visit <i class="bi bi-arrow-down" aria-hidden="true"></i></a>
            </aside>
        </div>
    </section>

    <section id="visit" class="section visit-section visit-section--editorial" aria-labelledby="visitTitle">
        <div class="container">
            <div class="visit-panel visit-panel-contact visit-panel--editorial">
                <div class="visit-panel__copy">
                    <span class="eyebrow">Plan a visit</span>
                    <h2 id="visitTitle">We would be glad to help you arrange a visit.</h2>
                    <p>Use the school office details below to find the school, choose a suitable time, and ask about the information your family needs.</p>
                </div>

                @if ($hasVisitDetails)
                    <dl class="visit-details visit-details--editorial">
                        @if ($identity['address'] !== '')
                            <div>
                                <dt><i class="bi bi-geo-alt" aria-hidden="true"></i> Address</dt>
                                <dd>{{ $identity['address'] }}</dd>
                            </div>
                        @endif
                        @if ($identity['office_hours'] !== '')
                            <div>
                                <dt><i class="bi bi-clock" aria-hidden="true"></i> Office hours</dt>
                                <dd>{{ $identity['office_hours'] }}</dd>
                            </div>
                        @endif
                        @if ($identity['phone'] !== '')
                            <div>
                                <dt><i class="bi bi-telephone" aria-hidden="true"></i> Phone</dt>
                                <dd><a href="tel:{{ preg_replace('/[^0-9+]/', '', $identity['phone']) }}">{{ $identity['phone'] }}</a></dd>
                            </div>
                        @endif
                        @if ($identity['email'] !== '')
                            <div>
                                <dt><i class="bi bi-envelope" aria-hidden="true"></i> Email</dt>
                                <dd><a href="mailto:{{ $identity['email'] }}">{{ $identity['email'] }}</a></dd>
                            </div>
                        @endif
                        @if ($identity['website_placeholder'] !== '')
                            <div>
                                <dt><i class="bi bi-globe" aria-hidden="true"></i> {{ __('public.website_coming_soon') }}</dt>
                                <dd>{{ $identity['website_placeholder'] }}</dd>
                            </div>
                        @endif
                        @if ($showSeparateLocation)
                            <div>
                                <dt><i class="bi bi-pin-map" aria-hidden="true"></i> Location</dt>
                                <dd>{{ $identity['location'] }}</dd>
                            </div>
                        @endif
                        @if ($identity['directions_url'] !== '')
                            <div>
                                <dt><i class="bi bi-signpost-2" aria-hidden="true"></i> Directions</dt>
                                <dd><a href="{{ $identity['directions_url'] }}" target="_blank" rel="noopener">Get directions</a></dd>
                            </div>
                        @endif
                        @if ($identity['visit_instructions'] !== '')
                            <div>
                                <dt><i class="bi bi-info-circle" aria-hidden="true"></i> Visit guidance</dt>
                                <dd>{!! nl2br(e($identity['visit_instructions'])) !!}</dd>
                            </div>
                        @endif
                    </dl>
                @else
                    <p class="visit-details-empty">Verified visit and contact details are being prepared by the school office.</p>
                @endif

                <div class="visit-panel-actions visit-panel-actions--editorial">
                    <a href="{{ route('public.admissions') }}#current-status" class="btn btn-primary">View admissions information <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                </div>
            </div>
        </div>
    </section>
@endsection
