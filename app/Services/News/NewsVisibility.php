<?php

namespace App\Services\News;

use App\Models\NewsStory;
use App\Models\NewsStorySlug;
use App\Models\Setting;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NewsVisibility
{
    /** @return Builder<NewsStory> */
    public function displayableQuery(?CarbonInterface $now = null): Builder
    {
        if (! $this->schemaIsReady()) {
            return NewsStory::query()->fromSub(
                DB::query()->selectRaw('1 as id')->whereRaw('1 = 0'),
                'news_stories',
            );
        }

        $time = $this->atSchoolTime($now);

        return NewsStory::query()
            ->where('publication_state', 'published')
            ->whereNotNull('published_revision_id')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', $time)
            ->whereNull('archived_at');
    }

    /**
     * @return array{story: ?NewsStory, redirect: ?NewsStory}
     */
    public function findDisplayableSlug(string $slug, ?CarbonInterface $now = null): array
    {
        if (! $this->schemaIsReady()) {
            return ['story' => null, 'redirect' => null];
        }

        $story = $this->displayableQuery($now)
            ->with(['publishedRevision.cover.asset'])
            ->where('slug', $slug)
            ->first();
        if ($story !== null) {
            return ['story' => $story, 'redirect' => null];
        }

        $formerSlug = NewsStorySlug::query()
            ->where('slug', $slug)
            ->with(['story.publishedRevision.cover.asset'])
            ->first();
        if ($formerSlug === null || $formerSlug->story === null || ! $this->isDisplayable($formerSlug->story, $now)) {
            return ['story' => null, 'redirect' => null];
        }

        return ['story' => $formerSlug->story, 'redirect' => $formerSlug->story];
    }

    public function featured(?CarbonInterface $now = null): ?NewsStory
    {
        if (! $this->schemaIsReady()) {
            return null;
        }

        $featuredId = Setting::get(NewsPublisher::FEATURED_SETTING);
        if (! is_numeric($featuredId)) {
            return null;
        }

        return $this->displayableQuery($now)
            ->with(['publishedRevision.cover.asset'])
            ->whereKey((int) $featuredId)
            ->first();
    }

    public function hasPublished(?CarbonInterface $now = null): bool
    {
        if (! $this->schemaIsReady()) {
            return false;
        }

        return $this->displayableQuery($now)->exists();
    }

    public function isDisplayable(NewsStory $story, ?CarbonInterface $now = null): bool
    {
        $time = $this->atSchoolTime($now);

        return $story->publication_state === 'published'
            && $story->published_revision_id !== null
            && $story->published_at !== null
            && $story->published_at->lessThanOrEqualTo($time)
            && $story->archived_at === null;
    }

    private function atSchoolTime(?CarbonInterface $value): CarbonImmutable
    {
        return $value === null
            ? CarbonImmutable::now('UTC')
            : CarbonImmutable::instance($value)->utc();
    }

    private function schemaIsReady(): bool
    {
        return Schema::hasTable('news_stories')
            && Schema::hasTable('news_story_revisions')
            && Schema::hasTable('news_story_slugs');
    }
}
