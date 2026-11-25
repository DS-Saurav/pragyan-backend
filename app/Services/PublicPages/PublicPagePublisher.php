<?php

namespace App\Services\PublicPages;

use App\Models\PublicPage;
use App\Models\PublicPageRevision;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PublicPagePublisher
{
    public const TIMEZONE = 'Asia/Kathmandu';

    public function createDraft(User $actor, string $type, array $data): PublicPage
    {
        $this->authorize($actor, 'public_pages.author');
        $this->validateType($type);
        $attributes = $this->draftAttributes($type, $data);

        return DB::transaction(function () use ($actor, $type, $attributes): PublicPage {
            $existing = PublicPage::query()->where('type', $type)->lockForUpdate()->first();
            $this->require($existing === null, 'This public page already exists. Edit its current draft instead.');

            $page = PublicPage::create([
                'type' => $type,
                'publication_state' => 'draft',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $revision = $page->revisions()->create($this->revisionAttributes($attributes) + [
                'revision_number' => 1,
                'workflow_state' => 'draft',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $this->replaceAcademicStages($revision, $attributes['stages']);

            return $page->fresh(['revisions.academicStages']);
        });
    }

    public function saveDraft(User $actor, PublicPage $page, array $data): PublicPageRevision
    {
        $this->authorize($actor, 'public_pages.author');

        return DB::transaction(function () use ($actor, $page, $data): PublicPageRevision {
            $lockedPage = PublicPage::query()->lockForUpdate()->findOrFail($page->getKey());
            $this->require($lockedPage->archived_at === null, 'Restore an archived public page before preparing a new revision.');
            $this->require($lockedPage->scheduled_revision_id === null, 'Publish, withdraw, or cancel the scheduled revision before preparing another one.');
            $attributes = $this->draftAttributes($lockedPage->type, $data);

            $revision = $lockedPage->revisions()
                ->where('workflow_state', 'draft')
                ->orderByDesc('revision_number')
                ->lockForUpdate()
                ->first();

            if ($revision === null) {
                $revision = $lockedPage->revisions()->create($this->revisionAttributes($attributes) + [
                    'revision_number' => ((int) $lockedPage->revisions()->max('revision_number')) + 1,
                    'workflow_state' => 'draft',
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);
            }

            $revision->fill($this->revisionAttributes($attributes) + [
                'workflow_state' => 'draft',
                'updated_by' => $actor->id,
                'submitted_by' => null,
                'submitted_at' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'published_by' => null,
                'published_at' => null,
            ]);
            $revision->save();
            $this->replaceAcademicStages($revision, $attributes['stages']);

            if ($lockedPage->published_revision_id === null && $lockedPage->publication_state !== 'withdrawn') {
                $lockedPage->publication_state = 'draft';
            }
            $lockedPage->updated_by = $actor->id;
            $lockedPage->save();

            return $revision->fresh(['academicStages']);
        });
    }

    public function submit(User $actor, PublicPage $page, PublicPageRevision $revision): PublicPageRevision
    {
        $this->authorize($actor, 'public_pages.author');

        return DB::transaction(function () use ($actor, $page, $revision): PublicPageRevision {
            $lockedPage = PublicPage::query()->lockForUpdate()->findOrFail($page->getKey());
            $lockedRevision = $this->lockedRevision($lockedPage, $revision);
            $this->require($lockedPage->archived_at === null, 'An archived public page cannot enter review.');
            $this->require($lockedRevision->workflow_state === 'draft', 'Only a draft revision can enter review.');

            $lockedRevision->fill([
                'workflow_state' => 'review',
                'submitted_by' => $actor->id,
                'submitted_at' => $this->now(),
                'updated_by' => $actor->id,
            ]);
            $lockedRevision->save();

            if ($lockedPage->published_revision_id === null && $lockedPage->publication_state !== 'withdrawn') {
                $lockedPage->publication_state = 'review';
            }
            $lockedPage->updated_by = $actor->id;
            $lockedPage->save();

            return $lockedRevision->refresh();
        });
    }

    public function approve(User $actor, PublicPage $page, PublicPageRevision $revision): PublicPageRevision
    {
        $this->authorize($actor, 'public_pages.review');

        return DB::transaction(function () use ($actor, $page, $revision): PublicPageRevision {
            $lockedPage = PublicPage::query()->lockForUpdate()->findOrFail($page->getKey());
            $lockedRevision = $this->lockedRevision($lockedPage, $revision);
            $this->require($lockedPage->archived_at === null, 'An archived public page cannot be approved.');
            $this->require($lockedRevision->workflow_state === 'review', 'Only a revision in review can be approved.');
            $this->require(
                $this->isAdministrator($actor)
                    || ($lockedRevision->created_by !== $actor->id && $lockedRevision->submitted_by !== $actor->id),
                'A different authorized reviewer must approve the author’s revision.',
            );

            $lockedRevision->fill([
                'workflow_state' => 'approved',
                'reviewed_by' => $actor->id,
                'reviewed_at' => $this->now(),
                'updated_by' => $actor->id,
            ]);
            $lockedRevision->save();
            $lockedPage->updated_by = $actor->id;
            $lockedPage->save();

            return $lockedRevision->refresh();
        });
    }

    public function publish(
        User $actor,
        PublicPage $page,
        PublicPageRevision $revision,
        ?CarbonInterface $scheduledFor = null,
    ): PublicPage {
        $this->authorize($actor, 'public_pages.publish');

        return DB::transaction(function () use ($actor, $page, $revision, $scheduledFor): PublicPage {
            $lockedPage = PublicPage::query()->lockForUpdate()->findOrFail($page->getKey());
            $lockedRevision = $this->lockedRevision($lockedPage, $revision);
            $this->require($lockedPage->archived_at === null, 'Restore an archived public page before publishing it.');
            $this->require($lockedRevision->workflow_state === 'approved', 'Only an approved revision can be published or scheduled.');
            $this->assertRevisionPublishable($lockedPage, $lockedRevision);

            $when = $scheduledFor === null ? null : $this->atSchoolTime($scheduledFor);
            if ($when !== null && $when->isFuture()) {
                $this->require(
                    $lockedPage->scheduled_revision_id === null || $lockedPage->scheduled_revision_id === $lockedRevision->id,
                    'This public page already has a scheduled revision.',
                );
                $lockedRevision->fill([
                    'workflow_state' => 'scheduled',
                    'published_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);
                $lockedRevision->save();
                $lockedPage->fill([
                    'scheduled_revision_id' => $lockedRevision->id,
                    'scheduled_for' => $when,
                    'updated_by' => $actor->id,
                ]);
                $lockedPage->save();

                return $lockedPage->fresh(['publishedRevision', 'scheduledRevision']);
            }

            $this->require(
                $lockedPage->scheduled_revision_id === null || $lockedPage->scheduled_revision_id === $lockedRevision->id,
                'This public page already has a different scheduled revision.',
            );
            $this->activateRevision($lockedPage, $lockedRevision, $actor, $this->now());

            return $lockedPage->fresh(['publishedRevision', 'scheduledRevision']);
        });
    }

    public function withdraw(User $actor, PublicPage $page): PublicPage
    {
        $this->authorize($actor, 'public_pages.publish');

        return DB::transaction(function () use ($actor, $page): PublicPage {
            $lockedPage = PublicPage::query()->lockForUpdate()->findOrFail($page->getKey());
            $this->require($lockedPage->publication_state === 'published', 'Only a published public page can be withdrawn.');
            $this->clearScheduledRevision($lockedPage);
            $lockedPage->fill([
                'publication_state' => 'withdrawn',
                'withdrawn_at' => $this->now(),
                'updated_by' => $actor->id,
            ]);
            $lockedPage->save();

            return $lockedPage->fresh();
        });
    }

    public function cancelSchedule(User $actor, PublicPage $page): PublicPage
    {
        $this->authorize($actor, 'public_pages.publish');

        return DB::transaction(function () use ($actor, $page): PublicPage {
            $lockedPage = PublicPage::query()->lockForUpdate()->findOrFail($page->getKey());
            $this->require($lockedPage->scheduled_revision_id !== null, 'There is no scheduled public page revision to cancel.');
            $this->clearScheduledRevision($lockedPage);

            if ($lockedPage->published_revision_id === null) {
                $lockedPage->publication_state = 'draft';
            }

            $lockedPage->updated_by = $actor->id;
            $lockedPage->save();

            return $lockedPage->fresh(['publishedRevision', 'scheduledRevision']);
        });
    }

    public function archive(User $actor, PublicPage $page): PublicPage
    {
        $this->authorize($actor, 'public_pages.publish');

        return DB::transaction(function () use ($actor, $page): PublicPage {
            $lockedPage = PublicPage::query()->lockForUpdate()->findOrFail($page->getKey());
            $this->require($lockedPage->archived_at === null, 'This public page is already archived.');
            $this->clearScheduledRevision($lockedPage);
            $lockedPage->fill([
                'publication_state' => 'archived',
                'archived_at' => $this->now(),
                'updated_by' => $actor->id,
            ]);
            $lockedPage->save();

            return $lockedPage->fresh();
        });
    }

    public function restore(User $actor, PublicPage $page): PublicPage
    {
        $this->authorize($actor, 'public_pages.publish');

        return DB::transaction(function () use ($actor, $page): PublicPage {
            $lockedPage = PublicPage::query()->lockForUpdate()->findOrFail($page->getKey());
            $this->require($lockedPage->archived_at !== null, 'Only an archived public page can be restored.');
            $this->clearScheduledRevision($lockedPage);
            $lockedPage->fill([
                'publication_state' => 'draft',
                'published_revision_id' => null,
                'published_at' => null,
                'withdrawn_at' => null,
                'archived_at' => null,
                'updated_by' => $actor->id,
            ]);
            $lockedPage->save();

            return $lockedPage->fresh();
        });
    }

    public function releaseDue(?CarbonInterface $now = null): void
    {
        $time = $this->atSchoolTime($now ?? $this->now());
        $pageIds = PublicPage::query()
            ->whereNotNull('scheduled_revision_id')
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', $time)
            ->pluck('id');

        foreach ($pageIds as $pageId) {
            DB::transaction(function () use ($pageId, $time): void {
                $lockedPage = PublicPage::query()->lockForUpdate()->find($pageId);
                if ($lockedPage === null
                    || $lockedPage->scheduled_revision_id === null
                    || $lockedPage->scheduled_for === null
                    || $lockedPage->scheduled_for->greaterThan($time)
                    || $lockedPage->publication_state === 'withdrawn') {
                    if ($lockedPage !== null && $lockedPage->scheduled_revision_id !== null) {
                        $this->clearScheduledRevision($lockedPage);
                        $lockedPage->save();
                    }

                    return;
                }

                if ($lockedPage->archived_at !== null) {
                    $this->clearScheduledRevision($lockedPage);
                    $lockedPage->save();

                    return;
                }

                $lockedRevision = $lockedPage->revisions()
                    ->whereKey($lockedPage->scheduled_revision_id)
                    ->lockForUpdate()
                    ->first();
                if ($lockedRevision === null || $lockedRevision->workflow_state !== 'scheduled') {
                    $this->clearScheduledRevision($lockedPage);
                    $lockedPage->save();

                    return;
                }

                try {
                    $this->assertRevisionPublishable($lockedPage, $lockedRevision);
                } catch (ValidationException) {
                    $lockedRevision->workflow_state = 'approved';
                    $lockedRevision->save();
                    $this->clearScheduledRevision($lockedPage);
                    $lockedPage->save();

                    return;
                }

                $this->activateRevision($lockedPage, $lockedRevision, null, $time);
            });
        }
    }

    private function lockedRevision(PublicPage $page, PublicPageRevision $revision): PublicPageRevision
    {
        return $page->revisions()->whereKey($revision->getKey())->lockForUpdate()->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function revisionAttributes(array $attributes): array
    {
        return [
            'title' => $attributes['title'],
            'introduction_markdown' => $attributes['introduction_markdown'],
            'history_markdown' => $attributes['history_markdown'],
            'mission_markdown' => $attributes['mission_markdown'],
            'vision_markdown' => $attributes['vision_markdown'],
            'approach_values' => $attributes['approach_values'] === [] ? null : $attributes['approach_values'],
            'facilities_markdown' => $attributes['facilities_markdown'],
        ];
    }

    /** @return array<string, mixed> */
    private function draftAttributes(string $type, array $data): array
    {
        $this->validateType($type);
        $validated = Validator::make($data, [
            'title' => ['required', 'string', 'max:180'],
            'introduction_markdown' => ['nullable', 'string', 'max:50000'],
            'history_markdown' => ['nullable', 'string', 'max:50000'],
            'mission_markdown' => ['nullable', 'string', 'max:30000'],
            'vision_markdown' => ['nullable', 'string', 'max:30000'],
            'approach_values' => ['nullable', 'array', 'max:12'],
            'approach_values.*' => ['nullable', 'string', 'max:400'],
            'facilities_markdown' => ['nullable', 'string', 'max:50000'],
            'stages' => ['nullable', 'array', 'max:12'],
        ])->validate();

        $stages = $this->normalizedStages($validated['stages'] ?? []);
        if ($type === PublicPage::TYPE_ABOUT && $stages !== []) {
            throw ValidationException::withMessages([
                'stages' => 'Learning stages belong only to the Academics page.',
            ]);
        }

        return [
            'title' => trim((string) $validated['title']),
            'introduction_markdown' => $this->nullableString($validated['introduction_markdown'] ?? null),
            'history_markdown' => $this->nullableString($validated['history_markdown'] ?? null),
            'mission_markdown' => $this->nullableString($validated['mission_markdown'] ?? null),
            'vision_markdown' => $this->nullableString($validated['vision_markdown'] ?? null),
            'approach_values' => $this->normalizedStrings($validated['approach_values'] ?? []),
            'facilities_markdown' => $this->nullableString($validated['facilities_markdown'] ?? null),
            'stages' => $stages,
        ];
    }

    /** @return array<int, array{title: string, stage_label: ?string, description_markdown: ?string, key_points: array<int, string>}> */
    private function normalizedStages(mixed $stages): array
    {
        if (! is_array($stages)) {
            return [];
        }

        $normalized = [];
        foreach ($stages as $index => $stage) {
            if (! is_array($stage)) {
                throw ValidationException::withMessages(["stages.{$index}" => 'Each learning stage must be an object.']);
            }
            $validated = Validator::make($stage, [
                'title' => ['nullable', 'string', 'max:180'],
                'stage_label' => ['nullable', 'string', 'max:100'],
                'description_markdown' => ['nullable', 'string', 'max:30000'],
                'key_points' => ['nullable', 'array', 'max:12'],
                'key_points.*' => ['nullable', 'string', 'max:400'],
            ])->validate();
            $title = trim((string) ($validated['title'] ?? ''));
            $stageLabel = $this->nullableString($validated['stage_label'] ?? null);
            $description = $this->nullableString($validated['description_markdown'] ?? null);
            $keyPoints = $this->normalizedStrings($validated['key_points'] ?? []);
            if ($title === '' && $stageLabel === null && $description === null && $keyPoints === []) {
                continue;
            }
            if ($title === '') {
                throw ValidationException::withMessages(["stages.{$index}.title" => 'Each learning stage needs a public title.']);
            }
            $normalized[] = [
                'title' => $title,
                'stage_label' => $stageLabel,
                'description_markdown' => $description,
                'key_points' => $keyPoints,
            ];
        }

        return $normalized;
    }

    /** @param array<int, string|null> $values
     * @return array<int, string>
     */
    private function normalizedStrings(array $values): array
    {
        return array_values(array_filter(array_map(
            fn (mixed $value): string => trim((string) $value),
            $values,
        ), fn (string $value): bool => $value !== ''));
    }

    /** @param array<int, array{title: string, stage_label: ?string, description_markdown: ?string, key_points: array<int, string>}> $stages */
    private function replaceAcademicStages(PublicPageRevision $revision, array $stages): void
    {
        $revision->academicStages()->delete();
        foreach ($stages as $displayOrder => $stage) {
            $revision->academicStages()->create($stage + [
                'display_order' => $displayOrder,
                'key_points' => $stage['key_points'] === [] ? null : $stage['key_points'],
            ]);
        }
    }

    private function assertRevisionPublishable(PublicPage $page, PublicPageRevision $revision): void
    {
        $this->require(trim($revision->title) !== '', 'A public page title is required before publication.');
        $this->require(
            trim((string) $revision->introduction_markdown) !== '',
            'A public introduction is required before publication.',
        );

        if ($page->type === PublicPage::TYPE_ACADEMICS) {
            $stages = $revision->academicStages()->lockForUpdate()->get();
            $this->require($stages->isNotEmpty(), 'At least one approved public learning stage is required before publishing Academics.');
            foreach ($stages as $stage) {
                $this->require(trim($stage->title) !== '', 'Each public learning stage needs a title.');
            }
        }
    }

    private function activateRevision(
        PublicPage $page,
        PublicPageRevision $revision,
        ?User $actor,
        CarbonInterface $publishedAt,
    ): void {
        $publisherId = $actor?->id ?? $revision->published_by;
        $revision->fill([
            'workflow_state' => 'published',
            'published_by' => $publisherId,
            'published_at' => $publishedAt,
            'updated_by' => $publisherId ?? $revision->updated_by,
        ]);
        $revision->save();
        $page->fill([
            'publication_state' => 'published',
            'published_revision_id' => $revision->id,
            'scheduled_revision_id' => null,
            'scheduled_for' => null,
            'published_at' => $publishedAt,
            'withdrawn_at' => null,
            'archived_at' => null,
            'published_by' => $publisherId,
            'updated_by' => $publisherId ?? $page->updated_by,
        ]);
        $page->save();
    }

    private function clearScheduledRevision(PublicPage $page): void
    {
        if ($page->scheduled_revision_id !== null) {
            $revision = $page->revisions()->whereKey($page->scheduled_revision_id)->lockForUpdate()->first();
            if ($revision !== null && $revision->workflow_state === 'scheduled') {
                $revision->workflow_state = 'approved';
                $revision->save();
            }
        }
        $page->scheduled_revision_id = null;
        $page->scheduled_for = null;
    }

    private function authorize(User $actor, string $ability): void
    {
        Gate::forUser($actor)->authorize($ability);
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

    private function atSchoolTime(CarbonInterface $value): CarbonImmutable
    {
        return CarbonImmutable::instance($value)->setTimezone(self::TIMEZONE)->utc();
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function validateType(string $type): void
    {
        if (! in_array($type, PublicPage::TYPES, true)) {
            throw ValidationException::withMessages(['type' => 'Choose an approved fixed public page type.']);
        }
    }

    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['public_page' => $message]);
        }
    }
}
