<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\AboutController;
use App\Http\Controllers\Admin\NoticeController;
use App\Models\Notice;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ContentAccessAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(?string $role): User
    {
        $user = User::factory()->create();
        if ($role !== null) {
            UserRole::create(['user_id' => $user->id, 'role' => $role]);
        }

        return $user;
    }

    private function noticeData(array $overrides = []): array
    {
        return array_replace([
            'title' => 'School announcement',
            'content' => 'School announcement body',
            'date' => '2026-11-01',
            'audience' => 'all',
            'status' => 'published',
        ], $overrides);
    }

    private function notice(array $overrides = []): Notice
    {
        return Notice::create($this->noticeData($overrides) + ['posted_by' => 'School office']);
    }

    public function test_admin_can_create_and_delete_notices_with_the_real_permission_catalog(): void
    {
        $admin = $this->userWithRole('admin');
        $this->assertTrue($admin->can('notices.manage'));
        $this->actingAs($admin)->post(route('admin.notices.store'), $this->noticeData())
            ->assertRedirect()->assertSessionHasNoErrors();
        $notice = Notice::sole();
        $this->assertSame($admin->name, $notice->posted_by);
        $this->assertDatabaseHas('notices', ['id' => $notice->id, 'title' => 'School announcement']);
        $this->delete(route('admin.notices.destroy', $notice))->assertRedirect();
        $this->assertDatabaseMissing('notices', ['id' => $notice->id]);
    }

    public function test_every_non_admin_role_and_roleless_user_is_denied_notice_writes(): void
    {
        $notice = $this->notice();
        foreach ([...array_diff(array_keys(config('permissions.role_labels')), ['admin']), null] as $role) {
            $user = $this->userWithRole($role);
            $this->assertFalse($user->can('notices.manage'), $role ?? 'roleless');
            $this->actingAs($user)->post(route('admin.notices.store'), $this->noticeData(['title' => 'Denied write']))
                ->assertForbidden();
            $this->delete(route('admin.notices.destroy', $notice))->assertForbidden();
            $this->assertDatabaseCount('notices', 1);
            $this->assertDatabaseHas('notices', ['id' => $notice->id, 'title' => 'School announcement']);
        }
    }

    public function test_guest_notice_requests_redirect_to_login_without_writing(): void
    {
        $notice = $this->notice();
        $this->post(route('admin.notices.store'), $this->noticeData())->assertRedirect(route('login'));
        $this->delete(route('admin.notices.destroy', $notice))->assertRedirect(route('login'));
        $this->assertDatabaseCount('notices', 1);
    }

    public function test_notice_controller_denies_writes_even_without_route_authorization_middleware(): void
    {
        $notice = $this->notice();
        $this->withoutMiddleware(Authorize::class);
        $this->actingAs($this->userWithRole('teacher'))
            ->post(route('admin.notices.store'), [])->assertForbidden();
        $this->delete(route('admin.notices.destroy', $notice))->assertForbidden();
        $this->assertDatabaseHas('notices', ['id' => $notice->id]);
        $this->assertDatabaseCount('notices', 1);
    }

    public function test_direct_notice_store_invocation_is_denied_before_validation_or_writes(): void
    {
        $this->actingAs($this->userWithRole('teacher'));
        $this->expectException(AuthorizationException::class);
        app(NoticeController::class)->store(Request::create('/', 'POST'));
    }

    public function test_direct_notice_delete_invocation_is_denied_without_deleting(): void
    {
        $notice = $this->notice();
        $this->actingAs($this->userWithRole('teacher'));
        try {
            app(NoticeController::class)->destroy($notice);
            $this->fail('Unauthorized controller invocation succeeded.');
        } catch (AuthorizationException) {
            $this->assertDatabaseHas('notices', ['id' => $notice->id]);
        }
    }

    public function test_notice_administration_requires_an_editorial_capability_but_remains_available_to_administrators(): void
    {
        $this->notice();
        $this->actingAs($this->userWithRole('teacher'))
            ->get(route('admin.notices.index'))->assertForbidden();
        $this->actingAs($this->userWithRole('admin'))
            ->get(route('admin.notices.index'))->assertOk()->assertSee('School announcement');
    }

    public function test_public_notice_visibility_preserves_publication_and_audience_filters(): void
    {
        $this->notice(['title' => 'Published public notice', 'audience' => 'public']);
        $this->notice(['title' => 'Published all notice']);
        $this->notice(['title' => 'Draft public notice', 'status' => 'draft', 'audience' => 'public']);
        $this->notice(['title' => 'Staff-only notice', 'audience' => 'staff']);
        $this->get(route('public.notices'))->assertOk()
            ->assertSee('Published public notice')->assertSee('Published all notice')
            ->assertDontSee('Draft public notice')->assertDontSee('Staff-only notice');
    }

    public function test_admin_and_existing_principal_settings_manager_can_view_about_content(): void
    {
        Setting::set('school_name', 'Preserved school identity');
        foreach (['admin', 'principal'] as $role) {
            $user = $this->userWithRole($role);
            $this->assertTrue($user->can('settings.manage'));
            $this->actingAs($user)->get(route('admin.about'))->assertOk()
                ->assertSee('Preserved school identity')->assertSee('Developed by');
        }
        $this->assertDatabaseHas('settings', ['key' => 'school_name', 'value' => 'Preserved school identity']);
    }

    public function test_every_role_without_settings_manage_and_roleless_users_are_denied_about(): void
    {
        foreach ([...array_diff(array_keys(config('permissions.role_labels')), ['admin', 'principal']), null] as $role) {
            $user = $this->userWithRole($role);
            $this->assertFalse($user->can('settings.manage'));
            $this->actingAs($user)->get(route('admin.about'))->assertForbidden();
        }
    }

    public function test_guest_about_access_redirects_to_login(): void
    {
        $this->get(route('admin.about'))->assertRedirect(route('login'));
    }

    public function test_about_controller_denies_access_without_route_authorization_middleware(): void
    {
        $this->withoutMiddleware(Authorize::class);
        $this->actingAs($this->userWithRole('teacher'))->get(route('admin.about'))->assertForbidden();
    }

    public function test_direct_about_action_invocation_requires_settings_manage(): void
    {
        $this->actingAs($this->userWithRole('teacher'));
        $this->expectException(AuthorizationException::class);
        app(AboutController::class)->index();
    }

    public function test_existing_settings_update_remains_available_to_admin_and_principal(): void
    {
        foreach (['admin', 'principal'] as $role) {
            $this->actingAs($this->userWithRole($role))->put(route('admin.settings.update'), [
                'school_name' => 'Updated identity '.$role,
                'time_zone' => 'Asia/Kathmandu',
                'currency_symbol' => 'Rs',
                'academic_year' => '2083',
                'primary_color' => '#112233',
                'accent_color' => '#ffee00',
            ])->assertRedirect(route('admin.settings.index'))->assertSessionHasNoErrors();
            $this->get(route('admin.about'))->assertOk()->assertSee('Updated identity '.$role);
            $this->assertDatabaseHas('settings', ['key' => 'school_name', 'value' => 'Updated identity '.$role]);
        }
    }

    public function test_existing_settings_update_denies_staff_students_parents_and_guests_without_writing(): void
    {
        Setting::set('school_name', 'Unchanged identity');
        $this->put(route('admin.settings.update'), ['school_name' => 'Denied'])->assertRedirect(route('login'));
        foreach (['teacher', 'student', 'parent'] as $role) {
            $this->actingAs($this->userWithRole($role))->put(route('admin.settings.update'), ['school_name' => 'Denied'])
                ->assertForbidden();
            $this->assertDatabaseHas('settings', ['key' => 'school_name', 'value' => 'Unchanged identity']);
        }
    }

    public function test_settings_view_alone_does_not_grant_about_or_settings_write_access(): void
    {
        // Isolated test configuration, not a new persisted role assignment.
        config(['permissions.roles.teacher' => ['settings.view']]);
        $teacher = $this->userWithRole('teacher');
        $this->actingAs($teacher)->get(route('admin.settings.index'))->assertOk();
        $this->get(route('admin.about'))->assertForbidden();
        $this->put(route('admin.settings.update'), [])->assertForbidden();
    }
}
