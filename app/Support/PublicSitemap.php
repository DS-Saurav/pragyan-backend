<?php

namespace App\Support;

use App\Models\PublicPage;
use App\Services\Gallery\GalleryVisibility;
use App\Services\News\NewsVisibility;
use App\Services\Notices\NoticeVisibility;
use App\Services\PublicAdmissions\AdmissionCycleResolver;
use App\Services\PublicPages\PublicPageVisibility;
use App\Services\PublicTeam\PublicTeamVisibility;
use App\Services\PublicTranslations\PublicTranslationSource;
use App\Services\PublicTranslations\PublicTranslationVisibility;
use Carbon\CarbonInterface;

/** Canonical-only, locale-aware discoverability projection for public content. */
final class PublicSitemap
{
    public function __construct(
        private NewsVisibility $newsVisibility,
        private GalleryVisibility $galleryVisibility,
        private NoticeVisibility $noticeVisibility,
        private PublicPageVisibility $publicPageVisibility,
        private PublicTeamVisibility $publicTeamVisibility,
        private AdmissionCycleResolver $admissionCycles,
        private PublicTranslationVisibility $translations,
    ) {}

    public function xml(): string
    {
        $urls = collect($this->entries())->map(function (array $entry): string {
            $lastModified = $entry['lastmod'] === null ? '' : "\n    <lastmod>".$this->xmlValue($entry['lastmod']).'</lastmod>';
            $alternates = collect($entry['alternates'])->map(fn (array $alternate): string => "\n    <xhtml:link rel=\"alternate\" hreflang=\"".$this->xmlValue($alternate['locale']).'" href="'.$this->xmlValue($alternate['url']).'" />')->implode('');

            return "  <url>\n    <loc>".$this->xmlValue($entry['loc']).'</loc>'.$lastModified.$alternates."\n  </url>";
        })->implode("\n");

        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n"
            .$urls."\n</urlset>\n";
    }

    public function robots(): string
    {
        return implode("\n", [
            'User-agent: *',
            'Disallow: /admin/',
            'Disallow: /dashboard',
            'Disallow: /profile',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /forgot-password',
            'Disallow: /reset-password',
            'Disallow: /verify-email',
            'Disallow: /confirm-password',
            'Disallow: /media/',
            'Disallow: /news/archive',
            'Disallow: /gallery/archive',
            'Disallow: /notices/archive',
            'Disallow: /ne/news/archive',
            'Disallow: /ne/gallery/archive',
            'Disallow: /ne/notices/archive',
            '',
            'Sitemap: '.self::canonicalUrl('/sitemap.xml'),
            '',
        ]);
    }

    public static function canonicalUrl(string $path = '/'): string
    {
        $origin = rtrim((string) config('app.url'), '/');
        $path = '/'.ltrim($path, '/');

        return $origin.($path === '/' ? '/' : $path);
    }

    public static function canonicalMediaUrl(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        $parts = parse_url($value);
        $path = is_array($parts) ? (string) ($parts['path'] ?? '') : '';

        return preg_match('#\A/media/[1-9][0-9]*\z#', $path) === 1 ? self::canonicalUrl($path) : null;
    }

    /** @return array<string, string> */
    public static function organizationSchema(): array
    {
        $identity = SiteIdentity::publicProfile();
        $schema = ['@context' => 'https://schema.org', '@type' => 'EducationalOrganization', 'name' => (string) $identity['name'], 'url' => self::canonicalUrl()];
        foreach (['email', 'phone', 'address'] as $field) {
            $value = trim((string) ($identity[$field] ?? ''));
            if ($value !== '') {
                $schema[$field === 'phone' ? 'telephone' : $field] = $value;
            }
        }

        return $schema;
    }

    /** @return array<int, array{loc: string, lastmod: ?string, alternates: array<int, array{locale: string, url: string}>}> */
    private function entries(): array
    {
        $entries = [];
        $homepage = $this->translations->siteRevision('homepage');
        $entries = [...$entries, ...$this->pair('/', null, $homepage !== null, $homepage?->published_at)];
        $admissionCycle = $this->admissionCycles->current();
        $admissionTranslation = $admissionCycle === null ? null : $this->translations->contentRevision(PublicTranslationSource::ADMISSION_CYCLE, $admissionCycle);
        $entries = [...$entries, ...$this->pair('/admissions', null, $admissionTranslation !== null, $admissionTranslation?->published_at)];
        $identity = $this->translations->siteRevision('identity');
        $entries = [...$entries, ...$this->pair('/contact', null, $identity !== null, $identity?->published_at)];
        $entries = [...$entries, ...$this->pair('/calendar', null, $this->translations->hasContent(PublicTranslationSource::SCHOOL_EVENT))];

        $latestNotice = $this->noticeVisibility->latest();
        $entries = [...$entries, ...$this->pair('/notices', $this->lastModified($latestNotice?->published_at ?? $latestNotice?->updated_at), $this->translations->hasContent(PublicTranslationSource::NOTICE))];

        foreach ([PublicPage::TYPE_ABOUT => '/about', PublicPage::TYPE_ACADEMICS => '/academics'] as $type => $path) {
            $page = $this->publicPageVisibility->page($type);
            if ($page !== null && $page->published_at !== null) {
                $translation = $this->translations->contentRevision(PublicTranslationSource::PUBLIC_PAGE, $page);
                $entries = [...$entries, ...$this->pair($path, $this->lastModified($page->publishedRevision?->published_at ?? $page->published_at), $translation !== null, $translation?->published_at)];
            }
        }

        $team = $this->publicTeamVisibility->displayableQuery()->orderByDesc('published_at')->first();
        if ($team !== null && $team->published_at !== null) {
            $entries = [...$entries, ...$this->pair('/team', $this->lastModified($team->published_at), $this->translations->hasContent(PublicTranslationSource::PUBLIC_TEAM_PROFILE))];
        }

        $stories = $this->newsVisibility->displayableQuery()->orderByDesc('published_at')->orderByDesc('id')->get();
        if ($stories->isNotEmpty()) {
            $entries = [...$entries, ...$this->pair('/news', $this->lastModified($stories->first()?->published_at), $this->translations->hasContent(PublicTranslationSource::NEWS_STORY))];
            foreach ($stories as $story) {
                if (filled($story->slug)) {
                    $translation = $this->translations->contentRevision(PublicTranslationSource::NEWS_STORY, $story);
                    $entries = [...$entries, ...$this->pair('/news/'.$story->slug, $this->lastModified($story->published_at), $translation !== null, $translation?->published_at)];
                }
            }
        }

        $albums = $this->galleryVisibility->displayableQuery()->orderByDesc('published_at')->orderByDesc('id')->get();
        if ($albums->isNotEmpty()) {
            $entries = [...$entries, ...$this->pair('/gallery', $this->lastModified($albums->first()?->published_at), $this->translations->hasContent(PublicTranslationSource::GALLERY_ALBUM))];
            foreach ($albums as $album) {
                if (filled($album->slug)) {
                    $translation = $this->translations->contentRevision(PublicTranslationSource::GALLERY_ALBUM, $album);
                    $entries = [...$entries, ...$this->pair('/gallery/'.$album->slug, $this->lastModified($album->published_at), $translation !== null, $translation?->published_at)];
                }
            }
        }

        $notices = $this->noticeVisibility->displayableQuery()->orderByDesc('published_at')->orderByDesc('id')->get();
        foreach ($notices as $notice) {
            if (filled($notice->slug)) {
                $translation = $this->translations->contentRevision(PublicTranslationSource::NOTICE, $notice);
                $entries = [...$entries, ...$this->pair('/notices/'.$notice->slug, $this->lastModified($notice->published_at ?? $notice->updated_at), $translation !== null, $translation?->published_at)];
            }
        }

        return $entries;
    }

    /** @return array<int, array{loc: string, lastmod: ?string, alternates: array<int, array{locale: string, url: string}>}> */
    private function pair(string $englishPath, ?string $englishLastModified, bool $nepaliAvailable, mixed $nepaliPublishedAt = null): array
    {
        $english = self::canonicalUrl($englishPath);
        if (! $nepaliAvailable) {
            return [$this->entry($english, $englishLastModified)];
        }
        $nepaliPath = $englishPath === '/' ? '/ne' : '/ne'.$englishPath;
        $nepali = self::canonicalUrl($nepaliPath);
        $alternates = [
            ['locale' => 'en', 'url' => $english],
            ['locale' => 'ne', 'url' => $nepali],
            ['locale' => 'x-default', 'url' => $english],
        ];

        return [
            $this->entry($english, $englishLastModified, $alternates),
            $this->entry($nepali, $this->lastModified($nepaliPublishedAt), $alternates),
        ];
    }

    /** @param array<int, array{locale: string, url: string}> $alternates
     * @return array{loc: string, lastmod: ?string, alternates: array<int, array{locale: string, url: string}>}
     */
    private function entry(string $url, ?string $lastModified = null, array $alternates = []): array
    {
        return ['loc' => $url, 'lastmod' => $lastModified, 'alternates' => $alternates];
    }

    private function lastModified(mixed $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->toAtomString() : null;
    }

    private function xmlValue(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
