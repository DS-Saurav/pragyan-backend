<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

use App\Http\Controllers\Admin\WebsiteSettingsController;
use App\Http\Resources\PublicGalleryAlbumResource;
use App\Models\HeroSlide;
use App\Models\PublicAcademicStage;
use App\Models\PublicPage;
use App\Models\PublicPageRevision;
use App\Models\SchoolEvent;
use App\Models\Setting;
use App\Services\Gallery\GalleryVisibility;
use App\Services\News\NewsVisibility;
use App\Services\Notices\NoticeVisibility;
use App\Services\PublicAdmissions\AdmissionCycleResolver;
use App\Services\PublicPhotos\PublicPhotoSlots;
use App\Services\PublicPages\PublicPageMarkdown;
use App\Services\PublicPages\PublicPageVisibility;
use App\Services\PublicTeam\PublicTeamVisibility;
use App\Support\SiteIdentity;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;

class PublicHomepageController extends Controller
{
    public function __construct(
        private AdmissionCycleResolver $admissionCycleResolver,
        private NewsVisibility $newsVisibility,
        private GalleryVisibility $galleryVisibility,
        private NoticeVisibility $noticeVisibility,
        private PublicTeamVisibility $publicTeamVisibility,
        private PublicPageVisibility $publicPageVisibility,
        private PublicPageMarkdown $publicPageMarkdown,
        private PublicPhotoSlots $publicPhotoSlots,
    ) {}

    public function index(): JsonResponse
    {
        $today = CarbonImmutable::now(AdmissionCycleResolver::TIMEZONE)->toDateString();
        $identity = SiteIdentity::publicProfile();
        $featuredStory = $this->newsVisibility->featured();
        $featuredAlbum = $this->galleryVisibility->featured();
        $academicsPage = $this->publicPageVisibility->page(PublicPage::TYPE_ACADEMICS);
        $academicsRevision = $academicsPage?->publishedRevision;

        return response()->json([
            'identity' => $identity,
            'heroSlides' => HeroSlide::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (HeroSlide $slide): array => $slide->toApiArray())
                ->values(),
            'homeHeroPhoto' => $this->publicPhotoSlots->resolve('home_hero'),
            'homeLearningPhoto' => $this->publicPhotoSlots->resolve('home_learning'),
            'heroHeadline' => Setting::get('website_hero_headline'),
            'heroIntro' => Setting::get('website_hero_intro'),
            'admissionCycle' => $this->admissionCycleResolver->current(),
            'latestNotice' => $this->noticeVisibility->latest(),
            'nextEvent' => SchoolEvent::query()
                ->where('is_public', true)
                ->whereDate('date', '>=', $today)
                ->orderBy('date')
                ->first(),
            'leadershipProfile' => $this->publicTeamVisibility->leadership(),
            'homeIntro' => trim((string) Setting::get('website_home_intro')),
            'highlights' => WebsiteSettingsController::highlights(),
            'featuredStory' => $featuredStory?->load(['publishedRevision.cover.asset']),
            'featuredAlbum' => $featuredAlbum !== null
                ? (new PublicGalleryAlbumResource(
                    $featuredAlbum->load(['category', 'coverPhoto', 'firstPhoto'])->loadCount('photos'),
                ))->resolve(request())
                : null,
            'academicsPreview' => $this->academicsPreview($academicsRevision),
            'homeMetaDescription' => $this->homepageMetaDescription($identity, $academicsRevision),
        ]);
    }

    /**
     * Create a deliberately small, text-only public projection. The visibility
     * service has already constrained the page and eager-loaded its published
     * learning stages, so drafts and internal SchoolClass records never reach
     * the homepage.
     *
     * @return array{
     *     title: string,
     *     introduction: string,
     *     learning_range: string,
     *     stages: array<int, array{label: string, title: string, description: string}>
     * }|null
     */
    private function academicsPreview(?PublicPageRevision $revision): ?array
    {
        if ($revision === null) {
            return null;
        }

        $publishedStages = $revision->academicStages;
        $stageLabels = $publishedStages
            ->pluck('stage_label')
            ->map(static fn (mixed $label): string => trim((string) $label))
            ->filter()
            ->values();

        return [
            'title' => trim((string) $revision->title) ?: 'Academics',
            'introduction' => $this->publicPageMarkdown->excerpt((string) $revision->introduction_markdown, 220),
            'learning_range' => $stageLabels->count() > 1
                ? $stageLabels->first().' to '.$stageLabels->last()
                : (string) $stageLabels->first(),
            'stages' => $publishedStages
                ->take(4)
                ->map(fn (PublicAcademicStage $stage): array => [
                    'label' => trim((string) $stage->stage_label),
                    'title' => trim((string) $stage->title),
                    'description' => $this->publicPageMarkdown->excerpt((string) $stage->description_markdown, 150),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * The homepage description may use only the approved public identity and
     * the currently displayable Academics revision. It intentionally does not
     * infer claims from unpublished CMS records, classes, legacy heroes, or
     * internal settings.
     *
     * @param  array<string, mixed>  $identity
     */
    private function homepageMetaDescription(array $identity, ?PublicPageRevision $academicsRevision): string
    {
        $name = trim((string) ($identity['name'] ?? ''));
        $tagline = trim((string) ($identity['tagline'] ?? ''));
        $academicsExcerpt = $academicsRevision === null
            ? ''
            : $this->publicPageMarkdown->excerpt((string) $academicsRevision->introduction_markdown, 130);

        return implode(' — ', array_filter([
            $name,
            $academicsExcerpt !== '' ? $academicsExcerpt : $tagline,
        ], static fn (string $value): bool => $value !== ''));
    }
}
