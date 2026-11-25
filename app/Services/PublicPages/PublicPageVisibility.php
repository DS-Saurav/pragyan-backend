<?php

namespace App\Services\PublicPages;

use App\Models\PublicPage;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PublicPageVisibility
{
    /** @return Builder<PublicPage> */
    public function displayableQuery(?CarbonInterface $now = null): Builder
    {
        if (! $this->schemaIsReady()) {
            return PublicPage::query()->fromSub(
                DB::query()->selectRaw('1 as id')->whereRaw('1 = 0'),
                'public_pages',
            );
        }

        $time = $this->atSchoolTime($now);

        return PublicPage::query()
            ->where('publication_state', 'published')
            ->whereNotNull('published_revision_id')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', $time)
            ->whereNull('withdrawn_at')
            ->whereNull('archived_at')
            ->whereHas('publishedRevision', function (Builder $query): void {
                $query->where('workflow_state', 'published');
            });
    }

    public function page(string $type, ?CarbonInterface $now = null): ?PublicPage
    {
        if (! in_array($type, PublicPage::TYPES, true)) {
            return null;
        }

        return $this->displayableQuery($now)
            ->where('type', $type)
            ->with([
                'publishedRevision.featureImage.asset',
                'publishedRevision.academicStages',
            ])
            ->first();
    }

    public function hasPublished(string $type, ?CarbonInterface $now = null): bool
    {
        return $this->page($type, $now) !== null;
    }

    public function isDisplayable(PublicPage $page, ?CarbonInterface $now = null): bool
    {
        $time = $this->atSchoolTime($now);

        return $page->publication_state === 'published'
            && $page->published_revision_id !== null
            && $page->published_at !== null
            && $page->published_at->lessThanOrEqualTo($time)
            && $page->withdrawn_at === null
            && $page->archived_at === null
            && $page->publishedRevision?->workflow_state === 'published';
    }

    private function atSchoolTime(?CarbonInterface $value): CarbonImmutable
    {
        return $value === null
            ? CarbonImmutable::now(PublicPagePublisher::TIMEZONE)->utc()
            : CarbonImmutable::instance($value)->setTimezone(PublicPagePublisher::TIMEZONE)->utc();
    }

    private function schemaIsReady(): bool
    {
        return Schema::hasTable('public_pages')
            && Schema::hasTable('public_page_revisions')
            && Schema::hasTable('public_academic_stages');
    }
}
