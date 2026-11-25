<?php

namespace App\Services\Notices;

use App\Models\Notice;
use App\Models\NoticeRevision;
use App\Models\NoticeSlug;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class NoticePublisher
{
    public const TIMEZONE = 'Asia/Kathmandu';

    /** @var array<int, string> */
    public const RESERVED_SLUGS = ['admin', 'archive', 'create', 'edit', 'preview'];

    public function createDraft(User $actor, array $data): Notice
    {
        $this->authorize($actor, 'notices.author');
        $attributes = $this->draftAttributes($data);

        return DB::transaction(function () use ($actor, $attributes): Notice {
            $slug = $this->canonicalSlug($attributes['slug'] ?: $attributes['title']);
            $this->ensureSlugClaimable($slug);

            $notice = Notice::create([
                'slug' => $slug,
                'title' => $attributes['title'],
                'content' => $attributes['content'],
                'audience' => $attributes['audience'],
                'posted_by' => $actor->name ?: 'School office',
                'date' => $attributes['date'],
                'status' => 'draft',
                'publication_state' => 'draft',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $notice->revisions()->create($this->revisionAttributes($attributes) + [
                'revision_number' => 1,
                'slug' => $slug,
                'workflow_state' => 'draft',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            return $notice->fresh(['revisions']);
        });
    }

    public function saveDraft(User $actor, Notice $notice, array $data): NoticeRevision
    {
        $this->authorize($actor, 'notices.author');
        $attributes = $this->draftAttributes($data);

        return DB::transaction(function () use ($actor, $notice, $attributes): NoticeRevision {
            $lockedNotice = Notice::query()->lockForUpdate()->findOrFail($notice->getKey());
            $this->require($lockedNotice->archived_at === null, 'Restore an archived Notice before preparing a new revision.');
            $this->require($lockedNotice->scheduled_revision_id === null, 'Publish, withdraw, or cancel the scheduled revision before preparing another one.');

            $slug = $this->canonicalSlug($attributes['slug'] ?: $attributes['title']);
            $this->ensureSlugClaimable($slug, $lockedNotice);

            $revision = $lockedNotice->revisions()
                ->where('workflow_state', 'draft')
                ->orderByDesc('revision_number')
                ->lockForUpdate()
                ->first();

            if ($revision === null) {
                $revision = $lockedNotice->revisions()->create($this->revisionAttributes($attributes) + [
                    'revision_number' => ((int) $lockedNotice->revisions()->max('revision_number')) + 1,
                    'slug' => $slug,
                    'workflow_state' => 'draft',
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);
            }

            $revision->fill($this->revisionAttributes($attributes) + [
                'slug' => $slug,
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

            if (! $this->hasLiveNoticeContent($lockedNotice)) {
                $lockedNotice->fill([
                    'slug' => $slug,
                    'title' => $attributes['title'],
                    'content' => $attributes['content'],
                    'audience' => $attributes['audience'],
                    'date' => $attributes['date'],
                    'status' => 'draft',
                    'publication_state' => 'draft',
                    'withdrawn_at' => null,
                ]);
            }
            $lockedNotice->updated_by = $actor->id;
            $lockedNotice->save();

            return $revision->refresh();
        });
    }

    public function submit(User $actor, Notice $notice, NoticeRevision $revision): NoticeRevision
    {
        $this->authorize($actor, 'notices.author');

        return DB::transaction(function () use ($actor, $notice, $revision): NoticeRevision {
            $lockedNotice = Notice::query()->lockForUpdate()->findOrFail($notice->getKey());
            $lockedRevision = $this->lockedRevision($lockedNotice, $revision);
            $this->require($lockedNotice->archived_at === null, 'An archived Notice cannot enter review.');
            $this->require($lockedRevision->workflow_state === 'draft', 'Only a draft revision can enter review.');

            $lockedRevision->fill([
                'workflow_state' => 'review',
                'submitted_by' => $actor->id,
                'submitted_at' => $this->now(),
                'updated_by' => $actor->id,
            ]);
            $lockedRevision->save();

            if (! $this->hasLiveNoticeContent($lockedNotice)) {
                $lockedNotice->publication_state = 'review';
            }
            $lockedNotice->updated_by = $actor->id;
            $lockedNotice->save();

            return $lockedRevision->refresh();
        });
    }

    public function approve(User $actor, Notice $notice, NoticeRevision $revision): NoticeRevision
    {
        $this->authorize($actor, 'notices.review');

        return DB::transaction(function () use ($actor, $notice, $revision): NoticeRevision {
            $lockedNotice = Notice::query()->lockForUpdate()->findOrFail($notice->getKey());
            $lockedRevision = $this->lockedRevision($lockedNotice, $revision);
            $this->require($lockedNotice->archived_at === null, 'An archived Notice cannot be approved.');
            $this->require($lockedRevision->workflow_state === 'review', 'Only a revision in review can be approved.');
            $this->require(
                $this->isNoticeManager($actor)
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
            $lockedNotice->updated_by = $actor->id;
            $lockedNotice->save();

            return $lockedRevision->refresh();
        });
    }

    public function publish(
        User $actor,
        Notice $notice,
        NoticeRevision $revision,
        ?CarbonInterface $scheduledFor = null,
    ): Notice {
        $this->authorize($actor, 'notices.publish');

        $published = DB::transaction(function () use ($actor, $notice, $revision, $scheduledFor): Notice {
            $lockedNotice = Notice::query()->lockForUpdate()->findOrFail($notice->getKey());
            $lockedRevision = $this->lockedRevision($lockedNotice, $revision);
            $this->require($lockedNotice->archived_at === null, 'Restore an archived Notice before publishing it.');
            $this->require($lockedRevision->workflow_state === 'approved', 'Only an approved revision can be published or scheduled.');

            $when = $scheduledFor === null
                ? $lockedRevision->publication_start_at
                : $this->atSchoolTime($scheduledFor);
            if ($when !== null && $when->isFuture()) {
                $this->require(
                    $lockedNotice->scheduled_revision_id === null || $lockedNotice->scheduled_revision_id === $lockedRevision->id,
                    'This Notice already has a scheduled revision.',
                );
                $this->require(
                    $lockedRevision->expires_at === null || $lockedRevision->expires_at->greaterThan($when),
                    'The Notice expiry must be after its scheduled publication time.',
                );

                $lockedRevision->fill([
                    'workflow_state' => 'scheduled',
                    'publication_start_at' => $when,
                    'published_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);
                $lockedRevision->save();
                $lockedNotice->fill([
                    'scheduled_revision_id' => $lockedRevision->id,
                    'scheduled_for' => $when,
                    'publication_state' => $this->hasLiveNoticeContent($lockedNotice) ? $lockedNotice->publication_state : 'scheduled',
                    'updated_by' => $actor->id,
                ]);
                $lockedNotice->save();

                return $lockedNotice->fresh(['publishedRevision', 'scheduledRevision']);
            }

            $this->require(
                $lockedNotice->scheduled_revision_id === null || $lockedNotice->scheduled_revision_id === $lockedRevision->id,
                'This Notice already has a different scheduled revision.',
            );
            $this->activateRevision($lockedNotice, $lockedRevision, $actor, $this->now());

            return $lockedNotice->fresh(['publishedRevision', 'scheduledRevision']);
        });

        self::invalidatePublicCache();

        return $published;
    }

    public function withdraw(User $actor, Notice $notice): Notice
    {
        $this->authorize($actor, 'notices.publish');

        $withdrawn = DB::transaction(function () use ($actor, $notice): Notice {
            $lockedNotice = Notice::query()->lockForUpdate()->findOrFail($notice->getKey());
            $this->require($this->hasLiveNoticeContent($lockedNotice), 'Only a published Notice can be withdrawn.');
            $this->clearScheduledRevision($lockedNotice);
            $lockedNotice->fill([
                'status' => 'draft',
                'publication_state' => 'withdrawn',
                'withdrawn_at' => $this->now(),
                'updated_by' => $actor->id,
            ]);
            $lockedNotice->save();

            return $lockedNotice->fresh();
        });

        self::invalidatePublicCache();

        return $withdrawn;
    }

    public function archive(User $actor, Notice $notice): Notice
    {
        $this->authorize($actor, 'notices.publish');

        $archived = DB::transaction(function () use ($actor, $notice): Notice {
            $lockedNotice = Notice::query()->lockForUpdate()->findOrFail($notice->getKey());
            $this->require($lockedNotice->archived_at === null, 'This Notice is already archived.');
            $this->clearScheduledRevision($lockedNotice);
            $lockedNotice->fill([
                'status' => 'draft',
                'publication_state' => 'archived',
                'archived_at' => $this->now(),
                'updated_by' => $actor->id,
            ]);
            $lockedNotice->save();

            return $lockedNotice->fresh();
        });

        self::invalidatePublicCache();

        return $archived;
    }

    public function restore(User $actor, Notice $notice): Notice
    {
        $this->authorize($actor, 'notices.publish');

        return DB::transaction(function () use ($actor, $notice): Notice {
            $lockedNotice = Notice::query()->lockForUpdate()->findOrFail($notice->getKey());
            $this->require($lockedNotice->archived_at !== null, 'Only an archived Notice can be restored.');
            $this->clearScheduledRevision($lockedNotice);
            $lockedNotice->fill([
                'status' => 'draft',
                'publication_state' => 'draft',
                'published_revision_id' => null,
                'published_at' => null,
                'expires_at' => null,
                'priority' => null,
                'is_urgent' => false,
                'withdrawn_at' => null,
                'archived_at' => null,
                'updated_by' => $actor->id,
            ]);
            $lockedNotice->save();

            return $lockedNotice->fresh();
        });
    }

    public function releaseDue(?CarbonInterface $now = null): void
    {
        $time = $this->atSchoolTime($now ?? $this->now());
        $dueNoticeIds = Notice::query()
            ->whereNotNull('scheduled_revision_id')
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', $time)
            ->pluck('id');
        $changed = false;

        foreach ($dueNoticeIds as $noticeId) {
            $changed = DB::transaction(function () use ($noticeId, $time): bool {
                $lockedNotice = Notice::query()->lockForUpdate()->find($noticeId);
                if ($lockedNotice === null
                    || $lockedNotice->scheduled_revision_id === null
                    || $lockedNotice->scheduled_for === null
                    || $lockedNotice->scheduled_for->greaterThan($time)
                    || $lockedNotice->archived_at !== null
                    || $lockedNotice->publication_state === 'withdrawn') {
                    if ($lockedNotice !== null && $lockedNotice->scheduled_revision_id !== null) {
                        $this->clearScheduledRevision($lockedNotice);
                        if ($lockedNotice->publication_state === 'scheduled' && ! $this->hasLiveNoticeContent($lockedNotice)) {
                            $lockedNotice->publication_state = 'draft';
                        }
                        $lockedNotice->save();
                    }

                    return true;
                }

                $lockedRevision = $lockedNotice->revisions()
                    ->whereKey($lockedNotice->scheduled_revision_id)
                    ->lockForUpdate()
                    ->first();
                if ($lockedRevision === null || $lockedRevision->workflow_state !== 'scheduled') {
                    $this->clearScheduledRevision($lockedNotice);
                    if ($lockedNotice->publication_state === 'scheduled' && ! $this->hasLiveNoticeContent($lockedNotice)) {
                        $lockedNotice->publication_state = 'draft';
                    }
                    $lockedNotice->save();

                    return true;
                }

                try {
                    $this->activateRevision($lockedNotice, $lockedRevision, null, $time);
                } catch (ValidationException) {
                    $lockedRevision->workflow_state = 'approved';
                    $lockedRevision->save();
                    $this->clearScheduledRevision($lockedNotice);
                    if ($lockedNotice->publication_state === 'scheduled' && ! $this->hasLiveNoticeContent($lockedNotice)) {
                        $lockedNotice->publication_state = 'draft';
                    }
                    $lockedNotice->save();
                }

                return true;
            }) || $changed;
        }

        if ($changed) {
            self::invalidatePublicCache();
        }
    }

    public static function invalidatePublicCache(): void
    {
        Cache::forget('public-notices:latest');
        Cache::forget('public-notices:urgent');
    }

    private function lockedRevision(Notice $notice, NoticeRevision $revision): NoticeRevision
    {
        return $notice->revisions()->whereKey($revision->getKey())->lockForUpdate()->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function revisionAttributes(array $attributes): array
    {
        return [
            'title' => $attributes['title'],
            'content' => $attributes['content'],
            'audience' => $attributes['audience'],
            'date' => $attributes['date'],
            'priority' => $attributes['priority'],
            'is_urgent' => $attributes['is_urgent'],
            'publication_start_at' => $attributes['publication_start_at'],
            'expires_at' => $attributes['expires_at'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function draftAttributes(array $data): array
    {
        $validated = Validator::make($data, [
            'slug' => ['nullable', 'string', 'max:180'],
            'title' => ['required', 'string', 'max:180'],
            'content' => ['nullable', 'string', 'max:50000'],
            'audience' => ['required', 'string', Rule::in(['all', 'public', 'teachers', 'parents', 'students', 'staff'])],
            'date' => ['required', 'date'],
            'priority' => ['nullable', 'integer', 'between:0,999'],
            'is_urgent' => ['nullable', 'boolean'],
            'publication_start_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
        ])->validate();

        $publicationStartAt = $this->nullableDateTime($validated['publication_start_at'] ?? null);
        $expiresAt = $this->nullableDateTime($validated['expires_at'] ?? null);
        if ($publicationStartAt !== null && $expiresAt !== null && $expiresAt->lessThanOrEqualTo($publicationStartAt)) {
            throw ValidationException::withMessages([
                'expires_at' => 'The expiry must be after the publication start.',
            ]);
        }

        $attributes = [
            'slug' => trim((string) ($validated['slug'] ?? '')),
            'title' => trim((string) $validated['title']),
            'content' => trim((string) ($validated['content'] ?? '')),
            'audience' => strtolower(trim((string) $validated['audience'])),
            'date' => CarbonImmutable::parse($validated['date'], self::TIMEZONE)->toDateString(),
            'priority' => $validated['priority'] ?? null,
            'is_urgent' => (bool) ($validated['is_urgent'] ?? false),
            'publication_start_at' => $publicationStartAt,
            'expires_at' => $expiresAt,
        ];

        if ($attributes['title'] === '') {
            throw ValidationException::withMessages([
                'title' => 'A Notice title is required.',
            ]);
        }

        if ($attributes['is_urgent'] && ! $this->isPublicAudience($attributes['audience'])) {
            throw ValidationException::withMessages([
                'is_urgent' => 'Only Notices intended for the public audience can be urgent on the public website.',
            ]);
        }

        return $attributes;
    }

    private function canonicalSlug(string $value): string
    {
        $slug = Str::limit(Str::slug(trim($value)), 180, '');

        if ($slug === '' || in_array($slug, self::RESERVED_SLUGS, true)) {
            throw ValidationException::withMessages([
                'slug' => 'Choose a public URL that is not reserved by the Notice Board.',
            ]);
        }

        return $slug;
    }

    private function ensureSlugClaimable(string $slug, ?Notice $notice = null): void
    {
        $notices = Notice::query()->where('slug', $slug);
        if ($notice !== null) {
            $notices->where('id', '!=', $notice->getKey());
        }
        $formerSlugs = NoticeSlug::query()->where('slug', $slug);
        if ($notice !== null) {
            $formerSlugs->where('notice_id', '!=', $notice->getKey());
        }

        if ($notices->exists() || $formerSlugs->exists()) {
            throw ValidationException::withMessages([
                'slug' => 'That public URL is already reserved by another Notice.',
            ]);
        }
    }

    private function activateRevision(
        Notice $notice,
        NoticeRevision $revision,
        ?User $actor,
        CarbonInterface $publishedAt,
    ): void {
        $this->require(
            $revision->expires_at === null || $revision->expires_at->greaterThan($publishedAt),
            'The Notice has already expired and cannot be published.',
        );
        $this->ensureSlugClaimable($revision->slug, $notice);
        $this->assertUrgentAvailability($notice, $revision, $publishedAt);

        $formerSlug = $notice->slug;
        $hadPublicContent = $this->hasPublicLiveContent($notice, $publishedAt);
        if ($hadPublicContent && $formerSlug !== null && $formerSlug !== $revision->slug) {
            NoticeSlug::query()->firstOrCreate(
                ['slug' => $formerSlug],
                ['notice_id' => $notice->id],
            );
            NoticeSlug::query()
                ->where('notice_id', $notice->id)
                ->where('slug', $revision->slug)
                ->delete();
        }

        $publisherId = $actor?->id ?? $revision->published_by;
        $revision->fill([
            'workflow_state' => 'published',
            'published_by' => $publisherId,
            'published_at' => $publishedAt,
            'updated_by' => $publisherId ?? $revision->updated_by,
        ]);
        $revision->save();
        $notice->fill([
            'slug' => $revision->slug,
            'title' => $revision->title,
            'content' => $revision->content,
            'audience' => $revision->audience,
            'date' => $revision->date,
            'status' => 'published',
            'publication_state' => 'published',
            'published_revision_id' => $revision->id,
            'scheduled_revision_id' => null,
            'scheduled_for' => null,
            'published_at' => $publishedAt,
            'expires_at' => $revision->expires_at,
            'priority' => $revision->priority,
            'is_urgent' => $revision->is_urgent,
            'withdrawn_at' => null,
            'archived_at' => null,
            'published_by' => $publisherId,
            'updated_by' => $publisherId ?? $notice->updated_by,
        ]);
        $notice->save();
    }

    private function assertUrgentAvailability(Notice $notice, NoticeRevision $revision, CarbonInterface $now): void
    {
        if (! $revision->is_urgent || ! $this->isPublicAudience($revision->audience)) {
            return;
        }

        $hasOtherUrgentNotice = Notice::query()
            ->where('id', '!=', $notice->id)
            ->where('is_urgent', true)
            ->where('publication_state', 'published')
            ->whereNotNull('published_revision_id')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', $now)
            ->whereNull('archived_at')
            ->whereIn('audience', ['all', 'public'])
            ->where(function ($query) use ($now): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            })
            ->lockForUpdate()
            ->exists();

        $this->require(! $hasOtherUrgentNotice, 'Only one active urgent public Notice can be published at a time.');
    }

    private function clearScheduledRevision(Notice $notice): void
    {
        if ($notice->scheduled_revision_id !== null) {
            $revision = $notice->revisions()->whereKey($notice->scheduled_revision_id)->lockForUpdate()->first();
            if ($revision !== null && $revision->workflow_state === 'scheduled') {
                $revision->workflow_state = 'approved';
                $revision->save();
            }
        }

        $notice->scheduled_revision_id = null;
        $notice->scheduled_for = null;
    }

    private function hasLiveNoticeContent(Notice $notice): bool
    {
        return $notice->publication_state === 'published'
            || ($notice->publication_state === null && $notice->status === 'published');
    }

    private function hasPublicLiveContent(Notice $notice, CarbonInterface $now): bool
    {
        if (! $this->isPublicAudience((string) $notice->audience)) {
            return false;
        }

        if ($notice->publication_state === null) {
            return $notice->status === 'published';
        }

        return $notice->publication_state === 'published'
            && $notice->published_revision_id !== null
            && $notice->published_at !== null
            && $notice->published_at->lessThanOrEqualTo($now)
            && $notice->archived_at === null
            && ($notice->expires_at === null || $notice->expires_at->greaterThan($now));
    }

    private function isPublicAudience(string $audience): bool
    {
        return in_array($audience, ['all', 'public'], true);
    }

    private function authorize(User $actor, string $ability): void
    {
        if (Gate::forUser($actor)->allows($ability) || $this->isNoticeManager($actor)) {
            return;
        }

        throw new AuthorizationException('You are not authorized to manage Notices.');
    }

    private function isNoticeManager(User $actor): bool
    {
        return Gate::forUser($actor)->allows('notices.manage');
    }

    private function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TIMEZONE)->utc();
    }

    private function atSchoolTime(CarbonInterface $value): CarbonImmutable
    {
        return CarbonImmutable::instance($value)->setTimezone(self::TIMEZONE)->utc();
    }

    private function nullableDateTime(mixed $value): ?CarbonImmutable
    {
        $value = trim((string) $value);

        return $value === ''
            ? null
            : CarbonImmutable::parse($value, self::TIMEZONE)->utc();
    }

    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['notice' => $message]);
        }
    }
}
