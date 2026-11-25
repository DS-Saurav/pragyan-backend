@extends('layouts.admin')

@section('title', $story->exists ? 'Edit News Story' : 'New News Story')

@section('content')
    @php
        $isEditing = $story->exists;
        $cover = $revision->cover;
        $canAuthor = auth()->user()?->can('news.author');
        $canReview = auth()->user()?->can('news.review');
        $canPublish = auth()->user()?->can('news.publish');
        $currentCoverId = old('cover_asset_id', $cover?->media_asset_id);
    @endphp

    <div class="ui-page">
        <x-ui.page-header :title="$isEditing ? 'Edit news story' : 'New news story'"
            subtitle="Draft revisions stay private until a separate reviewer and publisher approve them.">
            <x-slot name="actions">
                <a href="{{ route('admin.news.index') }}" class="ui-btn ui-btn-secondary">Back to news</a>
            </x-slot>
        </x-ui.page-header>

        @if ($isEditing)
            <div class="ui-alert ui-alert-info" role="status">
                <i class="bi bi-info-circle mt-0.5"></i>
                <span>
                    Story: <strong>{{ $states[$story->publication_state] ?? ucfirst($story->publication_state) }}</strong>.
                    Revision {{ $revision->revision_number ?? '—' }}: <strong>{{ $revisionStates[$revision->workflow_state] ?? ucfirst((string) $revision->workflow_state) }}</strong>.
                    @if ($story->publication_state === 'published' && $revision->id !== $story->published_revision_id)
                        This is a private edit; families still see the prior published revision.
                    @endif
                </span>
            </div>
        @endif

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <form action="{{ $isEditing ? route('admin.news.update', $story) : route('admin.news.store') }}" method="POST" class="space-y-6">
                @csrf
                @if ($isEditing) @method('PUT') @endif

                <fieldset @disabled(! $canAuthor) class="space-y-6">
                    <section class="ui-card">
                        <div class="ui-card-header">
                            <div>
                                <h2 class="text-sm font-bold text-slate-900">Story details</h2>
                                <p class="mt-0.5 text-xs text-slate-500">The summary appears in News listings and sharing metadata.</p>
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
                                <p class="mt-1 text-xs text-slate-500">Lowercase words and hyphens only. Old published links redirect safely after an approved change.</p>
                                @error('slug') <p class="ui-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="category" class="ui-label">Category</label>
                                <input id="category" name="category" value="{{ old('category', $revision->category) }}" maxlength="60" class="ui-input @error('category') ui-input-error @enderror" placeholder="e.g. Learning">
                                @error('category') <p class="ui-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="md:col-span-2">
                                <label for="summary" class="ui-label">Summary <span class="text-rose-600">*</span></label>
                                <textarea id="summary" name="summary" rows="3" required maxlength="600" class="ui-input @error('summary') ui-input-error @enderror">{{ old('summary', $revision->summary) }}</textarea>
                                @error('summary') <p class="ui-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="md:col-span-2">
                                <label for="body_markdown" class="ui-label">Story <span class="text-rose-600">*</span></label>
                                <textarea id="body_markdown" name="body_markdown" rows="18" required maxlength="30000" class="ui-input font-mono text-sm @error('body_markdown') ui-input-error @enderror">{{ old('body_markdown', $revision->body_markdown) }}</textarea>
                                <p class="mt-1 text-xs text-slate-500">Use simple Markdown for headings, paragraphs, lists and links. Raw HTML and unsafe links are removed before public display.</p>
                                @error('body_markdown') <p class="ui-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </section>

                    <section class="ui-card">
                        <div class="ui-card-header">
                            <div>
                                <h2 class="text-sm font-bold text-slate-900">Public cover and credit</h2>
                                <p class="mt-0.5 text-xs text-slate-500">Only published, consent-cleared Media Library items are available. A cover is required before publication.</p>
                            </div>
                            @can('media.upload')
                                <a href="{{ route('admin.media.index') }}" class="ui-btn ui-btn-secondary !px-3 !py-1.5 text-xs">Open Media Library</a>
                            @endcan
                        </div>
                        <div class="grid gap-4 p-5 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <label for="cover_asset_id" class="ui-label">Cover image</label>
                                <select id="cover_asset_id" name="cover_asset_id" class="ui-input @error('cover_asset_id') ui-input-error @enderror">
                                    <option value="">Keep the current cover</option>
                                    @foreach ($assets as $asset)
                                        <option value="{{ $asset->id }}" @selected((string) $currentCoverId === (string) $asset->id)>
                                            #{{ $asset->id }} — {{ $asset->title ?: $asset->original_filename }} ({{ $asset->category ?: 'Uncategorised' }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('cover_asset_id') <p class="ui-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="cover_alt_text" class="ui-label">Cover-specific alt text</label>
                                <input id="cover_alt_text" name="cover_alt_text" value="{{ old('cover_alt_text', $cover?->alt_text) }}" maxlength="255" class="ui-input @error('cover_alt_text') ui-input-error @enderror" placeholder="Uses the media default if left blank">
                                @error('cover_alt_text') <p class="ui-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="public_attribution" class="ui-label">Story credit</label>
                                <input id="public_attribution" name="public_attribution" value="{{ old('public_attribution', $revision->public_attribution) }}" maxlength="255" class="ui-input @error('public_attribution') ui-input-error @enderror" placeholder="Optional public byline or source">
                                @error('public_attribution') <p class="ui-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="md:col-span-2">
                                <label for="cover_caption" class="ui-label">Cover caption</label>
                                <textarea id="cover_caption" name="cover_caption" rows="2" maxlength="10000" class="ui-input @error('cover_caption') ui-input-error @enderror">{{ old('cover_caption', $cover?->caption) }}</textarea>
                                @error('cover_caption') <p class="ui-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="cover_focal_x" class="ui-label">Focal point horizontal (%)</label>
                                <input id="cover_focal_x" name="cover_focal_x" value="{{ old('cover_focal_x', $cover?->focal_x ?? 50) }}" type="number" min="0" max="100" step="0.01" class="ui-input @error('cover_focal_x') ui-input-error @enderror">
                                @error('cover_focal_x') <p class="ui-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="cover_focal_y" class="ui-label">Focal point vertical (%)</label>
                                <input id="cover_focal_y" name="cover_focal_y" value="{{ old('cover_focal_y', $cover?->focal_y ?? 50) }}" type="number" min="0" max="100" step="0.01" class="ui-input @error('cover_focal_y') ui-input-error @enderror">
                                @error('cover_focal_y') <p class="ui-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </section>
                </fieldset>

                @if ($canAuthor)
                    <div class="flex flex-wrap gap-3">
                        <button type="submit" class="ui-btn ui-btn-primary"><i class="bi bi-floppy"></i> Save private revision</button>
                        @if ($isEditing && $revision->exists)
                            <a href="{{ route('admin.news.preview', [$story, $revision]) }}" class="ui-btn ui-btn-secondary" target="_blank" rel="noopener"><i class="bi bi-eye"></i> Preview</a>
                        @endif
                    </div>
                @endif
            </form>

            @if ($isEditing)
                <aside class="space-y-4">
                    <section class="ui-card p-5">
                        <h2 class="text-sm font-bold text-slate-900">Editorial workflow</h2>
                        <p class="mt-1 text-sm text-slate-600">Every transition records who made it and when.</p>
                        <div class="mt-4 space-y-3">
                            @if ($revision->exists && $revision->workflow_state === 'draft' && $canAuthor)
                                <form action="{{ route('admin.news.submit', [$story, $revision]) }}" method="POST">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="ui-btn ui-btn-secondary w-full">Submit for review</button>
                                </form>
                            @endif
                            @if ($revision->exists && $revision->workflow_state === 'review' && $canReview)
                                <form action="{{ route('admin.news.approve', [$story, $revision]) }}" method="POST">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="ui-btn ui-btn-secondary w-full">Approve revision</button>
                                </form>
                            @endif
                            @if ($revision->exists && $revision->workflow_state === 'approved' && $canPublish)
                                <form action="{{ route('admin.news.publish', [$story, $revision]) }}" method="POST" class="space-y-2">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="ui-btn ui-btn-primary w-full">Publish now</button>
                                    <label for="scheduled_for" class="ui-label !mb-0">Or schedule (Nepal time)</label>
                                    <input id="scheduled_for" type="datetime-local" name="scheduled_for" class="ui-input text-sm" form="schedule-news-{{ $story->id }}">
                                </form>
                                <form id="schedule-news-{{ $story->id }}" action="{{ route('admin.news.publish', [$story, $revision]) }}" method="POST" class="mt-2">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="ui-btn ui-btn-secondary w-full">Schedule publication</button>
                                </form>
                            @endif
                            @if ($story->publication_state === 'published' && $canPublish)
                                <form action="{{ route('admin.news.withdraw', $story) }}" method="POST">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="ui-btn ui-btn-secondary w-full">Withdraw from public site</button>
                                </form>
                            @endif
                            @if ($story->publication_state !== 'archived' && $canPublish)
                                <form action="{{ route('admin.news.archive', $story) }}" method="POST" onsubmit="return confirm('Archive this news story? It will no longer be publicly available.')">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="ui-btn ui-btn-secondary w-full">Archive story</button>
                                </form>
                            @elseif ($story->publication_state === 'archived' && $canPublish)
                                <form action="{{ route('admin.news.restore', $story) }}" method="POST">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="ui-btn ui-btn-secondary w-full">Restore as draft</button>
                                </form>
                            @endif
                        </div>
                    </section>

                    <section class="ui-card p-5">
                        <h2 class="text-sm font-bold text-slate-900">Publishing notes</h2>
                        <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-slate-600">
                            <li>At least one public-safe cover image is required to publish.</li>
                            <li>Changing a published URL creates a safe redirect only after the replacement is published.</li>
                            <li>Withdrawing media consent automatically removes affected public uses.</li>
                        </ul>
                    </section>
                </aside>
            @endif
        </div>
    </div>
@endsection
