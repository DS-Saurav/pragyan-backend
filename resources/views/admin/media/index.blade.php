@extends('layouts.admin')

@section('title', 'Media Library')

@section('content')
    <div class="ui-page">
        <x-ui.page-header title="Media Library"
            subtitle="Prepare approved images for the public website. Originals remain private; only reviewed public derivatives can appear online.">
            <x-slot name="actions">
                <a href="{{ route('home') }}" target="_blank" rel="noopener" class="ui-btn ui-btn-secondary">
                    <i class="bi bi-box-arrow-up-right"></i> Open website
                </a>
            </x-slot>
        </x-ui.page-header>

        @if (session('success'))
            <div class="ui-alert ui-alert-success" role="status">
                <i class="bi bi-check2-circle mt-0.5"></i>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="ui-alert ui-alert-danger" role="alert">
                <i class="bi bi-exclamation-triangle mt-0.5"></i>
                <div>
                    <p class="font-semibold">The media item was not changed.</p>
                    <ul class="mt-1 list-disc pl-5 text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <div class="ui-alert ui-alert-info" role="status">
            <i class="bi bi-shield-check mt-0.5"></i>
            <span>Use meaningful alt text unless an image is truly decorative. Confirm consent before publication where it is required. Withdrawing consent immediately prevents its public derivative from being served.</span>
        </div>

        @if ($canUpload)
            <section class="ui-card mb-6">
                <div class="ui-card-header">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">Upload an image</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Images start as private drafts. JPEG, PNG, WebP, and GIF files up to 8 MB are accepted.</p>
                    </div>
                    <i class="bi bi-cloud-arrow-up text-slate-400"></i>
                </div>

                <form action="{{ route('admin.media.upload') }}" method="POST" enctype="multipart/form-data" class="grid gap-4 p-5 lg:grid-cols-2">
                    @csrf
                    <div class="lg:col-span-2">
                        <label for="image" class="ui-label">Image</label>
                        <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp,image/gif" required class="ui-input">
                    </div>
                    <div>
                        <label for="title" class="ui-label">Internal title</label>
                        <input id="title" name="title" type="text" value="{{ old('title') }}" maxlength="255" class="ui-input" placeholder="e.g. Science lab demonstration">
                    </div>
                    <div>
                        <label for="category" class="ui-label">Category</label>
                        <select id="category" name="category" class="ui-input">
                            <option value="">Choose a category</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category }}" @selected(old('category') === $category)>{{ str($category)->replace('-', ' ')->title() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="lg:col-span-2">
                        <label for="default_alt_text" class="ui-label">Public alt text</label>
                        <input id="default_alt_text" name="default_alt_text" type="text" value="{{ old('default_alt_text') }}" maxlength="255" class="ui-input" placeholder="Describe what the image communicates to visitors">
                        <p class="mt-1 text-xs text-slate-500">Required to publish unless the image is explicitly marked decorative.</p>
                    </div>
                    <div>
                        <label for="photographer_credit" class="ui-label">Photographer credit <span class="font-normal text-slate-500">(optional)</span></label>
                        <input id="photographer_credit" name="photographer_credit" type="text" value="{{ old('photographer_credit') }}" maxlength="255" class="ui-input" placeholder="Name or approved attribution">
                    </div>
                    <label class="mt-6 flex items-start gap-2 text-sm text-slate-700">
                        <input name="is_decorative" type="checkbox" value="1" @checked(old('is_decorative')) class="mt-0.5 rounded border-slate-300 text-emerald-700 focus:ring-emerald-600">
                        <span><span class="font-semibold">Decorative image</span><br><span class="text-xs text-slate-500">Use only when the image adds no information beyond nearby text.</span></span>
                    </label>
                    <div class="lg:col-span-2">
                        <label for="description" class="ui-label">Internal notes <span class="font-normal text-slate-500">(optional)</span></label>
                        <textarea id="description" name="description" rows="3" maxlength="10000" class="ui-input" placeholder="Private editorial context, not public story copy">{{ old('description') }}</textarea>
                    </div>
                    <div class="lg:col-span-2 flex justify-end">
                        <button type="submit" class="ui-btn ui-btn-primary"><i class="bi bi-cloud-arrow-up"></i> Upload private draft</button>
                    </div>
                </form>
            </section>
        @else
            <div class="ui-alert ui-alert-info" role="status">
                <i class="bi bi-eye mt-0.5"></i>
                <span>You can inspect the Media Library, but your account cannot upload or edit images.</span>
            </div>
        @endif

        <section class="ui-card overflow-hidden">
            <div class="ui-card-header">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Editorial media</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Check public usage before taking an image offline. News covers are attached from the News editor so their draft revision is preserved.</p>
                </div>
                <span class="text-xs font-medium text-slate-500">{{ $assets->total() }} {{ str('image')->plural($assets->total()) }}</span>
            </div>

            @forelse ($assets as $asset)
                @php
                    $statusTone = match ($asset->status) {
                        'published' => 'success',
                        'review' => 'warning',
                        default => 'neutral',
                    };
                    $consentTone = match ($asset->consent_state) {
                        'confirmed' => 'success',
                        'withdrawn' => 'danger',
                        default => 'neutral',
                    };
                    $canShowPublic = $asset->status === 'published' && $asset->archived_at === null
                        && $asset->consent_state !== 'withdrawn' && $asset->consent_withdrawn_at === null
                        && $asset->public_path !== null;
                @endphp
                <article class="border-t border-slate-200 p-5 first:border-t-0">
                    <div class="flex flex-col gap-5 xl:flex-row">
                        <div class="flex shrink-0 gap-3 xl:w-48 xl:flex-col">
                            @if ($canShowPublic)
                                <a href="{{ route('media.published', $asset) }}" target="_blank" rel="noopener" class="block overflow-hidden rounded-lg border border-slate-200 bg-slate-100">
                                    <img src="{{ route('media.published', $asset) }}" alt="" class="h-24 w-32 object-cover xl:h-28 xl:w-48">
                                </a>
                            @else
                                <div class="flex h-24 w-32 items-center justify-center rounded-lg border border-dashed border-slate-300 bg-slate-50 text-slate-400 xl:h-28 xl:w-48">
                                    <i class="bi bi-image text-2xl"></i>
                                </div>
                            @endif
                            @if ($canView)
                                <a href="{{ route('admin.media.original', $asset) }}" target="_blank" rel="noopener" class="ui-btn ui-btn-secondary h-8 !px-2 text-xs xl:w-full">
                                    <i class="bi bi-lock"></i> Private original
                                </a>
                            @endif
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-col justify-between gap-3 sm:flex-row">
                                <div class="min-w-0">
                                    <h3 class="truncate text-base font-bold text-slate-900">{{ $asset->title ?: $asset->original_filename }}</h3>
                                    <p class="mt-0.5 text-xs text-slate-500">
                                        {{ $asset->original_filename }} · {{ number_format($asset->file_size / 1024, 1) }} KB · {{ $asset->width }} × {{ $asset->height }} px
                                    </p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <x-ui.badge :tone="$statusTone">{{ str($asset->status)->title() }}</x-ui.badge>
                                    @if ($asset->archived_at)
                                        <x-ui.badge tone="danger">Archived</x-ui.badge>
                                    @endif
                                    <x-ui.badge :tone="$consentTone">Consent: {{ str($asset->consent_state)->replace('_', ' ')->title() }}</x-ui.badge>
                                </div>
                            </div>

                            <dl class="mt-4 grid gap-x-5 gap-y-2 text-sm sm:grid-cols-2 xl:grid-cols-3">
                                <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Alt text</dt><dd class="mt-0.5 text-slate-700">{{ $asset->is_decorative ? 'Decorative image' : ($asset->default_alt_text ?: 'Missing') }}</dd></div>
                                <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Credit</dt><dd class="mt-0.5 text-slate-700">{{ $asset->photographer_credit ?: 'None' }}</dd></div>
                                <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Category</dt><dd class="mt-0.5 text-slate-700">{{ $asset->category ? str($asset->category)->replace('-', ' ')->title() : 'Uncategorised' }}</dd></div>
                                <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Uploaded</dt><dd class="mt-0.5 text-slate-700">{{ $asset->uploader?->name ?? 'Unknown' }} · {{ $asset->created_at?->format('j M Y') }}</dd></div>
                                <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Review</dt><dd class="mt-0.5 text-slate-700">{{ $asset->reviewer?->name ?? 'Not yet reviewed' }}</dd></div>
                                <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Attachments</dt><dd class="mt-0.5 text-slate-700">{{ $asset->attachments_count }} {{ str('attachment')->plural($asset->attachments_count) }}</dd></div>
                            </dl>

                            @if (! empty($usageErrors[$asset->id]))
                                <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                                    <i class="bi bi-exclamation-triangle mr-1"></i> Usage could not be fully verified: {{ $usageErrors[$asset->id] }}
                                </div>
                            @elseif (! empty($publishedUses[$asset->id]))
                                <div class="mt-4 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-900">
                                    <span class="font-semibold">Currently needed by public content:</span>
                                    <ul class="mt-1 list-disc pl-5">
                                        @foreach ($publishedUses[$asset->id] as $use)
                                            <li>{{ $use }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @else
                                <p class="mt-4 text-xs text-slate-500">No current published or scheduled public use was found.</p>
                            @endif

                            <div class="mt-5 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4">
                                @if ($asset->status === 'draft' && $asset->archived_at === null && $canUpload)
                                    <details class="w-full rounded-lg border border-slate-200 bg-slate-50 p-3">
                                        <summary class="cursor-pointer text-sm font-semibold text-slate-700">Edit draft details</summary>
                                        <form action="{{ route('admin.media.metadata', $asset) }}" method="POST" class="mt-4 grid gap-3 md:grid-cols-2">
                                            @csrf
                                            @method('PATCH')
                                            <div>
                                                <label for="title-{{ $asset->id }}" class="ui-label">Internal title</label>
                                                <input id="title-{{ $asset->id }}" name="title" value="{{ $asset->title }}" maxlength="255" class="ui-input">
                                            </div>
                                            <div>
                                                <label for="category-{{ $asset->id }}" class="ui-label">Category</label>
                                                <select id="category-{{ $asset->id }}" name="category" class="ui-input">
                                                    <option value="">Choose a category</option>
                                                    @foreach ($categories as $category)
                                                        <option value="{{ $category }}" @selected($asset->category === $category)>{{ str($category)->replace('-', ' ')->title() }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="md:col-span-2">
                                                <label for="alt-{{ $asset->id }}" class="ui-label">Public alt text</label>
                                                <input id="alt-{{ $asset->id }}" name="default_alt_text" value="{{ $asset->default_alt_text }}" maxlength="255" class="ui-input">
                                            </div>
                                            <div>
                                                <label for="credit-{{ $asset->id }}" class="ui-label">Photographer credit</label>
                                                <input id="credit-{{ $asset->id }}" name="photographer_credit" value="{{ $asset->photographer_credit }}" maxlength="255" class="ui-input">
                                            </div>
                                            <label class="mt-6 flex items-start gap-2 text-sm text-slate-700">
                                                <input name="is_decorative" type="checkbox" value="1" @checked($asset->is_decorative) class="mt-0.5 rounded border-slate-300 text-emerald-700 focus:ring-emerald-600">
                                                <span><span class="font-semibold">Decorative image</span><br><span class="text-xs text-slate-500">No informative alt text is needed.</span></span>
                                            </label>
                                            <div class="md:col-span-2">
                                                <label for="description-{{ $asset->id }}" class="ui-label">Internal notes</label>
                                                <textarea id="description-{{ $asset->id }}" name="description" rows="3" maxlength="10000" class="ui-input">{{ $asset->description }}</textarea>
                                            </div>
                                            <div class="md:col-span-2 flex justify-end"><button class="ui-btn ui-btn-primary" type="submit">Save draft details</button></div>
                                        </form>
                                    </details>
                                @endif

                                @if ($asset->archived_at === null && $canReview && $asset->consent_state !== 'withdrawn')
                                    <form action="{{ route('admin.media.consent', $asset) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="consent_state" value="confirmed">
                                        <button type="submit" class="ui-btn ui-btn-secondary !px-3 !py-1.5 text-xs" @disabled($asset->consent_state === 'confirmed')>Confirm consent</button>
                                    </form>
                                    <form action="{{ route('admin.media.consent', $asset) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="consent_state" value="not_required">
                                        <button type="submit" class="ui-btn ui-btn-secondary !px-3 !py-1.5 text-xs" @disabled($asset->consent_state === 'not_required')>Consent not required</button>
                                    </form>
                                @endif

                                @if ($asset->archived_at === null && $canPublish && $asset->consent_state !== 'withdrawn')
                                    <form action="{{ route('admin.media.consent', $asset) }}" method="POST" onsubmit="return confirm('Withdraw consent? Public News stories using this image may be withdrawn.')">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="consent_state" value="withdrawn">
                                        <button type="submit" class="ui-btn ui-btn-secondary !px-3 !py-1.5 text-xs !text-rose-700">Withdraw consent</button>
                                    </form>
                                @endif

                                @if ($asset->status === 'draft' && $asset->archived_at === null && $canUpload)
                                    <form action="{{ route('admin.media.submit', $asset) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="ui-btn ui-btn-secondary !px-3 !py-1.5 text-xs">Submit for review</button>
                                    </form>
                                @endif
                                @if ($asset->status === 'review' && $asset->archived_at === null && $canReview)
                                    <form action="{{ route('admin.media.approve', $asset) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="ui-btn ui-btn-secondary !px-3 !py-1.5 text-xs">Approve review</button>
                                    </form>
                                @endif
                                @if ($asset->status === 'review' && $asset->archived_at === null && $canPublish)
                                    <form action="{{ route('admin.media.publish', $asset) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="ui-btn ui-btn-primary !px-3 !py-1.5 text-xs">Publish derivative</button>
                                    </form>
                                @endif
                                @if ($asset->status === 'published' && $asset->archived_at === null && $canPublish)
                                    <form action="{{ route('admin.media.draft', $asset) }}" method="POST" onsubmit="return confirm('Return this media item to draft? This is blocked while public content needs it.')">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="ui-btn ui-btn-secondary !px-3 !py-1.5 text-xs">Return to draft</button>
                                    </form>
                                @endif
                                @if ($asset->archived_at === null && $canPublish)
                                    <form action="{{ route('admin.media.archive', $asset) }}" method="POST" onsubmit="return confirm('Archive this media item? This is blocked while public content needs it.')">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="ui-btn ui-btn-secondary !px-3 !py-1.5 text-xs !text-rose-700">Archive</button>
                                    </form>
                                @elseif ($asset->archived_at !== null && $canPublish)
                                    <form action="{{ route('admin.media.restore', $asset) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="ui-btn ui-btn-secondary !px-3 !py-1.5 text-xs">Restore as draft</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                </article>
            @empty
                <div class="p-8">
                    <x-ui.empty-state icon="bi-images" title="No editorial media yet"
                        message="Upload an approved school image to start a private draft. Nothing is shown publicly until it is reviewed and published." />
                </div>
            @endforelse
        </section>

        @if ($assets->hasPages())
            <div class="mt-5">{{ $assets->links() }}</div>
        @endif
    </div>
@endsection
