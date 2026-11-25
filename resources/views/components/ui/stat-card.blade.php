@props(['label', 'value', 'icon' => 'bi-graph-up', 'tone' => 'blue'])

@php
    $tones = [
        'blue' => 'bg-blue-50 text-blue-700 ring-1 ring-blue-100',
        'emerald' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100',
        'sky' => 'bg-sky-50 text-sky-700 ring-1 ring-sky-100',
        'amber' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-100',
        'rose' => 'bg-rose-50 text-rose-700 ring-1 ring-rose-100',
        'indigo' => 'bg-indigo-50 text-indigo-700 ring-1 ring-indigo-100',
        'slate' => 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
    ];
    $toneClass = $tones[$tone] ?? $tones['blue'];
@endphp

<div {{ $attributes->merge(['class' => 'ui-stat transition-shadow hover:border-slate-300 hover:shadow-md']) }}>
    <div class="min-w-0">
        <p class="ui-stat-label">{{ $label }}</p>
        <p class="ui-stat-value">{{ $value }}</p>
    </div>
    <span class="ui-stat-icon {{ $toneClass }}"><i class="bi {{ $icon }}"></i></span>
</div>
