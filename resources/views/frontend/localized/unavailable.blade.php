@extends('layouts.public', [
    'title' => __('public.unavailable.heading'),
    'metaDescription' => $metaDescription ?? __('public.unavailable.message'),
])

@section('content')
    <section class="page-hero" aria-labelledby="neUnavailableTitle">
        <div class="container">
            <span class="hero-index" lang="ne">नेपाली</span>
            <span class="eyebrow">{{ __('public.unavailable.eyebrow') }}</span>
            <h1 id="neUnavailableTitle">{{ __('public.unavailable.heading') }}</h1>
        </div>
    </section>

    <section class="section" aria-label="{{ __('public.unavailable.heading') }}">
        <div class="container">
            <div class="news-empty mx-auto max-w-2xl">
                <i class="bi bi-translate" aria-hidden="true"></i>
                <p>{{ __('public.unavailable.message') }}</p>
                <a href="{{ $englishUrl }}" class="btn btn-primary" lang="en">{{ __('public.unavailable.view_english') }}</a>
            </div>
        </div>
    </section>
@endsection
