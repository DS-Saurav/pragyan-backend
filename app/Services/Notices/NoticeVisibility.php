<?php

namespace App\Services\Notices;

use App\Models\Notice;
use App\Models\NoticeSlug;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class NoticeVisibility
{
    /** @return Builder<Notice> */
    public function displayableQuery(?CarbonInterface $now = null): Builder
    {
        if (! $this->schemaIsReady()) {
            return $this->legacyDisplayableQuery();
        }

        $time = $this->atSchoolTime($now);

        return Notice::query()
            ->whereIn('audience', ['all', 'public'])
            ->where(function (Builder $query) use ($time): void {
                $query->where(function (Builder $editorial) use ($time): void {
                    $editorial->where('publication_state', 'published')
                        ->whereNotNull('published_revision_id')
                        ->whereNotNull('published_at')
                        ->where('published_at', '<=', $time)
                        ->whereNull('archived_at')
                        ->where(function (Builder $expiry) use ($time): void {
                            $expiry->whereNull('expires_at')->orWhere('expires_at', '>', $time);
                        });
                })->orWhere(function (Builder $legacy): void {
                    $legacy->whereNull('publication_state')
                        ->where('status', 'published');
                });
            });
    }

    /**
     * @return array{notice: ?Notice, redirect: ?Notice}
     */
    public function findDisplayableSlug(string $slug, ?CarbonInterface $now = null): array
    {
        if (! $this->schemaIsReady()) {
            return ['notice' => null, 'redirect' => null];
        }

        $notice = $this->displayableQuery($now)
            ->with('publishedRevision')
            ->where('slug', $slug)
            ->first();
        if ($notice !== null) {
            return ['notice' => $notice, 'redirect' => null];
        }

        $formerSlug = NoticeSlug::query()
            ->where('slug', $slug)
            ->with('notice.publishedRevision')
            ->first();
        if ($formerSlug === null
            || $formerSlug->notice === null
            || ! $this->isDisplayable($formerSlug->notice, $now)) {
            return ['notice' => null, 'redirect' => null];
        }

        return ['notice' => $formerSlug->notice, 'redirect' => $formerSlug->notice];
    }

    public function latest(?CarbonInterface $now = null): ?Notice
    {
        if (! $this->schemaIsReady()) {
            return $this->legacyDisplayableQuery()->orderByDesc('date')->first();
        }

        return $this->displayableQuery($now)
            ->with('publishedRevision')
            ->orderByDesc('published_at')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first();
    }

    public function urgent(?CarbonInterface $now = null): ?Notice
    {
        if (! $this->schemaIsReady()) {
            return null;
        }

        return $this->displayableQuery($now)
            ->with('publishedRevision')
            ->where('is_urgent', true)
            ->orderByDesc('priority')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->first();
    }

    public function isDisplayable(Notice $notice, ?CarbonInterface $now = null): bool
    {
        if (! $this->isPublicAudience($notice->audience)) {
            return false;
        }

        if ($notice->publication_state === null) {
            return $notice->status === 'published';
        }

        $time = $this->atSchoolTime($now);

        return $notice->publication_state === 'published'
            && $notice->published_revision_id !== null
            && $notice->published_at !== null
            && $notice->published_at->lessThanOrEqualTo($time)
            && $notice->archived_at === null
            && ($notice->expires_at === null || $notice->expires_at->greaterThan($time));
    }

    public function isPublicAudience(?string $audience): bool
    {
        return in_array($audience, ['all', 'public'], true);
    }

    /** @return Builder<Notice> */
    private function legacyDisplayableQuery(): Builder
    {
        return Notice::query()
            ->where('status', 'published')
            ->whereIn('audience', ['all', 'public']);
    }

    private function atSchoolTime(?CarbonInterface $value): CarbonImmutable
    {
        return $value === null
            ? CarbonImmutable::now(NoticePublisher::TIMEZONE)->utc()
            : CarbonImmutable::instance($value)->setTimezone(NoticePublisher::TIMEZONE)->utc();
    }

    private function schemaIsReady(): bool
    {
        return Schema::hasTable('notices')
            && Schema::hasTable('notice_revisions')
            && Schema::hasTable('notice_slugs');
    }
}
