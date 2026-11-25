@extends('layouts.admin')

@section('title', 'Public Pages')

@section('content')
    @php
        $pageDefinitions = ['about' => 'About', 'academics' => 'Academics'];
        $pagesByType = collect($pages)->keyBy('type');
    @endphp

    <div class="ui-page">
        <x-ui.page-header title="Public Pages"
            subtitle="Prepare the school-approved About and Academics pages through a separate review and publication workflow." />

        <div class="ui-alert ui-alert-info" role="status">
            <i class="bi bi-shield-check mt-0.5" aria-hidden="true"></i>
            <span>These are fixed public pages, not a general page builder. A live revision stays visible while a replacement is prepared privately.</span>
        </div>

        <section class="ui-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>Page</th>
                            <th>Public state</th>
                            <th>Live revision</th>
                            <th>Updated</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pageDefinitions as $type => $label)
                            @php
                                $page = $pagesByType->get($type);
                                $live = $page?->publishedRevision;
                                $stateTone = match ($page?->publication_state) {
                                    'published' => 'success',
                                    'review' => 'warning',
                                    'withdrawn' => 'danger',
                                    default => 'neutral',
                                };
                            @endphp
                            <tr>
                                <td>
                                    <p class="font-semibold text-slate-900">{{ $label }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500">
                                        {{ $type === 'about' ? 'School introduction, values and approved facilities details.' : 'Approved learning stages and family-facing programme information.' }}
                                    </p>
                                </td>
                                <td>
                                    @if ($page)
                                        <x-ui.badge :tone="$stateTone">{{ $states[$page->publication_state] ?? ucfirst($page->publication_state) }}</x-ui.badge>
                                    @else
                                        <x-ui.badge tone="neutral">Not started</x-ui.badge>
                                    @endif
                                </td>
                                <td class="text-sm text-slate-600">
                                    @if ($live)
                                        Revision {{ $live->revision_number }}
                                        @if ($page->published_at)
                                            <span class="block text-xs text-slate-500">{{ $page->published_at->timezone('Asia/Kathmandu')->format('j M Y, g:i A') }} Nepal time</span>
                                        @endif
                                    @elseif ($page?->scheduledRevision)
                                        Scheduled revision {{ $page->scheduledRevision->revision_number }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="whitespace-nowrap text-sm text-slate-600">{{ $page?->updated_at?->timezone('Asia/Kathmandu')->format('j M Y, g:i A') ?? '—' }}</td>
                                <td class="text-right">
                                    @if ($page)
                                        <div class="inline-flex flex-wrap justify-end gap-2">
                                            <a href="{{ route('admin.public-pages.edit', $page) }}" class="ui-btn ui-btn-secondary !px-3 !py-1.5 text-xs"><i class="bi bi-pencil" aria-hidden="true"></i> Open</a>
                                            @canany(['public_pages.author', 'public_pages.review', 'public_pages.publish'])
                                                <a href="{{ route('admin.public-localisations.content.edit', ['public_page', $page]) }}" class="ui-btn ui-btn-secondary !px-3 !py-1.5 text-xs" aria-label="Manage Nepali translation for {{ $label }}">
                                                    <i class="bi bi-translate" aria-hidden="true"></i> Nepali
                                                </a>
                                            @endcanany
                                        </div>
                                    @elseif ($canAuthor)
                                        <a href="{{ route('admin.public-pages.create', ['type' => $type]) }}" class="ui-btn ui-btn-primary !px-3 !py-1.5 text-xs"><i class="bi bi-plus-lg" aria-hidden="true"></i> Create draft</a>
                                    @else
                                        <span class="text-xs text-slate-500">Author permission required</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
