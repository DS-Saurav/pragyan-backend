<?php

namespace App\Services\News;

use App\Models\MediaAsset;
use App\Models\MediaAttachment;
use App\Models\NewsStory;
use App\Models\NewsStoryRevision;
use App\Models\NewsStorySlug;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class NewsPublisher
{
    public const FEATURED_SETTING = 'website_featured_news_story_id';

    /** @var array<int, string> */
    public const RESERVED_SLUGS = ['admin', 'archive', 'create', 'edit', 'feature', 'preview'];

    public const TIMEZONE = 'Asia/Kathmandu';

    public function createDraft(User $actor, array $data): NewsStory
    {
        $this->authorize($actor, 'news.author');
        $attributes = $this->draftAttributes($data);

        return DB::transaction(function () use ($actor, $attributes): NewsStory {
            $slug = $this->canonicalSlug($attributes['slug'] ?: $attributes['title']);
            $this->ensureSlugClaimable($slug);

            $story = NewsStory::create([
                'slug' => $slug,
                'publication_state' => 'draft',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $story->revisions()->create($this->revisionAttributes($attributes) + [
                'revision_number' => 1,
                'slug' => $slug,
                'workflow_state' => 'draft',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            return $story->fresh(['revisions']);
        });
    }

    public function saveDraft(User $actor, NewsStory $story, array $data): NewsStoryRevision
    {
        $this->authorize($actor, 'news.author');
        $attributes = $this->draftAttributes($data);

        return DB::transaction(function () use ($actor, $story, $attributes): NewsStoryRevision {
            $lockedStory = NewsStory::query()->lockForUpdate()->findOrFail($story->getKey());
            $this->require($lockedStory->archived_at === null, 'Restore an archived story before preparing a new revision.');
            $this->require($lockedStory->scheduled_revision_id === null, 'Publish, withdraw, or cancel the scheduled revision before preparing another one.');

            $slug = $this->canonicalSlug($attributes['slug'] ?: $attributes['title']);
            $this->ensureSlugClaimable($slug, $lockedStory);

            $revision = $lockedStory->revisions()
                ->where('workflow_state', 'draft')
                ->orderByDesc('revision_number')
                ->lockForUpdate()
                ->first();

            if ($revision === null) {
                $revision = $lockedStory->revisions()->create($this->revisionAttributes($attributes) + [
                    'revision_number' => ((int) $lockedStory->revisions()->max('revision_number')) + 1,
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

            if ($lockedStory->published_revision_id === null) {
                $lockedStory->fill([
                    'slug' => $slug,
                    'publication_state' => 'draft',
                    'withdrawn_at' => null,
                ]);
            }
            $lockedStory->updated_by = $actor->id;
            $lockedStory->save();

            return $revision->refresh();
        });
    }

    public function submit(User $actor, NewsStory $story, NewsStoryRevision $revision): NewsStoryRevision
    {
        $this->authorize($actor, 'news.author');

        return DB::transaction(function () use ($actor, $story, $revision): NewsStoryRevision {
            $lockedStory = NewsStory::query()->lockForUpdate()->findOrFail($story->getKey());
            $lockedRevision = $this->lockedRevision($lockedStory, $revision);
            $this->require($lockedStory->archived_at === null, 'An archived story cannot enter review.');
            $this->require($lockedRevision->workflow_state === 'draft', 'Only a draft revision can enter review.');

            $lockedRevision->fill([
                'workflow_state' => 'review',
                'submitted_by' => $actor->id,
                'submitted_at' => $this->now(),
                'updated_by' => $actor->id,
            ]);
            $lockedRevision->save();

            if ($lockedStory->published_revision_id === null) {
                $lockedStory->publication_state = 'review';
            }
            $lockedStory->updated_by = $actor->id;
            $lockedStory->save();

            return $lockedRevision->refresh();
        });
    }

    public function approve(User $actor, NewsStory $story, NewsStoryRevision $revision): NewsStoryRevision
    {
        $this->authorize($actor, 'news.review');

        return DB::transaction(function () use ($actor, $story, $revision): NewsStoryRevision {
            $lockedStory = NewsStory::query()->lockForUpdate()->findOrFail($story->getKey());
            $lockedRevision = $this->lockedRevision($lockedStory, $revision);
            $this->require($lockedStory->archived_at === null, 'An archived story cannot be approved.');
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
            $lockedStory->updated_by = $actor->id;
            $lockedStory->save();

            return $lockedRevision->refresh();
        });
    }

    public function publish(
        User $actor,
        NewsStory $story,
        NewsStoryRevision $revision,
        ?CarbonInterface $scheduledFor = null,
    ): NewsStory {
        $this->authorize($actor, 'news.publish');

        $published = DB::transaction(function () use ($actor, $story, $revision, $scheduledFor): NewsStory {
            $lockedStory = NewsStory::query()->lockForUpdate()->findOrFail($story->getKey());
            $lockedRevision = $this->lockedRevision($lockedStory, $revision);
            $this->require($lockedStory->archived_at === null, 'Restore an archived story before publishing it.');
            $this->require($lockedRevision->workflow_state === 'approved', 'Only an approved revision can be published or scheduled.');
            $this->assertCoverIsPublicSafe($lockedRevision);

            $when = $scheduledFor === null ? null : $this->atSchoolTime($scheduledFor);
            if ($when !== null && $when->isFuture()) {
                $this->require(
                    $lockedStory->scheduled_revision_id === null || $lockedStory->scheduled_revision_id === $lockedRevision->id,
                    'This story already has a scheduled revision.',
                );

                $lockedRevision->fill([
                    'workflow_state' => 'scheduled',
                    'published_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);
                $lockedRevision->save();
                $lockedStory->fill([
                    'scheduled_revision_id' => $lockedRevision->id,
                    'scheduled_for' => $when,
                    'updated_by' => $actor->id,
                ]);
                $lockedStory->save();

                return $lockedStory->fresh(['publishedRevision', 'scheduledRevision']);
            }

            $this->require(
                $lockedStory->scheduled_revision_id === null || $lockedStory->scheduled_revision_id === $lockedRevision->id,
                'This story already has a different scheduled revision.',
            );
            $this->activateRevision($lockedStory, $lockedRevision, $actor, $this->now());

            return $lockedStory->fresh(['publishedRevision', 'scheduledRevision']);
        });

        self::invalidatePublicCache();

        return $published;
    }

    public function withdraw(User $actor, NewsStory $story): NewsStory
    {
        $this->authorize($actor, 'news.publish');

        $withdrawn = DB::transaction(function () use ($actor, $story): NewsStory {
            $lockedStory = NewsStory::query()->lockForUpdate()->findOrFail($story->getKey());
            $this->require($lockedStory->publication_state === 'published', 'Only a published story can be withdrawn.');
            $this->clearScheduledRevision($lockedStory);
            $lockedStory->fill([
                'publication_state' => 'withdrawn',
                'withdrawn_at' => $this->now(),
                'updated_by' => $actor->id,
            ]);
            $lockedStory->save();
            $this->clearFeaturedStory($lockedStory);

            return $lockedStory->fresh();
        });

        self::invalidatePublicCache();

        return $withdrawn;
    }

    public function archive(User $actor, NewsStory $story): NewsStory
    {
        $this->authorize($actor, 'news.publish');

        $archived = DB::transaction(function () use ($actor, $story): NewsStory {
            $lockedStory = NewsStory::query()->lockForUpdate()->findOrFail($story->getKey());
            $this->require($lockedStory->archived_at === null, 'This story is already archived.');
            $this->clearScheduledRevision($lockedStory);
            $lockedStory->fill([
                'publication_state' => 'archived',
                'archived_at' => $this->now(),
                'updated_by' => $actor->id,
            ]);
            $lockedStory->save();
            $this->clearFeaturedStory($lockedStory);

            return $lockedStory->fresh();
        });

        self::invalidatePublicCache();

        return $archived;
    }

    public function restore(User $actor, NewsStory $story): NewsStory
    {
        $this->authorize($actor, 'news.publish');

        return DB::transaction(function () use ($actor, $story): NewsStory {
            $lockedStory = NewsStory::query()->lockForUpdate()->findOrFail($story->getKey());
            $this->require($lockedStory->archived_at !== null, 'Only an archived story can be restored.');
            $this->clearScheduledRevision($lockedStory);
            $lockedStory->fill([
                'publication_state' => 'draft',
                'published_revision_id' => null,
                'published_at' => null,
                'withdrawn_at' => null,
                'archived_at' => null,
                'updated_by' => $actor->id,
            ]);
            $lockedStory->save();

            return $lockedStory->fresh();
        });
    }

    public function setFeatured(User $actor, ?NewsStory $story): void
    {
        $this->authorize($actor, 'news.publish');

        DB::transaction(function () use ($story): void {
            if ($story !== null) {
                $lockedStory = NewsStory::query()->lockForUpdate()->findOrFail($story->getKey());
                $this->require($this->isDisplayable($lockedStory), 'Only a currently published story can be featured.');
                Setting::set(self::FEATURED_SETTING, (string) $lockedStory->id);

                return;
            }

            Setting::set(self::FEATURED_SETTING, null);
        });

        self::invalidatePublicCache();
    }

    public function releaseDue(?CarbonInterface $now = null): void
    {
        $time = $this->atSchoolTime($now ?? $this->now());
        $dueStoryIds = NewsStory::query()
            ->whereNotNull('scheduled_revision_id')
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', $time)
            ->pluck('id');
        $changed = false;

        foreach ($dueStoryIds as $storyId) {
            $changed = DB::transaction(function () use ($storyId, $time): bool {
                $lockedStory = NewsStory::query()->lockForUpdate()->find($storyId);
                if ($lockedStory === null
                    || $lockedStory->scheduled_revision_id === null
                    || $lockedStory->scheduled_for === null
                    || $lockedStory->scheduled_for->greaterThan($time)
                    || $lockedStory->archived_at !== null
                    || $lockedStory->publication_state === 'withdrawn') {
                    if ($lockedStory !== null && $lockedStory->scheduled_revision_id !== null) {
                        $this->clearScheduledRevision($lockedStory);
                        $lockedStory->save();
                    }

                    return true;
                }

                $lockedRevision = $lockedStory->revisions()
                    ->whereKey($lockedStory->scheduled_revision_id)
                    ->lockForUpdate()
                    ->first();
                if ($lockedRevision === null || $lockedRevision->workflow_state !== 'scheduled') {
                    $this->clearScheduledRevision($lockedStory);
                    $lockedStory->save();

                    return true;
                }

                try {
                    $this->assertCoverIsPublicSafe($lockedRevision);
                } catch (ValidationException) {
                    $lockedRevision->workflow_state = 'approved';
                    $lockedRevision->save();
                    $this->clearScheduledRevision($lockedStory);
                    $lockedStory->save();

                    return true;
                }

                $this->activateRevision($lockedStory, $lockedRevision, null, $time);

                return true;
            }) || $changed;
        }

        if ($changed) {
            self::invalidatePublicCache();
        }
    }

    public static function invalidatePublicCache(): void
    {
        Cache::forget('public-news:available');
        Cache::forget('public-news:featured');
    }

    private function lockedRevision(NewsStory $story, NewsStoryRevision $revision): NewsStoryRevision
    {
        return $story->revisions()->whereKey($revision->getKey())->lockForUpdate()->firstOrFail();
    }

    private function revisionAttributes(array $attributes): array
    {
        return [
            'title' => $attributes['title'],
            'summary' => $attributes['summary'],
            'body_markdown' => $attributes['body_markdown'],
            'category' => $attributes['category'],
            'public_attribution' => $attributes['public_attribution'],
        ];
    }

    private function draftAttributes(array $data): array
    {
        $validated = Validator::make($data, [
            'slug' => ['nullable', 'string', 'max:180'],
            'title' => ['required', 'string', 'max:180'],
            'summary' => ['required', 'string', 'max:500'],
            'body_markdown' => ['required', 'string', 'max:50000'],
            'category' => ['nullable', 'string', 'max:80'],
            'public_attribution' => ['nullable', 'string', 'max:255'],
        ])->validate();

        $attributes = [
            'slug' => trim((string) ($validated['slug'] ?? '')),
            'title' => trim((string) $validated['title']),
            'summary' => trim((string) $validated['summary']),
            'body_markdown' => trim((string) $validated['body_markdown']),
            'category' => $this->nullableString($validated['category'] ?? null),
            'public_attribution' => $this->nullableString($validated['public_attribution'] ?? null),
        ];

        if ($attributes['title'] === '' || $attributes['summary'] === '' || $attributes['body_markdown'] === '') {
            throw ValidationException::withMessages([
                'title' => 'A title, summary, and article body are required.',
            ]);
        }

        return $attributes;
    }

    private function canonicalSlug(string $value): string
    {
        $slug = Str::limit(Str::slug(trim($value)), 180, '');

        if ($slug === '' || in_array($slug, self::RESERVED_SLUGS, true)) {
            throw ValidationException::withMessages([
                'slug' => 'Choose a public URL that is not reserved by the News section.',
            ]);
        }

        return $slug;
    }

    private function ensureSlugClaimable(string $slug, ?NewsStory $story = null): void
    {
        $stories = NewsStory::query()->where('slug', $slug);
        if ($story !== null) {
            $stories->whereKey('!=', $story->getKey());
        }
        $formerSlugs = NewsStorySlug::query()->where('slug', $slug);
        if ($story !== null) {
            $formerSlugs->where('news_story_id', '!=', $story->getKey());
        }

        if ($stories->exists() || $formerSlugs->exists()) {
            throw ValidationException::withMessages([
                'slug' => 'That public URL is already reserved by another News story.',
            ]);
        }
    }

    private function assertCoverIsPublicSafe(NewsStoryRevision $revision): void
    {
        $cover = MediaAttachment::query()
            ->where('attachable_type', 'news-story-revision')
            ->where('attachable_id', $revision->id)
            ->where('placement', 'cover')
            ->where('display_order', 0)
            ->lockForUpdate()
            ->first();
        $this->require($cover !== null, 'An approved News cover image is required before publication.');

        $asset = MediaAsset::query()->lockForUpdate()->findOrFail($cover->media_asset_id);
        $this->require($asset->status === 'published' && $asset->archived_at === null, 'The News cover must be an active published media asset.');
        $this->require(
            in_array($asset->consent_state, ['not_required', 'confirmed'], true)
                && $asset->consent_withdrawn_at === null,
            'The News cover does not have consent for public use.',
        );
        $altText = trim((string) ($cover->alt_text ?: $asset->default_alt_text));
        $this->require((bool) $asset->is_decorative || $altText !== '', 'The News cover needs meaningful public alt text or must be marked decorative.');
    }

    private function activateRevision(
        NewsStory $story,
        NewsStoryRevision $revision,
        ?User $actor,
        CarbonInterface $publishedAt,
    ): void {
        $this->ensureSlugClaimable($revision->slug, $story);
        $formerSlug = $story->slug;
        $hadPublishedRevision = $story->published_revision_id !== null;

        if ($hadPublishedRevision && $formerSlug !== $revision->slug) {
            NewsStorySlug::query()->firstOrCreate(
                ['slug' => $formerSlug],
                ['news_story_id' => $story->id],
            );
            NewsStorySlug::query()
                ->where('news_story_id', $story->id)
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
        $story->fill([
            'slug' => $revision->slug,
            'publication_state' => 'published',
            'published_revision_id' => $revision->id,
            'scheduled_revision_id' => null,
            'scheduled_for' => null,
            'published_at' => $publishedAt,
            'withdrawn_at' => null,
            'archived_at' => null,
            'published_by' => $publisherId,
            'updated_by' => $publisherId ?? $story->updated_by,
        ]);
        $story->save();
    }

    private function clearScheduledRevision(NewsStory $story): void
    {
        if ($story->scheduled_revision_id !== null) {
            $revision = $story->revisions()->whereKey($story->scheduled_revision_id)->lockForUpdate()->first();
            if ($revision !== null && $revision->workflow_state === 'scheduled') {
                $revision->workflow_state = 'approved';
                $revision->save();
            }
        }
        $story->scheduled_revision_id = null;
        $story->scheduled_for = null;
    }

    private function clearFeaturedStory(NewsStory $story): void
    {
        if ((string) Setting::get(self::FEATURED_SETTING) === (string) $story->id) {
            Setting::set(self::FEATURED_SETTING, null);
        }
    }

    private function isDisplayable(NewsStory $story): bool
    {
        return $story->publication_state === 'published'
            && $story->published_revision_id !== null
            && $story->published_at !== null
            && $story->published_at->lessThanOrEqualTo($this->now())
            && $story->archived_at === null;
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
        return CarbonImmutable::now('UTC');
    }

    private function atSchoolTime(CarbonInterface $value): CarbonImmutable
    {
        return CarbonImmutable::instance($value)->utc();
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['news' => $message]);
        }
    }
}
