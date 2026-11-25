@extends('layouts.admin')

@section('title', 'Public Notices')

@section('content')
    @php
        $user = auth()->user();
        $canManage = $user?->can('notices.manage') ?? false;
        $canAuthor = $canManage || ($user?->can('notices.author') ?? false);
        $canReview = $canManage || ($user?->can('notices.review') ?? false);
        $canPublish = $canManage || ($user?->can('notices.publish') ?? false);
    @endphp

    <div class="ui-page">
        <x-ui.page-header title="Public notices"
            subtitle="Prepare family-facing notices privately, then review and publish a stable public version.">
            <x-slot name="actions">
                @if ($canAuthor)
                    <a href="{{ route('admin.notices.create') }}" class="ui-btn ui-btn-primary">
                        <i class="bi bi-plus-lg" aria-hidden="true"></i> New notice
                    </a>
                @endif
            </x-slot>
        </x-ui.page-header>

        <div class="ui-alert ui-alert-info" role="status">
            <i class="bi bi-shield-check mt-0.5" aria-hidden="true"></i>
            <span>Editing a live notice creates a private revision. Families continue to see the last published version until a replacement is approved.</span>
        </div>

        <section class="ui-card overflow-hidden" aria-labelledby="noticeTableTitle">
            <div class="sr-only" id="noticeTableTitle">Public notice editorial records</div>
            <div class="overflow-x-auto">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>Notice</th>
                            <th>Public state</th>
                            <th>Audience</th>
                            <th>Publication</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($notices as $notice)
                            @php
                                $live = $notice->publishedRevision;
                                $latest = $notice->revisions->sortByDesc('revision_number')->first();
                                $displayRevision = $live ?? $latest;
                                $displayTitle = $displayRevision?->title ?? $notice->title ?? 'Untitled notice';
                                $state = $notice->publication_state ?? ($notice->status === 'published' ? 'published' : 'draft');
                                $stateTone = match ($state) {
                                    'published' => 'success',
                                    'review', 'scheduled' => 'warning',
                                    'withdrawn' => 'danger',
                                    default => 'neutral',
                                };
                                $stateLabel = $states[$state] ?? ucfirst($state);
                            @endphp
                            <tr>
                                <td>
                                    <p class="font-semibold text-slate-900">{{ $displayTitle }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500">
                                        @if ($notice->slug)
                                            /notices/{{ $notice->slug }}
                                        @else
                                            Public address is created when a public version is published.
                                        @endif
                                        @if ($notice->is_urgent && $state === 'published')
                                            <span class="ml-1 font-semibold text-rose-700">• Urgent</span>
                                        @endif
                                    </p>
                                </td>
                                <td>
                                    <x-ui.badge :tone="$stateTone">{{ $stateLabel }}</x-ui.badge>
                                    @if ($notice->scheduled_for)
                                        <span class="mt-1 block text-xs text-slate-500">Scheduled {{ $notice->scheduled_for->timezone('Asia/Kathmandu')->format('j M Y, g:i A') }}</span>
                                    @endif
                                </td>
                                <td class="text-sm text-slate-600">{{ $live?->audience ?? $latest?->audience ?? $notice->audience }}</td>
                                <td class="text-sm text-slate-600">
                                    @if ($live)
                                        Revision {{ $live->revision_number }}
                                        @if ($notice->published_at)
                                            <span class="block text-xs text-slate-500">Updated {{ $notice->published_at->timezone('Asia/Kathmandu')->format('j M Y, g:i A') }}</span>
                                        @endif
                                    @elseif ($notice->scheduledRevision)
                                        Scheduled revision {{ $notice->scheduledRevision->revision_number }}
                                    @else
                                        <span class="text-slate-500">—</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if ($canAuthor || $canReview || $canPublish)
                                        <div class="inline-flex flex-wrap justify-end gap-2">
                                            <a href="{{ route('admin.notices.edit', $notice) }}" class="ui-btn ui-btn-secondary !px-3 !py-1.5 text-xs">
                                                <i class="bi bi-pencil" aria-hidden="true"></i> Open
                                            </a>
                                            <a href="{{ route('admin.public-localisations.content.edit', ['notice', $notice]) }}" class="ui-btn ui-btn-secondary !px-3 !py-1.5 text-xs" aria-label="Manage Nepali translation for this notice">
                                                <i class="bi bi-translate" aria-hidden="true"></i> Nepali
                                            </a>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-10">
                                    <x-ui.empty-state icon="bi-megaphone" title="No notices yet"
                                        message="Create a private draft when a school update is ready for editorial preparation." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if ($notices->hasPages())
            <div class="mt-5">{{ $notices->links() }}</div>
        @endif
    </div>
@endsection
