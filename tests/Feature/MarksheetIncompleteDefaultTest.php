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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stage P3D: the safe default for an INCOMPLETE overall result shows ONLY
 * the "INCOMPLETE" label - overall grade, GPA, division, total marks,
 * percentage and rank are held back so marks entered so far can never be
 * presented as the final outcome. All PASS/FAIL behaviour, the computed
 * result itself and all academic calculations are untouched.
 * Isolated fixtures only - no real student records or personal data.
 */
class MarksheetIncompleteDefaultTest extends TestCase
{
    use RefreshDatabase;

    private Exam $exam;

    private SchoolClass $class;

    private array $students; // roll => Student

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    /**
     * One class, one subject, two students: roll 1 completes (92/100 ->
     * PASS), roll 3 has nothing entered -> INCOMPLETE.
     */
    private function fixture(): void
    {
        $this->class = SchoolClass::create(['name' => 'Class 7-Default', 'section' => 'B']);
        $subject = Subject::create(['name' => 'Fixture Subject', 'code' => 'DEF7']);
        $this->exam = Exam::create([
            'name' => 'Default Terminal Exam',
            'type' => 'first_terminal',
            'academic_year' => '2083-2084',
            'start_date' => '2083-03-01',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $examSubject = ExamSubject::create([
            'exam_id' => $this->exam->id,
            'school_class_id' => $this->class->id,
            'subject_id' => $subject->id,
            'theory_full' => 100,
            'theory_pass' => 35,
            'practical_full' => 0,
            'practical_pass' => 0,
            'marks_status' => 'locked',
        ]);

        foreach ([1 => ['Done', 'One'], 3 => ['Wait', 'Three']] as $roll => [$first, $last]) {
            $this->students[$roll] = Student::create([
                'adm_number' => 'DEF-00'.$roll,
                'first_name' => $first,
                'last_name' => $last,
                'gender' => 'Female',
                'dob' => '2012-07-07',
                'class_applied' => 'Class 7-Default B',
                'school_class_id' => $this->class->id,
                'roll_no' => $roll,
            ]);
        }

        ExamMark::create([
            'exam_subject_id' => $examSubject->id,
            'student_id' => $this->students[1]->id,
            'theory_marks' => 92,
            'is_absent' => false,
        ]);
    }

    private function renderFor(int $roll): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->userWithRole('principal'))
            ->get(route('admin.results.marksheet', [$this->exam, $this->students[$roll]]));
    }

    public function test_incomplete_default_shows_only_the_result_label(): void
    {
        $this->fixture();

        // PASS: every supported summary field stays inherited as before.
        $this->renderFor(1)
            ->assertOk()
            ->assertSee('GPA (unweighted): 4.00')
            ->assertSee('Grade:')
            ->assertSee('Division:')
            ->assertSee('Total Marks:')
            ->assertSee('Percentage:')
            ->assertSee('Rank:')
            ->assertSee('Result:');

        // INCOMPLETE: only the honest status line remains - nothing that
        // looks like a finalized outcome.
        $this->renderFor(3)
            ->assertOk()
            ->assertSee('Result:')
            ->assertSee('INCOMPLETE')
            ->assertDontSee('GPA (unweighted):')
            ->assertDontSee('<td>Grade:', false)
            ->assertDontSee('Division:')
            ->assertDontSee('Total Marks:')
            ->assertDontSee('Percentage:')
            ->assertDontSee('Rank:');
    }

    public function test_administrator_can_show_incomplete_fields_individually(): void
    {
        $this->fixture();

        // Explicit per-field rules win over the safe default, one at a time.
        Setting::set('marksheet_layout', json_encode([
            'condition_rules' => ['incomplete' => ['total_marks' => 1, 'percentage' => 1]],
        ]));

        $this->renderFor(3)
            ->assertOk()
            ->assertSee('Total Marks:')
            ->assertSee('Percentage: 0.00%')
            ->assertSee('Result:')
            ->assertSee('INCOMPLETE')
            ->assertDontSee('GPA (unweighted):')
            ->assertDontSee('<td>Grade:', false)
            ->assertDontSee('Division:')
            ->assertDontSee('Rank:');
    }

    public function test_saved_rules_survive_settings_reload_through_the_editor(): void
    {
        $this->fixture();

        $this->actingAs($this->userWithRole('principal'))
            ->put(route('admin.settings.marksheet.update'), [
                'condition_rules' => [
                    'incomplete' => ['division' => '1', 'rank' => '1'],
                ],
            ])
            ->assertRedirect(route('admin.settings.marksheet'))
            ->assertSessionHas('success');

        // Re-read from the settings table, not the in-memory object.
        $layout = \App\Services\MarksheetLayout::load();
        $this->assertSame(
            ['division' => true, 'rank' => true],
            $layout->conditionRules['incomplete'],
            'Rules must persist and reload for the incomplete condition.',
        );

        $this->renderFor(3)
            ->assertOk()
            ->assertSee('Division:')
            ->assertSee('Rank: -')
            ->assertDontSee('GPA (unweighted):')
            ->assertDontSee('<td>Grade:', false)
            ->assertDontSee('Total Marks:')
            ->assertDontSee('Percentage:');
    }

    public function test_condition_rules_never_change_the_computed_result(): void
    {
        $this->fixture();

        // Aggressively hiding every cell must not alter the classification:
        // the result is still FAIL-classified machinery behind the scenes for
        // the other student and PASS below keeps its academic values.
        Setting::set('marksheet_layout', json_encode([
            'condition_rules' => ['pass' => [
                'gpa' => 0, 'grade' => 0, 'division' => 0, 'total_marks' => 0,
                'percentage' => 0, 'rank' => 0, 'result_label' => 0,
            ]],
        ]));

        $rows = collect((new \App\Services\ResultService)->classResults($this->exam, $this->class)['rows'])
            ->keyBy(fn ($r) => $r['roll']);

        $this->assertSame('PASS', $rows['1']['result']);
        $this->assertSame(4.0, $rows['1']['gpa']);
        $this->assertSame('INCOMPLETE', $rows['3']['result']);
        $this->assertSame(0.0, $rows['3']['gpa']);
    }
}
