@extends('layouts.admin')

@section('title', 'Public News')

@section('content')
    <div class="ui-page">
        <x-ui.page-header title="Public news"
            subtitle="Write, review and publish school stories without exposing unfinished revisions to families.">
            <x-slot name="actions">
                @can('news.author')
                    <a href="{{ route('admin.news.create') }}" class="ui-btn ui-btn-primary">
                        <i class="bi bi-plus-lg"></i> New story
                    </a>
                @endcan
            </x-slot>
        </x-ui.page-header>

        <div class="ui-alert ui-alert-info" role="status">
            <i class="bi bi-shield-check mt-0.5"></i>
            <span>Only an approved revision with an approved public cover can be published. Editing a live story creates a separate private revision.</span>
        </div>

        <section class="ui-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>Story</th>
                            <th>Public state</th>
                            <th>Live revision</th>
                            <th>Updated</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($stories as $story)
                            @php
                                $stateTone = match ($story->publication_state) {
                                    'published' => 'success',
                                    'review' => 'warning',
                                    'withdrawn' => 'danger',
                                    default => 'neutral',
                                };
                                $live = $story->publishedRevision;
                            @endphp
                            <tr>
                                <td>
                                    <p class="font-semibold text-slate-900">{{ $live?->title ?? $story->revisions->last()?->title ?? 'Untitled draft' }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500">
                                        {{ $story->slug ?: 'Slug will be chosen on publication' }}
                                        @if ($featuredStoryId === $story->id)
                                            <span class="ml-1 font-semibold text-blue-700">• Homepage feature</span>
                                        @endif
                                    </p>
                                </td>
                                <td><x-ui.badge :tone="$stateTone">{{ \App\Models\NewsStory::PUBLICATION_STATES[$story->publication_state] ?? ucfirst($story->publication_state) }}</x-ui.badge></td>
                                <td class="text-sm text-slate-600">
                                    @if ($live)
                                        Revision {{ $live->revision_number }}
                                        @if ($story->published_at)
                                            <span class="block text-xs text-slate-500">{{ $story->published_at->format('j M Y, g:i A') }}</span>
                                        @endif
                                    @elseif ($story->scheduledRevision)
                                        Scheduled revision {{ $story->scheduledRevision->revision_number }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="whitespace-nowrap text-sm text-slate-600">{{ $story->updated_at?->format('j M Y, g:i A') }}</td>
                                <td class="text-right">
                                    <div class="inline-flex flex-wrap justify-end gap-2">
                                        <a href="{{ route('admin.news.edit', $story) }}" class="ui-btn ui-btn-secondary !px-3 !py-1.5 text-xs">
                                            <i class="bi bi-pencil"></i> Open
                                        </a>
                                        @canany(['news.author', 'news.review', 'news.publish'])
                                            <a href="{{ route('admin.public-localisations.content.edit', ['news_story', $story]) }}" class="ui-btn ui-btn-secondary !px-3 !py-1.5 text-xs" aria-label="Manage Nepali translation for this story">
                                                <i class="bi bi-translate" aria-hidden="true"></i> Nepali
                                            </a>
                                        @endcanany
                                        @can('news.publish')
                                            @if ($story->publication_state === 'published' && $featuredStoryId !== $story->id)
                                                <form action="{{ route('admin.news.feature', $story) }}" method="POST">
                                                    @csrf @method('PATCH')
                                                    <button type="submit" class="ui-btn ui-btn-secondary !px-3 !py-1.5 text-xs">Feature</button>
                                                </form>
                                            @elseif ($featuredStoryId === $story->id)
                                                <form action="{{ route('admin.news.unfeature', $story) }}" method="POST">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="ui-btn ui-btn-secondary !px-3 !py-1.5 text-xs">Remove feature</button>
                                                </form>
                                            @endif
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-10">
                                    <x-ui.empty-state icon="bi-newspaper" title="No news stories yet"
                                        message="Create a private draft when a story has been approved for editorial preparation." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if ($stories->hasPages())
            <div class="mt-5">{{ $stories->links() }}</div>
        @endif
    </div>
@endsection
