<?php

namespace Tests\Feature;

use App\Models\Notice;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoticeAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_and_staff_without_notice_capabilities_cannot_open_the_editor(): void
    {
        $this->get(route('admin.notices.index'))->assertRedirect(route('login'));

        $this->actingAs($this->userWithPermissions([]))
            ->get(route('admin.notices.index'))
            ->assertForbidden();
    }

    public function test_parent_and_student_accounts_cannot_open_notice_administration_or_draft_previews(): void
    {
        $notice = Notice::factory()->create();
        $revision = $notice->revisions()->create([
            'revision_number' => 1,
            'slug' => 'private-notice-preview',
            'title' => 'Private notice preview',
            'content' => 'This draft is private.',
            'audience' => 'public',
            'date' => '2026-11-10',
            'workflow_state' => 'draft',
        ]);

        foreach (['parent', 'student'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)
                ->get(route('admin.notices.index'))
                ->assertForbidden()
                ->assertDontSee($revision->title)
                ->assertDontSee($revision->content);
            $this->get(route('admin.notices.preview', [$notice, $revision]))
                ->assertForbidden()
                ->assertDontSee($revision->title);
        }
    }

    public function test_author_can_create_and_preview_a_private_notice_but_cannot_review_or_publish_it(): void
    {
        $author = $this->userWithPermissions(['notices.author']);

        $this->actingAs($author)
            ->get(route('admin.notices.create'))
            ->assertOk();
        $this->post(route('admin.notices.store'), $this->noticePayload())
            ->assertRedirect();

        $notice = Notice::query()->sole();
        $revision = $notice->revisions()->sole();
        $this->assertSame('draft', $notice->publication_state);
        $this->assertSame('draft', $revision->workflow_state);

        $this->get(route('admin.notices.preview', [$notice, $revision]))
            ->assertOk()
            ->assertSee($revision->title);
        $this->patch(route('admin.notices.approve', [$notice, $revision]))->assertForbidden();
        $this->patch(route('admin.notices.publish', [$notice, $revision]))->assertForbidden();
    }

    public function test_reviewer_and_publisher_can_inspect_notices_but_do_not_gain_author_access(): void
    {
        foreach (['notices.review', 'notices.publish'] as $permission) {
            $user = $this->userWithPermissions([$permission]);

            $this->actingAs($user)
                ->get(route('admin.notices.index'))
                ->assertOk();
            $this->get(route('admin.notices.create'))->assertForbidden();
        }
    }

    public function test_existing_notice_manager_is_compatible_with_all_editorial_actions(): void
    {
        $manager = $this->userWithPermissions(['notices.manage']);

        $this->actingAs($manager)
            ->post(route('admin.notices.store'), $this->noticePayload())
            ->assertRedirect();

        $notice = Notice::query()->sole();
        $revision = $notice->revisions()->sole();
        $this->patch(route('admin.notices.submit', [$notice, $revision]))->assertRedirect();
        $this->patch(route('admin.notices.approve', [$notice, $revision]))->assertRedirect();
        $this->patch(route('admin.notices.publish', [$notice, $revision]))->assertRedirect();
        $this->assertSame('published', $notice->fresh()->publication_state);

        $this->delete(route('admin.notices.destroy', $notice))->assertRedirect();
        $this->assertDatabaseMissing('notices', ['id' => $notice->id]);
    }

    public function test_notice_author_permission_does_not_grant_sensitive_module_capabilities(): void
    {
        $author = $this->userWithPermissions(['notices.author']);

        foreach ([
            'admissions.manage',
            'fees.view',
            'payroll.view',
            'users.view',
            'gallery.author',
            'media.view',
            'documents.view',
        ] as $capability) {
            $this->assertFalse($author->can($capability), $capability.' must remain separate from notices.author.');
        }

        $this->actingAs($author)
            ->get(route('admin.admissions.index'))
            ->assertForbidden();
        $this->get(route('admin.gallery.index'))->assertForbidden();
        $this->get(route('admin.media.status'))->assertForbidden();
    }

    /** @param array<int, string> $permissions */
    private function userWithPermissions(array $permissions): User
    {
        config()->set('permissions.roles.teacher', $permissions);

        return $this->userWithRole('teacher');
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    /** @return array<string, mixed> */
    private function noticePayload(): array
    {
        return [
            'title' => 'A family update',
            'slug' => 'a-family-update',
            'content' => 'A carefully prepared private Notice.',
            'audience' => 'public',
            'date' => '2026-11-10',
            'priority' => 2,
            'is_urgent' => false,
        ];
    }
}
