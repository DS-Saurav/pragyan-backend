<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Models\UserRole;
use App\Services\ResultService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Stage P3E: authoritative credit-hour entry for exam subjects.
 *
 * - Entry through the existing exam-setup form, validated server-side:
 *   strictly positive, max two decimals; blank = UNSET (never zero).
 * - Copying a class setup copies credit hours with the other fields.
 * - Pre-migration safety: with the schema column absent, entry is
 *   disabled/guarded and a submitted value is REJECTED with a clear
 *   prerequisite message - nothing is silently discarded as success.
 *
 * The unapplied migration is never run against the live database; the
 * "pre-migration" behaviour is simulated on the throwaway test database
 * by dropping the (migration-created) column.
 * Isolated fixtures only - no real student records or personal data.
 */
class ExamSetupCreditHoursTest extends TestCase
{
    use RefreshDatabase;

    /** @var null cache of the "pre-migration" simulation state per test */
    private bool $preMigration = false;

    private Exam $exam;

    private SchoolClass $class;

    private SchoolClass $targetClass;

    private Subject $subject;

    private ExamSubject $examSubject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preMigration = false;
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role' => $role]);

        return $user; // academic_coordinator holds exams.manage
    }

    private function fixture(): void
    {
        $this->class = SchoolClass::create(['name' => 'Class 9-CH', 'section' => 'A']);
        $this->targetClass = SchoolClass::create(['name' => 'Class 9-CH', 'section' => 'B']);
        $this->subject = Subject::create(['name' => 'Fixture Subject', 'code' => 'CH9', 'max_marks' => 80, 'passing_marks' => 32]);
        $this->exam = Exam::create([
            'name' => 'CH Terminal Exam',
            'type' => 'first_terminal',
            'academic_year' => '2083-2084',
            'start_date' => '2083-03-01',
            'status' => 'pending',
        ]);
        $this->examSubject = ExamSubject::create([
            'exam_id' => $this->exam->id,
            'school_class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'theory_full' => 80,
            'theory_pass' => 32,
            'practical_full' => 20,
            'practical_pass' => 8,
            'marks_status' => 'pending',
        ]);
    }

    private function dropCreditHoursColumn(): void
    {
        Schema::table('exam_subjects', fn ($table) => $table->dropColumn('credit_hours'));
        $this->preMigration = true;
    }

    /** The form's field layout for one row, exactly as the setup page sends it. */
    private function rowPayload(array $overrides = []): array
    {
        return array_merge([
            'exam_date' => '', 'start_time' => '', 'end_time' => '',
            'theory_full' => '80', 'theory_pass' => '32',
            'practical_full' => '20', 'practical_pass' => '8',
            'credit_hours' => '',
        ], $overrides);
    }

    private function save(array $row = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->userWithRole('academic_coordinator'))
            ->put(route('admin.exams.setup.save', $this->exam), [
                'class_id' => $this->class->id,
                'rows' => [$this->examSubject->id => $row ?: $this->rowPayload()],
            ]);
    }

    public function test_credit_hours_entered_on_the_setup_form_persist(): void
    {
        $this->fixture();

        $this->save($this->rowPayload(['credit_hours' => '3.75']))
            ->assertRedirect(route('admin.exams.setup', ['exam' => $this->exam, 'class_id' => $this->class->id]))
            ->assertSessionHas('success');

        $fresh = $this->examSubject->fresh();
        $this->assertSame(3.75, $fresh->credit_hours);
    }

    public function test_unrelated_fields_are_persisted_alongside_credit_hours(): void
    {
        $this->fixture();

        $this->save($this->rowPayload([
            'start_time' => '10:00', 'end_time' => '12:00',
            'theory_full' => '100', 'theory_pass' => '40',
            'credit_hours' => '4',
        ]))->assertSessionHas('success');

        $fresh = $this->examSubject->fresh();
        $this->assertSame(4.0, $fresh->credit_hours);
        $this->assertSame(100.0, $fresh->theory_full); // unrelated field honoured
        $this->assertSame(40.0, $fresh->theory_pass);
        $this->assertSame('10:00', $fresh->start_time?->format('H:i'), 'Start time preserved');
    }

    public function test_blank_means_unset_and_confirmed_zero_is_rejected(): void
    {
        $this->fixture();

        // Blank stays unset - nothing silently turned into 0.
        $this->save($this->rowPayload(['credit_hours' => '']))->assertSessionHas('success');
        $this->assertNull($this->examSubject->fresh()->credit_hours);

        // A confirmed zero is not a valid credit hour: rejected server-side.
        $this->save($this->rowPayload(['credit_hours' => '0']))
            ->assertSessionHasErrors();
        $this->assertNull($this->examSubject->fresh()->credit_hours, 'Zero must not be stored.');
    }

    public function test_invalid_credit_hour_values_are_rejected(): void
    {
        $this->fixture();

        foreach (['-2', '-4.5', '100', '3.756', 'abc'] as $bad) {
            $this->save($this->rowPayload(['credit_hours' => $bad]))
                ->assertSessionHasErrors();

            $this->assertNull($this->examSubject->fresh()->credit_hours, "Value '{$bad}' must be rejected.");
        }
    }

    public function test_copying_a_class_setup_carries_credit_hours(): void
    {
        $this->fixture();

        $this->save($this->rowPayload(['credit_hours' => '3.5']))->assertSessionHas('success');

        $this->actingAs($this->userWithRole('academic_coordinator'))
            ->post(route('admin.exams.setup.copy', $this->exam), [
                'class_id' => $this->class->id,
                'target_ids' => [$this->targetClass->id],
            ])->assertRedirect()
            ->assertSessionHas('success');

        $copied = ExamSubject::where('exam_id', $this->exam->id)
            ->where('school_class_id', $this->targetClass->id)
            ->where('subject_id', $this->subject->id)
            ->first();

        $this->assertNotNull($copied, 'Target row was created.');
        $this->assertSame(3.5, $copied->credit_hours, 'Credit hours must be copied with the setup.');
        $this->assertSame(80.0, $copied->theory_full);
        $this->assertSame(32.0, $copied->theory_pass);
        $this->assertSame(20.0, $copied->practical_full);
        $this->assertSame(8.0, $copied->practical_pass);
    }

    // ------------------------------------------------------------------
    // Pre-migration behaviour (column dropped on the throwaway test DB).
    // ------------------------------------------------------------------

    public function test_pre_migration_entry_is_disabled_on_the_setup_page(): void
    {
        $this->fixture();
        $this->dropCreditHoursColumn();

        $user = $this->userWithRole('academic_coordinator');
        $response = $this->actingAs($user)
            ->get(route('admin.exams.setup', $this->exam))
            ->assertOk();

        // Column disabled/note visible: the prerequisite is explained, and
        // the header marks the column pending.
        $html = $response->getContent();
        $this->assertStringContainsString('Entry is disabled until the pending exam_subjects.credit_hours migration is applied', $html);
        $this->assertStringContainsString('Credit Hours (pending)', $html);
        $this->assertStringContainsString('Submitted values cannot be', $html);
    }

    public function test_pre_migration_submission_of_a_value_is_rejected_without_partial_success(): void
    {
        $this->fixture();
        $this->dropCreditHoursColumn();

        $before = $this->examSubject->fresh()->only([
            'exam_date', 'start_time', 'end_time', 'theory_full', 'theory_pass', 'practical_full', 'practical_pass', 'marks_status',
        ]);

        $this->save($this->rowPayload(['credit_hours' => '4', 'theory_full' => '90']))
            ->assertSessionHasErrors('rows')
            ->assertSessionMissing('success');

        // Absolutely nothing was saved - including the unrelated theory change.
        $after = $this->examSubject->fresh()->only([
            'exam_date', 'start_time', 'end_time', 'theory_full', 'theory_pass', 'practical_full', 'practical_pass', 'marks_status',
        ]);
        $this->assertSame($before, $after, 'Nothing may update when credit-hour input cannot be stored.');
    }

    public function test_pre_migration_submission_without_credit_hours_still_saves(): void
    {
        $this->fixture();
        $this->dropCreditHoursColumn();

        // Blank/unset credit hours: no pending-schema impact, save proceeds.
        $this->save($this->rowPayload(['theory_full' => '95', 'credit_hours' => '']))
            ->assertSessionHas('success');

        $fresh = $this->examSubject->fresh();
        $this->assertSame(95.0, $fresh->theory_full);
        $this->assertSame(null, $fresh->getAttribute('credit_hours'), 'Column absent => attribute is null, not an error.');
    }

    public function test_pre_migration_copy_copies_setup_without_credit_hours(): void
    {
        $this->fixture();
        $this->dropCreditHoursColumn();

        $this->actingAs($this->userWithRole('academic_coordinator'))
            ->post(route('admin.exams.setup.copy', $this->exam), [
                'class_id' => $this->class->id,
                'target_ids' => [$this->targetClass->id],
            ])->assertRedirect()
            ->assertSessionHas('success');

        $copied = ExamSubject::where('exam_id', $this->exam->id)
            ->where('school_class_id', $this->targetClass->id)
            ->where('subject_id', $this->subject->id)
            ->first();

        $this->assertNotNull($copied);
        $this->assertSame(80.0, $copied->theory_full);
        // Attribute is null when the column does not exist - nothing invented.
        $this->assertNull($copied->getAttribute('credit_hours'));
    }

    // ------------------------------------------------------------------
    // GPA-integrity cross-check after entry: weighted basis engages only
    // when every subject row validates (existing ResultService logic).
    // ------------------------------------------------------------------

    public function test_entered_credit_hours_switch_the_gpa_basis(): void
    {
        $this->fixture();

        $this->save($this->rowPayload(['credit_hours' => '4']))->assertSessionHas('success');

        $student = Student::create([
            'adm_number' => 'CH-001', 'first_name' => 'Sample', 'last_name' => 'Learner',
            'gender' => 'Male', 'dob' => '2011-03-03', 'class_applied' => 'Class 9-CH A',
            'school_class_id' => $this->class->id, 'roll_no' => 1,
        ]);
        \App\Models\ExamMark::create([
            'exam_subject_id' => $this->examSubject->id,
            'student_id' => $student->id,
            'theory_marks' => 76,   // component marks are raw: theory out of 80...
            'practical_marks' => 20, // ...practical out of 20 => 96 of 100 => A+ 4.0
            'is_absent' => false,
        ]);

        $examSubject = $this->examSubject->fresh();
        $data = (new ResultService)->classResults($this->exam, $this->class);
        $row = $data['rows'][0];

        $this->assertSame('credit_hour', $row['gpa_basis']);
        $this->assertSame(4.0, $row['gpa']);
        $this->assertSame(4.0, $examSubject->credit_hours);
    }
}
