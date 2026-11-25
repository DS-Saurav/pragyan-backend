<?php

namespace App\Services\PublicTeam;

use App\Models\PublicTeamProfile;
use App\Models\PublicTeamProfileRevision;
use App\Models\StaffMember;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PublicTeamProfilePublisher
{
    public const TIMEZONE = 'Asia/Kathmandu';

    public function createDraft(User $actor, array $data): PublicTeamProfile
    {
        $this->authorize($actor, 'public_team.author');
        $attributes = $this->draftAttributes($data);

        return DB::transaction(function () use ($actor, $attributes): PublicTeamProfile {
            $profile = PublicTeamProfile::query()->create([
                'publication_state' => 'draft',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $profile->revisions()->create($attributes + [
                'revision_number' => 1,
                'workflow_state' => 'draft',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            return $profile->fresh(['revisions']);
        });
    }

    public function saveDraft(User $actor, PublicTeamProfile $profile, array $data): PublicTeamProfileRevision
    {
        $this->authorize($actor, 'public_team.author');
        $attributes = $this->draftAttributes($data);

        return DB::transaction(function () use ($actor, $profile, $attributes): PublicTeamProfileRevision {
            $lockedProfile = PublicTeamProfile::query()->lockForUpdate()->findOrFail($profile->getKey());
            $this->require($lockedProfile->archived_at === null, 'Restore an archived profile before preparing a new revision.');
            $this->require($lockedProfile->scheduled_revision_id === null, 'Publish, withdraw, or cancel the scheduled revision before preparing another one.');

            $revision = $lockedProfile->revisions()
                ->where('workflow_state', 'draft')
                ->orderByDesc('revision_number')
                ->lockForUpdate()
                ->first();

            if ($revision === null) {
                $revision = $lockedProfile->revisions()->create($attributes + [
                    'revision_number' => ((int) $lockedProfile->revisions()->max('revision_number')) + 1,
                    'workflow_state' => 'draft',
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);
            }

            $revision->fill($attributes + [
                'workflow_state' => 'draft',
                'updated_by' => $actor->id,
                'submitted_by' => null,
                'submitted_at' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'published_by' => null,
                'published_at' => null,
            ]);
            $revision->save();

            if ($lockedProfile->published_revision_id === null) {
                $lockedProfile->fill([
                    'publication_state' => 'draft',
                    'withdrawn_at' => null,
                ]);
            }
            $lockedProfile->updated_by = $actor->id;
            $lockedProfile->save();

            return $revision->refresh();
        });
    }

    public function submit(
        User $actor,
        PublicTeamProfile $profile,
        PublicTeamProfileRevision $revision,
    ): PublicTeamProfileRevision {
        $this->authorize($actor, 'public_team.author');

        return DB::transaction(function () use ($actor, $profile, $revision): PublicTeamProfileRevision {
            $lockedProfile = PublicTeamProfile::query()->lockForUpdate()->findOrFail($profile->getKey());
            $lockedRevision = $this->lockedRevision($lockedProfile, $revision);
            $this->require($lockedProfile->archived_at === null, 'An archived profile cannot enter review.');
            $this->require($lockedRevision->workflow_state === 'draft', 'Only a draft revision can enter review.');

            $lockedRevision->fill([
                'workflow_state' => 'review',
                'submitted_by' => $actor->id,
                'submitted_at' => $this->now(),
                'updated_by' => $actor->id,
            ]);
            $lockedRevision->save();

            if ($lockedProfile->published_revision_id === null) {
                $lockedProfile->publication_state = 'review';
            }
            $lockedProfile->updated_by = $actor->id;
            $lockedProfile->save();

            return $lockedRevision->refresh();
        });
    }

    public function approve(
        User $actor,
        PublicTeamProfile $profile,
        PublicTeamProfileRevision $revision,
    ): PublicTeamProfileRevision {
        $this->authorize($actor, 'public_team.review');

        return DB::transaction(function () use ($actor, $profile, $revision): PublicTeamProfileRevision {
            $lockedProfile = PublicTeamProfile::query()->lockForUpdate()->findOrFail($profile->getKey());
            $lockedRevision = $this->lockedRevision($lockedProfile, $revision);
            $this->require($lockedProfile->archived_at === null, 'An archived profile cannot be approved.');
            $this->require($lockedRevision->workflow_state === 'review', 'Only a revision in review can be approved.');
            $this->require(
                $this->isAdministrator($actor)
                    || ($lockedRevision->created_by !== $actor->id && $lockedRevision->submitted_by !== $actor->id),
                'A different authorized reviewer must approve the author’s revision.',
            );

            $lockedRevision->fill([
                'workflow_state' => 'approved',
                'reviewed_by' => $actor->id,
                'reviewed_at' => $this->now(),
                'updated_by' => $actor->id,
            ]);
            $lockedRevision->save();
            $lockedProfile->updated_by = $actor->id;
            $lockedProfile->save();

            return $lockedRevision->refresh();
        });
    }

    public function publish(
        User $actor,
        PublicTeamProfile $profile,
        PublicTeamProfileRevision $revision,
        ?CarbonInterface $scheduledFor = null,
    ): PublicTeamProfile {
        $this->authorize($actor, 'public_team.publish');

        return DB::transaction(function () use ($actor, $profile, $revision, $scheduledFor): PublicTeamProfile {
            $lockedProfile = PublicTeamProfile::query()->lockForUpdate()->findOrFail($profile->getKey());
            $lockedRevision = $this->lockedRevision($lockedProfile, $revision);
            $this->require($lockedProfile->archived_at === null, 'Restore an archived profile before publishing it.');
            $this->require($lockedRevision->workflow_state === 'approved', 'Only an approved revision can be published or scheduled.');
            $this->requireCompleteAndEligible($lockedRevision);

            $when = $scheduledFor === null ? null : $this->atSchoolTime($scheduledFor);
            if ($when !== null && $when->isFuture()) {
                $this->require(
                    $lockedProfile->scheduled_revision_id === null || $lockedProfile->scheduled_revision_id === $lockedRevision->id,
                    'This profile already has a scheduled revision.',
                );

                $lockedRevision->fill([
                    'workflow_state' => 'scheduled',
                    'published_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);
                $lockedRevision->save();
                $lockedProfile->fill([
                    'scheduled_revision_id' => $lockedRevision->id,
                    'scheduled_for' => $when,
                    'updated_by' => $actor->id,
                ]);
                $lockedProfile->save();

                return $lockedProfile->fresh(['publishedRevision', 'scheduledRevision']);
            }

            $this->require(
                $lockedProfile->scheduled_revision_id === null || $lockedProfile->scheduled_revision_id === $lockedRevision->id,
                'This profile already has a different scheduled revision.',
            );
            $this->activateRevision($lockedProfile, $lockedRevision, $actor, $this->now());

            return $lockedProfile->fresh(['publishedRevision', 'scheduledRevision']);
        });
    }

    public function withdraw(User $actor, PublicTeamProfile $profile): PublicTeamProfile
    {
        $this->authorize($actor, 'public_team.publish');

        return DB::transaction(function () use ($actor, $profile): PublicTeamProfile {
            $lockedProfile = PublicTeamProfile::query()->lockForUpdate()->findOrFail($profile->getKey());
            $this->require($lockedProfile->publication_state === 'published', 'Only a published profile can be withdrawn.');
            $this->clearScheduledRevision($lockedProfile);
            $lockedProfile->fill([
                'publication_state' => 'withdrawn',
                'withdrawn_at' => $this->now(),
                'updated_by' => $actor->id,
            ]);
            $lockedProfile->save();

            return $lockedProfile->fresh();
        });
    }

    public function cancelSchedule(User $actor, PublicTeamProfile $profile): PublicTeamProfile
    {
        $this->authorize($actor, 'public_team.publish');

        return DB::transaction(function () use ($actor, $profile): PublicTeamProfile {
            $lockedProfile = PublicTeamProfile::query()->lockForUpdate()->findOrFail($profile->getKey());
            $this->require($lockedProfile->scheduled_revision_id !== null, 'There is no scheduled public Team profile to cancel.');
            $this->clearScheduledRevision($lockedProfile);

            if ($lockedProfile->published_revision_id === null) {
                $lockedProfile->publication_state = 'draft';
            }

            $lockedProfile->updated_by = $actor->id;
            $lockedProfile->save();

            return $lockedProfile->fresh(['publishedRevision', 'scheduledRevision']);
        });
    }

    public function archive(User $actor, PublicTeamProfile $profile): PublicTeamProfile
    {
        $this->authorize($actor, 'public_team.publish');

        return DB::transaction(function () use ($actor, $profile): PublicTeamProfile {
            $lockedProfile = PublicTeamProfile::query()->lockForUpdate()->findOrFail($profile->getKey());
            $this->require($lockedProfile->archived_at === null, 'This profile is already archived.');
            $this->clearScheduledRevision($lockedProfile);
            $lockedProfile->fill([
                'publication_state' => 'archived',
                'archived_at' => $this->now(),
                'updated_by' => $actor->id,
            ]);
            $lockedProfile->save();

            return $lockedProfile->fresh();
        });
    }

    public function restore(User $actor, PublicTeamProfile $profile): PublicTeamProfile
    {
        $this->authorize($actor, 'public_team.publish');

        return DB::transaction(function () use ($actor, $profile): PublicTeamProfile {
            $lockedProfile = PublicTeamProfile::query()->lockForUpdate()->findOrFail($profile->getKey());
            $this->require($lockedProfile->archived_at !== null, 'Only an archived profile can be restored.');
            $this->clearScheduledRevision($lockedProfile);
            $lockedProfile->fill([
                'publication_state' => 'draft',
                'published_revision_id' => null,
                'published_at' => null,
                'withdrawn_at' => null,
                'archived_at' => null,
                'updated_by' => $actor->id,
            ]);
            $lockedProfile->save();

            return $lockedProfile->fresh();
        });
    }

    public function releaseDue(?CarbonInterface $now = null): void
    {
        $time = $this->atSchoolTime($now ?? $this->now());
        $dueProfileIds = PublicTeamProfile::query()
            ->whereNotNull('scheduled_revision_id')
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', $time)
            ->pluck('id');

        foreach ($dueProfileIds as $profileId) {
            DB::transaction(function () use ($profileId, $time): void {
                $lockedProfile = PublicTeamProfile::query()->lockForUpdate()->find($profileId);
                if ($lockedProfile === null
                    || $lockedProfile->scheduled_revision_id === null
                    || $lockedProfile->scheduled_for === null
                    || $lockedProfile->scheduled_for->greaterThan($time)
                    || $lockedProfile->archived_at !== null
                    || $lockedProfile->publication_state === 'withdrawn') {
                    if ($lockedProfile !== null && $lockedProfile->scheduled_revision_id !== null) {
                        $this->clearScheduledRevision($lockedProfile);
                        $lockedProfile->save();
                    }

                    return;
                }

                $lockedRevision = $lockedProfile->revisions()
                    ->whereKey($lockedProfile->scheduled_revision_id)
                    ->lockForUpdate()
                    ->first();
                if ($lockedRevision === null || $lockedRevision->workflow_state !== 'scheduled') {
                    $this->clearScheduledRevision($lockedProfile);
                    $lockedProfile->save();

                    return;
                }

                try {
                    $this->requireCompleteAndEligible($lockedRevision);
                } catch (ValidationException) {
                    $lockedRevision->workflow_state = 'approved';
                    $lockedRevision->save();
                    $this->clearScheduledRevision($lockedProfile);
                    $lockedProfile->save();

                    return;
                }

                $this->activateRevision($lockedProfile, $lockedRevision, null, $time);
            });
        }
    }

    private function lockedRevision(
        PublicTeamProfile $profile,
        PublicTeamProfileRevision $revision,
    ): PublicTeamProfileRevision {
        return $profile->revisions()->whereKey($revision->getKey())->lockForUpdate()->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function draftAttributes(array $data): array
    {
        $validated = Validator::make($data, [
            'staff_member_id' => ['nullable', 'integer', Rule::exists('staff_members', 'id')],
            'display_name' => ['nullable', 'string', 'max:140'],
            'public_role' => ['nullable', 'string', 'max:140'],
            'team_group' => ['nullable', Rule::in(array_keys(PublicTeamProfile::TEAM_GROUPS))],
            'qualification' => ['nullable', 'string', 'max:255'],
            'bio_markdown' => ['nullable', 'string', 'max:2000'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ])->validate();

        $staffMemberId = isset($validated['staff_member_id']) ? (int) $validated['staff_member_id'] : null;
        if ($staffMemberId !== null) {
            $this->requireActiveStaffMember($staffMemberId);
        }

        return [
            'staff_member_id' => $staffMemberId,
            'display_name' => $this->nullableString($validated['display_name'] ?? null),
            'public_role' => $this->nullableString($validated['public_role'] ?? null),
            'team_group' => $this->nullableString($validated['team_group'] ?? null),
            'qualification' => $this->nullableString($validated['qualification'] ?? null),
            'bio_markdown' => $this->nullableString($validated['bio_markdown'] ?? null),
            'display_order' => (int) ($validated['display_order'] ?? 0),
        ];
    }

    private function requireCompleteAndEligible(PublicTeamProfileRevision $revision): void
    {
        $this->require($revision->staff_member_id !== null, 'A currently active staff member must be selected before this profile can be published.');
        $this->requireActiveStaffMember((int) $revision->staff_member_id);
        $this->require(trim((string) $revision->display_name) !== '', 'A public display name is required before publication.');
        $this->require(trim((string) $revision->public_role) !== '', 'A public role is required before publication.');
        $this->require(
            in_array((string) $revision->team_group, array_keys(PublicTeamProfile::TEAM_GROUPS), true),
            'A valid public team group is required before publication.',
        );
    }

    private function requireActiveStaffMember(int $staffMemberId): void
    {
        $this->require(
            StaffMember::query()->whereKey($staffMemberId)->where('status', 'active')->exists(),
            'Only an active staff member may be linked to a public team profile.',
        );
    }

    private function activateRevision(
        PublicTeamProfile $profile,
        PublicTeamProfileRevision $revision,
        ?User $actor,
        CarbonInterface $publishedAt,
    ): void {
        $publisherId = $actor?->id ?? $revision->published_by;
        $revision->fill([
            'workflow_state' => 'published',
            'published_by' => $publisherId,
            'published_at' => $publishedAt,
            'updated_by' => $publisherId ?? $revision->updated_by,
        ]);
        $revision->save();
        $profile->fill([
            'publication_state' => 'published',
            'published_revision_id' => $revision->id,
            'scheduled_revision_id' => null,
            'scheduled_for' => null,
            'published_at' => $publishedAt,
            'withdrawn_at' => null,
            'archived_at' => null,
            'published_by' => $publisherId,
            'updated_by' => $publisherId ?? $profile->updated_by,
        ]);
        $profile->save();
    }

    private function clearScheduledRevision(PublicTeamProfile $profile): void
    {
        if ($profile->scheduled_revision_id !== null) {
            $revision = $profile->revisions()->whereKey($profile->scheduled_revision_id)->lockForUpdate()->first();
            if ($revision !== null && $revision->workflow_state === 'scheduled') {
                $revision->workflow_state = 'approved';
                $revision->save();
            }
        }
        $profile->scheduled_revision_id = null;
        $profile->scheduled_for = null;
    }

    private function authorize(User $actor, string $ability): void
    {
        Gate::forUser($actor)->authorize($ability);
    }

    private function isAdministrator(User $actor): bool
    {
        return $actor->hasRole('admin')
            && in_array('*', config('permissions.roles.admin', []), true);
    }

    private function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TIMEZONE)->utc();
    }

    private function atSchoolTime(CarbonInterface $value): CarbonImmutable
    {
        return CarbonImmutable::instance($value)->setTimezone(self::TIMEZONE)->utc();
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['public_team' => $message]);
        }
    }
}
