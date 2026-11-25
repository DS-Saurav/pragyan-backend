@extends('layouts.admin')

@section('title', 'Public Admissions')

@section('content')
    <div class="ui-page">
        <x-ui.page-header title="Public admissions"
            subtitle="Manage the approved admissions information shown to families. Internal applicant records stay separate.">
            <x-slot name="actions">
                <a href="{{ route('admin.public-admissions.create') }}" class="ui-btn ui-btn-primary">
                    <i class="bi bi-plus-lg"></i> New cycle
                </a>
            </x-slot>
        </x-ui.page-header>

        <div class="ui-alert ui-alert-info" role="status">
            <i class="bi bi-shield-check mt-0.5"></i>
            <span>Draft changes stay private until you choose Publish. Archiving preserves historical public content without deleting it.</span>
        </div>

        <section class="ui-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>Cycle</th>
                            <th>Public status</th>
                            <th>Publication</th>
                            <th>Display window</th>
                            <th>Updated</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($cycles as $cycle)
                            @php
                                $statusTone = match ($cycle->public_status) {
                                    'open' => 'success',
                                    'closing_soon' => 'warning',
                                    'closed' => 'neutral',
                                    default => 'info',
                                };
                                $publicationTone = match ($cycle->publication_state) {
                                    'published' => 'success',
                                    'draft' => 'warning',
                                    'withdrawn' => 'danger',
                                    default => 'neutral',
                                };
                            @endphp
                            <tr>
                                <td>
                                    <p class="font-semibold text-slate-900">{{ $cycle->label }}</p>
                                    @if ($cycle->creator)
                                        <p class="mt-0.5 text-xs text-slate-500">Created by {{ $cycle->creator->name }}</p>
                                    @endif
                                </td>
                                <td><x-ui.badge :tone="$statusTone">{{ $cycle::PUBLIC_STATUSES[$cycle->public_status] }}</x-ui.badge></td>
                                <td><x-ui.badge :tone="$publicationTone">{{ $cycle::PUBLICATION_STATES[$cycle->publication_state] }}</x-ui.badge></td>
                                <td class="whitespace-nowrap text-sm text-slate-600">
                                    {{ $cycle->display_starts_on?->format('j M Y') ?? 'No start date' }}
                                    <span class="text-slate-400">to</span>
                                    {{ $cycle->display_ends_on?->format('j M Y') ?? 'No end date' }}
                                </td>
                                <td class="whitespace-nowrap text-sm text-slate-600">{{ $cycle->updated_at?->format('j M Y, g:i A') }}</td>
                                <td class="text-right">
                                    <div class="inline-flex flex-wrap justify-end gap-2">
                                        <a href="{{ route('admin.public-admissions.edit', $cycle) }}" class="ui-btn ui-btn-secondary !px-3 !py-1.5 text-xs">
                                            <i class="bi bi-pencil"></i> Edit
                                        </a>
                                        @can('public_admissions.manage')
                                            <a href="{{ route('admin.public-localisations.content.edit', ['admission_cycle', $cycle]) }}" class="ui-btn ui-btn-secondary !px-3 !py-1.5 text-xs" aria-label="Manage Nepali translation for this admissions cycle">
                                                <i class="bi bi-translate" aria-hidden="true"></i> Nepali
                                            </a>
                                        @endcan
                                        @if ($cycle->publication_state !== 'published' && $cycle->publication_state !== 'archived')
                                            <form action="{{ route('admin.public-admissions.publish', $cycle) }}" method="POST">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="ui-btn ui-btn-primary !px-3 !py-1.5 text-xs">Publish</button>
                                            </form>
                                        @endif
                                        @if ($cycle->publication_state === 'published')
                                            <form action="{{ route('admin.public-admissions.withdraw', $cycle) }}" method="POST">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="ui-btn ui-btn-secondary !px-3 !py-1.5 text-xs">Withdraw</button>
                                            </form>
                                        @endif
                                        @if ($cycle->publication_state !== 'archived')
                                            <form action="{{ route('admin.public-admissions.archive', $cycle) }}" method="POST"
                                                onsubmit="return confirm('Archive this public admissions cycle? It will remain in history and disappear from the website.')">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="ui-btn ui-btn-secondary !px-3 !py-1.5 text-xs">Archive</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10">
                                    <x-ui.empty-state icon="bi-megaphone" title="No public admissions cycle yet"
                                        message="Create a draft when the school has approved the next admissions information." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
