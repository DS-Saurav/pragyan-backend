<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Models\NewsStory;
use App\Models\NewsStoryRevision;
use App\Models\Setting;
use App\Models\User;
use App\Services\Media\MediaLibrary;
use App\Services\News\NewsMarkdown;
use App\Services\News\NewsPublisher;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class NewsStoryController extends Controller
{
    public function __construct(
        private NewsPublisher $newsPublisher,
        private MediaLibrary $mediaLibrary,
        private NewsMarkdown $newsMarkdown,
    ) {}

    public function index(): View
    {
        $this->authorizeAny(['news.author', 'news.review', 'news.publish']);

        return view('admin.news.index', [
            'stories' => NewsStory::query()
                ->with(['creator', 'editor', 'publisher', 'revisions', 'publishedRevision', 'scheduledRevision'])
                ->orderByDesc('updated_at')
                ->paginate(30),
            'featuredStoryId' => (int) Setting::get('website_featured_news_story_id'),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('news.author');

        return $this->formView(new NewsStory([
            'publication_state' => 'draft',
        ]), new NewsStoryRevision([
            'workflow_state' => 'draft',
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('news.author');
        $data = $this->validated($request);
        $story = $this->newsPublisher->createDraft($this->actor(), $this->storyData($data));
        $revision = $story->revisions->last();

        if ($revision instanceof NewsStoryRevision) {
            $this->replaceCoverWhenSelected($data, $revision);
        }

        return redirect()->route('admin.news.edit', $story)
            ->with('success', 'News story saved as a private draft. Submit it for review when the content and cover are ready.');
    }

    public function edit(NewsStory $newsStory): View
    {
        $this->authorizeAny(['news.author', 'news.review', 'news.publish']);
        $newsStory->load([
            'revisions.cover.asset',
            'publishedRevision.cover.asset',
            'scheduledRevision.cover.asset',
            'creator',
            'editor',
            'publisher',
        ]);

        return $this->formView($newsStory, $this->activeRevision($newsStory));
    }

    public function update(Request $request, NewsStory $newsStory): RedirectResponse
    {
        Gate::authorize('news.author');
        $data = $this->validated($request);
        $revision = $this->newsPublisher->saveDraft($this->actor(), $newsStory, $this->storyData($data));
        $this->replaceCoverWhenSelected($data, $revision);

        return redirect()->route('admin.news.edit', $newsStory)
            ->with('success', $newsStory->publication_state === 'published'
                ? 'A private revision was saved. The currently published story has not changed.'
                : 'News draft saved.');
    }

    public function preview(NewsStory $newsStory, NewsStoryRevision $revision): View
    {
        $this->authorizeAny(['news.author', 'news.review', 'news.publish']);
        $this->ensureRevisionBelongsToStory($newsStory, $revision);
        $revision->load('cover.asset');

        return view('admin.news.preview', [
            'story' => $newsStory,
            'revision' => $revision,
            'cover' => $revision->cover,
            'bodyHtml' => $this->newsMarkdown->toHtml((string) $revision->body_markdown),
        ]);
    }

    public function submit(NewsStory $newsStory, NewsStoryRevision $revision): RedirectResponse
    {
        Gate::authorize('news.author');
        $this->ensureRevisionBelongsToStory($newsStory, $revision);
        $this->newsPublisher->submit($this->actor(), $newsStory, $revision);

        return back()->with('success', 'News revision submitted for review.');
    }

    public function approve(NewsStory $newsStory, NewsStoryRevision $revision): RedirectResponse
    {
        Gate::authorize('news.review');
        $this->ensureRevisionBelongsToStory($newsStory, $revision);
        $this->newsPublisher->approve($this->actor(), $newsStory, $revision);

        return back()->with('success', 'News revision approved for publication.');
    }

    public function publish(Request $request, NewsStory $newsStory, NewsStoryRevision $revision): RedirectResponse
    {
        Gate::authorize('news.publish');
        $this->ensureRevisionBelongsToStory($newsStory, $revision);
        $data = $request->validate([
            'scheduled_for' => ['nullable', 'date', 'after:now'],
        ]);
        $scheduledFor = empty($data['scheduled_for'])
            ? null
            : CarbonImmutable::parse($data['scheduled_for'], 'Asia/Kathmandu');
        $this->newsPublisher->publish($this->actor(), $newsStory, $revision, $scheduledFor);

        return back()->with('success', $scheduledFor === null
            ? 'News story published.'
            : 'News story scheduled for publication.');
    }

    public function withdraw(NewsStory $newsStory): RedirectResponse
    {
        Gate::authorize('news.publish');
        $this->newsPublisher->withdraw($this->actor(), $newsStory);

        return back()->with('success', 'News story withdrawn from the public website.');
    }

    public function archive(NewsStory $newsStory): RedirectResponse
    {
        Gate::authorize('news.publish');
        $this->newsPublisher->archive($this->actor(), $newsStory);

        return back()->with('success', 'News story archived. Its editorial history has been retained.');
    }

    public function restore(NewsStory $newsStory): RedirectResponse
    {
        Gate::authorize('news.publish');
        $this->newsPublisher->restore($this->actor(), $newsStory);

        return back()->with('success', 'News story restored as a private draft.');
    }

    public function feature(NewsStory $newsStory): RedirectResponse
    {
        Gate::authorize('news.publish');
        $this->newsPublisher->setFeatured($this->actor(), $newsStory);

        return back()->with('success', 'This is now the optional featured story on the homepage.');
    }

    public function unfeature(NewsStory $newsStory): RedirectResponse
    {
        Gate::authorize('news.publish');
        $this->newsPublisher->setFeatured($this->actor(), null);

        return back()->with('success', 'The homepage no longer has a featured story.');
    }

    private function formView(NewsStory $story, NewsStoryRevision $revision): View
    {
        return view('admin.news.form', [
            'story' => $story,
            'revision' => $revision,
            'assets' => MediaAsset::query()
                ->where('status', 'published')
                ->whereNull('archived_at')
                ->whereNull('consent_withdrawn_at')
                ->whereIn('consent_state', ['not_required', 'confirmed'])
                ->where(function ($query): void {
                    $query->whereNotNull('default_alt_text')->where('default_alt_text', '!=', '')
                        ->orWhere('is_decorative', true);
                })
                ->orderByDesc('published_at')
                ->get(),
            'states' => NewsStory::PUBLICATION_STATES,
            'revisionStates' => NewsStoryRevision::WORKFLOW_STATES,
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:180', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/'],
            'summary' => ['required', 'string', 'max:500'],
            'body_markdown' => ['required', 'string', 'max:30000'],
            'category' => ['nullable', 'string', 'max:60'],
            'public_attribution' => ['nullable', 'string', 'max:255'],
            'cover_asset_id' => ['nullable', 'integer', Rule::exists('media_assets', 'id')],
            'cover_caption' => ['nullable', 'string', 'max:10000'],
            'cover_alt_text' => ['nullable', 'string', 'max:255'],
            'cover_focal_x' => ['nullable', 'numeric', 'between:0,100'],
            'cover_focal_y' => ['nullable', 'numeric', 'between:0,100'],
        ]);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function storyData(array $data): array
    {
        return [
            'title' => $data['title'],
            'slug' => $data['slug'] ?? null,
            'summary' => $data['summary'],
            'body_markdown' => $data['body_markdown'],
            'category' => $data['category'] ?? null,
            'public_attribution' => $data['public_attribution'] ?? null,
        ];
    }

    /** @param array<string, mixed> $data */
    private function replaceCoverWhenSelected(array $data, NewsStoryRevision $revision): void
    {
        if (empty($data['cover_asset_id'])) {
            return;
        }

        $asset = MediaAsset::findOrFail($data['cover_asset_id']);
        $this->mediaLibrary->replaceNewsStoryRevisionCover($this->actor(), $revision, $asset, [
            'caption' => $data['cover_caption'] ?? null,
            'alt_text' => $data['cover_alt_text'] ?? null,
            'focal_x' => $data['cover_focal_x'] ?? 50,
            'focal_y' => $data['cover_focal_y'] ?? 50,
        ]);
    }

    private function activeRevision(NewsStory $story): NewsStoryRevision
    {
        $active = $story->revisions
            ->filter(fn (NewsStoryRevision $revision): bool => in_array($revision->workflow_state, ['draft', 'review', 'approved', 'scheduled'], true))
            ->sortByDesc('revision_number')
            ->first();

        if ($active instanceof NewsStoryRevision) {
            return $active;
        }

        return $story->publishedRevision ?? new NewsStoryRevision(['workflow_state' => 'draft']);
    }

    private function ensureRevisionBelongsToStory(NewsStory $story, NewsStoryRevision $revision): void
    {
        abort_unless($revision->news_story_id === $story->id, 404);
    }

    /** @param array<int, string> $abilities */
    private function authorizeAny(array $abilities): void
    {
        foreach ($abilities as $ability) {
            if (Gate::allows($ability)) {
                return;
            }
        }

        abort(403);
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
