<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardExperienceTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    public function test_staff_dashboard_has_a_clear_operational_hierarchy(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-dashboard-shell', false)
            ->assertSee('School day at a glance')
            ->assertSee('School records')
            ->assertSee('Latest notices')
            ->assertSee('Next actions')
            ->assertSee('Manage students');
    }

    public function test_community_dashboard_keeps_staff_management_out_of_view(): void
    {
        $student = $this->userWithRole('student');

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Your school portal')
            ->assertSee('Open helpdesk')
            ->assertSee('Next actions')
            ->assertDontSee('School day at a glance')
            ->assertDontSee('School records')
            ->assertDontSee('Manage students');
    }
}
