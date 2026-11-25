@extends('layouts.admin')

@section('title', 'Public Team')

@section('content')
    <div class="ui-page">
        <x-ui.page-header title="Public Team"
            subtitle="Prepare narrow, consent-safe Team profiles without exposing employment, payroll, or personal staff data.">
            <x-slot name="actions">
                @if ($canAuthor)
                    <a href="{{ route('admin.public-team.create') }}" class="ui-btn ui-btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> New Team profile</a>
                @endif
            </x-slot>
        </x-ui.page-header>

        <div class="ui-alert ui-alert-info" role="status">
            <i class="bi bi-shield-check mt-0.5" aria-hidden="true"></i>
            <span>Public profiles contain only individually approved fields. A linked staff record is used only to verify active employment, and portrait consent can be withdrawn at any time.</span>
        </div>

        <section class="ui-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>Public profile</th>
                            <th>Public state</th>
                            <th>Live revision</th>
                            <th>Updated</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($profiles as $profile)
                            @php
                                $live = $profile->publishedRevision;
                                $latest = $profile->revisions->sortByDesc('revision_number')->first();
                                $stateTone = match ($profile->publication_state) {
                                    'published' => 'success',
                                    'review' => 'warning',
                                    'withdrawn' => 'danger',
                                    default => 'neutral',
                                };
                            @endphp
                            <tr>
                                <td>
                                    <p class="font-semibold text-slate-900">{{ $live?->display_name ?: $latest?->display_name ?: 'Untitled Team profile' }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500">{{ $live?->public_role ?: $latest?->public_role ?: 'Public role not set' }}</p>
                                </td>
                                <td><x-ui.badge :tone="$stateTone">{{ $states[$profile->publication_state] ?? ucfirst($profile->publication_state) }}</x-ui.badge></td>
                                <td class="text-sm text-slate-600">
                                    @if ($live)
                                        Revision {{ $live->revision_number }}
                                        @if ($profile->published_at)<span class="block text-xs text-slate-500">{{ $profile->published_at->timezone('Asia/Kathmandu')->format('j M Y, g:i A') }} Nepal time</span>@endif
                                    @elseif ($profile->scheduledRevision)
                                        Scheduled revision {{ $profile->scheduledRevision->revision_number }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="whitespace-nowrap text-sm text-slate-600">{{ $profile->updated_at?->timezone('Asia/Kathmandu')->format('j M Y, g:i A') }}</td>
                                <td class="text-right">
                                    <div class="inline-flex flex-wrap justify-end gap-2">
                                        <a href="{{ route('admin.public-team.edit', $profile) }}" class="ui-btn ui-btn-secondary !px-3 !py-1.5 text-xs"><i class="bi bi-pencil" aria-hidden="true"></i> Open</a>
                                        @canany(['public_team.author', 'public_team.review', 'public_team.publish'])
                                            <a href="{{ route('admin.public-localisations.content.edit', ['public_team_profile', $profile]) }}" class="ui-btn ui-btn-secondary !px-3 !py-1.5 text-xs" aria-label="Manage Nepali translation for this team profile">
                                                <i class="bi bi-translate" aria-hidden="true"></i> Nepali
                                            </a>
                                        @endcanany
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-10"><x-ui.empty-state icon="bi-people" title="No public Team profiles yet" message="Create a private draft only after the public name, role, biography and consent decisions are ready." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if (method_exists($profiles, 'hasPages') && $profiles->hasPages())
            <div class="mt-5">{{ $profiles->links() }}</div>
        @endif
    </div>
@endsection
