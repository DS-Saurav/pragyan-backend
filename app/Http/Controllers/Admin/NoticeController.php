<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use App\Models\NoticeRevision;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NoticeController extends Controller
{
    public function index(): JsonResponse
    {
        $notices = Notice::query()
            ->with(['publishedRevision', 'revisions'])
            ->latest('updated_at')
            ->get()
            ->map(fn (Notice $n) => $this->formatNotice($n));

        return response()->json(['notices' => $notices]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateNotice($request);
        $userId = $request->user()?->id;

        $notice = Notice::create([
            'title' => $validated['title'],
            'slug' => $validated['slug'] ?: null,
            'content' => $validated['content'],
            'date' => $validated['date'],
            'audience' => $validated['audience'],
            'priority' => $validated['priority'] ?? null,
            'is_urgent' => (bool) ($validated['is_urgent'] ?? false),
            'publication_state' => 'draft',
            'status' => 'draft',
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        NoticeRevision::create([
            'notice_id' => $notice->id,
            'revision_number' => 1,
            'workflow_state' => 'draft',
            'title' => $validated['title'],
            'slug' => $validated['slug'] ?: null,
            'content' => $validated['content'],
            'date' => $validated['date'],
            'audience' => $validated['audience'],
            'priority' => $validated['priority'] ?? null,
            'is_urgent' => (bool) ($validated['is_urgent'] ?? false),
            'created_by' => $userId,
        ]);

        return response()->json([
            'notice' => $this->formatNotice($notice->fresh(['publishedRevision', 'revisions'])),
        ], 201);
    }

    public function update(Request $request, Notice $notice): JsonResponse
    {
        $validated = $this->validateNotice($request);
        $userId = $request->user()?->id;
        $nextRev = ($notice->revisions()->max('revision_number') ?? 0) + 1;

        $notice->update([
            'title' => $validated['title'],
            'slug' => $validated['slug'] ?: $notice->slug,
            'content' => $validated['content'],
            'date' => $validated['date'],
            'audience' => $validated['audience'],
            'priority' => $validated['priority'] ?? null,
            'is_urgent' => (bool) ($validated['is_urgent'] ?? false),
            'updated_by' => $userId,
        ]);

        NoticeRevision::create([
            'notice_id' => $notice->id,
            'revision_number' => $nextRev,
            'workflow_state' => 'draft',
            'title' => $validated['title'],
            'slug' => $validated['slug'] ?: null,
            'content' => $validated['content'],
            'date' => $validated['date'],
            'audience' => $validated['audience'],
            'priority' => $validated['priority'] ?? null,
            'is_urgent' => (bool) ($validated['is_urgent'] ?? false),
            'created_by' => $userId,
        ]);

        return response()->json([
            'notice' => $this->formatNotice($notice->fresh(['publishedRevision', 'revisions'])),
        ]);
    }

    public function transition(Request $request, Notice $notice): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:submit,approve,publish,withdraw,archive,restore',
        ]);

        $latest = $notice->revisions()->sortByDesc('revision_number')->first();
        $userId = $request->user()?->id;

        switch ($validated['action']) {
            case 'submit':
                $notice->update(['publication_state' => 'review', 'updated_by' => $userId]);
                $latest?->update(['workflow_state' => 'review', 'submitted_at' => now()]);
                break;
            case 'approve':
                $latest?->update(['workflow_state' => 'approved', 'reviewed_at' => now(), 'reviewed_by' => $userId]);
                break;
            case 'publish':
                $slug = $notice->slug ?: Str::slug($latest?->title ?? $notice->title ?? 'notice-'.$notice->id);
                $latest?->update(['workflow_state' => 'published', 'published_at' => now()]);
                $notice->update([
                    'publication_state' => 'published',
                    'status' => 'published',
                    'slug' => $slug,
                    'published_revision_id' => $latest?->id,
                    'published_at' => now(),
                    'published_by' => $userId,
                ]);
                break;
            case 'withdraw':
                $notice->update(['publication_state' => 'withdrawn', 'status' => 'draft', 'withdrawn_at' => now()]);
                break;
            case 'archive':
                $notice->update(['publication_state' => 'archived', 'status' => 'draft', 'archived_at' => now()]);
                break;
            case 'restore':
                $notice->update(['publication_state' => 'draft', 'status' => 'draft']);
                $latest?->update(['workflow_state' => 'draft']);
                break;
        }

        return response()->json([
            'notice' => $this->formatNotice($notice->fresh(['publishedRevision', 'revisions'])),
        ]);
    }

    public function destroy(Notice $notice): JsonResponse
    {
        $notice->delete();

        return response()->json(['deleted' => true]);
    }

    private function validateNotice(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:180',
            'slug' => 'nullable|string|max:180',
            'date' => 'required|date',
            'audience' => 'required|in:all,public,teachers,parents,students,staff',
            'priority' => 'nullable|integer|min:0|max:999',
            'content' => 'required|string|max:30000',
            'is_urgent' => 'nullable|boolean',
        ]);
    }

    private function formatNotice(Notice $n): array
    {
        $live = $n->publishedRevision;
        $latest = $n->revisions->sortByDesc('revision_number')->first();
        $rev = $live ?? $latest;

        return [
            'id' => $n->id,
            'title' => $rev?->title ?? $n->title ?? 'Untitled notice',
            'slug' => $n->slug,
            'content' => $rev?->content ?? $n->content ?? '',
            'date' => optional($rev?->date ?? $n->date)->format('Y-m-d') ?? now()->format('Y-m-d'),
            'audience' => $rev?->audience ?? $n->audience ?? 'public',
            'priority' => $rev?->priority ?? $n->priority,
            'publicationState' => $n->publication_state ?? ($n->status === 'published' ? 'published' : 'draft'),
            'revisionNumber' => $latest?->revision_number ?? 1,
            'workflowState' => $latest?->workflow_state ?? 'draft',
            'isUrgent' => (bool) ($rev?->is_urgent ?? $n->is_urgent),
            'publishedAt' => optional($n->published_at)->toIso8601String(),
            'scheduledFor' => optional($n->scheduled_for)->toIso8601String(),
            'updatedAt' => optional($n->updated_at)->toIso8601String() ?? now()->toIso8601String(),
        ];
    }
}
