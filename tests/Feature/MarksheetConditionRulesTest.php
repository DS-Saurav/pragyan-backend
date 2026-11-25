<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamMark;
use App\Models\ExamSubject;
use App\Models\SchoolClass;
use App\Models\Setting;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Models\UserRole;
use App\Services\MarksheetLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Per-overall-result-condition display rules for the marksheet summary
 * section (Stage P3C). Conditions are exactly ResultService's overall
 * statuses - PASS, FAIL, INCOMPLETE - and rules only decide which summary
 * cells show; they can never change grade bands, GPA arithmetic, or the
 * computed result itself. Safe default: INCOMPLETE hides Grade and GPA.
 * Isolated fixtures only - no real student records or personal data.
 */
class MarksheetConditionRulesTest extends TestCase
{
    use RefreshDatabase;

    private Exam $exam;

    private SchoolClass $class;

    /** @return array<string, Student> roll => student */
    private array $students;

    private ExamSubject $examSubject;

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    /**
     * One class, one subject, three students:
     * roll 1 passes (92/100), roll 2 fails an entered mark (20/100 -> NG),
     * roll 3 has nothing entered (INCOMPLETE).
     */
    private function fixture(): void
    {
        $this->class = SchoolClass::create(['name' => 'Class 8-Rules', 'section' => 'A']);
        $subject = Subject::create(['name' => 'Fixture Subject', 'code' => 'RUL8']);
        $this->exam = Exam::create([
            'name' => 'Rules Terminal Exam',
            'type' => 'first_terminal',
            'academic_year' => '2083-2084',
            'start_date' => '2083-03-01',
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

        foreach ([1 => ['Passed', 'One'], 2 => ['Failed', 'Two'], 3 => ['Pending', 'Three']] as $roll => [$first, $last]) {
            $this->students[$roll] = Student::create([
                'adm_number' => 'RULE-00'.$roll,
                'first_name' => $first,
                'last_name' => $last,
                'gender' => 'Male',
                'dob' => '2012-02-02',
                'class_applied' => 'Class 8-Rules A',
                'school_class_id' => $this->class->id,
                'roll_no' => $roll,
            ]);
        }

        ExamMark::create([
            'exam_subject_id' => $this->examSubject->id,
            'student_id' => $this->students[1]->id,
            'theory_marks' => 92,
            'is_absent' => false,
        ]);
        ExamMark::create([
            'exam_subject_id' => $this->examSubject->id,
            'student_id' => $this->students[2]->id,
            'theory_marks' => 20, // 20% => NG, entered -> overall FAIL
            'is_absent' => false,
        ]);
        // No mark row for roll 3 -> INCOMPLETE.
    }

    private function renderFor(int $roll): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->userWithRole('principal'))
            ->get(route('admin.results.marksheet', [$this->exam, $this->students[$roll]]));
    }

    public function test_incomplete_results_hide_grade_and_gpa_by_default(): void
    {
        $this->fixture();

        // Passing student: summary fully inherited, GPA shown.
        $this->renderFor(1)
            ->assertOk()
            ->assertSee('GPA (unweighted): 4.00')
            ->assertSee('Grade:')
            ->assertSee('Result:');

        // Same layout, incomplete result: no numeric GPA can be
        // presented as the final outcome; the honest status stays visible.
        $this->renderFor(3)
            ->assertOk()
            ->assertSeeInOrder(['Result:', 'INCOMPLETE'], false)
            ->assertDontSee('GPA (unweighted):')
            ->assertDontSee('Grade:');
    }

    public function test_each_condition_can_have_its_own_saved_rules(): void
    {
        $this->fixture();
        Setting::set('marksheet_layout', json_encode([
            'condition_rules' => [
                'pass' => ['gpa' => 0, 'result_label' => 0],
                'fail' => ['gpa' => 0],
                'incomplete' => ['gpa' => 1, 'grade' => 1],
            ],
        ]));

        // PASS: GPA and result label hidden, grade still shown. With the
        // result cell hidden, "PASS" appears nowhere on the sheet.
        $this->renderFor(1)
            ->assertOk()
            ->assertDontSee('GPA (unweighted):')
            ->assertDontSee('PASS')
            ->assertSee('Grade:');

        // FAIL: GPA hidden, result label inherits and stays, with the
        // not-passed list intact.
        $this->renderFor(2)
            ->assertOk()
            ->assertDontSee('GPA (unweighted): 0.00')
            ->assertSeeInOrder(['Result:', 'FAIL'], false)
            ->assertSee('Not passed in:');

        // INCOMPLETE: explicit rules win over the safe default and may
        // re-show GPA/grade.
        $this->renderFor(3)
            ->assertOk()
            ->assertSee('GPA (unweighted): 0.00')
            ->assertSee('Grade:')
            ->assertSeeInOrder(['Result:', 'INCOMPLETE'], false);
    }

    public function test_hiding_every_cell_for_a_condition_renders_no_empty_table(): void
    {
        $this->fixture();
        Setting::set('marksheet_layout', json_encode([
            'condition_rules' => [
                'fail' => [
                    'gpa' => 0, 'grade' => 0, 'division' => 0, 'total_marks' => 0,
                    'percentage' => 0, 'rank' => 0, 'result_label' => 0,
                ],
            ],
        ]));

        $this->renderFor(2)
            ->assertOk()
            ->assertDontSee('class="gpa-summary"')
            ->assertDontSee('FAIL');

        // Other conditions keep their (inherited) summary row.
        $this->renderFor(1)->assertOk()->assertSee('PASS');
    }

    public function test_unsupported_conditions_and_keys_are_dropped(): void
    {
        Setting::set('marksheet_layout', json_encode([
            'condition_rules' => [
                'warn' => ['gpa' => 1],              // unsupported condition
                'fail' => ['bogus' => 1, 'gpa' => 'maybe'], // bogus key dropped, junk value coerced to Hide
                'pass' => 'not-an-array',            // malformed set dropped
            ],
        ]));

        $layout = MarksheetLayout::load();

        $this->assertSame([
            'fail' => ['gpa' => false],
        ], $layout->conditionRules, 'Only allowlisted condition/field pairs survive.');

        // The saved document renders safely for a fail student.
        $this->fixture();
        $this->renderFor(2)->assertOk()->assertDontSee('GPA (unweighted):');
    }

    public function test_form_submitted_rules_are_validated_and_persisted(): void
    {
        // A permitted administrator saves overrides through the real editor
        // endpoint; junk conditions and fields must not be persisted.
        $this->actingAs($this->userWithRole('principal'))
            ->put(route('admin.settings.marksheet.update'), [
                'condition_rules' => [
                    'fail' => ['gpa' => '0', 'grade' => '1'],
                    'practical' => ['gpa' => '1'],     // unsupported condition
                    'pass' => ['nope' => '1'],         // unsupported field
                    'incomplete' => ['gpa' => '1'],
                ],
            ])
            ->assertRedirect(route('admin.settings.marksheet'))
            ->assertSessionHas('success');

        $layout = MarksheetLayout::load();

        $this->assertSame([
            'fail' => ['gpa' => false, 'grade' => true],
            'incomplete' => ['gpa' => true],
        ], $layout->conditionRules, 'Only allowlisted condition/field pairs are stored.');

        $this->fixture();
        $this->renderFor(3)->assertOk()->assertSee('GPA (unweighted): 0.00', false);
        $this->renderFor(2)->assertOk()->assertSee('Grade:')->assertDontSee('GPA (unweighted): 0.00');
    }

    public function test_users_without_settings_permission_cannot_change_the_rules(): void
    {
        Setting::set('marksheet_layout', json_encode(['condition_rules' => ['fail' => ['gpa' => 0]]]));
        $before = Setting::get('marksheet_layout');

        $this->put(route('admin.settings.marksheet.update'), [
            'condition_rules' => ['pass' => ['gpa' => '0']],
        ])->assertRedirect();

        $this->actingAs(User::factory()->create())
            ->put(route('admin.settings.marksheet.update'), [
                'condition_rules' => ['fail' => ['gpa' => '1']],
            ])
            ->assertForbidden();

        $this->assertSame($before, Setting::get('marksheet_layout'), 'Nothing may change without settings.manage.');

        // A permitted role still works, proving the denial is permission-based.
        $this->actingAs($this->userWithRole('principal'))
            ->put(route('admin.settings.marksheet.update'), [
                'condition_rules' => ['pass' => ['gpa' => '0']],
            ])
            ->assertRedirect(route('admin.settings.marksheet'))
            ->assertSessionHas('success');
    }

    public function test_legacy_and_missing_settings_render_safely(): void
    {
        $this->fixture();

        // Legacy document without any condition_rules key.
        Setting::set('marksheet_layout', json_encode(['columns' => ['credit_hour' => false]]));
        $this->assertSame([], MarksheetLayout::load()->conditionRules);
        $this->renderFor(1)
            ->assertOk()
            ->assertSee('GPA (unweighted): 4.00')
            ->assertSee('Grade:')
            ->assertSee('Result:');

        // No stored settings at all: same safe inheritance.
        Setting::where('key', 'marksheet_layout')->delete();
        $this->assertSame([], MarksheetLayout::load()->conditionRules);
        $this->renderFor(3)
            ->assertOk()
            ->assertSeeInOrder(['Result:', 'INCOMPLETE'], false)
            ->assertDontSee('GPA (unweighted):', false)
            ->assertDontSee('<td>Grade:', false);
    }
}
