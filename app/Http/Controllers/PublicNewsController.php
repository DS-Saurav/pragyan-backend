<?php

namespace App\Http\Controllers;

use App\Models\NewsStory;
use App\Services\News\NewsMarkdown;
use App\Services\News\NewsVisibility;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class PublicNewsController extends Controller
{
    public function __construct(
        private NewsVisibility $newsVisibility,
        private NewsMarkdown $newsMarkdown,
    ) {}

    public function index(): View
    {
        return view('frontend.news', [
            'stories' => $this->newsVisibility->displayableQuery()
                ->with(['publishedRevision.cover.asset'])
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->paginate(9)
                ->withQueryString(),
            'isArchive' => false,
        ]);
    }

    public function archive(): View
    {
        return view('frontend.news', [
            'stories' => $this->newsVisibility->displayableQuery()
                ->with(['publishedRevision.cover.asset'])
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->paginate(24)
                ->withQueryString(),
            'isArchive' => true,
        ]);
    }

    public function show(string $slug): View|RedirectResponse
    {
        $result = $this->newsVisibility->findDisplayableSlug($slug);

        if ($result['redirect'] instanceof NewsStory) {
            return redirect()->route('public.news.show', ['slug' => $result['redirect']->slug], 301);
        }

        abort_unless($result['story'] instanceof NewsStory, 404);
        $story = $result['story']->load(['publishedRevision.cover.asset']);
        $revision = $story->publishedRevision;
        abort_unless($revision !== null, 404);

        $cover = $revision->cover;
        $coverUrl = $cover?->asset?->public_path !== null
            ? route('media.published', $cover->media_asset_id)
            : null;

        return view('frontend.news-show', [
            'story' => $story,
            'revision' => $revision,
            'cover' => $cover,
            'coverUrl' => $coverUrl,
            'bodyHtml' => $this->newsMarkdown->toHtml((string) $revision->body_markdown),
        ]);
    }
}
