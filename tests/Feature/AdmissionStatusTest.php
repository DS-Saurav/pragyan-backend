<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdmissionStatusTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    private function admission(string $status = 'registered'): Admission
    {
        return Admission::create([
            'reg_number' => 'REG-2026-00001',
            'first_name' => 'Test',
            'last_name' => 'Applicant',
            'gender' => 'Male',
            'dob' => '2012-01-01',
            'class_applied' => 'Class 9',
            'academic_year' => '2026',
            'parent_name' => 'Parent Name',
            'mobile' => '9800000000',
            'status' => $status,
        ]);
    }

    public function test_authorized_user_can_change_admission_status(): void
    {
        $coordinator = $this->userWithRole('academic_coordinator'); // admissions.manage
        $admission = $this->admission();

        $this->actingAs($coordinator)
            ->patch(route('admin.admissions.status', $admission), ['status' => 'admitted'])
            ->assertRedirect();

        $this->assertSame('admitted', $admission->fresh()->status);
    }

    public function test_user_without_permission_cannot_change_admission_status(): void
    {
        $teacher = $this->userWithRole('teacher'); // staff, but no admissions.manage
        $admission = $this->admission();

        $this->actingAs($teacher)
            ->patch(route('admin.admissions.status', $admission), ['status' => 'admitted'])
            ->assertForbidden();

        $this->assertSame('registered', $admission->fresh()->status);
    }

    public function test_invalid_status_is_rejected_and_no_change_persisted(): void
    {
        $coordinator = $this->userWithRole('academic_coordinator');
        $admission = $this->admission();

        $this->actingAs($coordinator)
            ->patch(route('admin.admissions.status', $admission), ['status' => 'not_a_real_status'])
            ->assertSessionHasErrors('status');

        $this->assertSame('registered', $admission->fresh()->status);
    }

    public function test_rejection_requires_valid_reason_and_stores_it(): void
    {
        $coordinator = $this->userWithRole('academic_coordinator');
        $admission = $this->admission();

        // Over-long reason is rejected.
        $this->actingAs($coordinator)
            ->patch(route('admin.admissions.status', $admission), [
                'status' => 'rejected',
                'rejection_reason' => str_repeat('x', 1001),
            ])
            ->assertSessionHasErrors('rejection_reason');

        $this->assertSame('registered', $admission->fresh()->status);

        // Valid reason is stored.
        $this->actingAs($coordinator)
            ->patch(route('admin.admissions.status', $admission), [
                'status' => 'rejected',
                'rejection_reason' => 'Did not meet criteria.',
            ])
            ->assertRedirect();

        $admission->refresh();
        $this->assertSame('rejected', $admission->status);
        $this->assertSame('Did not meet criteria.', $admission->rejection_reason);
    }

    public function test_rejection_reason_is_cleared_when_status_is_not_rejected(): void
    {
        $coordinator = $this->userWithRole('academic_coordinator');
        $admission = $this->admission();

        // First reject with a reason.
        $this->actingAs($coordinator)
            ->patch(route('admin.admissions.status', $admission), [
                'status' => 'rejected',
                'rejection_reason' => 'Incomplete documents.',
            ])
            ->assertRedirect();

        $this->assertSame('Incomplete documents.', $admission->fresh()->rejection_reason);

        // Then admit: the stale rejection reason must be cleared.
        $this->actingAs($coordinator)
            ->patch(route('admin.admissions.status', $admission), ['status' => 'admitted'])
            ->assertRedirect();

        $admission->refresh();
        $this->assertSame('admitted', $admission->status);
        $this->assertNull($admission->rejection_reason);
    }

    public function test_student_cannot_reach_admission_status_route(): void
    {
        $student = $this->userWithRole('student');
        $admission = $this->admission();

        $this->actingAs($student)
            ->patch(route('admin.admissions.status', $admission), ['status' => 'admitted'])
            ->assertForbidden();

        $this->assertSame('registered', $admission->fresh()->status);
    }
}
