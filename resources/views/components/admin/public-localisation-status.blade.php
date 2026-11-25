@props([
    'translation' => null,
    'source' => null,
    'revision' => null,
    'href' => null,
    'linkLabel' => 'Open translation',
    'label' => 'Nepali translation',
])

@php
    $publicationLabels = [
        'draft' => 'Draft',
        'review' => 'In review',
        'in_review' => 'In review',
        'approved' => 'Approved',
        'scheduled' => 'Scheduled',
        'published' => 'Published',
        'withdrawn' => 'Withdrawn',
        'archived' => 'Archived',
        'not_started' => 'Not started',
    ];

    $publicationClasses = [
        'draft' => 'ui-badge-neutral',
        'review' => 'ui-badge-warning',
        'in_review' => 'ui-badge-warning',
        'approved' => 'ui-badge-success',
        'scheduled' => 'ui-badge-warning',
        'published' => 'ui-badge-success',
        'withdrawn' => 'ui-badge-warning',
        'archived' => 'ui-badge-neutral',
        'not_started' => 'ui-badge-neutral',
    ];

    $sourceState = strtolower((string) (data_get($source, 'publication_state') ?? 'published'));
    $translationState = strtolower((string) (data_get($translation, 'publication_state') ?? 'not_started'));
    $workflowState = strtolower((string) (data_get($revision, 'workflow_state') ?? data_get($translation, 'workflow_state') ?? ''));
    $sourceTitle = data_get($source, 'title') ?? data_get($source, 'name') ?? data_get($source, 'slug') ?? 'English source';
    $translationRevision = data_get($revision, 'revision_number');
@endphp

<section {{ $attributes->merge(['class' => 'ui-card overflow-hidden']) }} aria-label="English source and Nepali translation status">
    <div class="grid divide-y divide-slate-200 sm:grid-cols-2 sm:divide-x sm:divide-y-0">
        <div class="p-4">
            <p class="ui-section-title">English source</p>
            <p class="mt-2 break-words text-sm font-semibold text-slate-900" style="overflow-wrap:anywhere">
                {{ $sourceTitle }}
            </p>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <span class="ui-badge {{ $publicationClasses[$sourceState] ?? 'ui-badge-neutral' }}">
                    {{ $publicationLabels[$sourceState] ?? ucfirst(str_replace('_', ' ', $sourceState)) }}
                </span>
                <span class="text-xs text-slate-500">Canonical public content</span>
            </div>
        </div>

        <div class="p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="ui-section-title">{{ $label }}</p>
                    <p class="mt-2 text-sm font-semibold text-slate-900" lang="ne">नेपाली</p>
                </div>
                @if ($href)
                    <a href="{{ $href }}" class="ui-btn ui-btn-secondary ui-btn-sm">{{ $linkLabel }}</a>
                @endif
            </div>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <span class="ui-badge {{ $publicationClasses[$translationState] ?? 'ui-badge-neutral' }}">
                    {{ $publicationLabels[$translationState] ?? ucfirst(str_replace('_', ' ', $translationState)) }}
                </span>
                @if ($workflowState !== '' && $workflowState !== $translationState)
                    <span class="text-xs text-slate-500">Revision: {{ $publicationLabels[$workflowState] ?? ucfirst(str_replace('_', ' ', $workflowState)) }}</span>
                @endif
                @if ($translationRevision)
                    <span class="text-xs text-slate-500">Revision {{ $translationRevision }}</span>
                @endif
            </div>
        </div>
    </div>
</section>
