<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use App\Models\NoticeRevision;
use App\Models\User;
use App\Services\Notices\NoticeMarkdown;
use App\Services\Notices\NoticePublisher;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class NoticeController extends Controller
{
    public function __construct(
        private NoticePublisher $noticePublisher,
        private NoticeMarkdown $noticeMarkdown,
    ) {}

    public function index(): View
    {
        $this->authorizeAny(['notices.author', 'notices.review', 'notices.publish']);

        return view('admin.notices.index', [
            'notices' => Notice::query()
                ->with(['creator', 'editor', 'publisher', 'revisions', 'publishedRevision', 'scheduledRevision'])
                ->orderByDesc('updated_at')
                ->paginate(30),
            'states' => Notice::PUBLICATION_STATES,
            'revisionStates' => NoticeRevision::WORKFLOW_STATES,
        ]);
    }

    public function create(): View
    {
        $this->authorizeCapability('notices.author');

        return $this->formView(
            new Notice(['publication_state' => 'draft']),
            new NoticeRevision([
                'workflow_state' => 'draft',
                'audience' => 'public',
                'date' => CarbonImmutable::now(NoticePublisher::TIMEZONE)->toDateString(),
            ]),
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeCapability('notices.author');
        $notice = $this->noticePublisher->createDraft($this->actor(), $this->validated($request));

        return redirect()->route('admin.notices.edit', $notice)
            ->with('success', 'Notice saved as a private draft. Submit it for review when it is ready.');
    }

    public function edit(Notice $notice): View
    {
        $this->authorizeAny(['notices.author', 'notices.review', 'notices.publish']);
        $notice->load([
            'revisions',
            'publishedRevision',
            'scheduledRevision',
            'creator',
            'editor',
            'publisher',
        ]);

        return $this->formView($notice, $this->activeRevision($notice));
    }

    public function update(Request $request, Notice $notice): RedirectResponse
    {
        $this->authorizeCapability('notices.author');
        $this->noticePublisher->saveDraft($this->actor(), $notice, $this->validated($request));

        return redirect()->route('admin.notices.edit', $notice)
            ->with('success', $notice->publication_state === 'published' || ($notice->publication_state === null && $notice->status === 'published')
                ? 'A private revision was saved. The currently published notice has not changed.'
                : 'Notice draft saved.');
    }

    public function preview(Notice $notice, NoticeRevision $revision): View
    {
        $this->authorizeAny(['notices.author', 'notices.review', 'notices.publish']);
        $this->ensureRevisionBelongsToNotice($notice, $revision);

        return view('admin.notices.preview', [
            'notice' => $notice,
            'revision' => $revision,
            'bodyHtml' => $this->noticeMarkdown->toHtml((string) $revision->content),
        ]);
    }

    public function submit(Notice $notice, NoticeRevision $revision): RedirectResponse
    {
        $this->authorizeCapability('notices.author');
        $this->ensureRevisionBelongsToNotice($notice, $revision);
        $this->noticePublisher->submit($this->actor(), $notice, $revision);

        return back()->with('success', 'Notice revision submitted for review.');
    }

    public function approve(Notice $notice, NoticeRevision $revision): RedirectResponse
    {
        $this->authorizeCapability('notices.review');
        $this->ensureRevisionBelongsToNotice($notice, $revision);
        $this->noticePublisher->approve($this->actor(), $notice, $revision);

        return back()->with('success', 'Notice revision approved for publication.');
    }

    public function publish(Request $request, Notice $notice, NoticeRevision $revision): RedirectResponse
    {
        $this->authorizeCapability('notices.publish');
        $this->ensureRevisionBelongsToNotice($notice, $revision);

        $scheduledFor = $this->scheduledFor($request);
        $published = $this->noticePublisher->publish($this->actor(), $notice, $revision, $scheduledFor);

        return back()->with('success', $published->scheduled_revision_id === $revision->id
            ? 'Notice scheduled for publication.'
            : 'Notice published.');
    }

    public function withdraw(Notice $notice): RedirectResponse
    {
        $this->authorizeCapability('notices.publish');
        $this->noticePublisher->withdraw($this->actor(), $notice);

        return back()->with('success', 'Notice withdrawn from public pages.');
    }

    public function archive(Notice $notice): RedirectResponse
    {
        $this->authorizeCapability('notices.publish');
        $this->noticePublisher->archive($this->actor(), $notice);

        return back()->with('success', 'Notice archived. Its editorial history has been retained.');
    }

    public function restore(Notice $notice): RedirectResponse
    {
        $this->authorizeCapability('notices.publish');
        $this->noticePublisher->restore($this->actor(), $notice);

        return back()->with('success', 'Notice restored as a private draft.');
    }

    public function destroy(Notice $notice): RedirectResponse
    {
        $this->authorizeCapability('notices.manage');
        $notice->delete();

        return back()->with('success', 'Notice deleted successfully.');
    }

    private function formView(Notice $notice, NoticeRevision $revision): View
    {
        return view('admin.notices.form', [
            'notice' => $notice,
            'revision' => $revision,
            'states' => Notice::PUBLICATION_STATES,
            'revisionStates' => NoticeRevision::WORKFLOW_STATES,
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:180', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/'],
            'content' => ['nullable', 'string', 'max:30000'],
            'body_markdown' => ['nullable', 'string', 'max:30000'],
            'audience' => ['required', 'string', 'in:all,public,teachers,parents,students,staff'],
            'date' => ['required', 'date'],
            'priority' => ['nullable', 'integer', 'between:0,999'],
            'is_urgent' => ['nullable', 'boolean'],
            'publication_start_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            // Legacy clients sent a status field. The editorial service now
            // determines public state through the reviewed workflow.
            'status' => ['nullable', 'in:published,draft'],
        ]);

        if (! array_key_exists('content', $data) && array_key_exists('body_markdown', $data)) {
            $data['content'] = $data['body_markdown'];
        }

        return $data;
    }

    private function activeRevision(Notice $notice): NoticeRevision
    {
        $active = $notice->revisions
            ->filter(fn (NoticeRevision $revision): bool => in_array($revision->workflow_state, ['draft', 'review', 'approved', 'scheduled'], true))
            ->sortByDesc('revision_number')
            ->first();

        if ($active instanceof NoticeRevision) {
            return $active;
        }

        if ($notice->publishedRevision instanceof NoticeRevision) {
            return $notice->publishedRevision;
        }

        return new NoticeRevision([
            'slug' => $notice->slug,
            'title' => $notice->title,
            'content' => $notice->content,
            'audience' => $notice->audience,
            'date' => $notice->date,
            'priority' => $notice->priority,
            'is_urgent' => $notice->is_urgent,
            'expires_at' => $notice->expires_at,
            'workflow_state' => 'draft',
        ]);
    }

    private function scheduledFor(Request $request): ?CarbonImmutable
    {
        $data = $request->validate([
            'publication_start_at' => ['nullable', 'date'],
        ]);
        $value = trim((string) ($data['publication_start_at'] ?? ''));
        if ($value === '') {
            return null;
        }

        $scheduledFor = CarbonImmutable::parse($value, NoticePublisher::TIMEZONE);
        if (! $scheduledFor->isFuture()) {
            throw ValidationException::withMessages([
                'publication_start_at' => 'Choose a future Nepal-time date and time when scheduling a Notice.',
            ]);
        }

        return $scheduledFor;
    }

    private function ensureRevisionBelongsToNotice(Notice $notice, NoticeRevision $revision): void
    {
        abort_unless($revision->notice_id === $notice->id, 404);
    }

    /** @param array<int, string> $abilities */
    private function authorizeAny(array $abilities): void
    {
        $this->requireStaffActor();

        if (Gate::allows('notices.manage')) {
            return;
        }

        foreach ($abilities as $ability) {
            if (Gate::allows($ability)) {
                return;
            }
        }

        Gate::authorize($abilities[0]);
    }

    private function authorizeCapability(string $ability): void
    {
        $this->requireStaffActor();

        if (Gate::allows('notices.manage')) {
            return;
        }

        Gate::authorize($ability);
    }

    private function actor(): User
    {
        return $this->requireStaffActor();
    }

    private function requireStaffActor(): User
    {
        $user = auth()->user();
        $staffRoles = array_values(array_diff(
            array_keys((array) config('permissions.role_labels', [])),
            ['student', 'parent'],
        ));

        abort_unless($user instanceof User && $user->hasAnyRole($staffRoles), 403);

        return $user;
    }
}
