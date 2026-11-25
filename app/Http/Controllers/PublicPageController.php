<?php

namespace App\Http\Controllers;

use App\Models\PublicPage;
use App\Models\PublicPageRevision;
use App\Services\PublicPages\PublicPageMarkdown;
use App\Services\PublicPages\PublicPageVisibility;
use Illuminate\Contracts\View\View;

class PublicPageController extends Controller
{
    public function __construct(
        private PublicPageVisibility $visibility,
        private PublicPageMarkdown $markdown,
    ) {}

    public function about(): View
    {
        return $this->pageView(PublicPage::TYPE_ABOUT, 'frontend.about');
    }

    public function academics(): View
    {
        return $this->pageView(PublicPage::TYPE_ACADEMICS, 'frontend.academics');
    }

    private function pageView(string $type, string $view): View
    {
        $page = $this->visibility->page($type);
        $revision = $page?->publishedRevision;
        [$featureUrl, $featureAlt, $featureWidth, $featureHeight] = $this->safeFeature($revision);

        return view($view, [
            'page' => $page,
            'revision' => $revision,
            'featureUrl' => $featureUrl,
            'featureAlt' => $featureAlt,
            'featureWidth' => $featureWidth,
            'featureHeight' => $featureHeight,
            'markdown' => $this->markdown,
        ]);
    }

    /** @return array{0: ?string, 1: string, 2: ?int, 3: ?int} */
    private function safeFeature(?PublicPageRevision $revision): array
    {
        $feature = $revision?->featureImage;
        $asset = $feature?->asset;
        $assetIsSafe = $asset !== null
            && $asset->status === 'published'
            && $asset->archived_at === null
            && in_array($asset->consent_state, ['not_required', 'confirmed'], true)
            && $asset->consent_withdrawn_at === null;
        $altText = $assetIsSafe
            ? trim((string) ($feature?->alt_text ?: $asset?->default_alt_text))
            : '';
        $hasMeaningfulAlternative = $asset?->is_decorative || $altText !== '';

        $isPublicFeature = $assetIsSafe && $hasMeaningfulAlternative;

        return [
            $isPublicFeature
                ? route('media.published', ['id' => $asset->getKey()])
                : null,
            $asset?->is_decorative ? '' : $altText,
            $isPublicFeature && (int) $asset->width > 0 ? (int) $asset->width : null,
            $isPublicFeature && (int) $asset->height > 0 ? (int) $asset->height : null,
        ];
    }
}
