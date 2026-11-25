<?php

namespace Tests\Feature;

use App\Models\PublicEditorialGrant;
use App\Models\PublicEditorialGrantEvent;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PublicEditorialGrantsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_grant_exact_public_editorial_actions_and_revoke_them_with_an_audit_trail(): void
    {
        $admin = $this->userWithRoles(['admin']);
        $teacher = $this->userWithRoles(['teacher']);

        $this->assertFalse($teacher->hasPermission('news.author'));
        $this->assertFalse($teacher->hasPermission('media.upload'));

        $this->actingAs($admin)
            ->get(route('admin.users.editorial-access', $teacher))
            ->assertOk()
            ->assertSee('Public Editorial Access');

        $this->put(route('admin.users.editorial-access.update', $teacher), [
            'permissions' => ['news.author', 'media.upload'],
        ])->assertRedirect(route('admin.users.editorial-access', $teacher));

        $this->assertSame(['media.upload', 'news.author'], PublicEditorialGrant::query()
            ->where('user_id', $teacher->id)->orderBy('permission')->pluck('permission')->all());
        $this->assertTrue(Gate::forUser($teacher)->allows('news.author'));
        $this->assertTrue(Gate::forUser($teacher)->allows('media.upload'));
        $this->assertFalse(Gate::forUser($teacher)->allows('news.review'));
        $this->assertFalse(Gate::forUser($teacher)->allows('users.manage'));
        $this->assertFalse(Gate::forUser($teacher)->allows('public_admissions.manage'));
        $this->assertFalse(Gate::forUser($teacher)->allows('settings.manage'));

        $this->actingAs($teacher)->get(route('admin.news.create'))->assertOk();
        $this->actingAs($teacher)->get(route('admin.users.editorial-access', $teacher))->assertForbidden();

        $this->actingAs($admin)->put(route('admin.users.editorial-access.update', $teacher), [
            'permissions' => ['news.author', 'media.upload'],
        ])->assertRedirect();
        $this->assertSame(2, PublicEditorialGrantEvent::query()->count());

        $this->put(route('admin.users.editorial-access.update', $teacher), [
            'permissions' => ['media.upload'],
        ])->assertRedirect();

        $this->assertFalse(Gate::forUser($teacher)->allows('news.author'));
        $this->assertSame(['granted', 'granted', 'revoked'], PublicEditorialGrantEvent::query()
            ->orderBy('id')->pluck('action')->all());
        $this->assertDatabaseHas('public_editorial_grant_events', [
            'target_user_id' => $teacher->id,
            'permission' => 'news.author',
            'action' => 'revoked',
            'actor_id' => $admin->id,
        ]);
    }

    public function test_only_admin_can_change_grants_even_if_another_role_gets_user_management_access(): void
    {
        $principal = $this->userWithRoles(['principal']);
        $teacher = $this->userWithRoles(['teacher']);
        config()->set('permissions.roles.principal', ['users.manage']);

        $this->actingAs($principal)
            ->get(route('admin.users.editorial-access', $teacher))
            ->assertForbidden();
        $this->put(route('admin.users.editorial-access.update', $teacher), [
            'permissions' => ['news.publish'],
        ])->assertForbidden();

        $this->assertDatabaseCount('public_editorial_grants', 0);
        $this->assertDatabaseCount('public_editorial_grant_events', 0);
    }

    public function test_suspended_admin_cannot_view_or_change_public_editorial_grants(): void
    {
        $admin = $this->userWithRoles(['admin']);
        $recipient = $this->userWithRoles(['teacher']);
        $admin->update(['status' => 'suspended']);

        $this->actingAs($admin->fresh())
            ->get(route('admin.users.editorial-access', $recipient))
            ->assertRedirect(route('login'));
        $this->actingAs($admin->fresh())->put(route('admin.users.editorial-access.update', $recipient), [
            'permissions' => ['gallery.author'],
        ])->assertRedirect(route('login'));

        $this->assertDatabaseCount('public_editorial_grants', 0);
    }

    public function test_grants_are_restricted_to_active_staff_without_parent_student_or_admin_roles(): void
    {
        $admin = $this->userWithRoles(['admin']);
        $this->actingAs($admin);

        foreach ([[], ['parent'], ['student'], ['teacher', 'parent'], ['teacher', 'student'], ['admin']] as $roles) {
            $recipient = $this->userWithRoles($roles);
            $this->put(route('admin.users.editorial-access.update', $recipient), [
                'permissions' => ['gallery.publish'],
            ])->assertSessionHasErrors('permissions');
        }

        $suspended = $this->userWithRoles(['teacher']);
        $suspended->update(['status' => 'suspended']);
        $this->put(route('admin.users.editorial-access.update', $suspended), [
            'permissions' => ['gallery.publish'],
        ])->assertSessionHasErrors('permissions');

        $this->assertDatabaseCount('public_editorial_grants', 0);
        $this->assertDatabaseCount('public_editorial_grant_events', 0);
    }

    public function test_unlisted_capabilities_cannot_be_granted_or_used_as_direct_grants(): void
    {
        $admin = $this->userWithRoles(['admin']);
        $teacher = $this->userWithRoles(['teacher']);

        $this->actingAs($admin)->put(route('admin.users.editorial-access.update', $teacher), [
            'permissions' => ['news.author', 'calendar.manage'],
        ])->assertSessionHasErrors('permissions.1');

        $this->assertDatabaseCount('public_editorial_grants', 0);
        $this->assertNotContains('public_identity.manage', PublicEditorialGrant::allowedPermissions());
        $this->assertNotContains('public_admissions.manage', PublicEditorialGrant::allowedPermissions());
        $this->assertNotContains('users.manage', PublicEditorialGrant::allowedPermissions());
        $this->assertNotContains('media.manage', PublicEditorialGrant::allowedPermissions());

        PublicEditorialGrant::create([
            'user_id' => $teacher->id,
            'permission' => 'users.manage',
            'granted_by' => $admin->id,
        ]);
        $this->assertFalse($teacher->hasPermission('users.manage'));
    }

    public function test_role_change_and_suspension_revoke_grants_without_restoring_them_later(): void
    {
        $admin = $this->userWithRoles(['admin']);
        $roleChanged = $this->userWithRoles(['teacher']);
        $suspended = $this->userWithRoles(['teacher']);

        $this->actingAs($admin)->put(route('admin.users.editorial-access.update', $roleChanged), [
            'permissions' => ['news.author'],
        ])->assertRedirect();
        $this->put(route('admin.users.editorial-access.update', $suspended), [
            'permissions' => ['gallery.publish'],
        ])->assertRedirect();

        $this->put(route('admin.users.update', $roleChanged), [
            'name' => $roleChanged->name,
            'email' => $roleChanged->email,
            'roles' => ['parent'],
        ])->assertRedirect();
        $this->post(route('admin.users.status', $suspended))->assertRedirect();

        $this->assertFalse($roleChanged->fresh()->hasPermission('news.author'));
        $this->assertFalse($suspended->fresh()->hasPermission('gallery.publish'));
        $this->assertDatabaseCount('public_editorial_grants', 0);
        $this->assertSame(2, PublicEditorialGrantEvent::query()->where('action', 'revoked')->count());

        $this->post(route('admin.users.status', $suspended))->assertRedirect();
        $this->assertFalse($suspended->fresh()->hasPermission('gallery.publish'));
        $this->assertDatabaseCount('public_editorial_grants', 0);
    }

    /** @param list<string> $roles */
    private function userWithRoles(array $roles): User
    {
        $user = User::factory()->create();

        foreach ($roles as $role) {
            UserRole::create(['user_id' => $user->id, 'role' => $role]);
        }

        return $user->fresh();
    }
}
