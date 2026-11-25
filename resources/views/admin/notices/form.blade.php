@extends('layouts.admin')

@section('title', $notice->exists ? 'Edit Public Notice' : 'New Public Notice')

@section('content')
    @php
        $isEditing = $notice->exists;
        $user = auth()->user();
        $canManage = $user?->can('notices.manage') ?? false;
        $canAuthor = $canManage || ($user?->can('notices.author') ?? false);
        $canReview = $canManage || ($user?->can('notices.review') ?? false);
        $canPublish = $canManage || ($user?->can('notices.publish') ?? false);
        $noticeDate = old('date', $revision->date?->format('Y-m-d') ?? $revision->date);
        $publicationStart = old('publication_start_at', $revision->publication_start_at?->timezone('Asia/Kathmandu')->format('Y-m-d\\TH:i'));
        $expiry = old('expires_at', $revision->expires_at?->timezone('Asia/Kathmandu')->format('Y-m-d\\TH:i'));
    @endphp

    <div class="ui-page">
        <x-ui.page-header :title="$isEditing ? 'Edit public notice' : 'New public notice'"
            subtitle="Use simple Markdown. Raw HTML and unsafe links are removed before public display.">
            <x-slot name="actions">
                <a href="{{ route('admin.notices.index') }}" class="ui-btn ui-btn-secondary">Back to notices</a>
            </x-slot>
        </x-ui.page-header>

        @if ($isEditing)
            <div class="ui-alert ui-alert-info" role="status">
                <i class="bi bi-info-circle mt-0.5" aria-hidden="true"></i>
                <span>
                    @php
                        $noticeState = $notice->publication_state ?? ($notice->status === 'published' ? 'published' : 'draft');
                    @endphp
                    Notice: <strong>{{ $states[$noticeState] ?? ucfirst($noticeState) }}</strong>.
                    Revision {{ $revision->revision_number ?? '—' }}: <strong>{{ $revisionStates[$revision->workflow_state] ?? ucfirst((string) $revision->workflow_state) }}</strong>.
                    @if ($notice->publication_state === 'published' && $revision->id !== $notice->published_revision_id)
                        This private revision does not change the currently published notice.
                    @endif
                </span>
            </div>
        @endif

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <form action="{{ $isEditing ? route('admin.notices.update', $notice) : route('admin.notices.store') }}" method="POST" class="space-y-6">
                @csrf
                @if ($isEditing)
                    @method('PUT')
                @endif

                <fieldset @disabled(! $canAuthor) class="space-y-6">
                    <section class="ui-card">
                        <div class="ui-card-header">
                            <div>
                                <h2 class="text-sm font-bold text-slate-900">Notice details</h2>
                                <p class="mt-0.5 text-xs text-slate-500">Public-facing details are only made live after review and publication.</p>
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
                                <p class="mt-1 text-xs text-slate-500">Lowercase words and hyphens only. A former public address redirects after an approved change.</p>
                                @error('slug') <p class="ui-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="date" class="ui-label">Notice date <span class="text-rose-600">*</span></label>
                                <input id="date" name="date" type="date" value="{{ $noticeDate }}" required class="ui-input @error('date') ui-input-error @enderror">
                                @error('date') <p class="ui-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="audience" class="ui-label">Audience <span class="text-rose-600">*</span></label>
                                <select id="audience" name="audience" required class="ui-input @error('audience') ui-input-error @enderror">
                                    @foreach (['all' => 'All (legacy public visibility)', 'public' => 'Public website', 'teachers' => 'Teachers', 'parents' => 'Parents', 'students' => 'Students', 'staff' => 'Staff'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('audience', $revision->audience ?? $notice->audience) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-slate-500">Only <strong>all</strong> and <strong>public</strong> are eligible for the public Notice Board.</p>
                                @error('audience') <p class="ui-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="priority" class="ui-label">Priority</label>
                                <input id="priority" name="priority" type="number" min="0" max="999" value="{{ old('priority', $revision->priority) }}" class="ui-input @error('priority') ui-input-error @enderror" placeholder="Optional">
                                <p class="mt-1 text-xs text-slate-500">A higher number can help staff order editorial work; it does not override publication checks.</p>
                                @error('priority') <p class="ui-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="md:col-span-2">
                                <label for="content" class="ui-label">Notice body <span class="text-rose-600">*</span></label>
                                <textarea id="content" name="content" rows="16" required maxlength="30000" class="ui-input font-mono text-sm @error('content') ui-input-error @enderror">{{ old('content', $revision->content) }}</textarea>
                                <p class="mt-1 text-xs text-slate-500">Use simple Markdown for paragraphs, headings, lists and links. Raw HTML is not published.</p>
                                @error('content') <p class="ui-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </section>

                    <section class="ui-card">
                        <div class="ui-card-header">
                            <div>
                                <h2 class="text-sm font-bold text-slate-900">Publication window</h2>
                                <p class="mt-0.5 text-xs text-slate-500">Times are interpreted in Nepal time. An expiry removes the notice from public pages without deleting its record.</p>
                            </div>
                        </div>
                        <div class="grid gap-4 p-5 md:grid-cols-2">
                            <div>
                                <label for="publication_start_at" class="ui-label">Publication start</label>
                                <input id="publication_start_at" name="publication_start_at" type="datetime-local" value="{{ $publicationStart }}" class="ui-input @error('publication_start_at') ui-input-error @enderror">
                                <p class="mt-1 text-xs text-slate-500">Leave blank for a publisher to choose when approving the release.</p>
                                @error('publication_start_at') <p class="ui-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="expires_at" class="ui-label">Expiry</label>
                                <input id="expires_at" name="expires_at" type="datetime-local" value="{{ $expiry }}" class="ui-input @error('expires_at') ui-input-error @enderror">
                                <p class="mt-1 text-xs text-slate-500">Optional. The Notice Board hides expired notices automatically.</p>
                                @error('expires_at') <p class="ui-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="md:col-span-2">
                                <label class="inline-flex items-start gap-3 text-sm font-semibold text-slate-700" for="is_urgent">
                                    <input id="is_urgent" name="is_urgent" type="checkbox" value="1" @checked(old('is_urgent', $revision->is_urgent))
                                        class="mt-0.5 rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                                    <span>
                                        Mark as urgent
                                        <span class="mt-0.5 block text-xs font-normal text-slate-500">Only one active public urgent notice can be published at a time. Urgent notices must use a public audience and respect their publication window.</span>
                                    </span>
                                </label>
                                @error('is_urgent') <p class="ui-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </section>
                </fieldset>

                @if ($canAuthor)
                    <div class="flex flex-wrap gap-3">
                        <button type="submit" class="ui-btn ui-btn-primary"><i class="bi bi-floppy" aria-hidden="true"></i> Save private revision</button>
                        @if ($isEditing && $revision->exists)
                            <a href="{{ route('admin.notices.preview', [$notice, $revision]) }}" class="ui-btn ui-btn-secondary" target="_blank" rel="noopener"><i class="bi bi-eye" aria-hidden="true"></i> Preview</a>
                        @endif
                    </div>
                @endif
            </form>

            @if ($isEditing)
                <aside class="space-y-4">
                    <section class="ui-card p-5">
                        <h2 class="text-sm font-bold text-slate-900">Editorial workflow</h2>
                        <p class="mt-1 text-sm text-slate-600">Each transition records who made it and when.</p>
                        <div class="mt-4 space-y-3">
                            @if ($revision->exists && $revision->workflow_state === 'draft' && $canAuthor)
                                <form action="{{ route('admin.notices.submit', [$notice, $revision]) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="ui-btn ui-btn-secondary w-full">Submit for review</button>
                                </form>
                            @endif
                            @if ($revision->exists && $revision->workflow_state === 'review' && $canReview)
                                <form action="{{ route('admin.notices.approve', [$notice, $revision]) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="ui-btn ui-btn-secondary w-full">Approve revision</button>
                                </form>
                            @endif
                            @if ($revision->exists && $revision->workflow_state === 'approved' && $canPublish)
                                <form action="{{ route('admin.notices.publish', [$notice, $revision]) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="ui-btn ui-btn-primary w-full">Publish now</button>
                                </form>
                                <form action="{{ route('admin.notices.publish', [$notice, $revision]) }}" method="POST" class="space-y-2">
                                    @csrf
                                    @method('PATCH')
                                    <label for="schedule_publication_start" class="ui-label !mb-0">Or schedule (Nepal time)</label>
                                    <input id="schedule_publication_start" type="datetime-local" name="publication_start_at" required class="ui-input text-sm">
                                    <button type="submit" class="ui-btn ui-btn-secondary w-full">Schedule publication</button>
                                </form>
                            @endif
                            @if (($notice->publication_state === 'published' || ($notice->publication_state === null && $notice->status === 'published')) && $canPublish)
                                <form action="{{ route('admin.notices.withdraw', $notice) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="ui-btn ui-btn-secondary w-full">Withdraw notice</button>
                                </form>
                            @endif
                            @if ($notice->publication_state !== 'archived' && $canPublish)
                                <form action="{{ route('admin.notices.archive', $notice) }}" method="POST" onsubmit="return confirm('Archive this notice? It will no longer be publicly available.')">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="ui-btn ui-btn-secondary w-full">Archive notice</button>
                                </form>
                            @elseif ($notice->publication_state === 'archived' && $canPublish)
                                <form action="{{ route('admin.notices.restore', $notice) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="ui-btn ui-btn-secondary w-full">Restore as draft</button>
                                </form>
                            @endif
                        </div>
                    </section>

                    <section class="ui-card p-5">
                        <h2 class="text-sm font-bold text-slate-900">Audit information</h2>
                        <dl class="mt-3 space-y-3 text-sm">
                            <div>
                                <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Created</dt>
                                <dd class="text-slate-700">{{ $revision->created_at?->timezone('Asia/Kathmandu')->format('j M Y, g:i A') ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Last saved</dt>
                                <dd class="text-slate-700">{{ $revision->updated_at?->timezone('Asia/Kathmandu')->format('j M Y, g:i A') ?? '—' }}</dd>
                            </div>
                            @if ($revision->submitted_at)
                                <div>
                                    <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Submitted</dt>
                                    <dd class="text-slate-700">{{ $revision->submitted_at->timezone('Asia/Kathmandu')->format('j M Y, g:i A') }}</dd>
                                </div>
                            @endif
                            @if ($revision->reviewed_at)
                                <div>
                                    <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Reviewed</dt>
                                    <dd class="text-slate-700">{{ $revision->reviewed_at->timezone('Asia/Kathmandu')->format('j M Y, g:i A') }}</dd>
                                </div>
                            @endif
                            @if ($revision->published_at)
                                <div>
                                    <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Published</dt>
                                    <dd class="text-slate-700">{{ $revision->published_at->timezone('Asia/Kathmandu')->format('j M Y, g:i A') }}</dd>
                                </div>
                            @endif
                        </dl>
                    </section>
                </aside>
            @endif
        </div>
    </div>
@endsection
