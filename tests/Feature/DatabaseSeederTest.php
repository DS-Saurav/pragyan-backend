<?php

namespace Tests\Feature;

use App\Models\FeeHead;
use App\Models\SalaryComponent;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_runs_on_a_clean_database(): void
    {
        $this->seed();

        $this->assertDatabaseHas('users', ['email' => 'admin@pragyan.edu.np']);
        $this->assertDatabaseHas('users', ['email' => 'teacher@pragyan.edu.np']);
        $this->assertDatabaseHas('users', ['email' => 'student@pragyan.edu.np']);
    }

    public function test_seeded_users_receive_canonical_role_assignments(): void
    {
        $this->seed();

        foreach ([
            'admin@pragyan.edu.np' => 'admin',
            'teacher@pragyan.edu.np' => 'teacher',
            'student@pragyan.edu.np' => 'student',
        ] as $email => $role) {
            $user = User::where('email', $email)->firstOrFail();

            $this->assertTrue(
                $user->hasRole($role),
                "Expected {$email} to have role {$role} in user_roles."
            );
        }
    }

    public function test_demo_student_profile_has_all_required_fields(): void
    {
        $this->seed();

        $student = Student::where('adm_number', 'ADM-1001')->firstOrFail();

        $this->assertSame('Saurav', $student->first_name);
        $this->assertSame('Student', $student->last_name);
        $this->assertSame('male', $student->gender);
        $this->assertSame('2010-05-15', (string) $student->dob);
        $this->assertSame('Class 10 A', $student->class_applied);
        $this->assertNotNull($student->school_class_id);
        $this->assertSame(1001, $student->roll_no);
    }

    public function test_repeated_seeding_does_not_create_duplicates(): void
    {
        $this->seed();

        $users = User::count();
        $classes = SchoolClass::count();
        $students = Student::count();
        $feeHeads = FeeHead::count();
        $salaryComponents = SalaryComponent::count();
        $userRoles = \DB::table('user_roles')->count();

        $this->seed();

        $this->assertSame($users, User::count(), 'Users were duplicated on re-seed.');
        $this->assertSame($classes, SchoolClass::count(), 'Classes were duplicated on re-seed.');
        $this->assertSame($students, Student::count(), 'Students were duplicated on re-seed.');
        $this->assertSame($feeHeads, FeeHead::count(), 'Fee heads were duplicated on re-seed.');
        $this->assertSame($salaryComponents, SalaryComponent::count(), 'Salary components were duplicated on re-seed.');
        $this->assertSame($userRoles, \DB::table('user_roles')->count(), 'User roles were duplicated on re-seed.');
    }

    public function test_only_the_expected_account_holds_the_admin_role(): void
    {
        $this->seed();

        $adminEmails = User::whereHas('userRoles', fn ($q) => $q->where('role', 'admin'))
            ->pluck('email')
            ->all();

        $this->assertSame(['admin@pragyan.edu.np'], $adminEmails);
    }
}
