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
use App\Services\ResultService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stage P3J: separate TH/IN component rows on the marksheet.
 *
 * - Component grade/point derive ONLY from the component's own
 *   obtained/full percent through GradeScale::forPercent (the same CDC
 *   bands as everywhere) - no new academic math.
 * - They are display details: the aggregate subject grade, pass/fail and
 *   GPA are byte-identical with component rows on or off.
 * - Missing marks stay unavailable (—), absent students show AB per
 *   component; nothing is fabricated.
 * Isolated fixture data only - no real students or personal data.
 */
class MarksheetComponentRowsTest extends TestCase
{
    use RefreshDatabase;

    private Exam $exam;

    private SchoolClass $class;

    private Student $student;

    private ExamSubject $examSubject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->class = SchoolClass::create(['name' => 'Class 5-CR', 'section' => 'A']);
        $subject = Subject::create(['name' => 'Fixture Subject', 'code' => 'CR5']);
        $this->exam = Exam::create([
            'name' => 'CR Terminal Exam', 'type' => 'first_terminal',
            'academic_year' => '2083-2084', 'start_date' => '2083-03-01',
            'status' => 'published', 'published_at' => now(),
        ]);
        $this->student = Student::create([
            'adm_number' => 'CR-001', 'first_name' => 'Sample', 'last_name' => 'Learner',
            'gender' => 'Male', 'dob' => '2012-03-03',
            'class_applied' => 'Class 5-CR A', 'school_class_id' => $this->class->id,
            'roll_no' => 1,
        ]);
        $this->examSubject = ExamSubject::create([
            'exam_id' => $this->exam->id, 'school_class_id' => $this->class->id,
            'subject_id' => $subject->id, 'theory_full' => 80, 'theory_pass' => 26,
            'practical_full' => 20, 'practical_pass' => 7, 'marks_status' => 'locked',
        ]);
    }

    private function setMark(?float $theory = null, ?float $practical = null, bool $absent = false): void
    {
        ExamMark::updateOrCreate(
            ['exam_subject_id' => $this->examSubject->id, 'student_id' => $this->student->id],
            ['theory_marks' => $theory, 'practical_marks' => ($absent ? null : $practical), 'is_absent' => $absent],
        );
    }

    private function render(bool $componentRows = true, bool $setSetting = true)
    {
        if ($setSetting) {
            Setting::set('marksheet_layout', json_encode($componentRows
                ? ['show_component_marks' => 1]
                : ['show_component_marks' => 0]));
        }

        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role' => 'principal']); // results.view

        return $this->actingAs($user)
            ->get(route('admin.results.marksheet', [$this->exam, $this->student]));
    }

    public function test_th_and_in_rows_render_separately_with_the_component_scores(): void
    {
        $this->setMark(72, 16); // TH 90% -> A+ 4.0, IN 80% -> A 3.6, total 88/100 -> A+ 4.0

        $html = $this->render()->assertOk()->getContent();

        foreach (['TH/IN', 'Full Marks', 'Pass Marks', 'Marks Obtained'] as $header) {
            $this->assertStringContainsString($header, $html);
        }
        $this->assertSame(1, substr_count($html, 'Fixture Subject'), 'Subject identity is not repeated per component row.');
        $this->assertSame(1, substr_count($html, '<th>Final Grade</th>'));
        // 6 merged cells: SN, Code, Subject, CH, Final Grade, Remarks.
        $this->assertSame(6, substr_count($html, 'rowspan="2"'));
        $this->assertStringContainsString('>TH<', $html);
        $this->assertStringContainsString('>IN<', $html);
        $this->assertMatchesRegularExpression('/>72\s*<\/td>/', $html);
        $this->assertMatchesRegularExpression('/>16\s*<\/td>/', $html);
        $this->assertStringContainsString('A+', $html, 'Aggregate final grade stays the same subject grade.');
    }

    public function test_different_theory_and_internal_marks_produce_independent_component_grades(): void
    {
        $this->setMark(79, 7); // TH 98.75% -> A+ 4.0, IN 35% -> D 1.6, total 86/100 -> A 3.6

        $es = $this->examSubject;
        $r = (new ResultService)->subjectResult($es, ExamMark::where('exam_subject_id', $es->id)->where('student_id', $this->student->id)->first());

        $this->assertSame('A+', $r['components'][0]['grade']);
        $this->assertSame(4.0, $r['components'][0]['point']);
        $this->assertSame('D', $r['components'][1]['grade']);
        $this->assertSame(1.6, $r['components'][1]['point']);
        $this->assertSame('A', $r['grade'], 'The aggregate grade is unchanged and distinct from both components.');

        $html = $this->render()->assertOk()->getContent();
        $this->assertStringContainsString('A+', $html);
        $this->assertMatchesRegularExpression('/>D\s*<\/b>/', $html, "The internal row shows its own D.");
        $this->assertStringContainsString('>1.6<', $html);
        $this->assertStringContainsString('PASS', $html, 'Aggregate classification unchanged (both components pass, 86%).');
    }

    public function test_a_component_below_35_percent_shows_ng_without_a_point(): void
    {
        $this->setMark(71, 5); // TH 88.75% -> A, IN 25% -> NG (no point); IN also below its pass mark

        $es = $this->examSubject;
        $mark = ExamMark::where('exam_subject_id', $es->id)->where('student_id', $this->student->id)->first();
        $r = (new ResultService)->subjectResult($es, $mark);

        $this->assertSame('NG', $r['components'][1]['grade']);
        $this->assertNull($r['components'][1]['point'], "NG carries no grade point, exactly like the scale's subject rows.");

        $html = $this->render()->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/>NG\s*<\/b>/', $html);
        $this->assertStringContainsString('FAIL', $html, 'Aggregate behavior unchanged.');
        $this->assertSame('NG', $r['grade'], 'The subject grade stays NG; only the per-component detail is new.');
    }

    public function test_a_missing_component_mark_is_never_fabricated(): void
    {
        $this->setMark(72, null); // only theory entered

        $es = $this->examSubject;
        $mark = ExamMark::where('exam_subject_id', $es->id)->where('student_id', $this->student->id)->first();
        $r = (new ResultService)->subjectResult($es, $mark);
        $in = $r['components'][1];

        $this->assertNull($in['obtained']);
        $this->assertNull($in['percent']);
        $this->assertSame('—', $in['grade'], 'A missing mark shows the unavailable state, not NG or zero.');
        $this->assertNull($in['point']);

        $html = $this->render()->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/>—\s*<\/b>/', $html, 'The IN row grade cell shows the em dash.');
        $this->assertSame('NG', $r['grade'], 'Legacy partial-entry aggregate behavior is unchanged.');
    }

    public function test_completely_missing_marks_show_the_unavailable_state_on_both_components(): void
    {
        $this->setMark(null, null);

        $r = (new ResultService)->subjectResult(
            $this->examSubject,
            ExamMark::where('exam_subject_id', $this->examSubject->id)->where('student_id', $this->student->id)->first()
        );

        foreach ($r['components'] as $c) {
            $this->assertSame('—', $c['grade']);
            $this->assertNull($c['point']);
        }
        $this->assertSame('—', $r['grade'], 'No marks, no grade - unchanged.');
    }

    public function test_an_absent_student_shows_ab_per_component_without_invented_points(): void
    {
        $this->setMark(null, null, absent: true);

        $es = $this->examSubject;
        $mark = ExamMark::where('exam_subject_id', $es->id)->where('student_id', $this->student->id)->first();
        $r = (new ResultService)->subjectResult($es, $mark);

        foreach ($r['components'] as $c) {
            $this->assertSame('AB', $c['grade']);
            $this->assertNull($c['point']);
            $this->assertSame('Absent', $c['remark']);
        }
        $this->assertSame('AB', $r['grade']);

        $html = $this->render()->assertOk()->getContent();
        // 2 component grade cells + 1 merged final-grade cell (also AB).
        $this->assertSame(3, substr_count($html, '>AB<'), 'Both component rows show AB.');
    }

    public function test_the_gpa_line_is_identical_with_and_without_component_rows(): void
    {
        $this->setMark(72, 16); // 88/100 -> A+ 4.0

        $off = $this->render(false)->assertOk()->getContent();
        $on = $this->render(true)->assertOk()->getContent();

        $gpaOff = $this->segment($off, 'class="gpa-summary"', '/table>');
        $this->assertStringContainsString('GPA (unweighted):', $gpaOff, 'Guard: the GPA summary must exist in the off-mode sheet.');
        $this->assertSame(
            $gpaOff,
            $this->segment($on, 'class="gpa-summary"', '/table>'),
            'Component rows must not move the GPA math or its rendering.'
        );
    }

    private function segment(string $html, string $marker, string $end): string
    {
        $start = strpos($html, $marker);

        return $start === false ? '' : substr($html, $start, (int) (strpos($html, $end, $start) - $start + strlen($end)));
    }

    public function test_a_theory_only_subject_prints_no_in_row(): void
    {
        $subject = Subject::create(['name' => 'Fixture Theory Only', 'code' => 'CR5T']);
        $es = ExamSubject::create([
            'exam_id' => $this->exam->id, 'school_class_id' => $this->class->id,
            'subject_id' => $subject->id, 'theory_full' => 100, 'theory_pass' => 35,
            'practical_full' => 0, 'practical_pass' => 0, 'marks_status' => 'locked',
        ]);
        ExamMark::create([
            'exam_subject_id' => $es->id, 'student_id' => $this->student->id,
            'theory_marks' => 60, 'practical_marks' => null, 'is_absent' => false,
        ]);

        $r = (new ResultService)->subjectResult($es, ExamMark::where('exam_subject_id', $es->id)->first());
        $this->assertCount(1, $r['components'], "Only the offered component gets a row.");
        $this->assertSame('TH', $r['components'][0]['label']);

        $html = $this->render()->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, '<td>IN</td>'), 'Only the two-component subject prints an IN row.');
        $this->assertSame(2, substr_count($html, '<td>TH</td>'), 'Both subjects print a TH row.');
        // Only the two-component subject merges cells: 6 rowspan="2" cells.
        $this->assertSame(6, substr_count($html, 'rowspan="2"'));
    }

    public function test_legacy_settings_without_the_flag_render_the_original_single_rows(): void
    {
        $this->setMark(72, 16);
        // A legacy stored document (no show_component_marks key at all).
        Setting::set('marksheet_layout', json_encode(['columns' => ['remarks' => true]]));

        // setSetting: false keeps the legacy document intact (the default
        // layout loads without the flag, i.e. showComponentMarks=false).
        $html = $this->render(componentRows: true, setSetting: false)->assertOk()->getContent();

        $this->assertStringNotContainsString('TH/IN', $html);
        $this->assertStringNotContainsString('rowspan', $html, 'No component structure without the flag.');
        $this->assertStringContainsString('A+', $html);
        $this->assertStringContainsString('Fixture Subject', $html);
    }
}
