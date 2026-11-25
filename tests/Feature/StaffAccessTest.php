<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffAccessTest extends TestCase
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

    public function test_user_without_role_is_denied_staff_routes(): void
    {
        $user = $this->userWithRole(null);

        $this->actingAs($user)->get('/admin/students')->assertForbidden();
        $this->actingAs($user)->get('/admin/classes')->assertForbidden();
    }

    public function test_student_cannot_access_staff_routes(): void
    {
        $student = $this->userWithRole('student');

        $this->actingAs($student)->get('/admin/students')->assertForbidden();
    }

    public function test_parent_cannot_access_staff_routes(): void
    {
        $parent = $this->userWithRole('parent');

        $this->actingAs($parent)->get('/admin/students')->assertForbidden();
    }

    public function test_authorized_staff_user_can_access_allowed_route(): void
    {
        $teacher = $this->userWithRole('teacher');

        $this->actingAs($teacher)->get('/admin/students')->assertOk();
    }

    public function test_public_registration_does_not_grant_administrative_privileges(): void
    {
        $this->post('/register', [
            'name' => 'New User',
            'email' => 'new-user@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'new-user@example.com')->firstOrFail();

        $this->assertSame([], $user->roleNames());
        $this->assertFalse($user->hasRole('admin'));
        $this->actingAs($user)->get('/admin/students')->assertForbidden();
    }
}
