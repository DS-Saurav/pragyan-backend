<?php

namespace App\Support;

use App\Models\GalleryAlbum;
use App\Models\NewsStory;
use App\Models\Notice;
use App\Models\PublicPage;
use App\Services\Gallery\GalleryVisibility;
use App\Services\News\NewsVisibility;
use App\Services\Notices\NoticeVisibility;
use App\Services\PublicAdmissions\AdmissionCycleResolver;
use App\Services\PublicPages\PublicPageVisibility;
use App\Services\PublicTranslations\PublicTranslationSource;
use App\Services\PublicTranslations\PublicTranslationVisibility;

/** Resolves an hreflang counterpart only when that exact content is public. */
final class PublicLocaleMetadata
{
    public function __construct(
        private PublicTranslationVisibility $translations,
        private NewsVisibility $news,
        private GalleryVisibility $gallery,
        private NoticeVisibility $notices,
        private PublicPageVisibility $pages,
        private AdmissionCycleResolver $admissions,
    ) {}

    public function alternateUrl(bool $localizedAvailable): ?string
    {
        $route = request()->route()?->getName();
        if ($route === null || $this->isArchive($route)) {
            return null;
        }
        if (PublicLocaleUrl::current() === PublicLocaleUrl::NEPALI) {
            return $localizedAvailable ? PublicLocaleUrl::counterpartUrl(PublicLocaleUrl::ENGLISH) : null;
        }

        return $this->nepaliAvailable($route)
            ? PublicLocaleUrl::counterpartUrl(PublicLocaleUrl::NEPALI)
            : null;
    }

    private function nepaliAvailable(string $route): bool
    {
        return match ($route) {
            'home' => $this->translations->siteRevision('homepage') !== null,
            'public.contact' => $this->translations->siteRevision('identity') !== null,
            'public.about' => $this->pageAvailable(PublicPage::TYPE_ABOUT),
            'public.academics' => $this->pageAvailable(PublicPage::TYPE_ACADEMICS),
            'public.admissions' => ($source = $this->admissions->current()) !== null
                && $this->translations->contentRevision(PublicTranslationSource::ADMISSION_CYCLE, $source) !== null,
            'public.news' => $this->translations->hasContent(PublicTranslationSource::NEWS_STORY),
            'public.gallery' => $this->translations->hasContent(PublicTranslationSource::GALLERY_ALBUM),
            'public.notices' => $this->translations->hasContent(PublicTranslationSource::NOTICE),
            'public.team' => $this->translations->hasContent(PublicTranslationSource::PUBLIC_TEAM_PROFILE),
            'public.calendar' => $this->translations->hasContent(PublicTranslationSource::SCHOOL_EVENT),
            'public.news.show' => $this->newsAvailable((string) request()->route('slug')),
            'public.gallery.show' => $this->galleryAvailable((string) request()->route('slug')),
            'public.notices.show' => $this->noticeAvailable((string) request()->route('slug')),
            default => false,
        };
    }

    private function pageAvailable(string $type): bool
    {
        $page = $this->pages->page($type);

        return $page !== null && $this->translations->contentRevision(PublicTranslationSource::PUBLIC_PAGE, $page) !== null;
    }

    private function newsAvailable(string $slug): bool
    {
        $result = $this->news->findDisplayableSlug($slug);

        return $result['story'] instanceof NewsStory
            && $this->translations->contentRevision(PublicTranslationSource::NEWS_STORY, $result['story']) !== null;
    }

    private function galleryAvailable(string $slug): bool
    {
        $result = $this->gallery->findDisplayableSlug($slug);

        return $result['album'] instanceof GalleryAlbum
            && $this->translations->contentRevision(PublicTranslationSource::GALLERY_ALBUM, $result['album']) !== null;
    }

    private function noticeAvailable(string $slug): bool
    {
        $result = $this->notices->findDisplayableSlug($slug);

        return $result['notice'] instanceof Notice
            && $this->translations->contentRevision(PublicTranslationSource::NOTICE, $result['notice']) !== null;
    }

    private function isArchive(string $route): bool
    {
        return in_array($route, [
            'public.news.archive', 'public.gallery.archive', 'public.notices.archive',
            'public.ne.news.archive', 'public.ne.gallery.archive', 'public.ne.notices.archive',
        ], true);
    }
}
