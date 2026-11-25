@extends('layouts.admin')

@section('title', $profile->exists ? 'Edit Public Team Profile' : 'New Public Team Profile')

@section('content')
    @php
        $isEditing = $profile->exists;
        $portrait = $revision->portrait;
        $canAuthor = $canAuthor ?? auth()->user()?->can('public_team.author');
        $canReview = $canReview ?? auth()->user()?->can('public_team.review');
        $canPublish = $canPublish ?? auth()->user()?->can('public_team.publish');
        $currentPortraitId = old('portrait_asset_id', $portrait?->media_asset_id);
        $groups = \App\Models\PublicTeamProfile::TEAM_GROUPS;
    @endphp

    <div class="ui-page">
        <x-ui.page-header :title="$isEditing ? 'Edit public Team profile' : 'New public Team profile'"
            subtitle="This editor publishes only a narrow approved profile. It does not expose legacy staff photos or internal employment records.">
            <x-slot name="actions"><a href="{{ route('admin.public-team.index') }}" class="ui-btn ui-btn-secondary">Back to public Team</a></x-slot>
        </x-ui.page-header>

        @if ($isEditing)
            <div class="ui-alert ui-alert-info" role="status">
                <i class="bi bi-info-circle mt-0.5" aria-hidden="true"></i>
                <span>
                    Profile: <strong>{{ $states[$profile->publication_state] ?? ucfirst($profile->publication_state) }}</strong>.
                    Revision {{ $revision->revision_number ?? '—' }}: <strong>{{ $revisionStates[$revision->workflow_state] ?? ucfirst((string) $revision->workflow_state) }}</strong>.
                    @if ($profile->publication_state === 'published' && $revision->id !== $profile->published_revision_id)
                        Families still see the prior approved public profile until this replacement is published.
                    @endif
                </span>
            </div>
        @endif

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <form action="{{ $isEditing ? route('admin.public-team.update', $profile) : route('admin.public-team.store') }}" method="POST" class="space-y-6">
                @csrf
                @if ($isEditing) @method('PUT') @endif

                <fieldset @disabled(! $canAuthor) class="space-y-6">
                    <section class="ui-card">
                        <div class="ui-card-header">
                            <div>
                                <h2 class="text-sm font-bold text-slate-900">Approved public profile</h2>
                                <p class="mt-0.5 text-xs text-slate-500">Select only an active staff identity for eligibility. The public page receives none of the staff record’s internal fields.</p>
                            </div>
                        </div>
                        <div class="grid gap-4 p-5 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <label for="staff_member_id" class="ui-label">Linked active staff member <span class="text-rose-600">*</span></label>
                                <select id="staff_member_id" name="staff_member_id" class="ui-input @error('staff_member_id') ui-input-error @enderror">
                                    <option value="">Choose an active staff member</option>
                                    @foreach ($staffOptions as $staffOption)
                                        <option value="{{ $staffOption->id }}" @selected((string) old('staff_member_id', $revision->staff_member_id) === (string) $staffOption->id)>{{ $staffOption->name }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-slate-500">The linked record is used only to hide this profile if employment is no longer active.</p>
                                @error('staff_member_id') <p class="ui-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="display_name" class="ui-label">Public display name <span class="text-rose-600">*</span></label>
                                <input id="display_name" name="display_name" value="{{ old('display_name', $revision->display_name) }}" maxlength="140" class="ui-input @error('display_name') ui-input-error @enderror">
                                @error('display_name') <p class="ui-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="public_role" class="ui-label">Public role or title <span class="text-rose-600">*</span></label>
                                <input id="public_role" name="public_role" value="{{ old('public_role', $revision->public_role) }}" maxlength="140" class="ui-input @error('public_role') ui-input-error @enderror">
                                @error('public_role') <p class="ui-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="team_group" class="ui-label">Team group <span class="text-rose-600">*</span></label>
                                <select id="team_group" name="team_group" class="ui-input @error('team_group') ui-input-error @enderror">
                                    <option value="">Choose a group</option>
                                    @foreach ($groups as $value => $label)
                                        <option value="{{ $value }}" @selected(old('team_group', $revision->team_group) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('team_group') <p class="ui-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="display_order" class="ui-label">Display order</label>
                                <input id="display_order" type="number" min="0" max="65535" name="display_order" value="{{ old('display_order', $revision->display_order ?? 0) }}" class="ui-input @error('display_order') ui-input-error @enderror">
                                @error('display_order') <p class="ui-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="md:col-span-2">
                                <label for="qualification" class="ui-label">Approved qualification or short credential</label>
                                <input id="qualification" name="qualification" value="{{ old('qualification', $revision->qualification) }}" maxlength="255" class="ui-input @error('qualification') ui-input-error @enderror">
                                @error('qualification') <p class="ui-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="md:col-span-2">
                                <label for="bio_markdown" class="ui-label">Approved short biography</label>
                                <textarea id="bio_markdown" name="bio_markdown" rows="8" maxlength="2000" class="ui-input font-mono text-sm @error('bio_markdown') ui-input-error @enderror">{{ old('bio_markdown', $revision->bio_markdown) }}</textarea>
                                <p class="mt-1 text-xs text-slate-500">Use simple Markdown. Do not include personal contact details, employment dates, salary, identification, or internal notes.</p>
                                @error('bio_markdown') <p class="ui-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </section>

                    <section class="ui-card">
                        <div class="ui-card-header">
                            <div>
                                <h2 class="text-sm font-bold text-slate-900">Consent-cleared portrait</h2>
                                <p class="mt-0.5 text-xs text-slate-500">Optional. A missing or withdrawn portrait automatically falls back to public initials; legacy staff photos are never used.</p>
                            </div>
                            @can('media.upload')<a href="{{ route('admin.media.index') }}" class="ui-btn ui-btn-secondary !px-3 !py-1.5 text-xs">Open Media Library</a>@endcan
                        </div>
                        <div class="grid gap-4 p-5 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <label for="portrait_asset_id" class="ui-label">Portrait image</label>
                                <select id="portrait_asset_id" name="portrait_asset_id" class="ui-input @error('portrait_asset_id') ui-input-error @enderror">
                                    <option value="">Keep the current portrait or use initials</option>
                                    @foreach ($assets as $asset)
                                        <option value="{{ $asset->id }}" @selected((string) $currentPortraitId === (string) $asset->id)>#{{ $asset->id }} — {{ $asset->title ?: $asset->original_filename }} ({{ $asset->category ?: 'Uncategorised' }})</option>
                                    @endforeach
                                </select>
                                @error('portrait_asset_id') <p class="ui-error">{{ $message }}</p> @enderror
                                @if ($portrait)
                                    <label class="mt-3 flex items-start gap-2 text-sm text-slate-700">
                                        <input type="checkbox" name="remove_portrait" value="1" @checked(old('remove_portrait')) class="mt-1 rounded border-slate-300 text-blue-700 focus:ring-blue-600">
                                        <span>Remove the current portrait and use initials instead.</span>
                                    </label>
                                @endif
                            </div>
                            <div>
                                <label for="portrait_alt_text" class="ui-label">Portrait-specific alt text</label>
                                <input id="portrait_alt_text" name="portrait_alt_text" value="{{ old('portrait_alt_text', $portrait?->alt_text) }}" maxlength="255" class="ui-input" placeholder="Uses media default if empty">
                            </div>
                            <div>
                                <label for="portrait_caption" class="ui-label">Caption</label>
                                <input id="portrait_caption" name="portrait_caption" value="{{ old('portrait_caption', $portrait?->caption) }}" maxlength="10000" class="ui-input">
                            </div>
                            <div>
                                <label for="portrait_focal_x" class="ui-label">Focal point horizontal (%)</label>
                                <input id="portrait_focal_x" name="portrait_focal_x" type="number" min="0" max="100" step="0.01" value="{{ old('portrait_focal_x', $portrait?->focal_x ?? 50) }}" class="ui-input">
                            </div>
                            <div>
                                <label for="portrait_focal_y" class="ui-label">Focal point vertical (%)</label>
                                <input id="portrait_focal_y" name="portrait_focal_y" type="number" min="0" max="100" step="0.01" value="{{ old('portrait_focal_y', $portrait?->focal_y ?? 50) }}" class="ui-input">
                            </div>
                        </div>
                    </section>
                </fieldset>

                @if ($canAuthor)
                    <div class="flex flex-wrap gap-3">
                        <button type="submit" class="ui-btn ui-btn-primary"><i class="bi bi-floppy" aria-hidden="true"></i> Save private revision</button>
                        @if ($isEditing && $revision->exists)<a href="{{ route('admin.public-team.preview', [$profile, $revision]) }}" target="_blank" rel="noopener" class="ui-btn ui-btn-secondary"><i class="bi bi-eye" aria-hidden="true"></i> Preview</a>@endif
                    </div>
                @endif
            </form>

            @if ($isEditing)
                <aside class="space-y-4">
                    <section class="ui-card p-5">
                        <h2 class="text-sm font-bold text-slate-900">Editorial workflow</h2>
                        <p class="mt-1 text-sm text-slate-600">Publishing confirms the selected staff record is currently active. Future employment changes hide the profile immediately.</p>
                        <div class="mt-4 space-y-3">
                            @if ($revision->exists && $revision->workflow_state === 'draft' && $canAuthor)<form action="{{ route('admin.public-team.submit', [$profile, $revision]) }}" method="POST">@csrf @method('PATCH')<button type="submit" class="ui-btn ui-btn-secondary w-full">Submit for review</button></form>@endif
                            @if ($revision->exists && $revision->workflow_state === 'review' && $canReview)<form action="{{ route('admin.public-team.approve', [$profile, $revision]) }}" method="POST">@csrf @method('PATCH')<button type="submit" class="ui-btn ui-btn-secondary w-full">Approve revision</button></form>@endif
                            @if ($revision->exists && $revision->workflow_state === 'approved' && $canPublish)
                                <form action="{{ route('admin.public-team.publish', [$profile, $revision]) }}" method="POST">@csrf @method('PATCH')<button type="submit" class="ui-btn ui-btn-primary w-full">Publish now</button></form>
                                <form action="{{ route('admin.public-team.publish', [$profile, $revision]) }}" method="POST" class="space-y-2">@csrf @method('PATCH')<label for="scheduled_for" class="ui-label !mb-0">Schedule (Nepal time)</label><input id="scheduled_for" name="scheduled_for" type="datetime-local" class="ui-input text-sm"><button type="submit" class="ui-btn ui-btn-secondary w-full">Schedule publication</button></form>
                            @endif
                            @if ($profile->scheduled_revision_id !== null && $canPublish)<form action="{{ route('admin.public-team.cancel-schedule', $profile) }}" method="POST">@csrf @method('PATCH')<button type="submit" class="ui-btn ui-btn-secondary w-full">Cancel scheduled publication</button></form>@endif
                            @if ($profile->publication_state === 'published' && $canPublish)<form action="{{ route('admin.public-team.withdraw', $profile) }}" method="POST">@csrf @method('PATCH')<button type="submit" class="ui-btn ui-btn-secondary w-full">Withdraw from public site</button></form>@endif
                            @if ($profile->publication_state !== 'archived' && $canPublish)<form action="{{ route('admin.public-team.archive', $profile) }}" method="POST" onsubmit="return confirm('Archive this public Team profile? It will no longer be publicly available.')">@csrf @method('PATCH')<button type="submit" class="ui-btn ui-btn-secondary w-full">Archive profile</button></form>@elseif ($profile->publication_state === 'archived' && $canPublish)<form action="{{ route('admin.public-team.restore', $profile) }}" method="POST">@csrf @method('PATCH')<button type="submit" class="ui-btn ui-btn-secondary w-full">Restore as draft</button></form>@endif
                        </div>
                    </section>
                    <section class="ui-card p-5"><h2 class="text-sm font-bold text-slate-900">Safety notes</h2><ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-slate-600"><li>Only explicitly entered public fields are projected to the website.</li><li>Portrait consent withdrawal removes the image immediately and leaves initials in its place.</li><li>Live and scheduled portrait attachments cannot be changed from the general Media Library.</li></ul></section>
                </aside>
            @endif
        </div>
    </div>
@endsection
