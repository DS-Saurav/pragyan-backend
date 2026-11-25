<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Models\PublicTeamProfile;
use App\Models\PublicTeamProfileRevision;
use App\Models\StaffMember;
use App\Models\User;
use App\Services\Media\MediaLibrary;
use App\Services\PublicTeam\PublicTeamMarkdown;
use App\Services\PublicTeam\PublicTeamProfilePublisher;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PublicTeamProfileController extends Controller
{
    public function __construct(
        private PublicTeamProfilePublisher $publisher,
        private MediaLibrary $mediaLibrary,
        private PublicTeamMarkdown $markdown,
    ) {}

    public function index(): View
    {
        $this->authorizeAny(['public_team.author', 'public_team.review', 'public_team.publish']);

        return view('admin.public-team.index', [
            'profiles' => PublicTeamProfile::query()
                ->with(['creator', 'editor', 'publisher', 'revisions', 'publishedRevision', 'scheduledRevision'])
                ->orderByDesc('updated_at')
                ->paginate(30),
            'canAuthor' => Gate::allows('public_team.author'),
            'canReview' => Gate::allows('public_team.review'),
            'canPublish' => Gate::allows('public_team.publish'),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('public_team.author');

        return $this->formView(
            new PublicTeamProfile(['publication_state' => 'draft']),
            new PublicTeamProfileRevision(['workflow_state' => 'draft']),
        );
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('public_team.author');
        $data = $this->validated($request);
        $profile = $this->publisher->createDraft($this->actor(), $this->profileData($data));
        $revision = $profile->revisions->last();

        if ($revision instanceof PublicTeamProfileRevision) {
            $this->updatePortraitWhenRequested($data, $revision);
        }

        return redirect()->route('admin.public-team.edit', $profile)
            ->with('success', 'Public Team profile saved as a private draft. Submit it for review when it is ready.');
    }

    public function edit(PublicTeamProfile $publicTeamProfile): View
    {
        $this->authorizeAny(['public_team.author', 'public_team.review', 'public_team.publish']);
        $publicTeamProfile->load([
            'revisions.portrait.asset',
            'publishedRevision.portrait.asset',
            'scheduledRevision.portrait.asset',
            'creator',
            'editor',
            'publisher',
        ]);

        return $this->formView($publicTeamProfile, $this->activeRevision($publicTeamProfile));
    }

    public function update(Request $request, PublicTeamProfile $publicTeamProfile): RedirectResponse
    {
        Gate::authorize('public_team.author');
        $data = $this->validated($request);
        $revision = $this->publisher->saveDraft($this->actor(), $publicTeamProfile, $this->profileData($data));
        $this->updatePortraitWhenRequested($data, $revision);

        return redirect()->route('admin.public-team.edit', $publicTeamProfile)
            ->with('success', $publicTeamProfile->publication_state === 'published'
                ? 'A private replacement revision was saved. The current public profile has not changed.'
                : 'Public Team profile draft saved.');
    }

    public function preview(PublicTeamProfile $publicTeamProfile, PublicTeamProfileRevision $revision): View
    {
        $this->authorizeAny(['public_team.author', 'public_team.review', 'public_team.publish']);
        $this->ensureRevisionBelongsToProfile($publicTeamProfile, $revision);
        $revision->load('portrait.asset');

        return view('admin.public-team.preview', [
            'profile' => $publicTeamProfile,
            'revision' => $revision,
            'portrait' => $revision->portrait,
            'markdown' => $this->markdown,
        ]);
    }

    public function submit(PublicTeamProfile $publicTeamProfile, PublicTeamProfileRevision $revision): RedirectResponse
    {
        Gate::authorize('public_team.author');
        $this->ensureRevisionBelongsToProfile($publicTeamProfile, $revision);
        $this->publisher->submit($this->actor(), $publicTeamProfile, $revision);

        return back()->with('success', 'Public Team profile revision submitted for review.');
    }

    public function approve(PublicTeamProfile $publicTeamProfile, PublicTeamProfileRevision $revision): RedirectResponse
    {
        Gate::authorize('public_team.review');
        $this->ensureRevisionBelongsToProfile($publicTeamProfile, $revision);
        $this->publisher->approve($this->actor(), $publicTeamProfile, $revision);

        return back()->with('success', 'Public Team profile revision approved for publication.');
    }

    public function publish(Request $request, PublicTeamProfile $publicTeamProfile, PublicTeamProfileRevision $revision): RedirectResponse
    {
        Gate::authorize('public_team.publish');
        $this->ensureRevisionBelongsToProfile($publicTeamProfile, $revision);
        $data = $request->validate(['scheduled_for' => ['nullable', 'date']]);
        $scheduledFor = empty($data['scheduled_for'])
            ? null
            : CarbonImmutable::parse($data['scheduled_for'], PublicTeamProfilePublisher::TIMEZONE);
        if ($scheduledFor !== null && ! $scheduledFor->isFuture()) {
            throw ValidationException::withMessages([
                'scheduled_for' => 'Choose a future publication time in Nepal time.',
            ]);
        }
        $this->publisher->publish($this->actor(), $publicTeamProfile, $revision, $scheduledFor);

        return back()->with('success', $scheduledFor === null
            ? 'Public Team profile published.'
            : 'Public Team profile scheduled for publication.');
    }

    public function withdraw(PublicTeamProfile $publicTeamProfile): RedirectResponse
    {
        Gate::authorize('public_team.publish');
        $this->publisher->withdraw($this->actor(), $publicTeamProfile);

        return back()->with('success', 'Public Team profile withdrawn from the public website.');
    }

    public function cancelSchedule(PublicTeamProfile $publicTeamProfile): RedirectResponse
    {
        Gate::authorize('public_team.publish');
        $this->publisher->cancelSchedule($this->actor(), $publicTeamProfile);

        return back()->with('success', 'Scheduled public Team profile publication cancelled.');
    }

    public function archive(PublicTeamProfile $publicTeamProfile): RedirectResponse
    {
        Gate::authorize('public_team.publish');
        $this->publisher->archive($this->actor(), $publicTeamProfile);

        return back()->with('success', 'Public Team profile archived. Its editorial history has been retained.');
    }

    public function restore(PublicTeamProfile $publicTeamProfile): RedirectResponse
    {
        Gate::authorize('public_team.publish');
        $this->publisher->restore($this->actor(), $publicTeamProfile);

        return back()->with('success', 'Public Team profile restored as a private draft.');
    }

    private function formView(PublicTeamProfile $profile, PublicTeamProfileRevision $revision): View
    {
        return view('admin.public-team.form', [
            'profile' => $profile,
            'revision' => $revision,
            'assets' => $this->safeAssets(),
            'staffOptions' => StaffMember::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name']),
            'groups' => PublicTeamProfile::TEAM_GROUPS,
            'states' => PublicTeamProfile::PUBLICATION_STATES,
            'revisionStates' => PublicTeamProfileRevision::WORKFLOW_STATES,
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'staff_member_id' => ['nullable', 'integer', Rule::exists('staff_members', 'id')],
            'display_name' => ['nullable', 'string', 'max:140'],
            'public_role' => ['nullable', 'string', 'max:140'],
            'team_group' => ['nullable', Rule::in(array_keys(PublicTeamProfile::TEAM_GROUPS))],
            'qualification' => ['nullable', 'string', 'max:255'],
            'bio_markdown' => ['nullable', 'string', 'max:2000'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'portrait_asset_id' => ['nullable', 'integer', Rule::exists('media_assets', 'id')],
            'portrait_caption' => ['nullable', 'string', 'max:10000'],
            'portrait_alt_text' => ['nullable', 'string', 'max:255'],
            'portrait_focal_x' => ['nullable', 'numeric', 'between:0,100'],
            'portrait_focal_y' => ['nullable', 'numeric', 'between:0,100'],
            'remove_portrait' => ['nullable', 'boolean'],
        ]);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function profileData(array $data): array
    {
        return Arr::only($data, [
            'staff_member_id',
            'display_name',
            'public_role',
            'team_group',
            'qualification',
            'bio_markdown',
            'display_order',
        ]);
    }

    /** @param array<string, mixed> $data */
    private function updatePortraitWhenRequested(array $data, PublicTeamProfileRevision $revision): void
    {
        if ((bool) ($data['remove_portrait'] ?? false)) {
            $this->mediaLibrary->clearPublicTeamProfileRevisionPortrait($this->actor(), $revision);

            return;
        }

        if (empty($data['portrait_asset_id'])) {
            return;
        }

        $asset = MediaAsset::query()->findOrFail((int) $data['portrait_asset_id']);
        $this->mediaLibrary->replacePublicTeamProfileRevisionPortrait($this->actor(), $revision, $asset, [
            'caption' => $data['portrait_caption'] ?? null,
            'alt_text' => $data['portrait_alt_text'] ?? null,
            'focal_x' => $data['portrait_focal_x'] ?? 50,
            'focal_y' => $data['portrait_focal_y'] ?? 50,
        ]);
    }

    private function activeRevision(PublicTeamProfile $profile): PublicTeamProfileRevision
    {
        $active = $profile->revisions
            ->filter(fn (PublicTeamProfileRevision $revision): bool => in_array($revision->workflow_state, ['draft', 'review', 'approved', 'scheduled'], true))
            ->sortByDesc('revision_number')
            ->first();

        if ($active instanceof PublicTeamProfileRevision) {
            return $active;
        }

        return $profile->publishedRevision ?? new PublicTeamProfileRevision(['workflow_state' => 'draft']);
    }

    private function ensureRevisionBelongsToProfile(PublicTeamProfile $profile, PublicTeamProfileRevision $revision): void
    {
        abort_unless($revision->public_team_profile_id === $profile->id, 404);
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
