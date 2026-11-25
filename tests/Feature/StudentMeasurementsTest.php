<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentMeasurement;
use App\Models\Subject;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stage P3G: academic-year-specific height/weight measurements.
 *
 * - One authoritative record per student per academic year, height in
 *   cm / weight in kg, both nullable: unset must stay UNSET (never 0).
 * - Year independence: promoting a student or recording 2084 values
 *   never rewrites 2083 data (the students table is updated in place
 *   on promotion, this table is not).
 * - Marksheet resolution is exact-match on the EXAM's academic_year:
 *   no cross-year substitution, missing => "Not recorded".
 * - Entry follows the students module's staff-gated authorization;
 *   validation bounds come from config/school.php "measurements".
 * Isolated fixtures only - no real student records or personal data.
 */
class StudentMeasurementsTest extends TestCase
{
    use RefreshDatabase;

    private Exam $exam2083;

    private Exam $exam2084;

    private SchoolClass $class;

    private Student $student;

    private function staff(): User
    {
        // Students module is staff-gated (StaffAccessTest): any staff
        // role may enter, same as the student edit itself.
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role' => 'teacher']);

        return $user;
    }

    private function fixture(): void
    {
        Setting::set('marksheet_layout', json_encode(['show_height_weight' => 1]));
        $this->class = SchoolClass::create(['name' => 'Class 5-HW', 'section' => 'A']);
        $subject = Subject::create(['name' => 'Fixture Subject', 'code' => 'HW5']);
        $this->exam2083 = Exam::create([
            'name' => 'HW Terminal 2083', 'type' => 'first_terminal',
            'academic_year' => '2083-2084', 'start_date' => '2083-03-01',
            'end_date' => '2083-03-10', 'status' => 'published', 'published_at' => now(),
        ]);
        $this->exam2084 = Exam::create([
            'name' => 'HW Terminal 2084', 'type' => 'first_terminal',
            'academic_year' => '2084-2085', 'start_date' => '2084-03-01',
            'end_date' => '2084-03-10', 'status' => 'published', 'published_at' => now(),
        ]);
        ExamSubject::create([
            'exam_id' => $this->exam2083->id, 'school_class_id' => $this->class->id,
            'subject_id' => $subject->id, 'theory_full' => 100, 'theory_pass' => 35,
            'practical_full' => 0, 'practical_pass' => 0, 'marks_status' => 'locked',
        ]);
        $this->student = Student::create([
            'adm_number' => 'HW-001', 'first_name' => 'Fixture', 'last_name' => 'Learner',
            'gender' => 'Female', 'dob' => '2017-05-05', 'class_applied' => 'Class 5-HW A',
            'school_class_id' => $this->class->id, 'roll_no' => 1,
            'academic_year' => '2083-2084',
        ]);
    }

    private function save(Student $student, array $payload)
    {
        return $this->actingAs($this->staff())
            ->post(route('admin.students.measurements', $student), $payload);
    }

    // ------------------------------------------------------------------
    // Data model: create, update, idempotency
    // ------------------------------------------------------------------

    public function test_measurements_are_created_then_updated_for_a_year(): void
    {
        $this->fixture();

        $this->save($this->student, [
            'academic_year' => '2083-2084',
            'height_cm' => '132.5', 'weight_kg' => '34.3',
            'measured_on' => now()->toDateString(),
        ])->assertRedirect();

        $record = StudentMeasurement::where('student_id', $this->student->id)
            ->where('academic_year', '2083-2084')->first();
        $this->assertNotNull($record);
        $this->assertSame(132.5, $record->height_cm);
        $this->assertSame(34.3, $record->weight_kg);
        $this->assertSame(now()->toDateString(), $record->measured_on?->toDateString());

        // Update the same record: still exactly one row for that year.
        $this->save($this->student, [
            'academic_year' => '2083-2084', 'height_cm' => '140', 'weight_kg' => '36',
        ])->assertRedirect();

        $this->assertSame(1, StudentMeasurement::where('student_id', $this->student->id)->count());
        $record = $record->fresh();
        $this->assertSame(140.0, $record->height_cm);
        $this->assertNull($record->measured_on, 'Leaving the date blank clears it, it is not inherited.');
    }

    public function test_blank_input_keeps_a_measurement_unset_and_zero_is_rejected(): void
    {
        $this->fixture();

        // Abbreviated save with nothing entered: unset stays unset.
        $this->save($this->student, ['academic_year' => '2083-2084'])->assertRedirect();

        $record = StudentMeasurement::where('academic_year', '2083-2084')->first();
        $this->assertNotNull($record);
        $this->assertNull($record->height_cm, 'A blank entry must be stored as unset (null), not zero.');
        $this->assertNull($record->weight_kg);

        // Height zero is not a body measurement: rejected server-side.
        $this->save($this->student, [
            'academic_year' => '2083-2084', 'height_cm' => '0', 'weight_kg' => '0',
        ])->assertSessionHasErrors(['height_cm', 'weight_kg'], null, 'measurements');
        $this->assertNull($record->fresh()->height_cm, 'Zero was not stored.');
    }

    public function test_out_of_bounds_malformed_and_imprecise_values_are_rejected(): void
    {
        $this->fixture();

        foreach ([
            ['height_cm' => '-3', 'weight_kg' => '25'],    // negative height
            ['height_cm' => '132.5', 'weight_kg' => '-1'], // negative weight
            ['height_cm' => '1000', 'weight_kg' => '25'],  // above bound
            ['height_cm' => '132.5', 'weight_kg' => '950'],  // above bound
            ['height_cm' => '132.55', 'weight_kg' => '25'],  // second decimal against decimal(4,1)
            ['height_cm' => 'abc', 'weight_kg' => '25'],     // not numeric
        ] as $bad) {
            $this->save($this->student, ['academic_year' => '2083-2084'] + $bad)
                ->assertSessionHasErrors();

            $this->assertNull(
                StudentMeasurement::where('academic_year', '2083-2084')->first()?->height_cm,
                'Nothing invalid was persisted for '.json_encode($bad).'.'
            );
        }

        // A defensible value then saves fine after all the failures.
        $this->save($this->student, [
            'academic_year' => '2083-2084', 'height_cm' => '132.5', 'weight_kg' => '34.3',
        ])->assertRedirect();
        $this->assertSame(132.5, StudentMeasurement::where('academic_year', '2083-2084')->first()->height_cm);
    }

    // ------------------------------------------------------------------
    // Year independence
    // ------------------------------------------------------------------

    public function test_measurements_for_different_years_stay_independent(): void
    {
        $this->fixture();

        $this->save($this->student, [
            'academic_year' => '2083-2084', 'height_cm' => '120', 'weight_kg' => '28',
        ])->assertRedirect();
        // Simulate the student advancing to the next year with new values.
        $this->save($this->student, [
            'academic_year' => '2084-2085', 'height_cm' => '129', 'weight_kg' => '30',
        ])->assertRedirect();

        $this->assertSame(2, StudentMeasurement::where('student_id', $this->student->id)->count());
        $y2083 = StudentMeasurement::where('academic_year', '2083-2084')->first();
        $y2084 = StudentMeasurement::where('academic_year', '2084-2085')->first();
        $this->assertSame(120.0, $y2083->height_cm);
        $this->assertSame(129.0, $y2084->height_cm, 'The next year keeps its own record.');
        $this->assertSame(28.0, $y2083->fresh()->weight_kg, '2083 data survives the new year.');
    }

    // ------------------------------------------------------------------
    // Marksheet integration
    // ------------------------------------------------------------------

    private function renderMarksheet(Exam $exam)
    {
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role' => 'principal']);

        return $this->actingAs($user)
            ->get(route('admin.results.marksheet', [$exam, $this->student]));
    }

    public function test_marksheet_shows_the_measurements_of_the_exams_own_academic_year(): void
    {
        $this->fixture();

        StudentMeasurement::create([
            'student_id' => $this->student->id, 'academic_year' => '2083-2084',
            'height_cm' => 132.5, 'weight_kg' => 34.3,
        ]);

        // 2083 exam prints 2083's record.
        $this->renderMarksheet($this->exam2083)->assertOk()
            ->assertSee('132.5')->assertSee('34.3');

        // 2084 exam (no 2084 record yet) prints the unavailable state and
        // MUST NOT fall back to the newer-or-older year's values.
        $html2084 = $this->renderMarksheet($this->exam2084)->assertOk()->getContent();

        $this->assertStringContainsString('Not recorded', $html2084);
        $this->assertStringNotContainsString('132.5', $html2084);
        $this->assertStringNotContainsString('34.3', $html2084);
    }

    public function test_missing_measurements_display_the_unavailable_state(): void
    {
        $this->fixture();

        $html = $this->renderMarksheet($this->exam2083)->assertOk()->getContent();

        $this->assertStringContainsString('Height (cm)', $html);
        $this->assertStringContainsString('Not recorded', $html);
        $this->assertStringNotContainsString('0 cm', $html);
        $this->assertStringNotContainsString('0 kg', $html);
    }

    public function test_a_later_years_measurement_never_leaks_into_an_earlier_marksheet(): void
    {
        $this->fixture();

        StudentMeasurement::create([
            'student_id' => $this->student->id, 'academic_year' => '2084-2085',
            'height_cm' => 141.5, 'weight_kg' => 40.5,
        ]);

        $html2083 = $this->renderMarksheet($this->exam2083)->assertOk()->getContent();

        $this->assertStringContainsString('Not recorded', $html2083);
        $this->assertStringNotContainsString('141.5', $html2083);
        $this->assertStringNotContainsString('40.5', $html2083);
    }

    // ------------------------------------------------------------------
    // Authorization
    // ------------------------------------------------------------------

    public function test_non_staff_roles_cannot_save_measurements(): void
    {
        $this->fixture();

        // A role-less user and a parent are not staff: students module 403s.
        foreach ([null, 'parent'] as $forbiddenRole) {
            $user = User::factory()->create();
            if ($forbiddenRole !== null) {
                UserRole::create(['user_id' => $user->id, 'role' => $forbiddenRole]);
            }

            $this->actingAs($user)
                ->post(route('admin.students.measurements', $this->student), [
                    'academic_year' => '2083-2084', 'height_cm' => '150',
                ])
                ->assertForbidden();

            $this->assertNull(StudentMeasurement::where('student_id', $this->student->id)->first());
        }

        // Staff can still save afterwards.
        $this->save($this->student, [
            'academic_year' => '2083-2084', 'height_cm' => '150',
        ])->assertRedirect();
        $this->assertSame(150.0, StudentMeasurement::where('academic_year', '2083-2084')->first()->height_cm);
    }
}
