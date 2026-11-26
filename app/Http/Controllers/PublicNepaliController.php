<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

use App\Models\GalleryAlbum;
use App\Models\NewsStory;
use App\Models\Notice;
use App\Models\PublicContentTranslationRevision;
use App\Models\PublicPage;
use App\Models\SchoolEvent;
use App\Services\Gallery\GalleryVisibility;
use App\Services\News\NewsVisibility;
use App\Services\Notices\NoticeVisibility;
use App\Services\PublicAdmissions\AdmissionCycleResolver;
use App\Services\PublicPages\PublicPageVisibility;
use App\Services\PublicPhotos\PublicPhotoSlots;
use App\Services\PublicTeam\PublicTeamVisibility;
use App\Services\PublicTranslations\PublicTranslationSource;
use App\Services\PublicTranslations\PublicTranslationVisibility;
use App\Support\SiteIdentity;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Nepali routes are separate from English controllers so a missing human
 * translation cannot accidentally render English source under a Nepali URL.
 */
final class PublicNepaliController extends Controller
{
    public function __construct(
        private PublicTranslationVisibility $translations,
        private PublicTranslationSource $sources,
        private NewsVisibility $news,
        private GalleryVisibility $gallery,
        private NoticeVisibility $notices,
        private PublicPageVisibility $pages,
        private PublicTeamVisibility $team,
        private AdmissionCycleResolver $admissions,
        private PublicPhotoSlots $publicPhotoSlots,
    ) {}

    public function home(): JsonResponse
    {
        $revision = $this->translations->siteRevision('homepage');

        return $revision === null
            ? $this->unavailable(route('home'))
            : $this->content((array) $revision->payload, __('public.home'), route('home'), null, false, $this->publicPhotoSlots->resolve('home_hero', 'ne'));
    }

    public function about(): JsonResponse
    {
        return $this->page(PublicPage::TYPE_ABOUT, route('public.about'));
    }

    public function academics(): JsonResponse
    {
        return $this->page(PublicPage::TYPE_ACADEMICS, route('public.academics'));
    }

    public function admissions(): JsonResponse
    {
        $source = $this->admissions->current();
        $revision = $source === null ? null : $this->translations->contentRevision(PublicTranslationSource::ADMISSION_CYCLE, $source);

        if ($revision === null) {
            return $this->unavailable(route('public.admissions'));
        }

        if ($source->public_status === 'closed') {
            return response()->json([
                'payload' => (array) $revision->payload,
                'identity' => SiteIdentity::publicProfile(),
                'source' => $source,
                'localizedAvailable' => true,
                'heroPhoto' => $this->publicPhotoSlots->resolve('admissions_hero', 'ne'),
            ]);
        }

        return $this->content((array) $revision->payload, $this->title((array) $revision->payload, __('public.admissions')), route('public.admissions'), $revision);
    }

    public function contact(): JsonResponse
    {
        $revision = $this->translations->siteRevision('identity');

        return $revision === null
            ? $this->unavailable(route('public.contact'))
            : view('frontend.localized.contact', [
                'payload' => (array) $revision->payload,
                'identity' => SiteIdentity::publicProfile(),
                'localizedAvailable' => true,
                'visitPhoto' => $this->publicPhotoSlots->resolve('contact_visit', 'ne'),
            ]);
    }

    public function team(): JsonResponse
    {
        $sources = $this->team->displayableQuery()->with('publishedRevision.portrait.asset')->orderBy('id')->get();

        return $this->listing(
            $this->translations->translated($sources, PublicTranslationSource::PUBLIC_TEAM_PROFILE),
            __('public.leadership_and_team'),
            route('public.team'),
        );
    }

    public function calendar(): JsonResponse
    {
        $sources = SchoolEvent::query()->where('is_public', true)->orderBy('date')->orderBy('id')->get();

        return $this->listing(
            $this->translations->translated($sources, PublicTranslationSource::SCHOOL_EVENT),
            __('public.calendar'),
            route('public.calendar'),
        );
    }

    public function news(): JsonResponse
    {
        return $this->newsListing(false);
    }

    public function newsArchive(): JsonResponse
    {
        return $this->newsListing(true);
    }

    public function newsShow(string $slug): JsonResponse
    {
        $result = $this->news->findDisplayableSlug($slug);
        if ($result['redirect'] instanceof NewsStory) {
            return response()->json(["ok" => true]);
        }
        abort_unless($result['story'] instanceof NewsStory, 404);
        $source = $result['story'];
        $revision = $this->translations->contentRevision(PublicTranslationSource::NEWS_STORY, $source);

        return $revision === null
            ? $this->unavailable(route('public.news.show', ['slug' => $source->slug]))
            : $this->content((array) $revision->payload, $this->title((array) $revision->payload, __('public.news')), route('public.news.show', ['slug' => $source->slug]), $revision);
    }

    public function gallery(): JsonResponse
    {
        return $this->galleryListing(false);
    }

    public function galleryArchive(): JsonResponse
    {
        return $this->galleryListing(true);
    }

    public function galleryShow(string $slug): JsonResponse
    {
        $result = $this->gallery->findDisplayableSlug($slug);
        if ($result['redirect'] instanceof GalleryAlbum) {
            return response()->json(["ok" => true]);
        }
        abort_unless($result['album'] instanceof GalleryAlbum, 404);
        $source = $result['album'];
        $revision = $this->translations->contentRevision(PublicTranslationSource::GALLERY_ALBUM, $source);

        return $revision === null
            ? $this->unavailable(route('public.gallery.show', ['slug' => $source->slug]))
            : $this->content((array) $revision->payload, $this->title((array) $revision->payload, __('public.gallery')), route('public.gallery.show', ['slug' => $source->slug]), $revision);
    }

    public function notices(): JsonResponse
    {
        return $this->noticeListing(false);
    }

    public function noticesArchive(): JsonResponse
    {
        return $this->noticeListing(true);
    }

    public function noticeShow(string $slug): JsonResponse
    {
        $result = $this->notices->findDisplayableSlug($slug);
        if ($result['redirect'] instanceof Notice) {
            return response()->json(["ok" => true]);
        }
        abort_unless($result['notice'] instanceof Notice, 404);
        $source = $result['notice'];
        $revision = $this->translations->contentRevision(PublicTranslationSource::NOTICE, $source);

        return $revision === null
            ? $this->unavailable(route('public.notices.show', ['slug' => $source->slug]))
            : $this->content((array) $revision->payload, $this->title((array) $revision->payload, __('public.notices')), route('public.notices.show', ['slug' => $source->slug]), $revision);
    }

    private function page(string $type, string $englishUrl): JsonResponse
    {
        $source = $this->pages->page($type);
        $revision = $source === null ? null : $this->translations->contentRevision(PublicTranslationSource::PUBLIC_PAGE, $source);

        return $revision === null
            ? $this->unavailable($englishUrl)
            : $this->content(
                (array) $revision->payload,
                $this->title((array) $revision->payload, $type === PublicPage::TYPE_ABOUT ? __('public.about') : __('public.academics')),
                $englishUrl,
                $revision,
                true,
                $type === PublicPage::TYPE_ACADEMICS ? $this->publicPhotoSlots->resolve('home_learning', 'ne') : null,
            );
    }

    private function newsListing(bool $archive): JsonResponse
    {
        $sources = $this->news->displayableQuery()->with('publishedRevision.cover.asset')->orderByDesc('published_at')->orderByDesc('id')->get();

        return $this->listing($this->translations->translated($sources, PublicTranslationSource::NEWS_STORY), $archive ? __('public.news_archive') : __('public.news'), $archive ? route('public.news.archive') : route('public.news'), $archive);
    }

    private function galleryListing(bool $archive): JsonResponse
    {
        $sources = $this->gallery->displayableQuery()->with(['publishedRevision.cover.asset', 'publishedRevision.photos.asset'])->orderByDesc('published_at')->orderByDesc('id')->get();

        return $this->listing($this->translations->translated($sources, PublicTranslationSource::GALLERY_ALBUM), $archive ? __('public.gallery_archive') : __('public.gallery'), $archive ? route('public.gallery.archive') : route('public.gallery'), $archive);
    }

    private function noticeListing(bool $archive): JsonResponse
    {
        $sources = $this->notices->displayableQuery()->with('publishedRevision')->orderByDesc('date')->orderByDesc('published_at')->orderByDesc('id')->get();

        return $this->listing($this->translations->translated($sources, PublicTranslationSource::NOTICE), $archive ? __('public.notices_archive') : __('public.notices'), $archive ? route('public.notices.archive') : route('public.notices'), $archive);
    }

    /** @param Collection<int, array{source: Model, revision: PublicContentTranslationRevision}> $translated */
    private function listing(Collection $translated, string $title, string $englishUrl, bool $archive = false): JsonResponse
    {
        $items = $translated->map(function (array $entry): array {
            $payload = (array) $entry['revision']->payload;

            return [
                'title' => $this->title($payload, __('public.published_content')),
                'summary' => $this->summary($payload),
                'url' => $this->localizedUrl($entry['source']),
                'media' => $this->media($entry['revision']),
            ];
        });
        if ($items->isEmpty()) {
            return $this->unavailable($englishUrl);
        }

        return response()->json([
            'title' => $title,
            'items' => $items,
            'englishUrl' => $englishUrl,
            'localizedAvailable' => true,
            'isArchive' => $archive,
            'metaDescription' => $title,
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function content(array $payload, string $title, string $englishUrl, ?PublicContentTranslationRevision $revision = null, bool $photoHero = false, ?array $slotHeroPhoto = null): JsonResponse
    {
        return response()->json([
            'title' => $title,
            'payload' => $payload,
            'englishUrl' => $englishUrl,
            'bodyHtml' => $this->bodyHtml($payload),
            'media' => $revision instanceof PublicContentTranslationRevision ? $this->media($revision) : collect(),
            'photoHero' => $photoHero,
            'slotHeroPhoto' => $slotHeroPhoto,
            'localizedAvailable' => true,
            'metaDescription' => $this->summary($payload) ?: $title,
        ]);
    }

    private function unavailable(string $englishUrl): JsonResponse
    {
        return response()->json([
            'englishUrl' => $englishUrl,
            'localizedAvailable' => false,
            'metaDescription' => __('public.unavailable.heading'),
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function title(array $payload, string $fallback): string
    {
        foreach (['title', 'label', 'name', 'hero_headline', 'display_name'] as $key) {
            $value = trim((string) ($payload[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return $fallback;
    }

    /** @param array<string, mixed> $payload */
    private function summary(array $payload): string
    {
        foreach (['summary', 'public_intro', 'hero_intro', 'introduction_markdown', 'description', 'content', 'body_markdown', 'bio_markdown'] as $key) {
            $value = trim(strip_tags((string) ($payload[$key] ?? '')));
            if ($value !== '') {
                return Str::limit($value, 220);
            }
        }

        return '';
    }

    /** @param array<string, mixed> $payload */
    private function bodyHtml(array $payload): HtmlString
    {
        foreach (['body_markdown', 'content', 'introduction_markdown', 'bio_markdown', 'description', 'public_intro', 'home_intro', 'visit_instructions'] as $key) {
            $body = trim((string) ($payload[$key] ?? ''));
            if ($body !== '') {
                return new HtmlString(Str::markdown($body, ['html_input' => 'strip', 'allow_unsafe_links' => false]));
            }
        }

        return new HtmlString('');
    }

    /** @return Collection<int, array{url: string, alt: string, caption: ?string}> */
    private function media(PublicContentTranslationRevision $revision): Collection
    {
        return $revision->media->map(function ($annotation): ?array {
            $attachment = $annotation->mediaAttachment;
            $asset = $attachment?->asset;
            $safe = $asset !== null
                && $asset->status === 'published'
                && $asset->archived_at === null
                && in_array($asset->consent_state, ['not_required', 'confirmed'], true)
                && $asset->consent_withdrawn_at === null
                && $asset->public_path !== null;
            $alt = trim((string) $annotation->alt_text);
            if (! $safe || (! $asset->is_decorative && $alt === '')) {
                return null;
            }

            return [
                'url' => route('media.published', ['id' => $asset->id]),
                'alt' => $asset->is_decorative ? '' : $alt,
                'caption' => filled($annotation->caption) ? (string) $annotation->caption : null,
            ];
        })->filter()->values();
    }

    private function localizedUrl(Model $source): ?string
    {
        return match (true) {
            $source instanceof NewsStory => route('public.ne.news.show', ['slug' => $source->slug]),
            $source instanceof GalleryAlbum => route('public.ne.gallery.show', ['slug' => $source->slug]),
            $source instanceof Notice => route('public.ne.notices.show', ['slug' => $source->slug]),
            default => request()->url(),
        };
    }
}
