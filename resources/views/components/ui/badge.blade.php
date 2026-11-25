@props(['tone' => 'neutral'])

@php
    $tones = [
        'neutral' => 'ui-badge-neutral',
        'success' => 'ui-badge-success',
        'warning' => 'ui-badge-warning',
        'danger' => 'ui-badge-danger',
        'info' => 'ui-badge-info',
        'brand' => 'ui-badge-brand',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'ui-badge ' . ($tones[$tone] ?? $tones['neutral'])]) }}>
    {{ $slot }}
</span>
