<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Models\Role;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stage P3F + P3F.1 + P3F.2: marksheet attendance distinguishes
 * genuine recorded attendance from an empty window, a marked student
 * from one nobody marked, and coverage of the CLASS-RECORDED dates
 * from partial coverage - evaluated, when possible, on the dates on
 * or after the student's authoritative students.admission_date.
 *
 * - Coverage denominator = distinct class-recorded attendance dates in
 *   the exam window (NOT every instructional day), intersected with
 *   [admission_date, window] when admission is valid and available.
 * - Pre-admission rows never enter numerator or denominator.
 * - Full eligible coverage (any status, absent-only included)
 *   => "present / eligible class-recorded days" - the plain fraction.
 * - Some eligible coverage => "Partially recorded" with exact detail.
 * - No eligible coverage / no relevant rows => "Not recorded", and a
 *   clear non-numeric state when admission leaves zero eligible dates.
 * - Missing or unusable admission date => no invented enrollment claim;
 *   the conservative class-wide denominator is used and the limitation
 *   is explained in the note.
 * - Dates outside the exam window never count; present/late arithmetic
 *   is untouched.
 * Isolated fixtures only - no real student records or personal data.
 */
class MarksheetAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private Exam $exam;

    private SchoolClass $class;

    /** roll => Student */
    private array $students = [];

    private ExamSubject $examSubject;

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    private function fixture(): void
    {
        // This file tests Attendance states only; disable the separate
        // height/weight block (Stage P3G prints "Not recorded" there when
        // a measurement is unset) so those cells cannot collide with the
        // attendance assertions.
        \App\Models\Setting::set('marksheet_layout', json_encode(['show_height_weight' => 0]));

        $this->class = SchoolClass::create(['name' => 'Class 4-Att', 'section' => 'A']);
        $subject = Subject::create(['name' => 'Fixture Subject', 'code' => 'ATT4']);
        $this->exam = Exam::create([
            'name' => 'Attendance Terminal Exam',
            'type' => 'first_terminal',
            'academic_year' => '2083-2084',
            'start_date' => '2083-03-01',
            'end_date' => '2083-03-10',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $this->examSubject = ExamSubject::create([
            'exam_id' => $this->exam->id,
            'school_class_id' => $this->class->id,
            'subject_id' => $subject->id,
            'theory_full' => 100,
            'theory_pass' => 35,
            'practical_full' => 0,
            'practical_pass' => 0,
            'marks_status' => 'locked',
        ]);

        foreach ([1 => ['Mark', 'One'], 2 => ['Nina', 'Two'], 3 => ['Omar', 'Three']] as $roll => [$first, $last]) {
            $this->students[$roll] = Student::create([
                'adm_number' => 'ATT-00'.$roll,
                'first_name' => $first,
                'last_name' => $last,
                'gender' => 'Male',
                'dob' => '2017-07-07',
                'class_applied' => 'Class 4-Att A',
                'school_class_id' => $this->class->id,
                'roll_no' => $roll,
            ]);
        }
    }

    private function markAttendance(int $roll, string $date, string $status): void
    {
        Attendance::create([
            'school_class_id' => $this->class->id,
            'student_id' => $this->students[$roll]->id,
            'date' => $date,
            'status' => $status,
        ]);
    }

    private function renderFor(int $roll): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->userWithRole('principal'))
            ->get(route('admin.results.marksheet', [$this->exam, $this->students[$roll]]));
    }

    public function test_recorded_attendance_counts_genuinely_per_student(): void
    {
        $this->fixture();

        // Roll 1: present + late count; r1 = 2 of 3 recorded days.
        $this->markAttendance(1, '2083-03-05', Attendance::PRESENT);
        $this->markAttendance(1, '2083-03-06', Attendance::LATE);
        $this->markAttendance(1, '2083-03-07', Attendance::ABSENT);
        // Roll 2: marked every day as absent => genuine 0 / 3, NOT
        // "Not recorded" (rows exist; someone actually recorded absence).
        $this->markAttendance(2, '2083-03-05', Attendance::ABSENT);
        $this->markAttendance(2, '2083-03-06', Attendance::ABSENT);
        $this->markAttendance(2, '2083-03-07', Attendance::ABSENT);

        $this->renderFor(1)->assertOk()->assertSee('2 / 3 days');
        $this->renderFor(2)->assertOk()->assertSee('0 / 3 days');
    }

    public function test_no_attendance_records_show_an_honest_unavailable_state(): void
    {
        $this->fixture();

        $html = $this->renderFor(1)->assertOk()->getContent();

        $this->assertStringContainsString('Not recorded', $html);
        $this->assertStringNotContainsString('0 / 0 days', $html);
    }

    public function test_marked_students_show_arithmetic_while_nobody_marked_students_do_not(): void
    {
        $this->fixture();

        // Window has records, but only for rolls 1 and 2. Roll 3 was never
        // marked: no row of ANY status, so 0 / 2 may not be implied.
        $this->markAttendance(1, '2083-03-05', Attendance::PRESENT);
        $this->markAttendance(1, '2083-03-06', Attendance::PRESENT);
        $this->markAttendance(2, '2083-03-05', Attendance::ABSENT);
        $this->markAttendance(2, '2083-03-06', Attendance::ABSENT);

        $html1 = $this->renderFor(1)->assertOk()->getContent();
        $html2 = $this->renderFor(2)->assertOk()->getContent();
        $html3 = $this->renderFor(3)->assertOk()->getContent();

        // Roll 1 covers every recorded date => genuine 2 / 2.
        $this->assertStringContainsString('2 / 2 days', $html1);
        $this->assertStringNotContainsString('Not recorded', $html1);
        // Roll 2 has an absent row on EVERY recorded date => genuine
        // arithmetic ("0 / 2"), NOT the unavailable or partial state.
        $this->assertStringContainsString('0 / 2 days', $html2);
        $this->assertStringNotContainsString('Not recorded', $html2);
        $this->assertStringNotContainsString('Partially recorded', $html2);
        $this->assertStringContainsString('Not recorded', $html3);
    }

    public function test_partial_coverage_shows_a_state_instead_of_a_misleading_fraction(): void
    {
        $this->fixture();

        // Class recorded dates = {05, 06}. Roll 1 has a row on 05 only
        // (1 of 2), roll 2 is absent-only but complete (0 / 2 is genuine).
        // No admission date on record: conservative class-wide denominator,
        // no invented enrollment claim.
        $this->markAttendance(1, '2083-03-05', Attendance::PRESENT);
        $this->markAttendance(2, '2083-03-05', Attendance::ABSENT);
        $this->markAttendance(2, '2083-03-06', Attendance::ABSENT);

        $html1 = $this->renderFor(1)->assertOk()->getContent();
        $html2 = $this->renderFor(2)->assertOk()->getContent();

        $this->assertStringContainsString('Partially recorded', $html1);
        $this->assertStringContainsString('(on 1 of 2 class-recorded days)', $html1);
        // The fraction that would imply absent-on-unrecorded-days must
        // not appear for the partial student.
        $this->assertStringNotContainsString('1 / 2 days', $html1);
        // The denominator is explained as class-recorded dates (not a
        // full instructional calendar), and the missing admission date
        // is disclosed, not fabricated.
        $this->assertStringContainsString('not every instructional day', $html1);
        $this->assertStringContainsString('not on record or not usable', $html1);
        $this->assertStringNotContainsString('admission on 2083', $html1);
        // Complete absent-only coverage remains genuine arithmetic.
        $this->assertStringContainsString('0 / 2 days', $html2);
        $this->assertStringNotContainsString('Partially recorded', $html2);
    }

    public function test_admission_between_recorded_dates_with_complete_eligible_coverage(): void
    {
        $this->fixture();

        // Class recorded dates = {05, 06}. Roll 3 was admitted on 06, so
        // only {06} is markable for them; the row covering 06 is COMPLETE
        // coverage of the eligible dates => the plain fraction, without
        // pre-admission 05 shrinking or distorting anything.
        $this->markAttendance(1, '2083-03-05', Attendance::PRESENT);
        $this->markAttendance(3, '2083-03-06', Attendance::PRESENT);
        $this->students[3]->forceFill(['admission_date' => '2083-03-06'])->save();

        $html3 = $this->renderFor(3)->assertOk()->getContent();

        $this->assertStringContainsString('1 / 1 days', $html3);
        $this->assertStringNotContainsString('Partially recorded', $html3);
        $this->assertStringNotContainsString('Not recorded', $html3);
    }

    public function test_admission_between_recorded_dates_with_incomplete_eligible_coverage(): void
    {
        $this->fixture();

        // Class recorded dates = {05, 06, 07}. Roll 3 admitted on 06 =>
        // eligible {06, 07}; marked on 06 only => partial over eligible
        // dates, with the admission named in the coverage note.
        $this->markAttendance(1, '2083-03-05', Attendance::PRESENT);
        $this->markAttendance(1, '2083-03-06', Attendance::PRESENT);
        $this->markAttendance(2, '2083-03-07', Attendance::ABSENT);
        $this->markAttendance(3, '2083-03-06', Attendance::PRESENT);
        $this->students[3]->forceFill(['admission_date' => '2083-03-06'])->save();

        $html3 = $this->renderFor(3)->assertOk()->getContent();

        $this->assertStringContainsString('Partially recorded', $html3);
        $this->assertStringContainsString('(on 1 of 2 class-recorded days)', $html3);
        $this->assertStringContainsString('on or after the admission on 2083-03-06', $html3);
        $this->assertStringNotContainsString('1 of 3', $html3);
    }

    public function test_admission_on_a_recorded_date_is_counted_from_that_day(): void
    {
        $this->fixture();

        // Admission date EQUALS a recorded date: that day itself is
        // eligible (>= admission), so covering 05 and 06 is complete.
        $this->markAttendance(1, '2083-03-05', Attendance::LATE);
        $this->markAttendance(1, '2083-03-06', Attendance::PRESENT);
        $this->students[1]->forceFill(['admission_date' => '2083-03-05'])->save();

        $this->renderFor(1)->assertOk()->assertSee('2 / 2 days');
    }

    public function test_admission_after_the_final_recorded_date_is_non_numeric(): void
    {
        $this->fixture();

        // Class recorded dates = {05}. Roll 3 admitted after all recorded
        // days: zero eligible dates, and their only row predates their own
        // admission (kept out of numerator and denominator). Must be a
        // clear non-numeric state, never 0/0 or an implied absence count.
        $this->markAttendance(1, '2083-03-05', Attendance::PRESENT);
        $this->markAttendance(3, '2083-03-05', Attendance::ABSENT);
        $this->students[3]->forceFill(['admission_date' => '2083-03-09'])->save();

        $html3 = $this->renderFor(3)->assertOk()->getContent();

        $this->assertStringContainsString('Not recorded', $html3);
        $this->assertStringContainsString('No class attendance was recorded on or after the admission on 2083-03-09', $html3);
        $this->assertStringNotContainsString('Partially recorded', $html3);
        $this->assertStringNotContainsString('0 / 0 days', $html3);
        $this->assertStringNotContainsString('/ 1 days', $html3);
    }

    public function test_unusable_admission_dates_do_not_invent_enrollment_coverage(): void
    {
        $this->fixture();

        // A structurally invalid admission date (round-trip fails: Feb 30
        // rolls over) must be treated like a missing one - conservative
        // class-wide denominator, limitation disclosed, no claim invented.
        $this->markAttendance(1, '2083-03-05', Attendance::PRESENT);
        $this->markAttendance(2, '2083-03-05', Attendance::ABSENT);
        $this->markAttendance(2, '2083-03-06', Attendance::ABSENT);
        $this->students[1]->forceFill(['admission_date' => '2083-02-30'])->save();

        $html1 = $this->renderFor(1)->assertOk()->getContent();

        $this->assertStringContainsString('Partially recorded', $html1);
        $this->assertStringContainsString('(on 1 of 2 class-recorded days)', $html1);
        $this->assertStringContainsString('not on record or not usable', $html1);
        $this->assertStringNotContainsString('admission on 2083-02-30', $html1);
    }

    public function test_days_outside_the_exam_window_are_not_counted(): void
    {
        $this->fixture();

        // In-window: two distinct recorded dates... but every date counts
        // only the rows belonging to the class window (03-01 .. 03-10).
        $this->markAttendance(1, '2083-03-05', Attendance::PRESENT);
        $this->markAttendance(1, '2083-03-06', Attendance::PRESENT);
        // Outside the exam window: must not add a recorded day.
        $this->markAttendance(1, '2083-02-20', Attendance::PRESENT);
        $this->markAttendance(1, '2083-11-01', Attendance::PRESENT);

        $this->renderFor(1)->assertOk()->assertSee('2 / 2 days');
    }

    public function test_open_ended_window_counts_every_recorded_day_from_start(): void
    {
        // exams.start_date is NOT NULL (create_exam_tables); only end_date
        // is nullable. An exam saved without an end date therefore has an
        // open-ended window: every recorded day on/after the start counts.
        $this->fixture();
        $this->exam->forceFill(['end_date' => null])->save();
        $this->exam->refresh();

        $this->markAttendance(1, '2083-02-20', Attendance::PRESENT); // before window: excluded
        $this->markAttendance(1, '2083-03-05', Attendance::PRESENT);
        $this->markAttendance(1, '2083-11-01', Attendance::PRESENT); // after open end: included

        $this->renderFor(1)->assertOk()->assertSee('2 / 2 days');
    }
}
