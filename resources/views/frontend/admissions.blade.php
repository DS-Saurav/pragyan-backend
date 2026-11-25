@extends('layouts.public', ['title' => 'Admissions', 'metaDescription' => 'Admissions at Pragyan Pathashala — current published admissions information for families.'])

@section('content')
    @php
        $phone = $cycle?->public_phone ?: $identity['phone'];
        $email = $cycle?->public_email ?: $identity['email'];
        $primaryActionLabel = $cycle?->primary_action_label ?: 'Plan a visit';
        $primaryActionUrl = $cycle?->safePrimaryActionUrl() ?: route('public.contact').'#visit';
        $actionIsExternal = str_starts_with($primaryActionUrl, 'http://') || str_starts_with($primaryActionUrl, 'https://');
        $hasDates = $cycle?->display_starts_on !== null || $cycle?->display_ends_on !== null;
        $cycleStatusLabel = $cycle ? ($cycle::PUBLIC_STATUSES[$cycle->public_status] ?? 'Contact us') : null;
        $jumpLinks = $cycle ? array_filter([
            '#current-status' => 'Current status',
            '#process' => $cycle->steps->isNotEmpty() ? 'Process' : null,
            '#requirements' => $cycle->requirements->isNotEmpty() ? 'Requirements' : null,
            '#fees' => $cycle->fee_summary ? 'Fees' : null,
            '#faq' => $cycle->faqs->isNotEmpty() ? 'FAQs' : null,
        ]) : [];
    @endphp

    <section class="editorial-page-hero editorial-page-hero--admissions {{ $heroPhoto ? 'editorial-page-hero--with-photo' : '' }}" aria-labelledby="admissionsTitle">
        @if ($heroPhoto)
            <figure class="editorial-page-hero__photo" data-admissions-hero-photo>
                <img src="{{ $heroPhoto['url'] }}" alt="{{ $heroPhoto['alt'] }}" width="{{ $heroPhoto['width'] }}" height="{{ $heroPhoto['height'] }}"
                    style="object-position: {{ $heroPhoto['focal_x'] }}% {{ $heroPhoto['focal_y'] }}%;" loading="eager" decoding="async">
                @if ($heroPhoto['caption'] !== '')
                    <figcaption>{{ $heroPhoto['caption'] }}</figcaption>
                @endif
            </figure>
        @endif
        <div class="container editorial-page-hero__layout">
            <div class="editorial-page-hero__copy">
                <span class="hero-index">Admissions</span>
                <span class="eyebrow">Joining our school</span>
                <h1 id="admissionsTitle">Admissions information for families</h1>
                <p>Find the school-approved information for the current admissions cycle, or arrange a visit with the school office.</p>
            </div>
            <aside class="editorial-page-hero__aside editorial-page-hero__aside--admissions" aria-label="Current admissions status">
                <span class="editorial-page-hero__aside-label">Current admissions</span>
                @if ($cycle)
                    <strong>{{ $cycle->label }}</strong>
                    <span class="admission-status admission-status-{{ $cycle->public_status }}">{{ $cycleStatusLabel }}</span>
                @else
                    <strong>Contact the school office</strong>
                    <p>The school has not published a current admissions cycle yet.</p>
                @endif
                <a href="#current-status" class="editorial-page-hero__aside-link">View current status <i class="bi bi-arrow-down" aria-hidden="true"></i></a>
            </aside>
        </div>
    </section>

    @if ($cycle)
        <section id="current-status" class="section admissions-status-section admissions-status-section--editorial" aria-labelledby="currentStatusTitle">
            <div class="container">
                <div class="admissions-status-card admissions-status-card--editorial" data-admissions-status>
                    <div class="admissions-status-copy">
                        <span class="eyebrow">Current cycle</span>
                        <h2 id="currentStatusTitle">{{ $cycle->label }}</h2>
                        <p class="admission-status admission-status-{{ $cycle->public_status }}">{{ $cycleStatusLabel }}</p>
                        @if ($cycle->public_intro)
                            <p class="lede">{{ $cycle->public_intro }}</p>
                        @endif
                        @if ($cycle->updated_at)
                            <p class="admissions-updated">Last updated {{ $cycle->updated_at->setTimezone('Asia/Kathmandu')->format('j M Y, g:i A') }} Nepal time</p>
                        @endif
                    </div>
                    <div class="admissions-status-action">
                        <a href="{{ $primaryActionUrl }}" class="btn btn-primary" @if ($actionIsExternal) target="_blank" rel="noopener" @endif>
                            {{ $primaryActionLabel }} <i class="bi bi-arrow-right" aria-hidden="true"></i>
                        </a>
                    </div>
                </div>

                @if ($hasDates || $cycle->grades->isNotEmpty())
                    <div class="admissions-facts admissions-facts--editorial">
                        @if ($hasDates)
                            <div>
                                <dt>Display dates</dt>
                                <dd>
                                    @if ($cycle->display_starts_on && $cycle->display_ends_on)
                                        {{ $cycle->display_starts_on->format('j M Y') }} to {{ $cycle->display_ends_on->format('j M Y') }}
                                    @elseif ($cycle->display_starts_on)
                                        From {{ $cycle->display_starts_on->format('j M Y') }}
                                    @else
                                        Until {{ $cycle->display_ends_on->format('j M Y') }}
                                    @endif
                                </dd>
                            </div>
                        @endif
                        @if ($cycle->grades->isNotEmpty())
                            <div>
                                <dt>Eligible grades</dt>
                                <dd>
                                    <ul class="pill-list" aria-label="Eligible grades">
                                        @foreach ($cycle->grades as $grade)
                                            <li class="pill">{{ $grade->grade }}</li>
                                        @endforeach
                                    </ul>
                                </dd>
                            </div>
                        @endif
                    </div>
                @endif

                @if (count($jumpLinks) > 1)
                    <nav class="admissions-jump-links admissions-jump-links--editorial" aria-label="Admissions page sections">
                        <span>On this page</span>
                        @foreach ($jumpLinks as $target => $label)
                            <a href="{{ $target }}">{{ $label }}</a>
                        @endforeach
                    </nav>
                @endif
            </div>
        </section>

        @if ($cycle->steps->isNotEmpty())
            <section id="process" class="section section-ivory" aria-labelledby="processTitle">
                <div class="container">
                    <div class="section-head">
                        <span class="eyebrow">Admissions process</span>
                        <h2 id="processTitle">What families can expect</h2>
                    </div>
                    <ol class="steps-list steps-list--editorial">
                        @foreach ($cycle->steps as $step)
                            <li class="step-card step-card--editorial">
                                <span class="step-num" aria-hidden="true">{{ $loop->iteration }}</span>
                                <div>
                                    <h3>{{ $step->title }}</h3>
                                    @if ($step->body)
                                        <p>{{ $step->body }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </section>
        @endif

        @if ($cycle->requirements->isNotEmpty())
            <section id="requirements" class="section" aria-labelledby="requirementsTitle">
                <div class="container admissions-content-column">
                    <div class="section-head">
                        <span class="eyebrow">Required documents</span>
                        <h2 id="requirementsTitle">Prepare these approved documents</h2>
                    </div>
                    <ul class="admissions-list admissions-list--editorial">
                        @foreach ($cycle->requirements as $requirement)
                            <li>
                                <i class="bi bi-check2-circle" aria-hidden="true"></i>
                                <span><strong>{{ $requirement->label }}</strong>@if ($requirement->detail)<span>{{ $requirement->detail }}</span>@endif</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endif

        @if ($cycle->fee_summary)
            <section id="fees" class="section admissions-notes admissions-notes--editorial" aria-labelledby="feesTitle">
                <div class="container admissions-notes-inner">
                    <span class="eyebrow">Fees</span>
                    <h2 id="feesTitle">Approved public fee summary</h2>
                    <p class="whitespace-pre-line">{{ $cycle->fee_summary }}</p>
                </div>
            </section>
        @endif

        @if ($cycle->faqs->isNotEmpty())
            <section id="faq" class="section section-ivory" aria-labelledby="faqTitle">
                <div class="container admissions-content-column">
                    <div class="section-head">
                        <span class="eyebrow">Frequently asked questions</span>
                        <h2 id="faqTitle">Helpful answers</h2>
                    </div>
                    <div class="admission-faqs admission-faqs--editorial">
                        @foreach ($cycle->faqs as $faq)
                            <details>
                                <summary>{{ $faq->question }}</summary>
                                <p>{{ $faq->answer }}</p>
                            </details>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif
    @else
        <section id="current-status" class="section admissions-status-section admissions-status-section--editorial" aria-labelledby="currentStatusTitle">
            <div class="container">
                <div class="admissions-status-card admissions-status-card--editorial admissions-status-fallback" data-admissions-status>
                    <div class="admissions-status-copy">
                        <span class="eyebrow">Admissions</span>
                        <h2 id="currentStatusTitle">Contact us for current admissions information</h2>
                        <p class="lede">The school has not published a current admissions cycle yet. Arrange a visit or contact the office for the latest verified information.</p>
                    </div>
                    <div class="admissions-status-action">
                        <a href="{{ route('public.contact') }}#visit" class="btn btn-primary">Plan a visit <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                    </div>
                </div>
            </div>
        </section>
    @endif

    <section class="section admissions-visit admissions-visit--editorial" aria-labelledby="admissionsVisitTitle">
        <div class="container admissions-content-column">
            <div class="contact-band contact-band--editorial">
                <span class="eyebrow">Visit and contact</span>
                <h2 id="admissionsVisitTitle">Talk with the school office</h2>
                <p>Arrange a visit to discuss the current admissions information and ask questions that matter to your family.</p>
                @if ($phone !== '' || $email !== '')
                    <p class="admissions-contact-links">
                        @if ($phone !== '')
                            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}">Call {{ $phone }}</a>
                        @endif
                        @if ($email !== '')
                            <a href="mailto:{{ $email }}">Email {{ $email }}</a>
                        @endif
                    </p>
                @endif
                <a href="{{ route('public.contact') }}#visit" class="text-link">View visit information <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
            </div>
        </div>
    </section>
@endsection
