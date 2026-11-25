@extends('layouts.admin')

@section('title', 'Public Gallery')

@section('content')
    <div class="ui-page">
        <x-ui.page-header title="Public gallery"
            subtitle="Prepare school moments privately, then publish only reviewed albums with consent-safe photographs.">
            <x-slot name="actions">
                @can('gallery.author')
                    <a href="{{ route('admin.gallery.create') }}" class="ui-btn ui-btn-primary">
                        <i class="bi bi-plus-lg"></i> New album
                    </a>
                @endcan
            </x-slot>
        </x-ui.page-header>

        <div class="ui-alert ui-alert-info" role="status">
            <i class="bi bi-shield-check mt-0.5"></i>
            <span>Only approved, consent-cleared Media Library photographs can appear in a published Gallery album. A live album remains unchanged while a replacement revision is prepared.</span>
        </div>

        <section class="ui-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>Album</th>
                            <th>Public state</th>
                            <th>Live revision</th>
                            <th>Updated</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($albums as $album)
                            @php
                                $stateTone = match ($album->publication_state) {
                                    'published' => 'success',
                                    'review' => 'warning',
                                    'withdrawn' => 'danger',
                                    default => 'neutral',
                                };
                                $live = $album->publishedRevision;
                            @endphp
                            <tr>
                                <td>
                                    <p class="font-semibold text-slate-900">{{ $live?->title ?? $album->revisions->last()?->title ?? 'Untitled draft' }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500">
                                        {{ $album->slug ?: 'Slug will be chosen on publication' }}
                                        @if ($featuredAlbumId === $album->id)
                                            <span class="ml-1 font-semibold text-blue-700">• Homepage preview</span>
                                        @endif
                                    </p>
                                </td>
                                <td><x-ui.badge :tone="$stateTone">{{ \App\Models\GalleryAlbum::PUBLICATION_STATES[$album->publication_state] ?? ucfirst($album->publication_state) }}</x-ui.badge></td>
                                <td class="text-sm text-slate-600">
                                    @if ($live)
                                        Revision {{ $live->revision_number }}
                                        @if ($album->published_at)
                                            <span class="block text-xs text-slate-500">{{ $album->published_at->format('j M Y, g:i A') }}</span>
                                        @endif
                                    @elseif ($album->scheduledRevision)
                                        Scheduled revision {{ $album->scheduledRevision->revision_number }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="whitespace-nowrap text-sm text-slate-600">{{ $album->updated_at?->format('j M Y, g:i A') }}</td>
                                <td class="text-right">
                                    <div class="inline-flex flex-wrap justify-end gap-2">
                                        <a href="{{ route('admin.gallery.edit', $album) }}" class="ui-btn ui-btn-secondary !px-3 !py-1.5 text-xs">
                                            <i class="bi bi-images"></i> Open
                                        </a>
                                        @canany(['gallery.author', 'gallery.review', 'gallery.publish'])
                                            <a href="{{ route('admin.public-localisations.content.edit', ['gallery_album', $album]) }}" class="ui-btn ui-btn-secondary !px-3 !py-1.5 text-xs" aria-label="Manage Nepali translation for this album">
                                                <i class="bi bi-translate" aria-hidden="true"></i> Nepali
                                            </a>
                                        @endcanany
                                        @can('gallery.publish')
                                            @if ($album->publication_state === 'published' && $featuredAlbumId !== $album->id)
                                                <form action="{{ route('admin.gallery.feature', $album) }}" method="POST">
                                                    @csrf @method('PATCH')
                                                    <button type="submit" class="ui-btn ui-btn-secondary !px-3 !py-1.5 text-xs">Feature</button>
                                                </form>
                                            @elseif ($featuredAlbumId === $album->id)
                                                <form action="{{ route('admin.gallery.unfeature', $album) }}" method="POST">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="ui-btn ui-btn-secondary !px-3 !py-1.5 text-xs">Remove preview</button>
                                                </form>
                                            @endif
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-10">
                                    <x-ui.empty-state icon="bi-images" title="No Gallery albums yet"
                                        message="Create a private album when an event or school moment is ready for editorial preparation." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if ($albums->hasPages())
            <div class="mt-5">{{ $albums->links() }}</div>
        @endif
    </div>
@endsection
