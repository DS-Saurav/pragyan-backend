<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;

use App\Http\Controllers\Controller;
use App\Models\MediaAttachment;
use App\Models\PublicContentTranslation;
use App\Models\PublicContentTranslationRevision;
use App\Models\PublicSiteTranslation;
use App\Models\PublicSiteTranslationRevision;
use App\Models\User;
use App\Services\PublicTranslations\PublicTranslationPublisher;
use App\Services\PublicTranslations\PublicTranslationSource;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Private editor for human-authored Nepali public translations. */
final class PublicTranslationController extends Controller
{
    public function __construct(
        private PublicTranslationSource $sources,
        private PublicTranslationPublisher $publisher,
    ) {}

    public function editContent(string $contentType, int $contentId): JsonResponse
    {
        $this->authorizeAnyContent($contentType);
        $source = $this->sources->sourceFor($contentType, $contentId);
        $translation = PublicContentTranslation::query()
            ->where('content_type', $contentType)
            ->where('content_id', $contentId)
            ->where('locale', PublicContentTranslation::LOCALE_NEPALI)
            ->with(['revisions.media', 'publishedRevision.media', 'scheduledRevision.media', 'auditEvents.actor'])
            ->first();

        return $this->contentEditor($contentType, $source, $translation);
    }

    public function saveContent(Request $request, string $contentType, int $contentId): JsonResponse
    {
        $this->authorizeContent($contentType, 'author');
        $source = $this->sources->sourceFor($contentType, $contentId);
        $translation = $this->publisher->saveContentDraft(
            $this->actor(),
            $contentType,
            $source,
            (array) $request->input('payload', []),
            (array) $request->input('media', []),
        );

        return response()->json(["message" => $translation->published_revision_id === null
                ? 'Nepali draft saved privately.'
                : 'A private Nepali revision was saved; the published translation has not changed.']);
    }

    public function previewContent(PublicContentTranslation $translation, PublicContentTranslationRevision $revision): JsonResponse
    {
        $this->ensureContentRevision($translation, $revision);
        $this->authorizeAnyContent($translation->content_type);
        $source = $this->sources->sourceFor($translation->content_type, (int) $translation->content_id);

        return $this->preview(
            $revision->loadMissing('media.mediaAttachment.asset'),
            $this->sources->sourceLabel($translation->content_type, $source),
            route('admin.public-localisations.content.edit', [$translation->content_type, $translation->content_id]),
        );
    }

    public function submitContent(PublicContentTranslation $translation, PublicContentTranslationRevision $revision): JsonResponse
    {
        $this->ensureContentRevision($translation, $revision);
        $this->publisher->submitContent($this->actor(), $translation, $revision);

        return response()->json(["message" => 'Nepali revision submitted for review.']);
    }

    public function approveContent(PublicContentTranslation $translation, PublicContentTranslationRevision $revision): JsonResponse
    {
        $this->ensureContentRevision($translation, $revision);
        $this->publisher->approveContent($this->actor(), $translation, $revision);

        return response()->json(["message" => 'Nepali revision approved.']);
    }

    public function publishContent(Request $request, PublicContentTranslation $translation, PublicContentTranslationRevision $revision): JsonResponse
    {
        $this->ensureContentRevision($translation, $revision);
        $this->publisher->publishContent($this->actor(), $translation, $revision, $this->scheduledFor($request));

        return response()->json(["message" => 'Nepali translation publication updated.']);
    }

    public function withdrawContent(PublicContentTranslation $translation): JsonResponse
    {
        $this->publisher->withdrawContent($this->actor(), $translation);

        return response()->json(["message" => 'Nepali translation withdrawn. English remains unchanged.']);
    }

    public function archiveContent(PublicContentTranslation $translation): JsonResponse
    {
        $this->publisher->archiveContent($this->actor(), $translation);

        return response()->json(["message" => 'Nepali translation archived; its history is retained.']);
    }

    public function restoreContent(PublicContentTranslation $translation): JsonResponse
    {
        $this->publisher->restoreContent($this->actor(), $translation);

        return response()->json(["message" => 'Nepali translation restored as a private draft.']);
    }

    public function editSite(string $scope): JsonResponse
    {
        $this->authorizeAnySite($scope);
        $translation = PublicSiteTranslation::query()
            ->where('scope', $scope)
            ->where('locale', PublicSiteTranslation::LOCALE_NEPALI)
            ->with(['revisions', 'publishedRevision', 'scheduledRevision', 'auditEvents.actor'])
            ->first();

        return $this->siteEditor($scope, $translation);
    }

    public function saveSite(Request $request, string $scope): JsonResponse
    {
        $this->authorizeSite($scope);
        $this->publisher->saveSiteDraft($this->actor(), $scope, (array) $request->input('payload', []));

        return response()->json(["message" => 'Nepali site translation saved privately.']);
    }

    public function previewSite(PublicSiteTranslation $translation, PublicSiteTranslationRevision $revision): JsonResponse
    {
        $this->ensureSiteRevision($translation, $revision);
        $this->authorizeSite($translation->scope);

        return $this->preview(
            $revision,
            ucfirst($translation->scope).' English source',
            route('admin.public-localisations.site.edit', $translation->scope),
        );
    }

    public function submitSite(PublicSiteTranslation $translation, PublicSiteTranslationRevision $revision): JsonResponse
    {
        $this->ensureSiteRevision($translation, $revision);
        $this->publisher->submitSite($this->actor(), $translation, $revision);

        return response()->json(["message" => 'Nepali revision submitted for review.']);
    }

    public function approveSite(PublicSiteTranslation $translation, PublicSiteTranslationRevision $revision): JsonResponse
    {
        $this->ensureSiteRevision($translation, $revision);
        $this->publisher->approveSite($this->actor(), $translation, $revision);

        return response()->json(["message" => 'Nepali revision approved.']);
    }

    public function publishSite(Request $request, PublicSiteTranslation $translation, PublicSiteTranslationRevision $revision): JsonResponse
    {
        $this->ensureSiteRevision($translation, $revision);
        $this->publisher->publishSite($this->actor(), $translation, $revision, $this->scheduledFor($request));

        return response()->json(["message" => 'Nepali site translation publication updated.']);
    }

    public function withdrawSite(PublicSiteTranslation $translation): JsonResponse
    {
        $this->publisher->withdrawSite($this->actor(), $translation);

        return response()->json(["message" => 'Nepali site translation withdrawn. English remains unchanged.']);
    }

    public function archiveSite(PublicSiteTranslation $translation): JsonResponse
    {
        $this->publisher->archiveSite($this->actor(), $translation);

        return response()->json(["message" => 'Nepali site translation archived.']);
    }

    public function restoreSite(PublicSiteTranslation $translation): JsonResponse
    {
        $this->publisher->restoreSite($this->actor(), $translation);

        return response()->json(["message" => 'Nepali site translation restored as a private draft.']);
    }

    private function contentEditor(string $type, Model $source, ?PublicContentTranslation $translation): JsonResponse
    {
        $revision = $this->editingRevision($translation) ?? new PublicContentTranslationRevision([
            'workflow_state' => 'draft', 'payload' => [],
        ]);
        $sourceSchema = $this->sources->payloadSchema($type, $source);
        $schema = [
            'title' => 'Nepali '.$this->sources->sourceLabel($type, $source),
            'subtitle' => 'English remains canonical while this separately reviewed Nepali translation is drafted and approved.',
            'fields' => $this->editorFields($sourceSchema['fields']),
            'collections' => $sourceSchema['collections'],
            'source_title' => $this->sources->sourceLabel($type, $source),
            'source_fields' => $this->sources->sourceFields($type, $source),
            'source_fingerprint' => $this->sources->fingerprint($type, $source),
            'revision' => $revision,
            'preview_url' => $translation !== null && $revision->exists
                ? route('admin.public-localisations.content.preview', [$translation, $revision])
                : null,
            'audit_events' => $this->auditEvents($translation),
            'translation_media' => $revision->media->keyBy('media_attachment_id')->map(fn ($item): array => ['alt_text' => $item->alt_text, 'caption' => $item->caption])->all(),
            'media' => $this->mediaEntries($type, $source),
            'update_url' => route('admin.public-localisations.content.save', [$type, $source->getKey()]),
            'update_method' => 'PUT',
            'workflow_urls' => $translation === null ? [] : $this->contentWorkflowUrls($translation, $revision),
        ];

        return response()->json([
            'translation' => $translation ?? new PublicContentTranslation(['publication_state' => 'not_started']),
            'source' => $source,
            'schema' => $schema,
            'canAuthor' => $this->allowsContent($type, 'author'),
            'canReview' => $this->allowsContent($type, 'review'),
            'canPublish' => $this->allowsContent($type, 'publish'),
        ]);
    }

    private function siteEditor(string $scope, ?PublicSiteTranslation $translation): JsonResponse
    {
        $revision = $this->editingRevision($translation) ?? new PublicSiteTranslationRevision(['workflow_state' => 'draft', 'payload' => []]);
        $sourceSchema = $this->publisher->siteSchema($scope);
        $schema = [
            'title' => 'Nepali '.ucfirst($scope).' translation',
            'subtitle' => 'English remains canonical while this separately reviewed Nepali translation is drafted and approved.',
            'fields' => $this->editorFields($sourceSchema['fields']),
            'source_title' => ucfirst($scope).' English source',
            'source_fingerprint' => $this->publisher->siteFingerprint($scope),
            'revision' => $revision,
            'preview_url' => $translation !== null && $revision->exists
                ? route('admin.public-localisations.site.preview', [$translation, $revision])
                : null,
            'audit_events' => $this->auditEvents($translation),
            'update_url' => route('admin.public-localisations.site.save', $scope),
            'update_method' => 'PUT',
            'workflow_urls' => $translation === null ? [] : $this->siteWorkflowUrls($translation, $revision),
        ];

        return response()->json([
            'translation' => $translation ?? new PublicSiteTranslation(['publication_state' => 'not_started']),
            'source' => ['title' => ucfirst($scope).' English source', 'publication_state' => 'published'],
            'schema' => $schema,
            'canAuthor' => $this->allowsSite($scope),
            'canReview' => $this->allowsSite($scope),
            'canPublish' => $this->allowsSite($scope),
        ]);
    }

    private function preview(
        PublicContentTranslationRevision|PublicSiteTranslationRevision $revision,
        string $sourceLabel,
        string $backUrl,
    ): JsonResponse {
        return response()->json([
            'revision' => $revision,
            'payload' => (array) $revision->payload,
            'sourceLabel' => $sourceLabel,
            'backUrl' => $backUrl,
        ]);
    }

    /** @return array<int, array{action: string, actor: string, at: string, revision: int|null}> */
    private function auditEvents(PublicContentTranslation|PublicSiteTranslation|null $translation): array
    {
        if ($translation === null) {
            return [];
        }

        return $translation->auditEvents->map(fn ($event): array => [
            'action' => (string) $event->action,
            'actor' => (string) ($event->actor?->name ?? 'System'),
            'at' => $event->created_at?->timezone(PublicTranslationPublisher::TIMEZONE)->format('j M Y, g:i A').' Nepal time',
            'revision' => $event->revision_id === null ? null : (int) $event->revision_id,
        ])->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function editorFields(array $fields): array
    {
        return array_map(static fn (array $field): array => $field + [
            // Authors may save an incomplete private draft. The badge still
            // explains what the publisher will require later.
            'required' => false,
            'max_length' => $field['max'] ?? null,
            'wide' => in_array($field['type'] ?? 'text', ['textarea', 'lines'], true),
        ], $fields);
    }

    /** @return array<int, array<string, mixed>> */
    private function mediaEntries(string $type, Model $source): array
    {
        $revisionId = $this->sources->currentRevisionId($source);
        $alias = match ($type) {
            PublicTranslationSource::NEWS_STORY => 'news-story-revision',
            PublicTranslationSource::GALLERY_ALBUM => 'gallery-album-revision',
            PublicTranslationSource::PUBLIC_PAGE => 'public-page-revision',
            PublicTranslationSource::PUBLIC_TEAM_PROFILE => 'public-team-profile-revision',
            default => null,
        };
        if ($alias === null || $revisionId === null) {
            return [];
        }

        return MediaAttachment::query()->with('asset')->where('attachable_type', $alias)->where('attachable_id', $revisionId)
            ->orderBy('display_order')->orderBy('id')->get()->map(fn (MediaAttachment $attachment): array => [
                'key' => (string) $attachment->id,
                'media_attachment_id' => $attachment->id,
                'label' => ucfirst((string) $attachment->placement),
                'asset_label' => (string) ($attachment->asset?->title ?? ''),
                'source_alt_text' => (string) ($attachment->alt_text ?: $attachment->asset?->default_alt_text),
                'source_caption' => (string) $attachment->caption,
                'consent_status' => (string) $attachment->asset?->consent_state,
                'alt_required' => ! (bool) $attachment->asset?->is_decorative,
            ])->all();
    }

    /** @return array<string, string> */
    private function contentWorkflowUrls(PublicContentTranslation $translation, PublicContentTranslationRevision $revision): array
    {
        return [
            'submit' => route('admin.public-localisations.content.submit', [$translation, $revision]),
            'approve' => route('admin.public-localisations.content.approve', [$translation, $revision]),
            'publish' => route('admin.public-localisations.content.publish', [$translation, $revision]),
            'withdraw' => route('admin.public-localisations.content.withdraw', $translation),
            'archive' => route('admin.public-localisations.content.archive', $translation),
            'restore' => route('admin.public-localisations.content.restore', $translation),
        ];
    }

    /** @return array<string, string> */
    private function siteWorkflowUrls(PublicSiteTranslation $translation, PublicSiteTranslationRevision $revision): array
    {
        return [
            'submit' => route('admin.public-localisations.site.submit', [$translation, $revision]),
            'approve' => route('admin.public-localisations.site.approve', [$translation, $revision]),
            'publish' => route('admin.public-localisations.site.publish', [$translation, $revision]),
            'withdraw' => route('admin.public-localisations.site.withdraw', $translation),
            'archive' => route('admin.public-localisations.site.archive', $translation),
            'restore' => route('admin.public-localisations.site.restore', $translation),
        ];
    }

    private function editingRevision(PublicContentTranslation|PublicSiteTranslation|null $translation): PublicContentTranslationRevision|PublicSiteTranslationRevision|null
    {
        if ($translation === null) {
            return null;
        }

        return $translation->revisions->filter(fn (Model $revision): bool => in_array($revision->workflow_state, ['draft', 'review', 'approved', 'scheduled'], true))
            ->sortByDesc('revision_number')->first() ?? $translation->publishedRevision;
    }

    private function ensureContentRevision(PublicContentTranslation $translation, PublicContentTranslationRevision $revision): void
    {
        abort_unless($translation->id === $revision->public_content_translation_id, 404);
    }

    private function ensureSiteRevision(PublicSiteTranslation $translation, PublicSiteTranslationRevision $revision): void
    {
        abort_unless($translation->id === $revision->public_site_translation_id, 404);
    }

    private function scheduledFor(Request $request): ?CarbonImmutable
    {
        $validated = $request->validate(['scheduled_for' => ['nullable', 'date', 'after:now']]);
        $value = trim((string) ($validated['scheduled_for'] ?? ''));

        return $value === '' ? null : CarbonImmutable::parse($value, PublicTranslationPublisher::TIMEZONE);
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function authorizeAnyContent(string $type): void
    {
        if (! $this->allowsContent($type, 'author') && ! $this->allowsContent($type, 'review') && ! $this->allowsContent($type, 'publish')) {
            abort(403);
        }
    }

    private function authorizeContent(string $type, string $stage): void
    {
        if ($type === PublicTranslationSource::NOTICE && Gate::allows('notices.manage')) {
            return;
        }

        Gate::authorize($this->contentAbility($type, $stage));
    }

    private function allowsContent(string $type, string $stage): bool
    {
        return ($type === PublicTranslationSource::NOTICE && Gate::allows('notices.manage'))
            || Gate::allows($this->contentAbility($type, $stage));
    }

    private function contentAbility(string $type, string $stage): string
    {
        return match ($type) {
            PublicTranslationSource::NEWS_STORY => 'news.'.$stage,
            PublicTranslationSource::GALLERY_ALBUM => 'gallery.'.$stage,
            PublicTranslationSource::NOTICE => 'notices.'.$stage,
            PublicTranslationSource::PUBLIC_PAGE => 'public_pages.'.$stage,
            PublicTranslationSource::PUBLIC_TEAM_PROFILE => 'public_team.'.$stage,
            PublicTranslationSource::ADMISSION_CYCLE => 'public_admissions.manage',
            PublicTranslationSource::SCHOOL_EVENT => 'public_calendar.manage',
            default => throw ValidationException::withMessages(['content_type' => 'Unsupported public translation source.']),
        };
    }

    private function authorizeAnySite(string $scope): void
    {
        $this->authorizeSite($scope);
    }

    private function authorizeSite(string $scope): void
    {
        Gate::authorize($this->siteAbility($scope));
    }

    private function allowsSite(string $scope): bool
    {
        return Gate::allows($this->siteAbility($scope));
    }

    private function siteAbility(string $scope): string
    {
        return match ($scope) {
            PublicSiteTranslation::SCOPE_HOMEPAGE => 'public_homepage.manage',
            PublicSiteTranslation::SCOPE_IDENTITY => 'public_identity.manage',
            default => throw ValidationException::withMessages(['scope' => 'Unsupported site translation scope.']),
        };
    }
}
