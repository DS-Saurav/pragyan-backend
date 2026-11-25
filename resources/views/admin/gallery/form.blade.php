@extends('layouts.admin')

@section('title', $album->exists ? 'Edit Gallery Album' : 'New Gallery Album')

@section('content')
    @php
        $isEditing = $album->exists;
        $cover = $revision->cover;
        $photos = $revision->photos ?? collect();
        $canAuthor = auth()->user()?->can('gallery.author');
        $canReview = auth()->user()?->can('gallery.review');
        $canPublish = auth()->user()?->can('gallery.publish');
        $canManagePhotos = $canAuthor && $revision->exists && $revision->workflow_state === 'draft';
    @endphp

    <div class="ui-page">
        <x-ui.page-header :title="$isEditing ? 'Edit Gallery album' : 'New Gallery album'"
            subtitle="Organise an event or school moment privately before its photographs are released to families.">
            <x-slot name="actions">
                <a href="{{ route('admin.gallery.index') }}" class="ui-btn ui-btn-secondary">Back to Gallery</a>
            </x-slot>
        </x-ui.page-header>

        @if ($isEditing)
            <div class="ui-alert ui-alert-info" role="status">
                <i class="bi bi-info-circle mt-0.5"></i>
                <span>
                    Album: <strong>{{ $states[$album->publication_state] ?? ucfirst($album->publication_state) }}</strong>.
                    Revision {{ $revision->revision_number ?? '—' }}: <strong>{{ $revisionStates[$revision->workflow_state] ?? ucfirst((string) $revision->workflow_state) }}</strong>.
                    @if ($album->publication_state === 'published' && $revision->id !== $album->published_revision_id)
                        This is a private replacement; families still see the prior published revision.
                    @endif
                </span>
            </div>
        @endif

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <div class="space-y-6">
                <form action="{{ $isEditing ? route('admin.gallery.update', $album) : route('admin.gallery.store') }}" method="POST" class="space-y-6">
                    @csrf
                    @if ($isEditing) @method('PUT') @endif

                    <fieldset @disabled(! $canAuthor) class="space-y-6">
                        <section class="ui-card">
                            <div class="ui-card-header">
                                <div>
                                    <h2 class="text-sm font-bold text-slate-900">Album details</h2>
                                    <p class="mt-0.5 text-xs text-slate-500">The summary, date and category help families understand the school moment shown.</p>
                                </div>
                            </div>
                            <div class="grid gap-4 p-5 md:grid-cols-2">
                                <div class="md:col-span-2">
                                    <label for="title" class="ui-label">Title <span class="text-rose-600">*</span></label>
                                    <input id="title" name="title" value="{{ old('title', $revision->title) }}" required maxlength="180" class="ui-input @error('title') ui-input-error @enderror">
                                    @error('title') <p class="ui-error">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="slug" class="ui-label">Stable web address</label>
                                    <input id="slug" name="slug" value="{{ old('slug', $revision->slug) }}" maxlength="180" pattern="[a-z0-9]+(-[a-z0-9]+)*" class="ui-input @error('slug') ui-input-error @enderror" placeholder="Generated from the title if left blank">
                                    <p class="mt-1 text-xs text-slate-500">Lowercase words and hyphens only. A former published address redirects safely after a replacement is published.</p>
                                    @error('slug') <p class="ui-error">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="album_date" class="ui-label">Event or album date <span class="text-rose-600">*</span></label>
                                    <input id="album_date" name="album_date" type="date" value="{{ old('album_date', $revision->album_date?->toDateString()) }}" required class="ui-input @error('album_date') ui-input-error @enderror">
                                    @error('album_date') <p class="ui-error">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="category" class="ui-label">Category</label>
                                    <input id="category" name="category" value="{{ old('category', $revision->category) }}" maxlength="60" class="ui-input @error('category') ui-input-error @enderror" placeholder="e.g. Learning">
                                    @error('category') <p class="ui-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="md:col-span-2">
                                    <label for="summary" class="ui-label">Summary <span class="text-rose-600">*</span></label>
                                    <textarea id="summary" name="summary" rows="3" required maxlength="500" class="ui-input @error('summary') ui-input-error @enderror">{{ old('summary', $revision->summary) }}</textarea>
                                    @error('summary') <p class="ui-error">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </section>
                    </fieldset>

                    @if ($canAuthor)
                        <button type="submit" class="ui-btn ui-btn-primary"><i class="bi bi-floppy"></i> Save private revision</button>
                    @endif
                </form>

                @if ($isEditing)
                    <section class="ui-card">
                        <div class="ui-card-header">
                            <div>
                                <h2 class="text-sm font-bold text-slate-900">Cover photograph</h2>
                                <p class="mt-0.5 text-xs text-slate-500">The cover is separate from the ordered album photographs and is required before publication.</p>
                            </div>
                            @can('media.upload')
                                <a href="{{ route('admin.media.index') }}" class="ui-btn ui-btn-secondary !px-3 !py-1.5 text-xs">Open Media Library</a>
                            @endcan
                        </div>
                        @if ($cover?->asset?->public_path)
                            <div class="border-b border-slate-200 bg-slate-50 p-5">
                                <img src="{{ route('media.published', $cover->media_asset_id) }}" alt="{{ $cover->alt_text ?: ($cover->asset->default_alt_text ?? '') }}" class="max-h-72 w-full rounded-lg object-cover" style="object-position: {{ $cover->focal_x ?? 50 }}% {{ $cover->focal_y ?? 50 }}%;">
                                <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-600">
                                    <span><strong>Usage:</strong> Gallery album cover · Media #{{ $cover->media_asset_id }}</span>
                                    @can('media.view')
                                        <a href="{{ route('admin.media.index') }}" class="font-semibold text-blue-700 hover:text-blue-800">View Media Library</a>
                                    @endcan
                                </div>
                            </div>
                        @endif
                        @if ($canManagePhotos)
                            <form action="{{ route('admin.gallery.cover', [$album, $revision]) }}" method="POST" class="grid gap-4 p-5 md:grid-cols-2">
                                @csrf @method('PATCH')
                                <div class="md:col-span-2">
                                    <label for="cover_asset_id" class="ui-label">Eligible Media Library image</label>
                                    <select id="cover_asset_id" name="cover_asset_id" required class="ui-input @error('cover_asset_id') ui-input-error @enderror">
                                        <option value="">Choose an approved image</option>
                                        @foreach ($assets as $asset)
                                            <option value="{{ $asset->id }}" @selected((string) old('cover_asset_id', $cover?->media_asset_id) === (string) $asset->id)>
                                                #{{ $asset->id }} — {{ $asset->title ?: $asset->original_filename }} ({{ $asset->category ?: 'Uncategorised' }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('cover_asset_id') <p class="ui-error">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="cover_alt_text" class="ui-label">Cover-specific alt text</label>
                                    <input id="cover_alt_text" name="alt_text" value="{{ old('alt_text', $cover?->alt_text) }}" maxlength="255" class="ui-input @error('alt_text') ui-input-error @enderror" placeholder="Uses the media default if left blank">
                                </div>
                                <div>
                                    <label for="cover_caption" class="ui-label">Cover caption</label>
                                    <input id="cover_caption" name="caption" value="{{ old('caption', $cover?->caption) }}" maxlength="10000" class="ui-input @error('caption') ui-input-error @enderror">
                                </div>
                                <div>
                                    <label for="cover_focal_x" class="ui-label">Focal point horizontal (%)</label>
                                    <input id="cover_focal_x" name="focal_x" value="{{ old('focal_x', $cover?->focal_x ?? 50) }}" type="number" min="0" max="100" step="0.01" class="ui-input">
                                </div>
                                <div>
                                    <label for="cover_focal_y" class="ui-label">Focal point vertical (%)</label>
                                    <input id="cover_focal_y" name="focal_y" value="{{ old('focal_y', $cover?->focal_y ?? 50) }}" type="number" min="0" max="100" step="0.01" class="ui-input">
                                </div>
                                <div class="md:col-span-2">
                                    <button type="submit" class="ui-btn ui-btn-secondary">{{ $cover ? 'Replace cover photograph' : 'Set cover photograph' }}</button>
                                </div>
                            </form>
                        @elseif ($canAuthor)
                            <p class="p-5 text-sm text-slate-600">Save a private draft revision before changing its cover image.</p>
                        @endif
                    </section>

                    <section class="ui-card">
                        <div class="ui-card-header">
                            <div>
                                <h2 class="text-sm font-bold text-slate-900">Ordered album photographs</h2>
                                <p class="mt-0.5 text-xs text-slate-500">At least one ordered photograph is required before an album can be published. These placements become protected while public or scheduled.</p>
                            </div>
                            <span class="text-sm font-semibold text-slate-600">{{ $photos->count() }} {{ \Illuminate\Support\Str::plural('photo', $photos->count()) }}</span>
                        </div>

                        @if ($canManagePhotos)
                            <form action="{{ route('admin.gallery.photos.store', [$album, $revision]) }}" method="POST" class="grid gap-4 border-b border-slate-200 p-5 md:grid-cols-2">
                                @csrf
                                <div class="md:col-span-2">
                                    <label for="photo_asset_id" class="ui-label">Add an approved Media Library image</label>
                                    <select id="photo_asset_id" name="media_asset_id" required class="ui-input @error('media_asset_id') ui-input-error @enderror">
                                        <option value="">Choose an approved image</option>
                                        @foreach ($assets as $asset)
                                            <option value="{{ $asset->id }}">#{{ $asset->id }} — {{ $asset->title ?: $asset->original_filename }} ({{ $asset->category ?: 'Uncategorised' }})</option>
                                        @endforeach
                                    </select>
                                    @error('media_asset_id') <p class="ui-error">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="photo_alt_text" class="ui-label">Photo-specific alt text</label>
                                    <input id="photo_alt_text" name="alt_text" maxlength="255" class="ui-input" placeholder="Uses the media default if left blank">
                                </div>
                                <div>
                                    <label for="photo_caption" class="ui-label">Caption</label>
                                    <input id="photo_caption" name="caption" maxlength="10000" class="ui-input">
                                </div>
                                <div>
                                    <label for="photo_focal_x" class="ui-label">Focal point horizontal (%)</label>
                                    <input id="photo_focal_x" name="focal_x" value="50" type="number" min="0" max="100" step="0.01" class="ui-input">
                                </div>
                                <div>
                                    <label for="photo_focal_y" class="ui-label">Focal point vertical (%)</label>
                                    <input id="photo_focal_y" name="focal_y" value="50" type="number" min="0" max="100" step="0.01" class="ui-input">
                                </div>
                                <div class="md:col-span-2"><button type="submit" class="ui-btn ui-btn-secondary">Add photograph</button></div>
                            </form>
                        @endif

                        <div class="divide-y divide-slate-200">
                            @forelse ($photos as $photo)
                                <article class="grid gap-4 p-5 lg:grid-cols-[10rem_minmax(0,1fr)]">
                                    @if ($photo->asset?->public_path)
                                        <img src="{{ route('media.published', $photo->media_asset_id) }}" alt="{{ $photo->alt_text ?: ($photo->asset?->default_alt_text ?? '') }}" class="h-36 w-full rounded-lg object-cover" style="object-position: {{ $photo->focal_x ?? 50 }}% {{ $photo->focal_y ?? 50 }}%;">
                                    @else
                                        <div class="flex h-36 items-center justify-center rounded-lg bg-slate-100 text-slate-500"><i class="bi bi-image"></i></div>
                                    @endif
                                    <div class="min-w-0">
                                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                            <p class="text-sm font-semibold text-slate-900">Photo {{ $loop->iteration }} <span class="font-normal text-slate-500">· Usage: Gallery album photo · Media #{{ $photo->media_asset_id }}</span></p>
                                            @if ($canManagePhotos)
                                                <form action="{{ route('admin.gallery.photos.destroy', [$album, $revision, $photo]) }}" method="POST" onsubmit="return confirm('Remove this photograph from the private album revision?')">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="ui-btn ui-btn-secondary !px-3 !py-1.5 text-xs">Remove</button>
                                                </form>
                                            @endif
                                        </div>
                                        @if ($canManagePhotos)
                                            <form action="{{ route('admin.gallery.photos.update', [$album, $revision, $photo]) }}" method="POST" class="grid gap-3 md:grid-cols-2">
                                                @csrf @method('PATCH')
                                                <div class="md:col-span-2">
                                                    <label for="photo_asset_{{ $photo->id }}" class="ui-label">Replace image (optional)</label>
                                                    <select id="photo_asset_{{ $photo->id }}" name="media_asset_id" class="ui-input">
                                                        <option value="">Keep current image</option>
                                                        @foreach ($assets as $asset)
                                                            <option value="{{ $asset->id }}" @selected($asset->id === $photo->media_asset_id)>#{{ $asset->id }} — {{ $asset->title ?: $asset->original_filename }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label for="photo_alt_{{ $photo->id }}" class="ui-label">Alt text</label>
                                                    <input id="photo_alt_{{ $photo->id }}" name="alt_text" value="{{ $photo->alt_text }}" maxlength="255" class="ui-input">
                                                </div>
                                                <div>
                                                    <label for="photo_caption_{{ $photo->id }}" class="ui-label">Caption</label>
                                                    <input id="photo_caption_{{ $photo->id }}" name="caption" value="{{ $photo->caption }}" maxlength="10000" class="ui-input">
                                                </div>
                                                <div>
                                                    <label for="photo_focal_x_{{ $photo->id }}" class="ui-label">Focal X (%)</label>
                                                    <input id="photo_focal_x_{{ $photo->id }}" name="focal_x" value="{{ $photo->focal_x ?? 50 }}" type="number" min="0" max="100" step="0.01" class="ui-input">
                                                </div>
                                                <div>
                                                    <label for="photo_focal_y_{{ $photo->id }}" class="ui-label">Focal Y (%)</label>
                                                    <input id="photo_focal_y_{{ $photo->id }}" name="focal_y" value="{{ $photo->focal_y ?? 50 }}" type="number" min="0" max="100" step="0.01" class="ui-input">
                                                </div>
                                                <div class="md:col-span-2"><button type="submit" class="ui-btn ui-btn-secondary !px-3 !py-1.5 text-xs">Save photo changes</button></div>
                                            </form>
                                        @else
                                            <p class="text-sm text-slate-600">{{ $photo->caption ?: 'No public caption.' }}</p>
                                        @endif
                                    </div>
                                </article>
                            @empty
                                <div class="p-5 text-sm text-slate-600">No photographs have been selected for this revision yet.</div>
                            @endforelse
                        </div>

                        @if ($canManagePhotos && $photos->isNotEmpty())
                            <form action="{{ route('admin.gallery.photos.reorder', [$album, $revision]) }}" method="POST" class="border-t border-slate-200 p-5">
                                @csrf @method('PATCH')
                                <label class="ui-label" for="photo_order">Display order</label>
                                <p class="mb-2 text-xs text-slate-500">Enter the current photo IDs in the order they should appear, separated by commas. Every selected photo must appear once.</p>
                                <div class="flex flex-wrap gap-3">
                                    <input id="photo_order" name="photo_order" value="{{ $photos->pluck('id')->implode(',') }}" class="ui-input max-w-xl" aria-describedby="photo-order-help">
                                    <button type="submit" class="ui-btn ui-btn-secondary">Save order</button>
                                </div>
                            </form>
                        @endif
                    </section>
                @endif
            </div>

            @if ($isEditing)
                <aside class="space-y-4">
                    <section class="ui-card p-5">
                        <h2 class="text-sm font-bold text-slate-900">Editorial workflow</h2>
                        <p class="mt-1 text-sm text-slate-600">Every transition records who made it and when.</p>
                        <div class="mt-4 space-y-3">
                            @if ($revision->exists && $revision->workflow_state === 'draft' && $canAuthor)
                                <form action="{{ route('admin.gallery.submit', [$album, $revision]) }}" method="POST">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="ui-btn ui-btn-secondary w-full">Submit for review</button>
                                </form>
                            @endif
                            @if ($revision->exists && $revision->workflow_state === 'review' && $canReview)
                                <form action="{{ route('admin.gallery.approve', [$album, $revision]) }}" method="POST">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="ui-btn ui-btn-secondary w-full">Approve revision</button>
                                </form>
                            @endif
                            @if ($revision->exists && $revision->workflow_state === 'approved' && $canPublish)
                                <form action="{{ route('admin.gallery.publish', [$album, $revision]) }}" method="POST" class="space-y-2">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="ui-btn ui-btn-primary w-full">Publish now</button>
                                    <label for="scheduled_for" class="ui-label !mb-0">Or schedule (Nepal time)</label>
                                    <input id="scheduled_for" type="datetime-local" name="scheduled_for" class="ui-input text-sm">
                                    <button type="submit" class="ui-btn ui-btn-secondary w-full">Schedule publication</button>
                                </form>
                            @endif
                            @if ($album->publication_state === 'published' && $canPublish)
                                <form action="{{ route('admin.gallery.withdraw', $album) }}" method="POST">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="ui-btn ui-btn-secondary w-full">Withdraw from public site</button>
                                </form>
                            @endif
                            @if ($album->publication_state !== 'archived' && $canPublish)
                                <form action="{{ route('admin.gallery.archive', $album) }}" method="POST" onsubmit="return confirm('Archive this Gallery album? It will no longer be publicly available.')">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="ui-btn ui-btn-secondary w-full">Archive album</button>
                                </form>
                            @elseif ($album->publication_state === 'archived' && $canPublish)
                                <form action="{{ route('admin.gallery.restore', $album) }}" method="POST">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="ui-btn ui-btn-secondary w-full">Restore as draft</button>
                                </form>
                            @endif
                            @if ($revision->exists)
                                <a href="{{ route('admin.gallery.preview', [$album, $revision]) }}" class="ui-btn ui-btn-secondary w-full" target="_blank" rel="noopener"><i class="bi bi-eye"></i> Private preview</a>
                            @endif
                        </div>
                    </section>

                    <section class="ui-card p-5">
                        <h2 class="text-sm font-bold text-slate-900">Publication checks</h2>
                        <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-slate-600">
                            <li>One public-safe cover and at least one ordered photo are required.</li>
                            <li>Every public image needs meaningful alt text unless the Media Library item is marked decorative.</li>
                            <li>Withdrawing media consent safely removes affected public uses.</li>
                            <li>Published and scheduled placements are protected from media archive or removal.</li>
                        </ul>
                    </section>
                </aside>
            @endif
        </div>
    </div>
@endsection
