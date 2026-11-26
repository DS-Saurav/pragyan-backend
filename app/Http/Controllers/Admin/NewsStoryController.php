<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsStory;
use App\Models\NewsStoryRevision;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NewsStoryController extends Controller
{
    public function index(): JsonResponse
    {
        $featuredId = (int) Setting::get('featured_news_story_id', 0);
        $stories = NewsStory::query()
            ->with(['publishedRevision', 'revisions'])
            ->latest('updated_at')
            ->get()
            ->map(fn (NewsStory $s) => $this->formatStory($s, $featuredId));

        return response()->json(['stories' => $stories]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateStory($request);
        $userId = $request->user()?->id;

        $story = NewsStory::create([
            'slug' => $validated['slug'] ?: null,
            'publication_state' => 'draft',
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        NewsStoryRevision::create([
            'news_story_id' => $story->id,
            'revision_number' => 1,
            'workflow_state' => 'draft',
            'title' => $validated['title'],
            'slug' => $validated['slug'] ?: null,
            'category' => $validated['category'] ?: 'Learning',
            'summary' => $validated['summary'],
            'body_markdown' => $validated['body_markdown'],
            'created_by' => $userId,
        ]);

        $featuredId = (int) Setting::get('featured_news_story_id', 0);

        return response()->json([
            'story' => $this->formatStory($story->fresh(['publishedRevision', 'revisions']), $featuredId),
        ], 201);
    }

    public function update(Request $request, NewsStory $newsStory): JsonResponse
    {
        $validated = $this->validateStory($request);
        $userId = $request->user()?->id;
        $nextRev = ($newsStory->revisions()->max('revision_number') ?? 0) + 1;

        $newsStory->update([
            'slug' => $validated['slug'] ?: $newsStory->slug,
            'updated_by' => $userId,
        ]);

        NewsStoryRevision::create([
            'news_story_id' => $newsStory->id,
            'revision_number' => $nextRev,
            'workflow_state' => 'draft',
            'title' => $validated['title'],
            'slug' => $validated['slug'] ?: null,
            'category' => $validated['category'] ?: 'Learning',
            'summary' => $validated['summary'],
            'body_markdown' => $validated['body_markdown'],
            'created_by' => $userId,
        ]);

        $featuredId = (int) Setting::get('featured_news_story_id', 0);

        return response()->json([
            'story' => $this->formatStory($newsStory->fresh(['publishedRevision', 'revisions']), $featuredId),
        ]);
    }

    public function transition(Request $request, NewsStory $newsStory): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:submit,approve,publish,withdraw,archive,restore',
        ]);

        $latest = $newsStory->revisions()->sortByDesc('revision_number')->first();
        $userId = $request->user()?->id;

        switch ($validated['action']) {
            case 'submit':
                $newsStory->update(['publication_state' => 'review', 'updated_by' => $userId]);
                $latest?->update(['workflow_state' => 'review', 'submitted_at' => now()]);
                break;
            case 'approve':
                $latest?->update(['workflow_state' => 'approved', 'reviewed_at' => now(), 'reviewed_by' => $userId]);
                break;
            case 'publish':
                $slug = $newsStory->slug ?: Str::slug($latest?->title ?? 'story-'.$newsStory->id);
                $latest?->update(['workflow_state' => 'published', 'published_at' => now()]);
                $newsStory->update([
                    'publication_state' => 'published',
                    'slug' => $slug,
                    'published_revision_id' => $latest?->id,
                    'published_at' => now(),
                    'published_by' => $userId,
                ]);
                break;
            case 'withdraw':
                $newsStory->update(['publication_state' => 'withdrawn', 'withdrawn_at' => now()]);
                break;
            case 'archive':
                $newsStory->update(['publication_state' => 'archived', 'archived_at' => now()]);
                break;
            case 'restore':
                $newsStory->update(['publication_state' => 'draft']);
                $latest?->update(['workflow_state' => 'draft']);
                break;
        }

        $featuredId = (int) Setting::get('featured_news_story_id', 0);

        return response()->json([
            'story' => $this->formatStory($newsStory->fresh(['publishedRevision', 'revisions']), $featuredId),
        ]);
    }

    public function feature(NewsStory $newsStory): JsonResponse
    {
        Setting::set('featured_news_story_id', (string) $newsStory->id);

        return response()->json(['featuredStoryId' => $newsStory->id]);
    }

    public function unfeature(): JsonResponse
    {
        Setting::set('featured_news_story_id', '');

        return response()->json(['featuredStoryId' => null]);
    }

    private function validateStory(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:180',
            'slug' => 'nullable|string|max:180',
            'category' => 'nullable|string|max:60',
            'summary' => 'required|string|max:600',
            'body_markdown' => 'required|string|max:30000',
        ]);
    }

    private function formatStory(NewsStory $s, int $featuredId): array
    {
        $live = $s->publishedRevision;
        $latest = $s->revisions->sortByDesc('revision_number')->first();
        $rev = $live ?? $latest;

        return [
            'id' => $s->id,
            'title' => $rev?->title ?? 'Untitled draft',
            'slug' => $s->slug,
            'category' => $rev?->category ?? 'Learning',
            'summary' => $rev?->summary ?? '',
            'bodyMarkdown' => $rev?->body_markdown ?? '',
            'publicationState' => $s->publication_state ?? 'draft',
            'revisionNumber' => $latest?->revision_number ?? 1,
            'workflowState' => $latest?->workflow_state ?? 'draft',
            'isFeatured' => $featuredId > 0 && $featuredId === $s->id,
            'publishedAt' => optional($s->published_at)->toIso8601String(),
            'updatedAt' => optional($s->updated_at)->toIso8601String() ?? now()->toIso8601String(),
        ];
    }
}
