<?php

namespace App\Services\PublicTranslations;

use App\Models\AdmissionCycle;
use App\Models\GalleryAlbum;
use App\Models\NewsStory;
use App\Models\Notice;
use App\Models\PublicContentTranslation;
use App\Models\PublicContentTranslationRevision;
use App\Models\PublicPage;
use App\Models\PublicSiteTranslation;
use App\Models\PublicSiteTranslationRevision;
use App\Models\PublicTeamProfile;
use App\Models\SchoolEvent;
use App\Services\Gallery\GalleryVisibility;
use App\Services\News\NewsVisibility;
use App\Services\Notices\NoticeVisibility;
use App\Services\PublicAdmissions\AdmissionCycleResolver;
use App\Services\PublicPages\PublicPageVisibility;
use App\Services\PublicTeam\PublicTeamVisibility;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/** Read-only public projection of approved, current Nepali translations. */
final class PublicTranslationVisibility
{
    public function __construct(
        private PublicTranslationSource $sources,
        private PublicTranslationPublisher $publisher,
        private NewsVisibility $news,
        private GalleryVisibility $gallery,
        private NoticeVisibility $notices,
        private PublicPageVisibility $pages,
        private PublicTeamVisibility $team,
        private AdmissionCycleResolver $admissions,
    ) {}

    public function contentRevision(string $type, Model $source): ?PublicContentTranslationRevision
    {
        if (! $this->sourceIsPublic($type, $source)) {
            return null;
        }

        $translation = PublicContentTranslation::query()
            ->where('content_type', $type)
            ->where('content_id', $source->getKey())
            ->where('locale', PublicContentTranslation::LOCALE_NEPALI)
            ->where('publication_state', 'published')
            ->whereNotNull('published_revision_id')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', $this->now())
            ->whereNull('withdrawn_at')
            ->whereNull('archived_at')
            ->with(['publishedRevision.media.mediaAttachment.asset'])
            ->first();

        if (! $translation instanceof PublicContentTranslation
            || ! $translation->publishedRevision instanceof PublicContentTranslationRevision
            || $translation->publishedRevision->workflow_state !== 'published'
            || ! $this->sources->isCurrentSnapshot($type, $source, $translation->publishedRevision)) {
            return null;
        }

        return $translation->publishedRevision;
    }

    public function siteRevision(string $scope): ?PublicSiteTranslationRevision
    {
        $translation = PublicSiteTranslation::query()
            ->where('scope', $scope)
            ->where('locale', PublicSiteTranslation::LOCALE_NEPALI)
            ->where('publication_state', 'published')
            ->whereNotNull('published_revision_id')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', $this->now())
            ->whereNull('withdrawn_at')
            ->whereNull('archived_at')
            ->with('publishedRevision')
            ->first();

        if (! $translation instanceof PublicSiteTranslation
            || ! $translation->publishedRevision instanceof PublicSiteTranslationRevision
            || $translation->publishedRevision->workflow_state !== 'published'
            || ! hash_equals($this->publisher->siteFingerprint($scope), (string) $translation->publishedRevision->source_fingerprint)) {
            return null;
        }

        return $translation->publishedRevision;
    }

    public function hasContent(string $type): bool
    {
        return PublicContentTranslation::query()
            ->where('content_type', $type)
            ->where('locale', PublicContentTranslation::LOCALE_NEPALI)
            ->where('publication_state', 'published')
            ->whereNotNull('published_revision_id')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', $this->now())
            ->whereNull('withdrawn_at')
            ->whereNull('archived_at')
            ->with('publishedRevision')
            ->get()
            ->contains(function (PublicContentTranslation $translation) use ($type): bool {
                $revision = $translation->publishedRevision;
                if (! $revision instanceof PublicContentTranslationRevision || $revision->workflow_state !== 'published') {
                    return false;
                }
                try {
                    $source = $this->sources->sourceFor($type, (int) $translation->content_id);

                    return $this->sourceIsPublic($type, $source)
                        && $this->sources->isCurrentSnapshot($type, $source, $revision);
                } catch (\Throwable) {
                    return false;
                }
            });
    }

    /** @return Collection<int, array{source: Model, revision: PublicContentTranslationRevision}> */
    public function translated(Collection $sources, string $type): Collection
    {
        return $sources->map(function (Model $source) use ($type): ?array {
            $revision = $this->contentRevision($type, $source);

            return $revision instanceof PublicContentTranslationRevision
                ? ['source' => $source, 'revision' => $revision]
                : null;
        })->filter()->values();
    }

    /** @return array<int, string> */
    public function translatedSourceIds(string $type): array
    {
        return PublicContentTranslation::query()
            ->where('content_type', $type)
            ->where('locale', PublicContentTranslation::LOCALE_NEPALI)
            ->where('publication_state', 'published')
            ->whereNotNull('published_revision_id')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', $this->now())
            ->whereNull('withdrawn_at')
            ->whereNull('archived_at')
            ->pluck('content_id')->map(static fn (mixed $id): int => (int) $id)->all();
    }

    private function sourceIsPublic(string $type, Model $source): bool
    {
        return match ($type) {
            PublicTranslationSource::NEWS_STORY => $source instanceof NewsStory && $this->news->isDisplayable($source),
            PublicTranslationSource::GALLERY_ALBUM => $source instanceof GalleryAlbum && $this->gallery->isDisplayable($source),
            PublicTranslationSource::NOTICE => $source instanceof Notice && $this->notices->isDisplayable($source),
            PublicTranslationSource::PUBLIC_PAGE => $source instanceof PublicPage && $this->pages->isDisplayable($source),
            PublicTranslationSource::PUBLIC_TEAM_PROFILE => $source instanceof PublicTeamProfile
                && $this->team->displayableQuery()->whereKey($source->getKey())->exists(),
            PublicTranslationSource::ADMISSION_CYCLE => $source instanceof AdmissionCycle
                && $this->admissions->current()?->is($source),
            PublicTranslationSource::SCHOOL_EVENT => $source instanceof SchoolEvent && (bool) $source->is_public,
            default => false,
        };
    }

    private function now(): CarbonImmutable
    {
        return CarbonImmutable::now(PublicTranslationPublisher::TIMEZONE)->utc();
    }
}
