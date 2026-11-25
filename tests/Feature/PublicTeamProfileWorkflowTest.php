<?php

namespace Tests\Feature;

use App\Models\PublicTeamProfile;
use App\Models\StaffMember;
use App\Models\User;
use App\Models\UserRole;
use App\Services\PublicTeam\PublicTeamProfilePublisher;
use App\Services\PublicTeam\PublicTeamVisibility;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PublicTeamProfileWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_approve_and_publish_own_team_profile_after_submission(): void
    {
        $admin = $this->editor('admin');
        $publisher = app(PublicTeamProfilePublisher::class);
        $profile = $publisher->createDraft($admin, $this->draftData($this->activeStaff()));
        $revision = $profile->revisions()->sole();

        $publisher->submit($admin, $profile, $revision);
        $publisher->approve($admin, $profile, $revision);
        $this->assertSame($admin->id, $revision->fresh()->reviewed_by);
        $this->assertNotNull($revision->fresh()->reviewed_at);

        $publisher->publish($admin, $profile, $revision);
        $this->assertSame('published', $profile->fresh()->publication_state);
        $this->assertSame($admin->id, $profile->fresh()->published_by);
    }

    public function test_non_admin_team_editor_cannot_approve_own_revision(): void
    {
        config(['permissions.roles.teacher' => [
            'public_team.author', 'public_team.review', 'public_team.publish',
        ]]);
        $author = $this->editor();
        $publisher = app(PublicTeamProfilePublisher::class);
        $profile = $publisher->createDraft($author, $this->draftData($this->activeStaff()));
        $revision = $profile->revisions()->sole();
        $publisher->submit($author, $profile, $revision);

        try {
            $publisher->approve($author, $profile, $revision);
            $this->fail('A non-admin editor must not approve their own Team profile.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('different authorized reviewer', json_encode($exception->errors()));
        }

        $this->assertSame('review', $revision->fresh()->workflow_state);
        $this->assertNull($revision->fresh()->reviewed_by);
    }

    public function test_team_profile_revisions_follow_review_publish_schedule_withdraw_archive_and_restore_lifecycle(): void
    {
        config(['permissions.roles.teacher' => [
            'public_team.author',
            'public_team.review',
            'public_team.publish',
        ]]);

        $author = $this->editor();
        $reviewer = $this->editor();
        $staff = $this->activeStaff();
        $publisher = app(PublicTeamProfilePublisher::class);

        $profile = $publisher->createDraft($author, $this->draftData($staff));
        $firstRevision = $profile->revisions()->sole();

        $this->assertSame('draft', $profile->publication_state);
        $this->assertSame('draft', $firstRevision->workflow_state);

        $publisher->submit($author, $profile, $firstRevision);
        $this->assertSame('review', $firstRevision->fresh()->workflow_state);
        $publisher->approve($reviewer, $profile, $firstRevision);
        $this->assertSame('approved', $firstRevision->fresh()->workflow_state);
        $publisher->publish($reviewer, $profile, $firstRevision);

        $published = $profile->fresh();
        $this->assertSame('published', $published->publication_state);
        $this->assertSame($firstRevision->id, $published->published_revision_id);
        $this->assertSame('Approved Team Member', $this->visibility()->profiles()->sole()->displayName);

        $replacement = $publisher->saveDraft($author, $profile, $this->draftData($staff, [
            'display_name' => 'Scheduled Team Member',
            'public_role' => 'Learning Coordinator',
        ]));

        $this->assertSame('draft', $replacement->workflow_state);
        $this->assertSame($firstRevision->id, $profile->fresh()->published_revision_id);
        $this->assertSame('Approved Team Member', $this->visibility()->profiles()->sole()->displayName);

        $publisher->submit($author, $profile, $replacement);
        $publisher->approve($reviewer, $profile, $replacement);
        $scheduledFor = CarbonImmutable::now(PublicTeamProfilePublisher::TIMEZONE)->addMinutes(10);
        $publisher->publish($reviewer, $profile, $replacement, $scheduledFor);

        $scheduled = $profile->fresh();
        $this->assertSame($firstRevision->id, $scheduled->published_revision_id);
        $this->assertSame($replacement->id, $scheduled->scheduled_revision_id);
        $this->assertSame('scheduled', $replacement->fresh()->workflow_state);
        $this->assertSame('Approved Team Member', $this->visibility()->profiles()->sole()->displayName);

        $publishedAt = $scheduledFor->addSecond();
        $publisher->releaseDue($publishedAt);

        $released = $profile->fresh();
        $this->assertSame($replacement->id, $released->published_revision_id);
        $this->assertNull($released->scheduled_revision_id);
        $this->assertSame('Scheduled Team Member', $this->visibility()->profiles($publishedAt)->sole()->displayName);

        $publisher->withdraw($reviewer, $profile);
        $this->assertSame('withdrawn', $profile->fresh()->publication_state);
        $this->assertTrue($this->visibility()->profiles($publishedAt)->isEmpty());

        $publisher->archive($reviewer, $profile);
        $this->assertSame('archived', $profile->fresh()->publication_state);
        $this->assertNotNull($profile->fresh()->archived_at);

        $publisher->restore($reviewer, $profile);
        $restored = $profile->fresh();
        $this->assertSame('draft', $restored->publication_state);
        $this->assertNull($restored->published_revision_id);
        $this->assertNull($restored->scheduled_revision_id);
        $this->assertNull($restored->archived_at);
    }

    public function test_team_editor_permissions_are_granular_and_do_not_grant_internal_admissions_access(): void
    {
        $this->get(route('admin.public-team.index'))->assertRedirect(route('login'));

        $unprivilegedStaff = $this->editor('principal');
        $this->actingAs($unprivilegedStaff)
            ->get(route('admin.public-team.index'))
            ->assertForbidden();

        config(['permissions.roles.teacher' => ['public_team.author']]);
        $author = $this->editor();
        $staff = $this->activeStaff();

        $this->actingAs($author)
            ->get(route('admin.public-team.create'))
            ->assertOk();

        $this->actingAs($author)
            ->post(route('admin.public-team.store'), $this->draftData($staff))
            ->assertRedirect();

        $profile = PublicTeamProfile::query()->sole();
        $revision = $profile->revisions()->sole();

        $this->actingAs($author)
            ->patch(route('admin.public-team.approve', [$profile, $revision]))
            ->assertForbidden();

        $this->actingAs($author)
            ->patch(route('admin.public-team.publish', [$profile, $revision]))
            ->assertForbidden();

        $this->actingAs($author)
            ->get(route('admin.admissions.index'))
            ->assertForbidden();
    }

    public function test_due_scheduled_replacement_is_cancelled_when_its_staff_link_becomes_ineligible_without_withdrawing_the_live_profile(): void
    {
        config(['permissions.roles.teacher' => [
            'public_team.author',
            'public_team.review',
            'public_team.publish',
        ]]);

        $author = $this->editor();
        $reviewer = $this->editor();
        $liveStaff = $this->activeStaff(['name' => 'Active Live Staff']);
        $replacementStaff = $this->activeStaff(['name' => 'Scheduled Replacement Staff']);
        $publisher = app(PublicTeamProfilePublisher::class);

        $profile = $publisher->createDraft($author, $this->draftData($liveStaff, [
            'display_name' => 'Current Live Profile',
        ]));
        $liveRevision = $profile->revisions()->sole();
        $publisher->submit($author, $profile, $liveRevision);
        $publisher->approve($reviewer, $profile, $liveRevision);
        $publisher->publish($reviewer, $profile, $liveRevision);

        $replacement = $publisher->saveDraft($author, $profile, $this->draftData($replacementStaff, [
            'display_name' => 'Unsafe Scheduled Replacement',
        ]));
        $publisher->submit($author, $profile, $replacement);
        $publisher->approve($reviewer, $profile, $replacement);
        $scheduledFor = CarbonImmutable::now(PublicTeamProfilePublisher::TIMEZONE)->addMinutes(10);
        $publisher->publish($reviewer, $profile, $replacement, $scheduledFor);

        $replacementStaff->update(['status' => 'inactive']);
        $dueAt = $scheduledFor->addSecond();
        $publisher->releaseDue($dueAt);

        $afterCancellation = $profile->fresh();
        $this->assertSame('published', $afterCancellation->publication_state);
        $this->assertSame($liveRevision->id, $afterCancellation->published_revision_id);
        $this->assertNull($afterCancellation->scheduled_revision_id);
        $this->assertNull($afterCancellation->scheduled_for);
        $this->assertSame('approved', $replacement->fresh()->workflow_state);
        $this->assertSame('Current Live Profile', $this->visibility()->profiles($dueAt)->sole()->displayName);
    }

    private function editor(string $role = 'teacher'): User
    {
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    /** @param array<string, mixed> $overrides */
    private function activeStaff(array $overrides = []): StaffMember
    {
        return StaffMember::create(array_replace([
            'name' => 'Approved Staff Member',
            'designation' => 'Teacher',
            'type' => 'teaching',
            'basic_salary' => 0,
            'status' => 'active',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function draftData(StaffMember $staff, array $overrides = []): array
    {
        return array_replace([
            'staff_member_id' => $staff->id,
            'display_name' => 'Approved Team Member',
            'public_role' => 'Class Teacher',
            'team_group' => 'teaching',
            'qualification' => 'B.Ed.',
            'bio_markdown' => 'A short, approved introduction for families.',
            'display_order' => 10,
        ], $overrides);
    }

    private function visibility(): PublicTeamVisibility
    {
        return app(PublicTeamVisibility::class);
    }
}
