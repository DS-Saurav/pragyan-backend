{{-- A quiet, consistent admissions path for the shared public header. --}}
<div {{ $attributes->merge(['class' => 'auth-actions']) }}>
    <a href="{{ \App\Support\PublicLocaleUrl::route('public.admissions') }}" class="header-admissions"
        aria-label="{{ __('public.admissions') }}">
        <i class="bi bi-mortarboard-fill" aria-hidden="true"></i>
        <span>{{ __('public.admissions') }}</span>
    </a>
</div>
