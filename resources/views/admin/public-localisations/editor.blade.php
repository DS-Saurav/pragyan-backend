@extends('layouts.admin')

@section('title', data_get($schema ?? [], 'title', 'Nepali translation editor'))

@section('content')
    @php
        /*
         * View contract:
         * $schema may provide title, subtitle, back_url, source_url, revision, fields,
         * media, translation_media, update_url, update_method and workflow_actions.
         * Values in fields and media are intentionally read only from the Nepali revision.
         */
        $schema = is_array($schema ?? null) ? $schema : [];
        $revision = data_get($schema, 'revision')
            ?? data_get($translation, 'editingRevision')
            ?? data_get($translation, 'latestRevision')
            ?? data_get($translation, 'currentRevision');
        $revisionPayload = data_get($revision, 'payload', []);
        $revisionPayload = is_array($revisionPayload) ? $revisionPayload : [];
        $payload = old('payload', $revisionPayload);
        $payload = is_array($payload) ? $payload : [];
        $translationMedia = data_get($schema, 'translation_media', []);
        $translationMedia = is_array($translationMedia) ? $translationMedia : [];
        $fields = collect(data_get($schema, 'fields', []))
            ->filter(fn ($field): bool => is_array($field) && filled(data_get($field, 'key')))
            ->values();
        $collections = collect(data_get($schema, 'collections', []))
            ->filter(fn ($collection): bool => is_array($collection) && filled(data_get($collection, 'key')))
            ->values();
        $mediaEntries = collect(data_get($schema, 'media', []))
            ->filter(fn ($media): bool => is_array($media))
            ->values();
        $canAuthor = $canAuthor ?? false;
        $canReview = $canReview ?? false;
        $canPublish = $canPublish ?? false;
        $sourceTitle = data_get($schema, 'source_title')
            ?? data_get($source, 'title')
            ?? data_get($source, 'name')
            ?? data_get($source, 'slug')
            ?? 'English source';
        $sourceUrl = data_get($schema, 'source_url');
        $sourceFields = data_get($schema, 'source_fields', []);
        $sourceFields = is_array($sourceFields) ? $sourceFields : [];
        $sourceFingerprint = data_get($revision, 'source_fingerprint') ?? data_get($schema, 'source_fingerprint');
        $sourceRevisionLabel = data_get($schema, 'source_revision_label')
            ?? data_get($revision, 'source_revision_id')
            ?? data_get($source, 'published_revision_id');
        $formAction = data_get($schema, 'update_url') ?? data_get($schema, 'form_action') ?? url()->current();
        $formMethod = strtoupper((string) (data_get($schema, 'update_method') ?? data_get($schema, 'form_method') ?? 'PUT'));
        $formMethod = in_array($formMethod, ['POST', 'PUT', 'PATCH'], true) ? $formMethod : 'PUT';
        $workflowState = strtolower((string) (data_get($revision, 'workflow_state') ?? data_get($translation, 'workflow_state') ?? 'draft'));
        $publicationState = strtolower((string) (data_get($translation, 'publication_state') ?? 'draft'));
        $workflowUrls = data_get($schema, 'workflow_urls', []);
        $workflowUrls = is_array($workflowUrls) ? $workflowUrls : [];
        $workflowActions = data_get($schema, 'workflow_actions', []);
        $workflowActions = is_array($workflowActions) ? $workflowActions : [];
        $auditEvents = data_get($schema, 'audit_events', []);
        $auditEvents = is_array($auditEvents) ? $auditEvents : [];

        if ($workflowActions === []) {
            $workflowActions = [
                ['key' => 'submit', 'label' => 'Submit for review', 'url' => data_get($workflowUrls, 'submit'), 'role' => 'author', 'workflow_states' => ['draft']],
                ['key' => 'approve', 'label' => 'Approve revision', 'url' => data_get($workflowUrls, 'approve'), 'role' => 'review', 'workflow_states' => ['review', 'in_review']],
                ['key' => 'publish', 'label' => 'Publish now', 'url' => data_get($workflowUrls, 'publish'), 'role' => 'publish', 'workflow_states' => ['approved'], 'style' => 'primary'],
                ['key' => 'schedule', 'label' => 'Schedule publication', 'url' => data_get($workflowUrls, 'schedule') ?? data_get($workflowUrls, 'publish'), 'role' => 'publish', 'workflow_states' => ['approved'], 'schedule' => true],
                ['key' => 'withdraw', 'label' => 'Withdraw from public site', 'url' => data_get($workflowUrls, 'withdraw'), 'role' => 'publish', 'publication_states' => ['published']],
                ['key' => 'archive', 'label' => 'Archive translation', 'url' => data_get($workflowUrls, 'archive'), 'role' => 'publish', 'publication_states' => ['draft', 'withdrawn', 'published', 'scheduled']],
                ['key' => 'restore', 'label' => 'Restore as draft', 'url' => data_get($workflowUrls, 'restore'), 'role' => 'publish', 'publication_states' => ['archived']],
            ];
        }

        $workflowActions = collect($workflowActions)
            ->filter(function ($action) use ($canAuthor, $canReview, $canPublish, $workflowState, $publicationState): bool {
                if (! is_array($action) || ! filled(data_get($action, 'url'))) {
                    return false;
                }

                $role = data_get($action, 'role');
                $hasPermission = match ($role) {
                    'author' => $canAuthor,
                    'review' => $canReview,
                    'publish' => $canPublish,
                    default => true,
                };

                if (! $hasPermission) {
                    return false;
                }

                $requiredWorkflowStates = data_get($action, 'workflow_states');
                if (is_array($requiredWorkflowStates) && ! in_array($workflowState, $requiredWorkflowStates, true)) {
                    return false;
                }

                $requiredPublicationStates = data_get($action, 'publication_states');

                return ! is_array($requiredPublicationStates) || in_array($publicationState, $requiredPublicationStates, true);
            })
            ->values();
    @endphp

    <div class="ui-page">
        <x-ui.page-header :title="$schema['title'] ?? 'Nepali translation editor'"
            :subtitle="$schema['subtitle'] ?? 'Create a separately reviewed Nepali public translation while English remains the canonical source.'">
            <x-slot name="actions">
                @if (data_get($schema, 'preview_url'))
                    <a href="{{ data_get($schema, 'preview_url') }}" class="ui-btn ui-btn-secondary">
                        <i class="bi bi-eye" aria-hidden="true"></i> Preview Nepali
                    </a>
                @endif
                @if (data_get($schema, 'back_url'))
                    <a href="{{ data_get($schema, 'back_url') }}" class="ui-btn ui-btn-secondary">Back to content</a>
                @endif
            </x-slot>
        </x-ui.page-header>

        @if (session('status'))
            <div class="ui-alert ui-alert-success" role="status">
                <i class="bi bi-check-circle mt-0.5" aria-hidden="true"></i>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="ui-alert ui-alert-error" role="alert">
                <i class="bi bi-exclamation-circle mt-0.5" aria-hidden="true"></i>
                <span>Review the highlighted Nepali fields before saving this revision.</span>
            </div>
        @endif

        <div class="mt-6 space-y-6">
            <x-admin.public-localisation-status
                :translation="$translation"
                :source="$source"
                :revision="$revision"
                :href="$sourceUrl"
                link-label="View English source"
            />

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
                <form action="{{ $formAction }}" method="POST" class="space-y-6">
                    @csrf
                    @if ($formMethod !== 'POST')
                        @method($formMethod)
                    @endif

                    <fieldset @disabled(! $canAuthor) class="space-y-6">
                        <section class="ui-card">
                            <div class="ui-card-header">
                                <div>
                                    <h2 class="text-sm font-bold text-slate-900">Nepali public copy</h2>
                                    <p class="mt-0.5 text-xs text-slate-500">Only approved Nepali copy belongs in these fields. They never inherit English source text automatically.</p>
                                </div>
                                <span class="ui-badge ui-badge-neutral" lang="ne">नेपाली</span>
                            </div>
                            <div class="grid gap-4 p-5 md:grid-cols-2">
                                @forelse ($fields as $field)
                                    @php
                                        $fieldKey = (string) data_get($field, 'key');
                                        $fieldId = 'translation-'.preg_replace('/[^A-Za-z0-9_-]+/', '-', $fieldKey);
                                        $fieldType = strtolower((string) data_get($field, 'type', 'textarea'));
                                        $fieldType = in_array($fieldType, ['text', 'textarea', 'email', 'url', 'date', 'datetime-local', 'number'], true) ? $fieldType : 'textarea';
                                        $fieldValue = old('payload.'.$fieldKey, data_get($payload, $fieldKey, ''));
                                        $fieldValue = is_array($fieldValue) ? implode("\n", $fieldValue) : $fieldValue;
                                        $fieldValue = is_scalar($fieldValue) || $fieldValue === null ? $fieldValue : '';
                                        $fieldRequired = false;
                                        $publishRequired = (bool) data_get($field, 'requiredForPublish', data_get($field, 'required', false));
                                        $fieldRows = max(2, (int) data_get($field, 'rows', $fieldType === 'textarea' ? 6 : 2));
                                        $fieldMaxLength = data_get($field, 'maxlength') ?? data_get($field, 'max_length');
                                        $fieldWide = (bool) data_get($field, 'wide', $fieldType === 'textarea');
                                    @endphp
                                    <div @class(['md:col-span-2' => $fieldWide])>
                                        <label for="{{ $fieldId }}" class="ui-label">
                                            {{ data_get($field, 'label', $fieldKey) }}
                                            @if ($publishRequired)<span class="text-rose-600">*</span>@endif
                                        </label>
                                        @if ($fieldType === 'textarea')
                                            <textarea
                                                id="{{ $fieldId }}"
                                                name="payload[{{ $fieldKey }}]"
                                                rows="{{ $fieldRows }}"
                                                lang="ne"
                                                dir="ltr"
                                                @if ($fieldRequired) required @endif
                                                @if ($fieldMaxLength) maxlength="{{ $fieldMaxLength }}" @endif
                                                class="ui-input break-words font-sans leading-6 @error('payload.'.$fieldKey) ui-input-error @enderror"
                                            >{{ $fieldValue }}</textarea>
                                        @else
                                            <input
                                                id="{{ $fieldId }}"
                                                type="{{ $fieldType }}"
                                                name="payload[{{ $fieldKey }}]"
                                                value="{{ $fieldValue }}"
                                                lang="ne"
                                                dir="ltr"
                                                @if ($fieldRequired) required @endif
                                                @if ($fieldMaxLength) maxlength="{{ $fieldMaxLength }}" @endif
                                                class="ui-input @error('payload.'.$fieldKey) ui-input-error @enderror"
                                            >
                                        @endif
                                        @if (data_get($field, 'help'))
                                            <p class="ui-help">{{ data_get($field, 'help') }}</p>
                                        @endif
                                        @error('payload.'.$fieldKey)
                                            <p class="ui-error">{{ $message }}</p>
                                        @enderror
                                    </div>
                                @empty
                                    <div class="md:col-span-2 rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-600">
                                        This content type has no translatable text fields configured yet.
                                    </div>
                                @endforelse
                            </div>
                        </section>

                        @foreach ($collections as $collection)
                            @php
                                $collectionKey = (string) data_get($collection, 'key');
                                $collectionFields = collect(data_get($collection, 'fields', []))->filter(fn ($field): bool => is_array($field) && filled(data_get($field, 'key')));
                                $sourceIds = collect(data_get($collection, 'source_ids', []))->filter(fn ($id): bool => is_numeric($id));
                            @endphp
                            <section class="ui-card">
                                <div class="ui-card-header">
                                    <div>
                                        <h2 class="text-sm font-bold text-slate-900">{{ data_get($collection, 'label', $collectionKey) }}</h2>
                                        <p class="mt-0.5 text-xs text-slate-500">Each entry is tied to an existing English source row. Enter only reviewed Nepali copy; English values are not copied into this form.</p>
                                    </div>
                                </div>
                                <div class="space-y-5 p-5">
                                    @forelse ($sourceIds as $sourceId)
                                        @php
                                            $rowPayload = old('payload.'.$collectionKey.'.'.$sourceId, data_get($payload, $collectionKey.'.'.$sourceId, []));
                                            $rowPayload = is_array($rowPayload) ? $rowPayload : [];
                                        @endphp
                                        <fieldset class="rounded-xl border border-slate-200 p-4">
                                            <legend class="px-1 text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('Source row') }} #{{ $sourceId }}</legend>
                                            <div class="mt-2 grid gap-4 md:grid-cols-2">
                                                @foreach ($collectionFields as $field)
                                                    @php
                                                        $fieldKey = (string) data_get($field, 'key');
                                                        $fieldId = 'translation-'.preg_replace('/[^A-Za-z0-9_-]+/', '-', $collectionKey.'-'.$sourceId.'-'.$fieldKey);
                                                        $fieldType = strtolower((string) data_get($field, 'type', 'textarea'));
                                                        $fieldType = $fieldType === 'text' ? 'text' : 'textarea';
                                                        $fieldValue = data_get($rowPayload, $fieldKey, '');
                                                        $fieldValue = is_array($fieldValue) ? implode("\n", $fieldValue) : $fieldValue;
                                                        $fieldValue = is_scalar($fieldValue) || $fieldValue === null ? $fieldValue : '';
                                                        $fieldRequired = (bool) data_get($field, 'requiredForPublish', false);
                                                    @endphp
                                                    <div @class(['md:col-span-2' => $fieldType === 'textarea'])>
                                                        <label for="{{ $fieldId }}" class="ui-label">{{ data_get($field, 'label', $fieldKey) }} @if ($fieldRequired)<span class="text-rose-600">*</span>@endif</label>
                                                        @if ($fieldType === 'textarea')
                                                            <textarea id="{{ $fieldId }}" name="payload[{{ $collectionKey }}][{{ $sourceId }}][{{ $fieldKey }}]" rows="4" lang="ne" dir="ltr" maxlength="{{ data_get($field, 'max', 50000) }}" class="ui-input break-words font-sans leading-6">{{ $fieldValue }}</textarea>
                                                        @else
                                                            <input id="{{ $fieldId }}" type="text" name="payload[{{ $collectionKey }}][{{ $sourceId }}][{{ $fieldKey }}]" value="{{ $fieldValue }}" lang="ne" dir="ltr" maxlength="{{ data_get($field, 'max', 255) }}" class="ui-input">
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </fieldset>
                                    @empty
                                        <p class="rounded-lg bg-slate-50 p-3 text-sm text-slate-600">No current English source rows are available for this section.</p>
                                    @endforelse
                                </div>
                            </section>
                        @endforeach

                        @if ($mediaEntries->isNotEmpty())
                            <section class="ui-card">
                                <div class="ui-card-header">
                                    <div>
                                        <h2 class="text-sm font-bold text-slate-900">Nepali media descriptions</h2>
                                        <p class="mt-0.5 text-xs text-slate-500">The existing Media Library asset is reused. Only its public-facing Nepali alt text and caption are edited here.</p>
                                    </div>
                                </div>
                                <div class="space-y-5 p-5">
                                    @foreach ($mediaEntries as $mediaIndex => $media)
                                        @php
                                            $mediaKey = (string) (data_get($media, 'key') ?? data_get($media, 'media_attachment_id') ?? $mediaIndex);
                                            $storedMedia = data_get($translationMedia, $mediaKey, []);
                                            $storedMedia = is_array($storedMedia) ? $storedMedia : [];
                                            // Never use source_alt_text or source_caption as an input default.
                                            $nepaliAlt = old('media.'.$mediaKey.'.alt_text', data_get($media, 'translation_alt_text', data_get($storedMedia, 'alt_text', '')));
                                            $nepaliCaption = old('media.'.$mediaKey.'.caption', data_get($media, 'translation_caption', data_get($storedMedia, 'caption', '')));
                                            $nepaliAlt = is_scalar($nepaliAlt) || $nepaliAlt === null ? $nepaliAlt : '';
                                            $nepaliCaption = is_scalar($nepaliCaption) || $nepaliCaption === null ? $nepaliCaption : '';
                                            $mediaId = 'translation-media-'.preg_replace('/[^A-Za-z0-9_-]+/', '-', $mediaKey);
                                            $mediaAltRequired = (bool) data_get($media, 'alt_required', false);
                                        @endphp
                                        <article class="rounded-xl border border-slate-200 p-4">
                                            <div class="flex flex-wrap items-start justify-between gap-3">
                                                <div>
                                                    <h3 class="text-sm font-bold text-slate-900">{{ data_get($media, 'label', 'Media item') }}</h3>
                                                    @if (data_get($media, 'asset_label'))
                                                        <p class="mt-1 break-words text-xs text-slate-500" style="overflow-wrap:anywhere">{{ data_get($media, 'asset_label') }}</p>
                                                    @endif
                                                </div>
                                                @if (data_get($media, 'consent_status'))
                                                    <span class="ui-badge ui-badge-neutral">{{ data_get($media, 'consent_status') }}</span>
                                                @endif
                                            </div>

                                            <dl class="mt-4 grid gap-3 rounded-lg bg-slate-50 p-3 text-sm md:grid-cols-2">
                                                <div>
                                                    <dt class="ui-section-title">English alt text</dt>
                                                    <dd class="mt-1 whitespace-pre-wrap break-words text-slate-700" style="overflow-wrap:anywhere">{{ data_get($media, 'source_alt_text') ?: 'No English alt text supplied.' }}</dd>
                                                </div>
                                                <div>
                                                    <dt class="ui-section-title">English caption</dt>
                                                    <dd class="mt-1 whitespace-pre-wrap break-words text-slate-700" style="overflow-wrap:anywhere">{{ data_get($media, 'source_caption') ?: 'No English caption supplied.' }}</dd>
                                                </div>
                                            </dl>

                                            <div class="mt-4 grid gap-4 md:grid-cols-2">
                                                <div>
                                                    <label for="{{ $mediaId }}-alt" class="ui-label">Nepali alt text @if ($mediaAltRequired)<span class="text-rose-600">*</span>@endif</label>
                                                    <textarea id="{{ $mediaId }}-alt" name="media[{{ $mediaKey }}][alt_text]" rows="3" lang="ne" dir="ltr" @if ($mediaAltRequired) required @endif maxlength="1000" class="ui-input break-words font-sans leading-6 @error('media.'.$mediaKey.'.alt_text') ui-input-error @enderror">{{ $nepaliAlt }}</textarea>
                                                    <p class="ui-help">Describe meaningful image content for families using assistive technology. Leave decorative media blank only when it is genuinely decorative.</p>
                                                    @error('media.'.$mediaKey.'.alt_text')<p class="ui-error">{{ $message }}</p>@enderror
                                                </div>
                                                <div>
                                                    <label for="{{ $mediaId }}-caption" class="ui-label">Nepali caption</label>
                                                    <textarea id="{{ $mediaId }}-caption" name="media[{{ $mediaKey }}][caption]" rows="3" lang="ne" dir="ltr" maxlength="10000" class="ui-input break-words font-sans leading-6 @error('media.'.$mediaKey.'.caption') ui-input-error @enderror">{{ $nepaliCaption }}</textarea>
                                                    <p class="ui-help">Optional public caption. Do not introduce information that is absent from the approved English source.</p>
                                                    @error('media.'.$mediaKey.'.caption')<p class="ui-error">{{ $message }}</p>@enderror
                                                </div>
                                            </div>
                                        </article>
                                    @endforeach
                                </div>
                            </section>
                        @endif
                    </fieldset>

                    @if ($canAuthor)
                        <button type="submit" class="ui-btn ui-btn-primary"><i class="bi bi-floppy" aria-hidden="true"></i> Save private Nepali revision</button>
                    @else
                        <p class="text-sm text-slate-500">You have read-only access to this translation.</p>
                    @endif
                </form>

                <aside class="space-y-4">
                    <section class="ui-card p-5">
                        <h2 class="text-sm font-bold text-slate-900">English source fidelity</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-600">This revision is tied to the approved English source. Check it again whenever the English source changes; do not add claims, dates or details that are not supported by that source.</p>

                        <dl class="mt-4 space-y-3 text-sm">
                            <div>
                                <dt class="ui-section-title">English source</dt>
                                <dd class="mt-1 whitespace-pre-wrap break-words font-medium text-slate-800" style="overflow-wrap:anywhere">{{ $sourceTitle }}</dd>
                            </div>
                            @foreach ($sourceFields as $sourceField)
                                @if (is_array($sourceField) && filled(data_get($sourceField, 'label')))
                                    <div>
                                        <dt class="ui-section-title">{{ data_get($sourceField, 'label') }}</dt>
                                        <dd class="mt-1 whitespace-pre-wrap break-words text-slate-700" style="overflow-wrap:anywhere">{{ data_get($sourceField, 'value') ?: '—' }}</dd>
                                    </div>
                                @endif
                            @endforeach
                            @if ($sourceRevisionLabel)
                                <div>
                                    <dt class="ui-section-title">Source revision</dt>
                                    <dd class="mt-1 break-words text-slate-700" style="overflow-wrap:anywhere">{{ $sourceRevisionLabel }}</dd>
                                </div>
                            @endif
                            @if ($sourceFingerprint)
                                <div>
                                    <dt class="ui-section-title">Source fingerprint</dt>
                                    <dd class="mt-1 break-all font-mono text-xs text-slate-600">{{ $sourceFingerprint }}</dd>
                                </div>
                            @endif
                        </dl>
                    </section>

                    <section class="ui-card p-5">
                        <h2 class="text-sm font-bold text-slate-900">Translation workflow</h2>
                        <p class="mt-1 text-sm leading-6 text-slate-600">Draft, review, approval and publication remain separate recorded steps. English stays available while Nepali work is private.</p>
                        <div class="mt-4 space-y-3">
                            @forelse ($workflowActions as $action)
                                @php
                                    $actionMethod = strtoupper((string) data_get($action, 'method', 'PATCH'));
                                    $actionMethod = in_array($actionMethod, ['POST', 'PUT', 'PATCH', 'DELETE'], true) ? $actionMethod : 'PATCH';
                                    $actionStyle = data_get($action, 'style') === 'danger' ? 'ui-btn-danger' : (data_get($action, 'style') === 'primary' ? 'ui-btn-primary' : 'ui-btn-secondary');
                                    $actionKey = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) data_get($action, 'key', 'workflow'));
                                    $requiresSchedule = (bool) data_get($action, 'schedule', false);
                                    $confirmMessage = data_get($action, 'confirm');
                                    $actionInputs = data_get($action, 'inputs', []);
                                    $actionInputs = is_array($actionInputs) ? $actionInputs : [];
                                @endphp
                                <form action="{{ data_get($action, 'url') }}" method="POST" class="space-y-2" @if ($confirmMessage) onsubmit="return confirm({{ \Illuminate\Support\Js::from($confirmMessage) }})" @endif>
                                    @csrf
                                    @if ($actionMethod !== 'POST')
                                        @method($actionMethod)
                                    @endif
                                    @foreach ($actionInputs as $inputName => $inputValue)
                                        <input type="hidden" name="{{ $inputName }}" value="{{ $inputValue }}">
                                    @endforeach
                                    @if ($requiresSchedule)
                                        <label for="schedule-{{ $actionKey }}" class="ui-label !mb-0">Publication time (Nepal time)</label>
                                        <input id="schedule-{{ $actionKey }}" type="datetime-local" name="scheduled_for" value="{{ old('scheduled_for') }}" class="ui-input text-sm @error('scheduled_for') ui-input-error @enderror">
                                        @error('scheduled_for')<p class="ui-error">{{ $message }}</p>@enderror
                                    @endif
                                    <button type="submit" class="ui-btn {{ $actionStyle }} w-full">{{ data_get($action, 'label', 'Continue workflow') }}</button>
                                </form>
                            @empty
                                <p class="rounded-lg bg-slate-50 p-3 text-sm text-slate-600">No workflow action is currently available for this revision.</p>
                            @endforelse
                        </div>
                    </section>

                    <section class="ui-card p-5">
                        <h2 class="text-sm font-bold text-slate-900">Audit timeline</h2>
                        <p class="mt-1 text-sm leading-6 text-slate-600">Records who completed each private editorial step and when. Translation copy itself is not duplicated in this log.</p>
                        <ol class="mt-4 space-y-3">
                            @forelse ($auditEvents as $event)
                                <li class="border-l-2 border-sky-200 pl-3 text-sm">
                                    <p class="font-semibold text-slate-800">{{ ucfirst(str_replace('_', ' ', (string) data_get($event, 'action'))) }}</p>
                                    <p class="mt-0.5 text-slate-600">{{ data_get($event, 'actor', 'System') }} · {{ data_get($event, 'at') }}</p>
                                    @if (data_get($event, 'revision'))
                                        <p class="mt-0.5 text-xs text-slate-500">Translation revision #{{ data_get($event, 'revision') }}</p>
                                    @endif
                                </li>
                            @empty
                                <li class="rounded-lg bg-slate-50 p-3 text-sm text-slate-600">The first audit entry appears when this Nepali draft is saved.</li>
                            @endforelse
                        </ol>
                    </section>
                </aside>
            </div>
        </div>
    </div>
@endsection
