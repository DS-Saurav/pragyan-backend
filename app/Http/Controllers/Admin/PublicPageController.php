<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Models\PublicPage;
use App\Models\PublicPageRevision;
use App\Models\User;
use App\Services\Media\MediaLibrary;
use App\Services\PublicPages\PublicPageMarkdown;
use App\Services\PublicPages\PublicPagePublisher;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PublicPageController extends Controller
{
    public function __construct(
        private PublicPagePublisher $publisher,
        private MediaLibrary $mediaLibrary,
        private PublicPageMarkdown $markdown,
    ) {}

    public function index(): View
    {
        $this->authorizeAny(['public_pages.author', 'public_pages.review', 'public_pages.publish']);
        $existing = PublicPage::query()
            ->with(['creator', 'editor', 'publisher', 'revisions', 'publishedRevision', 'scheduledRevision'])
            ->get()
            ->keyBy('type');

        return view('admin.public-pages.index', [
            'pages' => collect(PublicPage::TYPES)->mapWithKeys(
                fn (string $type): array => [$type => $existing->get($type)],
            ),
            'types' => [
                PublicPage::TYPE_ABOUT => 'About',
                PublicPage::TYPE_ACADEMICS => 'Academics',
            ],
            'canAuthor' => Gate::allows('public_pages.author'),
            'canReview' => Gate::allows('public_pages.review'),
            'canPublish' => Gate::allows('public_pages.publish'),
        ]);
    }

    public function create(string $type): View
    {
        Gate::authorize('public_pages.author');
        $this->ensureType($type);
        abort_if(PublicPage::query()->where('type', $type)->exists(), 409, 'This public page already exists.');

        return $this->formView(
            new PublicPage(['type' => $type, 'publication_state' => 'draft']),
            new PublicPageRevision(['workflow_state' => 'draft']),
        );
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        Gate::authorize('public_pages.author');
        $this->ensureType($type);
        $data = $this->validated($request);
        $page = $this->publisher->createDraft($this->actor(), $type, $this->pageData($data));
        $revision = $page->revisions->last();

        if ($revision instanceof PublicPageRevision) {
            $this->updateFeatureWhenRequested($data, $revision);
        }

        return redirect()->route('admin.public-pages.edit', $page)
            ->with('success', 'Public page saved as a private draft. Submit it for review when the approved content is ready.');
    }

    public function edit(PublicPage $publicPage): View
    {
        $this->authorizeAny(['public_pages.author', 'public_pages.review', 'public_pages.publish']);
        $publicPage->load([
            'revisions.academicStages',
            'revisions.featureImage.asset',
            'publishedRevision.featureImage.asset',
            'scheduledRevision.featureImage.asset',
            'creator',
            'editor',
            'publisher',
        ]);

        return $this->formView($publicPage, $this->activeRevision($publicPage));
    }

    public function update(Request $request, PublicPage $publicPage): RedirectResponse
    {
        Gate::authorize('public_pages.author');
        $data = $this->validated($request);
        $revision = $this->publisher->saveDraft($this->actor(), $publicPage, $this->pageData($data));
        $this->updateFeatureWhenRequested($data, $revision);

        return redirect()->route('admin.public-pages.edit', $publicPage)
            ->with('success', $publicPage->publication_state === 'published'
                ? 'A private replacement revision was saved. The current public page has not changed.'
                : 'Public page draft saved.');
    }

    public function preview(PublicPage $publicPage, PublicPageRevision $revision): View
    {
        $this->authorizeAny(['public_pages.author', 'public_pages.review', 'public_pages.publish']);
        $this->ensureRevisionBelongsToPage($publicPage, $revision);
        $revision->load(['academicStages', 'featureImage.asset']);

        return view('admin.public-pages.preview', [
            'page' => $publicPage,
            'revision' => $revision,
            'markdown' => $this->markdown,
            'feature' => $revision->featureImage,
        ]);
    }

    public function submit(PublicPage $publicPage, PublicPageRevision $revision): RedirectResponse
    {
        Gate::authorize('public_pages.author');
        $this->ensureRevisionBelongsToPage($publicPage, $revision);
        $this->publisher->submit($this->actor(), $publicPage, $revision);

        return back()->with('success', 'Public page revision submitted for review.');
    }

    public function approve(PublicPage $publicPage, PublicPageRevision $revision): RedirectResponse
    {
        Gate::authorize('public_pages.review');
        $this->ensureRevisionBelongsToPage($publicPage, $revision);
        $this->publisher->approve($this->actor(), $publicPage, $revision);

        return back()->with('success', 'Public page revision approved for publication.');
    }

    public function publish(Request $request, PublicPage $publicPage, PublicPageRevision $revision): RedirectResponse
    {
        Gate::authorize('public_pages.publish');
        $this->ensureRevisionBelongsToPage($publicPage, $revision);
        $data = $request->validate(['scheduled_for' => ['nullable', 'date']]);
        $scheduledFor = empty($data['scheduled_for'])
            ? null
            : CarbonImmutable::parse($data['scheduled_for'], PublicPagePublisher::TIMEZONE);
        if ($scheduledFor !== null && ! $scheduledFor->isFuture()) {
            throw ValidationException::withMessages([
                'scheduled_for' => 'Choose a future publication time in Nepal time.',
            ]);
        }
        $this->publisher->publish($this->actor(), $publicPage, $revision, $scheduledFor);

        return back()->with('success', $scheduledFor === null
            ? 'Public page published.'
            : 'Public page scheduled for publication.');
    }

    public function withdraw(PublicPage $publicPage): RedirectResponse
    {
        Gate::authorize('public_pages.publish');
        $this->publisher->withdraw($this->actor(), $publicPage);

        return back()->with('success', 'Public page withdrawn from the public website.');
    }

    public function cancelSchedule(PublicPage $publicPage): RedirectResponse
    {
        Gate::authorize('public_pages.publish');
        $this->publisher->cancelSchedule($this->actor(), $publicPage);

        return back()->with('success', 'Scheduled public page publication cancelled.');
    }

    public function archive(PublicPage $publicPage): RedirectResponse
    {
        Gate::authorize('public_pages.publish');
        $this->publisher->archive($this->actor(), $publicPage);

        return back()->with('success', 'Public page archived. Its editorial history has been retained.');
    }

    public function restore(PublicPage $publicPage): RedirectResponse
    {
        Gate::authorize('public_pages.publish');
        $this->publisher->restore($this->actor(), $publicPage);

        return back()->with('success', 'Public page restored as a private draft.');
    }

    private function formView(PublicPage $page, PublicPageRevision $revision): View
    {
        return view('admin.public-pages.form', [
            'page' => $page,
            'revision' => $revision,
            'type' => $page->type,
            'assets' => $this->safeAssets(),
            'states' => PublicPage::PUBLICATION_STATES,
            'revisionStates' => PublicPageRevision::WORKFLOW_STATES,
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'introduction_markdown' => ['nullable', 'string', 'max:50000'],
            'history_markdown' => ['nullable', 'string', 'max:50000'],
            'mission_markdown' => ['nullable', 'string', 'max:30000'],
            'vision_markdown' => ['nullable', 'string', 'max:30000'],
            'approach_values' => ['nullable', 'array', 'max:12'],
            'approach_values.*' => ['nullable', 'string', 'max:400'],
            'facilities_markdown' => ['nullable', 'string', 'max:50000'],
            'stages' => ['nullable', 'array', 'max:12'],
            'stages.*.title' => ['nullable', 'string', 'max:180'],
            'stages.*.stage_label' => ['nullable', 'string', 'max:100'],
            'stages.*.description_markdown' => ['nullable', 'string', 'max:30000'],
            'stages.*.key_points' => ['nullable', 'array', 'max:12'],
            'stages.*.key_points.*' => ['nullable', 'string', 'max:400'],
            'feature_asset_id' => ['nullable', 'integer', Rule::exists('media_assets', 'id')],
            'feature_caption' => ['nullable', 'string', 'max:10000'],
            'feature_alt_text' => ['nullable', 'string', 'max:255'],
            'feature_focal_x' => ['nullable', 'numeric', 'between:0,100'],
            'feature_focal_y' => ['nullable', 'numeric', 'between:0,100'],
            'remove_feature' => ['nullable', 'boolean'],
        ]);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function pageData(array $data): array
    {
        return Arr::only($data, [
            'title',
            'introduction_markdown',
            'history_markdown',
            'mission_markdown',
            'vision_markdown',
            'approach_values',
            'facilities_markdown',
            'stages',
        ]);
    }

    /** @param array<string, mixed> $data */
    private function updateFeatureWhenRequested(array $data, PublicPageRevision $revision): void
    {
        if ((bool) ($data['remove_feature'] ?? false)) {
            $this->mediaLibrary->clearPublicPageRevisionFeature($this->actor(), $revision);

            return;
        }

        if (empty($data['feature_asset_id'])) {
            return;
        }

        $asset = MediaAsset::query()->findOrFail((int) $data['feature_asset_id']);
        $this->mediaLibrary->replacePublicPageRevisionFeature($this->actor(), $revision, $asset, [
            'caption' => $data['feature_caption'] ?? null,
            'alt_text' => $data['feature_alt_text'] ?? null,
            'focal_x' => $data['feature_focal_x'] ?? 50,
            'focal_y' => $data['feature_focal_y'] ?? 50,
        ]);
    }

    private function activeRevision(PublicPage $page): PublicPageRevision
    {
        $active = $page->revisions
            ->filter(fn (PublicPageRevision $revision): bool => in_array($revision->workflow_state, ['draft', 'review', 'approved', 'scheduled'], true))
            ->sortByDesc('revision_number')
            ->first();

        if ($active instanceof PublicPageRevision) {
            return $active;
        }

        return $page->publishedRevision ?? new PublicPageRevision(['workflow_state' => 'draft']);
    }

    private function ensureType(string $type): void
    {
        abort_unless(in_array($type, PublicPage::TYPES, true), 404);
    }

    private function ensureRevisionBelongsToPage(PublicPage $page, PublicPageRevision $revision): void
    {
        abort_unless($revision->public_page_id === $page->id, 404);
    }

    /** @return Collection<int, MediaAsset> */
    private function safeAssets(): Collection
    {
        return MediaAsset::query()
            ->where('status', 'published')
            ->whereNull('archived_at')
            ->whereNull('consent_withdrawn_at')
            ->whereIn('consent_state', ['not_required', 'confirmed'])
            ->where(function ($query): void {
                $query->whereNotNull('default_alt_text')->where('default_alt_text', '!=', '')
                    ->orWhere('is_decorative', true);
            })
            ->orderByDesc('published_at')
            ->get();
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
