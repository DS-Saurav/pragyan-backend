<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamMark;
use App\Models\ExamSubject;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarksLockAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Student $student;

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    /**
     * Build a class with one enrolled student and an exam subject for it.
     * When $withExistingMarks is true the student already has persisted marks.
     */
    private function examSubject(
        string $status = 'pending',
        bool $withStudent = true,
        bool $withExistingMarks = false,
        float $existingTheory = 60,
    ): ExamSubject {
        $class = SchoolClass::create(['name' => 'Class 9', 'section' => 'A']);
        $subject = Subject::create(['name' => 'Maths', 'code' => 'MTH9']);
        $exam = Exam::create([
            'name' => 'Unit Test 1',
            'type' => 'unit_test',
            'academic_year' => '2026',
            'start_date' => now()->toDateString(),
            'status' => 'scheduled',
        ]);

        $this->student = Student::create([
            'adm_number' => 'ADM-001',
            'first_name' => 'Test',
            'last_name' => 'Student',
            'gender' => 'Male',
            'dob' => '2012-01-01',
            'class_applied' => $withStudent ? 'Class 9 A' : 'Class 10 A',
            'school_class_id' => $withStudent ? $class->id : null,
            'roll_no' => 1,
        ]);

        $examSubject = ExamSubject::create([
            'exam_id' => $exam->id,
            'school_class_id' => $class->id,
            'subject_id' => $subject->id,
            'theory_full' => 100,
            'theory_pass' => 35,
            'practical_full' => 0,
            'practical_pass' => 0,
            'marks_status' => $status,
        ]);

        if ($withExistingMarks) {
            ExamMark::create([
                'exam_subject_id' => $examSubject->id,
                'student_id' => $this->student->id,
                'theory_marks' => $existingTheory,
                'practical_marks' => null,
                'is_absent' => false,
            ]);
        }

        return $examSubject;
    }

    /**
     * Complete marks payload for the enrolled student.
     *
     * @return array<string, mixed>
     */
    private function marksPayload(float $theory = 75): array
    {
        return ['marks' => [$this->student->id => ['theory' => $theory]]];
    }

    public function test_authorized_teacher_can_save_marks(): void
    {
        $teacher = $this->userWithRole('teacher'); // marks.enter
        $examSubject = $this->examSubject();

        $this->actingAs($teacher)
            ->put(route('admin.marks.save', $examSubject), ['action' => 'draft'] + $this->marksPayload(82))
            ->assertRedirect();

        $this->assertDatabaseHas('exam_marks', [
            'exam_subject_id' => $examSubject->id,
            'student_id' => $this->student->id,
            'theory_marks' => 82,
        ]);
        $this->assertSame('draft', $examSubject->fresh()->marks_status);
    }

    public function test_user_with_marks_enter_but_not_lock_cannot_lock_marks(): void
    {
        $teacher = $this->userWithRole('teacher'); // marks.enter, no marks.lock
        $examSubject = $this->examSubject();

        $this->actingAs($teacher)
            ->put(route('admin.marks.save', $examSubject), ['action' => 'draft'] + $this->marksPayload())
            ->assertRedirect();

        $this->assertSame('draft', $examSubject->fresh()->marks_status);

        $this->actingAs($teacher)
            ->put(route('admin.marks.save', $examSubject), ['action' => 'lock'] + $this->marksPayload())
            ->assertForbidden();

        $this->assertSame('draft', $examSubject->fresh()->marks_status);
    }

    public function test_user_with_marks_lock_can_lock_class_with_real_marks(): void
    {
        $coordinator = $this->userWithRole('academic_coordinator'); // marks.lock
        // Populated class WITH persisted marks: this is the path that previously
        // threw MassAssignmentException on ExamMark.
        $examSubject = $this->examSubject(status: 'draft', withExistingMarks: true);

        $this->actingAs($coordinator)
            ->put(route('admin.marks.save', $examSubject), ['action' => 'lock'] + $this->marksPayload(60))
            ->assertRedirect();

        $this->assertSame('locked', $examSubject->fresh()->marks_status);
        $this->assertDatabaseHas('exam_marks', [
            'exam_subject_id' => $examSubject->id,
            'student_id' => $this->student->id,
            'theory_marks' => 60,
        ]);
    }

    public function test_user_without_marks_lock_cannot_unlock(): void
    {
        $teacher = $this->userWithRole('teacher'); // no marks.lock
        $examSubject = $this->examSubject(status: 'locked', withExistingMarks: true);

        $this->actingAs($teacher)
            ->post(route('admin.marks.unlock', $examSubject))
            ->assertForbidden();

        $this->assertSame('locked', $examSubject->fresh()->marks_status);
    }

    public function test_user_with_marks_lock_can_unlock(): void
    {
        $coordinator = $this->userWithRole('academic_coordinator');
        $examSubject = $this->examSubject(status: 'locked', withExistingMarks: true);

        $this->actingAs($coordinator)
            ->post(route('admin.marks.unlock', $examSubject))
            ->assertRedirect();

        $this->assertSame('draft', $examSubject->fresh()->marks_status);
    }

    public function test_marks_are_unchanged_when_lock_authorization_fails(): void
    {
        $teacher = $this->userWithRole('teacher'); // marks.enter, no marks.lock
        $examSubject = $this->examSubject(status: 'draft', withExistingMarks: true, existingTheory: 60);

        $this->actingAs($teacher)
            ->put(route('admin.marks.save', $examSubject), ['action' => 'lock'] + $this->marksPayload(95))
            ->assertForbidden();

        // No status change and no mark overwrite.
        $this->assertSame('draft', $examSubject->fresh()->marks_status);
        $this->assertDatabaseHas('exam_marks', [
            'exam_subject_id' => $examSubject->id,
            'student_id' => $this->student->id,
            'theory_marks' => 60,
        ]);
    }
}
