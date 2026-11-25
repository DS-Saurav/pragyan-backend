<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UiSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    public function test_public_pages_render(): void
    {
        $this->get('/')->assertOk();
        $this->get('/notices')->assertOk();
        $this->get('/team')->assertOk();
        $this->get('/about')->assertOk();
        $this->get('/academics')->assertOk();
        $this->get('/admissions')->assertOk();
        $this->get('/news')->assertOk();
        $this->get('/gallery')->assertOk();
        $this->get('/contact')->assertOk();
        $this->get('/calendar')->assertOk();
        $this->get('/login')->assertOk();
    }

    public function test_admin_pages_render(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->get('/dashboard')->assertOk();
        $this->actingAs($admin)->get('/profile')->assertOk();
        $this->actingAs($admin)->get('/admin/settings')->assertOk();
        $this->actingAs($admin)->get('/admin/settings/website')->assertOk();
        $this->actingAs($admin)->get('/admin/about')->assertOk();
        $this->actingAs($admin)->get('/admin/substitutes')->assertOk();
        $this->actingAs($admin)->get('/admin/activities')->assertOk();
        $this->actingAs($admin)->get('/admin/calendar')->assertOk();
        $this->actingAs($admin)->get('/admin/attendance')->assertOk();
        $this->actingAs($admin)->get('/admin/accounts')->assertOk();
        $this->actingAs($admin)->get('/admin/assets')->assertOk();
        $this->actingAs($admin)->get('/admin/admissions')->assertOk();
        $this->actingAs($admin)->get('/admin/users')->assertOk();
    }

    public function test_student_dashboard_renders_without_management_links(): void
    {
        $student = $this->userWithRole('student');

        $this->actingAs($student)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Total students')
            ->assertDontSee('Manage students');

        $this->actingAs($student)->get('/profile')->assertOk();
        $this->actingAs($student)->get('/admin/about')->assertForbidden();
        $this->actingAs($student)->get('/admin/students')->assertForbidden();
    }

    public function test_staff_can_open_student_management(): void
    {
        $teacher = $this->userWithRole('teacher');

        $this->actingAs($teacher)->get('/admin/students')->assertOk();
    }
}
