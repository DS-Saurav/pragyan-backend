<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

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

    public function index(): JsonResponse
    {
        return response()->json([
            'stories' => $this->newsVisibility->displayableQuery()
                ->with(['publishedRevision.cover.asset'])
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->paginate(9)
                ->withQueryString(),
            'isArchive' => false,
        ]);
    }

    public function archive(): JsonResponse
    {
        return response()->json([
            'stories' => $this->newsVisibility->displayableQuery()
                ->with(['publishedRevision.cover.asset'])
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->paginate(24)
                ->withQueryString(),
            'isArchive' => true,
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $result = $this->newsVisibility->findDisplayableSlug($slug);

        if ($result['redirect'] instanceof NewsStory) {
            return response()->json(["ok" => true]);
        }

        abort_unless($result['story'] instanceof NewsStory, 404);
        $story = $result['story']->load(['publishedRevision.cover.asset']);
        $revision = $story->publishedRevision;
        abort_unless($revision !== null, 404);

        $cover = $revision->cover;
        $coverUrl = $cover?->asset?->public_path !== null
            ? route('media.published', $cover->media_asset_id)
            : null;

        return response()->json([
            'story' => $story,
            'revision' => $revision,
            'cover' => $cover,
            'coverUrl' => $coverUrl,
            'bodyHtml' => $this->newsMarkdown->toHtml((string) $revision->body_markdown),
        ]);
    }
}
