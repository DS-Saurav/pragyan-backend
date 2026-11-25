@extends('layouts.admin')

@section('title', $page->exists ? 'Edit Public Page' : 'New Public Page')

@section('content')
    @php
        $isEditing = $page->exists;
        $feature = $revision->featureImage;
        $canAuthor = $canAuthor ?? auth()->user()?->can('public_pages.author');
        $canReview = $canReview ?? auth()->user()?->can('public_pages.review');
        $canPublish = $canPublish ?? auth()->user()?->can('public_pages.publish');
        $isAcademics = $type === 'academics';
        $stageRows = $revision->academicStages ?? collect();
        $currentFeatureId = old('feature_asset_id', $feature?->media_asset_id);
        $pageLabel = $isAcademics ? 'Academics' : 'About';
    @endphp

    <div class="ui-page">
        <x-ui.page-header :title="$isEditing ? 'Edit '.$pageLabel.' page' : 'New '.$pageLabel.' page'"
            subtitle="Use only school-approved family-facing information. Drafts stay private until reviewed and published.">
            <x-slot name="actions">
                <a href="{{ route('admin.public-pages.index') }}" class="ui-btn ui-btn-secondary">Back to public pages</a>
            </x-slot>
        </x-ui.page-header>

        @if ($isEditing)
            <div class="ui-alert ui-alert-info" role="status">
                <i class="bi bi-info-circle mt-0.5" aria-hidden="true"></i>
                <span>
                    Page: <strong>{{ $states[$page->publication_state] ?? ucfirst($page->publication_state) }}</strong>.
                    Revision {{ $revision->revision_number ?? '—' }}: <strong>{{ $revisionStates[$revision->workflow_state] ?? ucfirst((string) $revision->workflow_state) }}</strong>.
                    @if ($page->publication_state === 'published' && $revision->id !== $page->published_revision_id)
                        Families still see the previous published revision until this replacement is released.
                    @endif
                </span>
            </div>
        @endif

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <form action="{{ $isEditing ? route('admin.public-pages.update', $page) : route('admin.public-pages.store', ['type' => $type]) }}" method="POST" class="space-y-6">
                @csrf
                @if ($isEditing) @method('PUT') @endif

                <fieldset @disabled(! $canAuthor) class="space-y-6">
                    <section class="ui-card">
                        <div class="ui-card-header">
                            <div>
                                <h2 class="text-sm font-bold text-slate-900">Page introduction</h2>
                                <p class="mt-0.5 text-xs text-slate-500">A title and short introduction are required before this page can be published.</p>
                            </div>
                        </div>
                        <div class="space-y-4 p-5">
                            <div>
                                <label for="title" class="ui-label">Public title <span class="text-rose-600">*</span></label>
                                <input id="title" type="text" name="title" value="{{ old('title', $revision->title) }}" maxlength="180" required class="ui-input @error('title') ui-input-error @enderror">
                                @error('title') <p class="ui-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="introduction_markdown" class="ui-label">Introduction <span class="text-rose-600">*</span></label>
                                <textarea id="introduction_markdown" name="introduction_markdown" rows="7" maxlength="50000" class="ui-input font-mono text-sm @error('introduction_markdown') ui-input-error @enderror">{{ old('introduction_markdown', $revision->introduction_markdown) }}</textarea>
                                <p class="mt-1 text-xs text-slate-500">Use simple Markdown. Raw HTML and unsafe links are removed before public display.</p>
                                @error('introduction_markdown') <p class="ui-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </section>

                    @if ($isAcademics)
                        <section class="ui-card">
                            <div class="ui-card-header">
                                <div>
                                    <h2 class="text-sm font-bold text-slate-900">Approved learning stages</h2>
                                    <p class="mt-0.5 text-xs text-slate-500">Only add stages, labels and family-facing descriptions the school has explicitly approved. At least one stage is required before publication.</p>
                                </div>
                            </div>
                            <div class="space-y-6 p-5">
                                @for ($stageIndex = 0; $stageIndex < 6; $stageIndex++)
                                    @php
                                        $stage = $stageRows->get($stageIndex);
                                        $keyPoints = is_array($stage?->key_points) ? $stage->key_points : [];
                                    @endphp
                                    <fieldset class="rounded-xl border border-slate-200 p-4">
                                        <legend class="px-1 text-sm font-bold text-slate-800">Learning stage {{ $stageIndex + 1 }}</legend>
                                        <div class="mt-2 grid gap-4 md:grid-cols-2">
                                            <div>
                                                <label for="stage-{{ $stageIndex }}-title" class="ui-label">Public title</label>
                                                <input id="stage-{{ $stageIndex }}-title" name="stages[{{ $stageIndex }}][title]" value="{{ old('stages.'.$stageIndex.'.title', $stage?->title) }}" maxlength="180" class="ui-input @error('stages.'.$stageIndex.'.title') ui-input-error @enderror">
                                                @error('stages.'.$stageIndex.'.title') <p class="ui-error">{{ $message }}</p> @enderror
                                            </div>
                                            <div>
                                                <label for="stage-{{ $stageIndex }}-label" class="ui-label">Optional grade or stage label</label>
                                                <input id="stage-{{ $stageIndex }}-label" name="stages[{{ $stageIndex }}][stage_label]" value="{{ old('stages.'.$stageIndex.'.stage_label', $stage?->stage_label) }}" maxlength="100" class="ui-input @error('stages.'.$stageIndex.'.stage_label') ui-input-error @enderror">
                                                @error('stages.'.$stageIndex.'.stage_label') <p class="ui-error">{{ $message }}</p> @enderror
                                            </div>
                                            <div class="md:col-span-2">
                                                <label for="stage-{{ $stageIndex }}-description" class="ui-label">Short description</label>
                                                <textarea id="stage-{{ $stageIndex }}-description" name="stages[{{ $stageIndex }}][description_markdown]" rows="3" maxlength="30000" class="ui-input font-mono text-sm @error('stages.'.$stageIndex.'.description_markdown') ui-input-error @enderror">{{ old('stages.'.$stageIndex.'.description_markdown', $stage?->description_markdown) }}</textarea>
                                                @error('stages.'.$stageIndex.'.description_markdown') <p class="ui-error">{{ $message }}</p> @enderror
                                            </div>
                                            <div class="md:col-span-2">
                                                <p class="ui-label">Ordered key points</p>
                                                <div class="grid gap-3 sm:grid-cols-2">
                                                    @for ($pointIndex = 0; $pointIndex < 4; $pointIndex++)
                                                        <input name="stages[{{ $stageIndex }}][key_points][{{ $pointIndex }}]" value="{{ old('stages.'.$stageIndex.'.key_points.'.$pointIndex, $keyPoints[$pointIndex] ?? '') }}" maxlength="400" class="ui-input" placeholder="Optional key point {{ $pointIndex + 1 }}">
                                                    @endfor
                                                </div>
                                            </div>
                                        </div>
                                    </fieldset>
                                @endfor
                            </div>
                        </section>
                    @else
                        <section class="ui-card">
                            <div class="ui-card-header">
                                <div>
                                    <h2 class="text-sm font-bold text-slate-900">Approved About content</h2>
                                    <p class="mt-0.5 text-xs text-slate-500">Every section is optional. Leave anything unapproved blank rather than adding a placeholder claim.</p>
                                </div>
                            </div>
                            <div class="space-y-4 p-5">
                                <div>
                                    <label for="history_markdown" class="ui-label">School introduction or history</label>
                                    <textarea id="history_markdown" name="history_markdown" rows="7" maxlength="50000" class="ui-input font-mono text-sm @error('history_markdown') ui-input-error @enderror">{{ old('history_markdown', $revision->history_markdown) }}</textarea>
                                    @error('history_markdown') <p class="ui-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label for="mission_markdown" class="ui-label">Mission</label>
                                        <textarea id="mission_markdown" name="mission_markdown" rows="5" maxlength="30000" class="ui-input font-mono text-sm @error('mission_markdown') ui-input-error @enderror">{{ old('mission_markdown', $revision->mission_markdown) }}</textarea>
                                        @error('mission_markdown') <p class="ui-error">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label for="vision_markdown" class="ui-label">Vision</label>
                                        <textarea id="vision_markdown" name="vision_markdown" rows="5" maxlength="30000" class="ui-input font-mono text-sm @error('vision_markdown') ui-input-error @enderror">{{ old('vision_markdown', $revision->vision_markdown) }}</textarea>
                                        @error('vision_markdown') <p class="ui-error">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                                <div>
                                    <p class="ui-label">Ordered values or approach statements</p>
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        @for ($valueIndex = 0; $valueIndex < 8; $valueIndex++)
                                            <input name="approach_values[{{ $valueIndex }}]" value="{{ old('approach_values.'.$valueIndex, $revision->approach_values[$valueIndex] ?? '') }}" maxlength="400" class="ui-input" placeholder="Optional statement {{ $valueIndex + 1 }}">
                                        @endfor
                                    </div>
                                </div>
                                <div>
                                    <label for="facilities_markdown" class="ui-label">Approved facilities summary</label>
                                    <textarea id="facilities_markdown" name="facilities_markdown" rows="6" maxlength="50000" class="ui-input font-mono text-sm @error('facilities_markdown') ui-input-error @enderror">{{ old('facilities_markdown', $revision->facilities_markdown) }}</textarea>
                                    @error('facilities_markdown') <p class="ui-error">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </section>
                    @endif

                    <section class="ui-card">
                        <div class="ui-card-header">
                            <div>
                                <h2 class="text-sm font-bold text-slate-900">Optional feature image</h2>
                                <p class="mt-0.5 text-xs text-slate-500">Use only a published, consent-cleared Media Library derivative. Content remains complete without an image.</p>
                            </div>
                            @can('media.upload')
                                <a href="{{ route('admin.media.index') }}" class="ui-btn ui-btn-secondary !px-3 !py-1.5 text-xs">Open Media Library</a>
                            @endcan
                        </div>
                        <div class="grid gap-4 p-5 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <label for="feature_asset_id" class="ui-label">Feature image</label>
                                <select id="feature_asset_id" name="feature_asset_id" class="ui-input @error('feature_asset_id') ui-input-error @enderror">
                                    <option value="">Keep the current image or publish without one</option>
                                    @foreach ($assets as $asset)
                                        <option value="{{ $asset->id }}" @selected((string) $currentFeatureId === (string) $asset->id)>#{{ $asset->id }} — {{ $asset->title ?: $asset->original_filename }} ({{ $asset->category ?: 'Uncategorised' }})</option>
                                    @endforeach
                                </select>
                                @error('feature_asset_id') <p class="ui-error">{{ $message }}</p> @enderror
                                @if ($feature)
                                    <label class="mt-3 flex items-start gap-2 text-sm text-slate-700">
                                        <input type="checkbox" name="remove_feature" value="1" @checked(old('remove_feature')) class="mt-1 rounded border-slate-300 text-blue-700 focus:ring-blue-600">
                                        <span>Remove the current feature image and publish this page without one.</span>
                                    </label>
                                @endif
                            </div>
                            <div>
                                <label for="feature_alt_text" class="ui-label">Page-specific alt text</label>
                                <input id="feature_alt_text" name="feature_alt_text" value="{{ old('feature_alt_text', $feature?->alt_text) }}" maxlength="255" class="ui-input @error('feature_alt_text') ui-input-error @enderror" placeholder="Uses the media default if empty">
                                @error('feature_alt_text') <p class="ui-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="feature_caption" class="ui-label">Caption</label>
                                <input id="feature_caption" name="feature_caption" value="{{ old('feature_caption', $feature?->caption) }}" maxlength="10000" class="ui-input @error('feature_caption') ui-input-error @enderror">
                                @error('feature_caption') <p class="ui-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="feature_focal_x" class="ui-label">Focal point horizontal (%)</label>
                                <input id="feature_focal_x" name="feature_focal_x" type="number" min="0" max="100" step="0.01" value="{{ old('feature_focal_x', $feature?->focal_x ?? 50) }}" class="ui-input">
                            </div>
                            <div>
                                <label for="feature_focal_y" class="ui-label">Focal point vertical (%)</label>
                                <input id="feature_focal_y" name="feature_focal_y" type="number" min="0" max="100" step="0.01" value="{{ old('feature_focal_y', $feature?->focal_y ?? 50) }}" class="ui-input">
                            </div>
                        </div>
                    </section>
                </fieldset>

                @if ($canAuthor)
                    <div class="flex flex-wrap gap-3">
                        <button type="submit" class="ui-btn ui-btn-primary"><i class="bi bi-floppy" aria-hidden="true"></i> Save private revision</button>
                        @if ($isEditing && $revision->exists)
                            <a href="{{ route('admin.public-pages.preview', [$page, $revision]) }}" target="_blank" rel="noopener" class="ui-btn ui-btn-secondary"><i class="bi bi-eye" aria-hidden="true"></i> Preview</a>
                        @endif
                    </div>
                @endif
            </form>

            @if ($isEditing)
                <aside class="space-y-4">
                    <section class="ui-card p-5">
                        <h2 class="text-sm font-bold text-slate-900">Editorial workflow</h2>
                        <p class="mt-1 text-sm text-slate-600">Every action records the responsible staff member and Nepal-time publication boundary.</p>
                        <div class="mt-4 space-y-3">
                            @if ($revision->exists && $revision->workflow_state === 'draft' && $canAuthor)
                                <form action="{{ route('admin.public-pages.submit', [$page, $revision]) }}" method="POST">@csrf @method('PATCH')<button type="submit" class="ui-btn ui-btn-secondary w-full">Submit for review</button></form>
                            @endif
                            @if ($revision->exists && $revision->workflow_state === 'review' && $canReview)
                                <form action="{{ route('admin.public-pages.approve', [$page, $revision]) }}" method="POST">@csrf @method('PATCH')<button type="submit" class="ui-btn ui-btn-secondary w-full">Approve revision</button></form>
                            @endif
                            @if ($revision->exists && $revision->workflow_state === 'approved' && $canPublish)
                                <form action="{{ route('admin.public-pages.publish', [$page, $revision]) }}" method="POST">@csrf @method('PATCH')<button type="submit" class="ui-btn ui-btn-primary w-full">Publish now</button></form>
                                <form action="{{ route('admin.public-pages.publish', [$page, $revision]) }}" method="POST" class="space-y-2">@csrf @method('PATCH')<label for="scheduled_for" class="ui-label !mb-0">Schedule (Nepal time)</label><input id="scheduled_for" name="scheduled_for" type="datetime-local" class="ui-input text-sm"><button type="submit" class="ui-btn ui-btn-secondary w-full">Schedule publication</button></form>
                            @endif
                            @if ($page->scheduled_revision_id !== null && $canPublish)
                                <form action="{{ route('admin.public-pages.cancel-schedule', $page) }}" method="POST">@csrf @method('PATCH')<button type="submit" class="ui-btn ui-btn-secondary w-full">Cancel scheduled publication</button></form>
                            @endif
                            @if ($page->publication_state === 'published' && $canPublish)
                                <form action="{{ route('admin.public-pages.withdraw', $page) }}" method="POST">@csrf @method('PATCH')<button type="submit" class="ui-btn ui-btn-secondary w-full">Withdraw from public site</button></form>
                            @endif
                            @if ($page->publication_state !== 'archived' && $canPublish)
                                <form action="{{ route('admin.public-pages.archive', $page) }}" method="POST" onsubmit="return confirm('Archive this public page? It will no longer be publicly available.')">@csrf @method('PATCH')<button type="submit" class="ui-btn ui-btn-secondary w-full">Archive page</button></form>
                            @elseif ($page->publication_state === 'archived' && $canPublish)
                                <form action="{{ route('admin.public-pages.restore', $page) }}" method="POST">@csrf @method('PATCH')<button type="submit" class="ui-btn ui-btn-secondary w-full">Restore as draft</button></form>
                            @endif
                        </div>
                    </section>
                    <section class="ui-card p-5">
                        <h2 class="text-sm font-bold text-slate-900">Publishing notes</h2>
                        <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-slate-600">
                            <li>Incomplete, withdrawn, and archived revisions never become public.</li>
                            <li>Academics must contain at least one approved public learning stage.</li>
                            <li>Media is optional, consent-checked, and never uses a legacy public-disk file.</li>
                        </ul>
                    </section>
                </aside>
            @endif
        </div>
    </div>
@endsection
