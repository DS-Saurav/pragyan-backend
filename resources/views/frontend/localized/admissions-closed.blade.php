@extends('layouts.public', [
    'title' => $payload['label'] ?? __('public.admissions'),
    'metaDescription' => $payload['public_intro'] ?? $payload['public_status_message'] ?? __('public.admissions'),
])

@section('content')
    @php
        $phone = trim((string) ($source->public_phone ?: ($identity['phone'] ?? '')));
        $faqs = is_array($payload['faqs'] ?? null) ? $payload['faqs'] : [];
    @endphp

    <section class="page-hero {{ $heroPhoto ? 'page-hero--with-photo' : '' }}" aria-labelledby="admissionsTitle">
        @if ($heroPhoto)
            <figure class="page-hero__photo" data-admissions-hero-photo>
                <img src="{{ $heroPhoto['url'] }}" alt="{{ $heroPhoto['alt'] }}" width="{{ $heroPhoto['width'] }}" height="{{ $heroPhoto['height'] }}"
                    style="object-position: {{ $heroPhoto['focal_x'] }}% {{ $heroPhoto['focal_y'] }}%;" loading="eager" decoding="async">
            </figure>
        @endif
        <div class="container">
            <span class="hero-index">{{ __('public.admissions') }}</span>
            <h1 id="admissionsTitle">{{ $payload['label'] ?? __('public.admissions') }}</h1>
            <p>{{ $payload['public_status_message'] ?? '' }}</p>
        </div>
    </section>

    <section id="current-status" class="section admissions-status-section" aria-labelledby="statusTitle">
        <div class="container">
            <div class="admissions-status-card">
                <div class="admissions-status-copy">
                    <span class="eyebrow">हालको अवस्था</span>
                    <h2 id="statusTitle">{{ $payload['public_status_message'] ?? '' }}</h2>
                    @if (filled($payload['public_intro'] ?? null))<p class="lede">{{ $payload['public_intro'] }}</p>@endif
                </div>
                <div class="admissions-status-action">
                    <a href="{{ route('public.ne.contact') }}#visit" class="btn btn-primary">विद्यालय भ्रमणबारे सोध्नुहोस् <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                </div>
            </div>
            @if ($phone !== '')
                <p class="mt-5">कार्यालयमा सम्पर्क: <a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" class="font-semibold text-blue-800 underline">{{ $phone }}</a></p>
            @endif
        </div>
    </section>

    @if ($faqs !== [])
        <section class="section section-ivory" aria-labelledby="faqTitle">
            <div class="container admissions-content-column">
                <h2 id="faqTitle" class="text-2xl font-extrabold text-slate-900">बारम्बार सोधिने प्रश्नहरू</h2>
                <div class="mt-5 space-y-4">
                    @foreach ($faqs as $faq)
                        @if (is_array($faq) && filled($faq['question'] ?? null))
                            <article class="rounded-xl border border-slate-200 bg-white p-5">
                                <h3 class="font-bold text-slate-900">{{ $faq['question'] }}</h3>
                                @if (filled($faq['answer'] ?? null))<p class="mt-2 text-slate-700">{{ $faq['answer'] }}</p>@endif
                            </article>
                        @endif
                    @endforeach
                </div>
            </div>
        </section>
    @endif
@endsection
