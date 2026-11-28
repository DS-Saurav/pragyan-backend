<?php

namespace App\Services\PublicTranslations;

use App\Models\MediaAttachment;
use App\Models\PublicContentTranslation;
use App\Models\PublicContentTranslationMedia;
use App\Models\PublicContentTranslationRevision;
use App\Models\PublicSiteTranslation;
use App\Models\PublicSiteTranslationRevision;
use App\Models\PublicTranslationAuditEvent;
use App\Models\Setting;
use App\Models\User;
use App\Support\SiteIdentity;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Additive lifecycle for Nepali public translations. It never writes to an
 * English source record; source visibility is checked again by the public
 * projection, so withdrawing Nepali cannot withdraw English.
 */
final class PublicTranslationPublisher
{
    public const TIMEZONE = 'Asia/Kathmandu';

    public function __construct(private PublicTranslationSource $sources) {}

    public function saveContentDraft(
        User $actor,
        string $contentType,
        Model $source,
        array $payload,
        array $media = [],
    ): PublicContentTranslation {
        $this->authorizeContent($actor, $contentType, 'author');
        $this->require($this->sources->typeFor($source) === $contentType, 'The selected English source does not match this translation type.');
        $payload = $this->sources->validatePayload($contentType, $source, $payload);
        $media = $this->normaliseMedia($media);

        return DB::transaction(function () use ($actor, $contentType, $source, $payload, $media): PublicContentTranslation {
            $translation = PublicContentTranslation::query()
                ->where('content_type', $contentType)
                ->where('content_id', $source->getKey())
                ->where('locale', PublicContentTranslation::LOCALE_NEPALI)
                ->lockForUpdate()
                ->first();

            if (! $translation instanceof PublicContentTranslation) {
                $translation = PublicContentTranslation::create([
                    'content_type' => $contentType,
                    'content_id' => $source->getKey(),
                    'locale' => PublicContentTranslation::LOCALE_NEPALI,
                    'publication_state' => 'draft',
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);
            }

            $this->require($translation->archived_at === null, 'Restore this archived translation before preparing a new revision.');
            $this->require($translation->scheduled_revision_id === null, 'Release, withdraw, or cancel the scheduled translation before preparing a new revision.');
            $revision = $this->draftRevision($translation, PublicContentTranslationRevision::class, $actor);
            $revision->fill([
                'source_revision_id' => $this->sources->currentRevisionId($source),
                'source_fingerprint' => $this->sources->fingerprint($contentType, $source),
                'payload' => $payload,
            ] + $this->draftReset($actor));
            $revision->save();
            $this->syncMedia($revision, $contentType, $source, $media);

            if ($translation->published_revision_id === null) {
                $translation->fill(['publication_state' => 'draft', 'withdrawn_at' => null]);
            }
            $translation->updated_by = $actor->id;
            $translation->save();
            $this->audit($translation, $revision, $actor, 'draft_saved');

            return $translation->fresh(['revisions.media', 'publishedRevision.media', 'scheduledRevision.media']);
        });
    }

    public function submitContent(User $actor, PublicContentTranslation $translation, PublicContentTranslationRevision $revision): PublicContentTranslationRevision
    {
        $this->authorizeContent($actor, $translation->content_type, 'author');

        /** @var PublicContentTranslationRevision $result */
        $result = $this->submit($actor, $translation, $revision);

        return $result;
    }

    public function approveContent(User $actor, PublicContentTranslation $translation, PublicContentTranslationRevision $revision): PublicContentTranslationRevision
    {
        $this->authorizeContent($actor, $translation->content_type, 'review');

        /** @var PublicContentTranslationRevision $result */
        $result = $this->approve($actor, $translation, $revision);

        return $result;
    }

    public function publishContent(
        User $actor,
        PublicContentTranslation $translation,
        PublicContentTranslationRevision $revision,
        ?CarbonInterface $scheduledFor = null,
    ): PublicContentTranslation {
        $this->authorizeContent($actor, $translation->content_type, 'publish');

        /** @var PublicContentTranslation $result */
        $result = DB::transaction(function () use ($actor, $translation, $revision, $scheduledFor): PublicContentTranslation {
            $root = $this->lockedRoot($translation);
            $current = $this->lockedRevision($root, $revision);
            $source = $this->sources->sourceFor($root->content_type, (int) $root->content_id);
            $this->assertReady($root, $current);
            $this->require($this->sources->isCurrentSnapshot($root->content_type, $source, $current), 'The English source changed. Save and review a fresh Nepali revision before publishing.');
            $this->assertPayloadComplete($root->content_type, $source, (array) $current->payload);
            $this->assertMediaSafe($current, $root->content_type, $source);

            return $this->publishRevision($actor, $root, $current, $scheduledFor);
        });

        return $result;
    }

    public function withdrawContent(User $actor, PublicContentTranslation $translation): PublicContentTranslation
    {
        $this->authorizeContent($actor, $translation->content_type, 'publish');

        /** @var PublicContentTranslation $result */
        $result = $this->withdraw($actor, $translation);

        return $result;
    }

    public function archiveContent(User $actor, PublicContentTranslation $translation): PublicContentTranslation
    {
        $this->authorizeContent($actor, $translation->content_type, 'publish');

        /** @var PublicContentTranslation $result */
        $result = $this->archive($actor, $translation);

        return $result;
    }

    public function restoreContent(User $actor, PublicContentTranslation $translation): PublicContentTranslation
    {
        $this->authorizeContent($actor, $translation->content_type, 'publish');

        /** @var PublicContentTranslation $result */
        $result = $this->restore($actor, $translation);

        return $result;
    }

    public function saveSiteDraft(User $actor, string $scope, array $payload): PublicSiteTranslation
    {
        $this->authorizeSite($actor, $scope);
        $payload = $this->validateSitePayload($scope, $payload);

        return DB::transaction(function () use ($actor, $scope, $payload): PublicSiteTranslation {
            $translation = PublicSiteTranslation::query()
                ->where('scope', $scope)
                ->where('locale', PublicSiteTranslation::LOCALE_NEPALI)
                ->lockForUpdate()
                ->first();
            if (! $translation instanceof PublicSiteTranslation) {
                $translation = PublicSiteTranslation::create([
                    'scope' => $scope,
                    'locale' => PublicSiteTranslation::LOCALE_NEPALI,
                    'publication_state' => 'draft',
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);
            }
            $this->require($translation->archived_at === null, 'Restore this archived translation before preparing a new revision.');
            $this->require($translation->scheduled_revision_id === null, 'Release, withdraw, or cancel the scheduled translation before preparing a new revision.');
            $revision = $this->draftRevision($translation, PublicSiteTranslationRevision::class, $actor);
            $revision->fill([
                'source_fingerprint' => $this->siteFingerprint($scope),
                'payload' => $payload,
            ] + $this->draftReset($actor));
            $revision->save();
            if ($translation->published_revision_id === null) {
                $translation->fill(['publication_state' => 'draft', 'withdrawn_at' => null]);
            }
            $translation->updated_by = $actor->id;
            $translation->save();
            $this->audit($translation, $revision, $actor, 'draft_saved');

            return $translation->fresh(['revisions', 'publishedRevision', 'scheduledRevision']);
        });
    }

    public function submitSite(User $actor, PublicSiteTranslation $translation, PublicSiteTranslationRevision $revision): PublicSiteTranslationRevision
    {
        $this->authorizeSite($actor, $translation->scope);

        /** @var PublicSiteTranslationRevision $result */
        $result = $this->submit($actor, $translation, $revision);

        return $result;
    }

    public function approveSite(User $actor, PublicSiteTranslation $translation, PublicSiteTranslationRevision $revision): PublicSiteTranslationRevision
    {
        $this->authorizeSite($actor, $translation->scope);

        /** @var PublicSiteTranslationRevision $result */
        $result = $this->approve($actor, $translation, $revision);

        return $result;
    }

    public function publishSite(
        User $actor,
        PublicSiteTranslation $translation,
        PublicSiteTranslationRevision $revision,
        ?CarbonInterface $scheduledFor = null,
    ): PublicSiteTranslation {
        $this->authorizeSite($actor, $translation->scope);

        /** @var PublicSiteTranslation $result */
        $result = DB::transaction(function () use ($actor, $translation, $revision, $scheduledFor): PublicSiteTranslation {
            $root = $this->lockedRoot($translation);
            $current = $this->lockedRevision($root, $revision);
            $this->assertReady($root, $current);
            $this->require(hash_equals($this->siteFingerprint($root->scope), (string) $current->source_fingerprint), 'The English source changed. Save and review a fresh Nepali revision before publishing.');
            $this->assertSitePayloadComplete($root->scope, (array) $current->payload);

            return $this->publishRevision($actor, $root, $current, $scheduledFor);
        });

        return $result;
    }

    public function withdrawSite(User $actor, PublicSiteTranslation $translation): PublicSiteTranslation
    {
        $this->authorizeSite($actor, $translation->scope);

        /** @var PublicSiteTranslation $result */
        $result = $this->withdraw($actor, $translation);

        return $result;
    }

    public function archiveSite(User $actor, PublicSiteTranslation $translation): PublicSiteTranslation
    {
        $this->authorizeSite($actor, $translation->scope);

        /** @var PublicSiteTranslation $result */
        $result = $this->archive($actor, $translation);

        return $result;
    }

    public function restoreSite(User $actor, PublicSiteTranslation $translation): PublicSiteTranslation
    {
        $this->authorizeSite($actor, $translation->scope);

        /** @var PublicSiteTranslation $result */
        $result = $this->restore($actor, $translation);

        return $result;
    }

    /** Scheduler-only release; public GET routes never call this. */
    public function releaseDue(?CarbonInterface $now = null): void
    {
        $time = $this->atSchoolTime($now ?? $this->now());
        foreach (PublicContentTranslation::query()->whereNotNull('scheduled_revision_id')->where('scheduled_for', '<=', $time)->pluck('id') as $id) {
            DB::transaction(function () use ($id, $time): void {
                $root = PublicContentTranslation::query()->lockForUpdate()->find($id);
                if (! $root instanceof PublicContentTranslation || ! $this->due($root, $time)) {
                    return;
                }
                $revision = $root->revisions()->whereKey($root->scheduled_revision_id)->lockForUpdate()->first();
                if (! $revision instanceof PublicContentTranslationRevision || $revision->workflow_state !== 'scheduled') {
                    $this->clearSchedule($root);
                    $root->save();

                    return;
                }
                try {
                    $source = $this->sources->sourceFor($root->content_type, (int) $root->content_id);
                    $this->require($this->sources->isCurrentSnapshot($root->content_type, $source, $revision), 'Source changed.');
                    $this->assertPayloadComplete($root->content_type, $source, (array) $revision->payload);
                    $this->assertMediaSafe($revision, $root->content_type, $source);
                    $this->activate($root, $revision, null, $time);
                } catch (\Throwable) {
                    $revision->workflow_state = 'approved';
                    $revision->save();
                    $this->clearSchedule($root);
                    if ($root->published_revision_id === null) {
                        $root->publication_state = 'draft';
                    }
                    $root->save();
                }
            });
        }

        foreach (PublicSiteTranslation::query()->whereNotNull('scheduled_revision_id')->where('scheduled_for', '<=', $time)->pluck('id') as $id) {
            DB::transaction(function () use ($id, $time): void {
                $root = PublicSiteTranslation::query()->lockForUpdate()->find($id);
                if (! $root instanceof PublicSiteTranslation || ! $this->due($root, $time)) {
                    return;
                }
                $revision = $root->revisions()->whereKey($root->scheduled_revision_id)->lockForUpdate()->first();
                if (! $revision instanceof PublicSiteTranslationRevision || $revision->workflow_state !== 'scheduled') {
                    $this->clearSchedule($root);
                    $root->save();

                    return;
                }
                if (! hash_equals($this->siteFingerprint($root->scope), (string) $revision->source_fingerprint)) {
                    $revision->workflow_state = 'approved';
                    $revision->save();
                    $this->clearSchedule($root);
                    if ($root->published_revision_id === null) {
                        $root->publication_state = 'draft';
                    }
                    $root->save();

                    return;
                }
                $this->activate($root, $revision, null, $time);
            });
        }
    }

    /** @return array<string, mixed> */
    public function siteSchema(string $scope): array
    {
        return match ($scope) {
            PublicSiteTranslation::SCOPE_HOMEPAGE => [
                'fields' => [
                    ['key' => 'hero_headline', 'label' => 'Hero headline', 'type' => 'text', 'max' => 160, 'requiredForPublish' => true],
                    ['key' => 'hero_intro', 'label' => 'Hero introduction', 'type' => 'textarea', 'max' => 400, 'requiredForPublish' => false],
                    ['key' => 'primary_action_label', 'label' => 'Primary action label', 'type' => 'text', 'max' => 60, 'requiredForPublish' => false],
                    ['key' => 'secondary_action_label', 'label' => 'Secondary action label', 'type' => 'text', 'max' => 60, 'requiredForPublish' => false],
                    ['key' => 'home_intro', 'label' => 'Homepage introduction', 'type' => 'textarea', 'max' => 900, 'requiredForPublish' => false],
                    ['key' => 'highlights', 'label' => 'Learning highlights', 'type' => 'lines', 'max' => 220, 'max_items' => 6, 'requiredForPublish' => false],
                ],
            ],
            PublicSiteTranslation::SCOPE_IDENTITY => [
                'fields' => [
                    ['key' => 'name', 'label' => 'School name', 'type' => 'text', 'max' => 180, 'requiredForPublish' => true],
                    ['key' => 'tagline', 'label' => 'Tagline', 'type' => 'text', 'max' => 255, 'requiredForPublish' => false],
                    ['key' => 'address', 'label' => 'Address', 'type' => 'textarea', 'max' => 2000, 'requiredForPublish' => false],
                    ['key' => 'location', 'label' => 'Location', 'type' => 'textarea', 'max' => 500, 'requiredForPublish' => false],
                    ['key' => 'office_hours', 'label' => 'Office hours', 'type' => 'textarea', 'max' => 1000, 'requiredForPublish' => false],
                    ['key' => 'visit_instructions', 'label' => 'Visit guidance', 'type' => 'textarea', 'max' => 3000, 'requiredForPublish' => false],
                ],
            ],
            default => throw ValidationException::withMessages(['scope' => 'Unsupported site translation scope.']),
        };
    }

    public function siteFingerprint(string $scope): string
    {
        $snapshot = match ($scope) {
            PublicSiteTranslation::SCOPE_HOMEPAGE => [
                'headline' => (string) Setting::get('website_hero_headline'),
                'intro' => (string) Setting::get('website_hero_intro'),
                'primary' => (string) Setting::get('website_cta_primary_text'),
                'secondary' => (string) Setting::get('website_cta_secondary_text'),
                'home_intro' => (string) Setting::get('website_home_intro'),
                'highlights' => (string) Setting::get('website_home_highlights'),
            ],
            PublicSiteTranslation::SCOPE_IDENTITY => array_intersect_key(
                SiteIdentity::publicProfile(),
                array_flip(['name', 'tagline', 'address', 'location', 'office_hours', 'visit_instructions']),
            ),
            default => throw ValidationException::withMessages(['scope' => 'Unsupported site translation scope.']),
        };

        return hash('sha256', (string) json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function submit(User $actor, Model $translation, Model $revision): Model
    {
        return DB::transaction(function () use ($actor, $translation, $revision): Model {
            $root = $this->lockedRoot($translation);
            $current = $this->lockedRevision($root, $revision);
            $this->require($root->archived_at === null, 'An archived translation cannot enter review.');
            $this->require($current->workflow_state === 'draft', 'Only a draft translation revision can enter review.');
            $current->fill(['workflow_state' => 'review', 'submitted_by' => $actor->id, 'submitted_at' => $this->now(), 'updated_by' => $actor->id]);
            $current->save();
            if ($root->published_revision_id === null) {
                $root->publication_state = 'review';
            }
            $root->updated_by = $actor->id;
            $root->save();
            $this->audit($root, $current, $actor, 'submitted_for_review');

            return $current->refresh();
        });
    }

    private function approve(User $actor, Model $translation, Model $revision): Model
    {
        return DB::transaction(function () use ($actor, $translation, $revision): Model {
            $root = $this->lockedRoot($translation);
            $current = $this->lockedRevision($root, $revision);
            $this->require($root->archived_at === null, 'An archived translation cannot be approved.');
            $this->require($current->workflow_state === 'review', 'Only a translation revision in review can be approved.');
            $this->require(
                $this->isAdministrator($actor)
                    || ($current->created_by !== $actor->id && $current->submitted_by !== $actor->id),
                'A different authorized reviewer must approve the translator’s revision.',
            );
            $current->fill(['workflow_state' => 'approved', 'reviewed_by' => $actor->id, 'reviewed_at' => $this->now(), 'updated_by' => $actor->id]);
            $current->save();
            $root->updated_by = $actor->id;
            $root->save();
            $this->audit($root, $current, $actor, 'approved');

            return $current->refresh();
        });
    }

    private function publishRevision(User $actor, Model $root, Model $revision, ?CarbonInterface $scheduledFor): Model
    {
        $when = $scheduledFor === null ? null : $this->atSchoolTime($scheduledFor);
        if ($when !== null && $when->isFuture()) {
            $this->require($root->scheduled_revision_id === null || $root->scheduled_revision_id === $revision->id, 'This translation already has a scheduled revision.');
            $revision->fill(['workflow_state' => 'scheduled', 'published_by' => $actor->id, 'updated_by' => $actor->id]);
            $revision->save();
            $root->fill([
                'publication_state' => $root->published_revision_id === null ? 'scheduled' : 'published',
                'scheduled_revision_id' => $revision->id,
                'scheduled_for' => $when,
                'updated_by' => $actor->id,
            ]);
            $root->save();
            $this->audit($root, $revision, $actor, 'scheduled', ['scheduled_for' => $when->toAtomString()]);

            return $root->fresh(['publishedRevision', 'scheduledRevision']);
        }
        $this->require($root->scheduled_revision_id === null || $root->scheduled_revision_id === $revision->id, 'This translation already has a different scheduled revision.');
        $this->activate($root, $revision, $actor, $this->now());

        return $root->fresh(['publishedRevision', 'scheduledRevision']);
    }

    private function withdraw(User $actor, Model $translation): Model
    {
        return DB::transaction(function () use ($actor, $translation): Model {
            $root = $this->lockedRoot($translation);
            $this->require($root->publication_state === 'published', 'Only a published translation can be withdrawn.');
            $this->clearSchedule($root);
            $root->fill(['publication_state' => 'withdrawn', 'withdrawn_at' => $this->now(), 'updated_by' => $actor->id]);
            $root->save();
            $this->audit($root, null, $actor, 'withdrawn');

            return $root->fresh();
        });
    }

    private function archive(User $actor, Model $translation): Model
    {
        return DB::transaction(function () use ($actor, $translation): Model {
            $root = $this->lockedRoot($translation);
            $this->require($root->archived_at === null, 'This translation is already archived.');
            $this->clearSchedule($root);
            $root->fill(['publication_state' => 'archived', 'archived_at' => $this->now(), 'updated_by' => $actor->id]);
            $root->save();
            $this->audit($root, null, $actor, 'archived');

            return $root->fresh();
        });
    }

    private function restore(User $actor, Model $translation): Model
    {
        return DB::transaction(function () use ($actor, $translation): Model {
            $root = $this->lockedRoot($translation);
            $this->require($root->archived_at !== null, 'Only an archived translation can be restored.');
            $this->clearSchedule($root);
            $root->fill([
                'publication_state' => 'draft',
                'published_revision_id' => null,
                'published_at' => null,
                'published_by' => null,
                'withdrawn_at' => null,
                'archived_at' => null,
                'updated_by' => $actor->id,
            ]);
            $root->save();
            $this->audit($root, null, $actor, 'restored');

            return $root->fresh();
        });
    }

    private function draftRevision(Model $root, string $revisionClass, User $actor): Model
    {
        $draft = $root->revisions()->where('workflow_state', 'draft')->orderByDesc('revision_number')->lockForUpdate()->first();
        if ($draft instanceof Model) {
            return $draft;
        }

        return $root->revisions()->create([
            'revision_number' => ((int) $root->revisions()->max('revision_number')) + 1,
            'workflow_state' => 'draft',
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }

    /** @return array<string, mixed> */
    private function draftReset(User $actor): array
    {
        return [
            'workflow_state' => 'draft',
            'updated_by' => $actor->id,
            'submitted_by' => null,
            'submitted_at' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'published_by' => null,
            'published_at' => null,
        ];
    }

    private function lockedRoot(Model $root): Model
    {
        return $root->newQuery()->lockForUpdate()->findOrFail($root->getKey());
    }

    private function lockedRevision(Model $root, Model $revision): Model
    {
        return $root->revisions()->whereKey($revision->getKey())->lockForUpdate()->firstOrFail();
    }

    private function activate(Model $root, Model $revision, ?User $actor, CarbonInterface $publishedAt): void
    {
        $revision->fill([
            'workflow_state' => 'published',
            'published_by' => $actor?->id ?? $revision->published_by,
            'published_at' => $publishedAt,
            'updated_by' => $actor?->id ?? $revision->updated_by,
        ]);
        $revision->save();
        $root->fill([
            'publication_state' => 'published',
            'published_revision_id' => $revision->id,
            'published_at' => $publishedAt,
            'published_by' => $actor?->id ?? $root->published_by,
            'scheduled_revision_id' => null,
            'scheduled_for' => null,
            'withdrawn_at' => null,
            'updated_by' => $actor?->id ?? $root->updated_by,
        ]);
        $root->save();
        $this->audit($root, $revision, $actor, $actor === null ? 'released' : 'published');
    }

    private function clearSchedule(Model $root): void
    {
        if ($root->scheduled_revision_id !== null) {
            $revision = $root->revisions()->whereKey($root->scheduled_revision_id)->lockForUpdate()->first();
            if ($revision instanceof Model && $revision->workflow_state === 'scheduled') {
                $revision->workflow_state = 'approved';
                $revision->save();
            }
        }
        $root->scheduled_revision_id = null;
        $root->scheduled_for = null;
    }

    private function due(Model $root, CarbonInterface $time): bool
    {
        return $root->archived_at === null
            && $root->publication_state !== 'withdrawn'
            && $root->scheduled_revision_id !== null
            && $root->scheduled_for !== null
            && $root->scheduled_for->lessThanOrEqualTo($time);
    }

    private function assertReady(Model $root, Model $revision): void
    {
        $this->require($root->archived_at === null, 'Restore an archived translation before publishing it.');
        $this->require($revision->workflow_state === 'approved', 'Only an approved translation revision can be published or scheduled.');
    }

    private function assertPayloadComplete(string $type, Model $source, array $payload): void
    {
        $schema = $this->sources->payloadSchema($type, $source);
        foreach ($schema['fields'] as $field) {
            if (($field['requiredForPublish'] ?? false) && trim((string) ($payload[$field['key']] ?? '')) === '') {
                throw ValidationException::withMessages(['payload.'.$field['key'] => 'Complete this Nepali field before publication.']);
            }
        }
        foreach ($schema['collections'] as $collection) {
            $items = $payload[$collection['key']] ?? [];
            if (! is_array($items)) {
                throw ValidationException::withMessages(['payload.'.$collection['key'] => 'The translated source rows are incomplete.']);
            }
            foreach ($collection['source_ids'] as $sourceId) {
                $row = $items[(string) $sourceId] ?? $items[$sourceId] ?? null;
                if (! is_array($row)) {
                    throw ValidationException::withMessages(['payload.'.$collection['key'] => 'Translate every current English source row before publication.']);
                }
                foreach ($collection['fields'] as $field) {
                    if (($field['requiredForPublish'] ?? false) && trim((string) ($row[$field['key']] ?? '')) === '') {
                        throw ValidationException::withMessages(['payload.'.$collection['key'] => 'Complete every required Nepali source-row field before publication.']);
                    }
                }
            }
        }
    }

    private function assertSitePayloadComplete(string $scope, array $payload): void
    {
        foreach ($this->siteSchema($scope)['fields'] as $field) {
            if (($field['requiredForPublish'] ?? false) && trim((string) ($payload[$field['key']] ?? '')) === '') {
                throw ValidationException::withMessages(['payload.'.$field['key'] => 'Complete this Nepali field before publication.']);
            }
        }
    }

    /** @param array<int, array<string, mixed>> $media */
    private function syncMedia(PublicContentTranslationRevision $revision, string $type, Model $source, array $media): void
    {
        $attachments = $this->sourceAttachments($type, $source)->keyBy('id');
        foreach ($media as $entry) {
            $attachment = $attachments->get($entry['media_attachment_id']);
            $this->require($attachment instanceof MediaAttachment, 'Localized media text must refer to a current English source placement.');
        }
        $revision->media()->delete();
        foreach ($media as $entry) {
            $revision->media()->create($entry);
        }
    }

    private function assertMediaSafe(PublicContentTranslationRevision $revision, string $type, Model $source): void
    {
        $annotations = $revision->media()->get()->keyBy('media_attachment_id');
        foreach ($this->sourceAttachments($type, $source) as $attachment) {
            $asset = $attachment->asset;
            $this->require(
                $asset !== null
                    && $asset->status === 'published'
                    && $asset->archived_at === null
                    && in_array($asset->consent_state, ['not_required', 'confirmed'], true)
                    && $asset->consent_withdrawn_at === null
                    && $asset->public_path !== null,
                'A source image is no longer a consent-cleared public derivative.',
            );
            if (! $asset->is_decorative) {
                $annotation = $annotations->get($attachment->id);
                $this->require(
                    $annotation instanceof PublicContentTranslationMedia && trim((string) $annotation->alt_text) !== '',
                    'Each non-decorative image needs meaningful approved Nepali alt text before publication.',
                );
            }
        }
    }

    /** @return Collection<int, MediaAttachment> */
    private function sourceAttachments(string $type, Model $source): Collection
    {
        $sourceRevisionId = $this->sources->currentRevisionId($source);
        $alias = match ($type) {
            PublicTranslationSource::NEWS_STORY => 'news-story-revision',
            PublicTranslationSource::PUBLIC_PAGE => 'public-page-revision',
            PublicTranslationSource::PUBLIC_TEAM_PROFILE => 'public-team-profile-revision',
            default => null,
        };
        if ($alias === null || $sourceRevisionId === null) {
            return collect();
        }

        return MediaAttachment::query()->with('asset')
            ->where('attachable_type', $alias)
            ->where('attachable_id', $sourceRevisionId)
            ->orderBy('display_order')->orderBy('id')->get();
    }

    /** @return array<int, array{media_attachment_id: int, alt_text: ?string, caption: ?string}> */
    private function normaliseMedia(array $media): array
    {
        $normalised = [];
        foreach ($media as $key => $entry) {
            if (! is_array($entry)) {
                throw ValidationException::withMessages(['media' => 'Each localized media entry must be an object.']);
            }
            $id = $entry['media_attachment_id'] ?? $key;
            if (! is_numeric($id) || (int) $id < 1) {
                throw ValidationException::withMessages(['media' => 'Each localized media entry needs its source placement id.']);
            }
            $validated = Validator::make($entry, [
                'alt_text' => ['nullable', 'string', 'max:255'],
                'caption' => ['nullable', 'string', 'max:10000'],
            ])->validate();
            $normalised[] = [
                'media_attachment_id' => (int) $id,
                'alt_text' => $this->nullableString($validated['alt_text'] ?? null),
                'caption' => $this->nullableString($validated['caption'] ?? null),
            ];
        }
        if (count(array_unique(array_column($normalised, 'media_attachment_id'))) !== count($normalised)) {
            throw ValidationException::withMessages(['media' => 'A source media placement may be translated only once per revision.']);
        }

        return $normalised;
    }

    /** @return array<string, mixed> */
    private function validateSitePayload(string $scope, array $payload): array
    {
        $schema = $this->siteSchema($scope);
        $rules = [];
        foreach ($schema['fields'] as $field) {
            $rules[$field['key']] = ($field['type'] ?? 'text') === 'lines'
                ? ['nullable']
                : ['nullable', 'string', 'max:'.$field['max']];
        }
        $unexpected = array_diff(array_keys($payload), array_keys($rules));
        if ($unexpected !== []) {
            throw ValidationException::withMessages(['payload' => 'Unsupported site translation fields were submitted.']);
        }
        $validated = Validator::make($payload, $rules)->validate();
        foreach ($schema['fields'] as $field) {
            $key = $field['key'];
            if (($field['type'] ?? 'text') === 'lines') {
                $validated[$key] = $this->normaliseLines(
                    $validated[$key] ?? null,
                    (int) ($field['max_items'] ?? 6),
                    (int) $field['max'],
                );
            } else {
                $validated[$key] = $this->nullableString($validated[$key] ?? null);
            }
        }

        return $validated;
    }

    private function authorizeContent(User $actor, string $type, string $stage): void
    {
        // Notices retain their established manager override. This is not a
        // new grant: the existing Notice publisher already lets a designated
        // notice manager perform each editorial stage.
        if ($type === PublicTranslationSource::NOTICE && Gate::forUser($actor)->allows('notices.manage')) {
            return;
        }

        $ability = match ($type) {
            PublicTranslationSource::NEWS_STORY => 'news.'.$stage,
            PublicTranslationSource::GALLERY_ALBUM => 'gallery.'.$stage,
            PublicTranslationSource::NOTICE => 'notices.'.$stage,
            PublicTranslationSource::PUBLIC_PAGE => 'public_pages.'.$stage,
            PublicTranslationSource::PUBLIC_TEAM_PROFILE => 'public_team.'.$stage,
            PublicTranslationSource::ADMISSION_CYCLE => 'public_admissions.manage',
            PublicTranslationSource::SCHOOL_EVENT => 'public_calendar.manage',
            default => throw ValidationException::withMessages(['content_type' => 'Unsupported public translation source.']),
        };
        Gate::forUser($actor)->authorize($ability);
    }

    private function authorizeSite(User $actor, string $scope): void
    {
        Gate::forUser($actor)->authorize(match ($scope) {
            PublicSiteTranslation::SCOPE_HOMEPAGE => 'public_homepage.manage',
            PublicSiteTranslation::SCOPE_IDENTITY => 'public_identity.manage',
            default => throw ValidationException::withMessages(['scope' => 'Unsupported site translation scope.']),
        });
    }

    private function isAdministrator(User $actor): bool
    {
        return $actor->hasRole('admin')
            && in_array('*', config('permissions.roles.admin', []), true);
    }

    private function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TIMEZONE)->utc();
    }

    private function atSchoolTime(CarbonInterface $time): CarbonImmutable
    {
        return CarbonImmutable::instance($time)->setTimezone(self::TIMEZONE)->utc();
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** @return array<int, string> */
    private function normaliseLines(mixed $value, int $maxItems, int $maxLength): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (is_string($value)) {
            $value = preg_split('/\r\n|\r|\n/', $value) ?: [];
        }
        if (! is_array($value) || count($value) > $maxItems) {
            throw ValidationException::withMessages(['payload' => 'The translation contains an invalid number of list entries.']);
        }

        $lines = [];
        foreach ($value as $line) {
            if (! is_string($line)) {
                throw ValidationException::withMessages(['payload' => 'Each list entry must be text.']);
            }
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (mb_strlen($line) > $maxLength) {
                throw ValidationException::withMessages(['payload' => 'A list entry is too long.']);
            }
            $lines[] = $line;
        }

        return array_values($lines);
    }

    /**
     * Record only workflow metadata. Payloads and source copy are deliberately
     * excluded so the audit timeline stays private, compact, and non-sensitive.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function audit(Model $root, ?Model $revision, ?User $actor, string $action, array $metadata = []): void
    {
        $type = match (true) {
            $root instanceof PublicContentTranslation => PublicTranslationAuditEvent::TYPE_CONTENT,
            $root instanceof PublicSiteTranslation => PublicTranslationAuditEvent::TYPE_SITE,
            default => throw new \LogicException('Unsupported public translation audit root.'),
        };

        PublicTranslationAuditEvent::create([
            'translation_type' => $type,
            'translation_id' => $root->getKey(),
            'revision_id' => $revision?->getKey(),
            'action' => $action,
            'actor_id' => $actor?->id,
            'metadata' => $metadata === [] ? null : $metadata,
            'created_at' => $this->now(),
        ]);
    }

    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['translation' => $message]);
        }
    }
}
