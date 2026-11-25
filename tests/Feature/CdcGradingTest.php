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
use App\Services\GradeScale;
use App\Services\ResultService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CDC Letter Grading Directive, 2083 (लेटर ग्रेडिङ निर्देशिका, २०८३),
 * verified against the official document:
 *
 *  - Annexure-1 (dafa 5(1)) grade bands, including NG (<35%) with NO grade
 *    point (the official sheet prints "-").
 *  - A subject below 35% is NG even when its theory/internal components
 *    individually pass (Annexure-1 is aggregate-based).
 *  - Weighted GPA = Σ(CH × GP) / ΣCH per the owner's marksheet direction
 *    (the directive defines "credit hour" as 32 teaching hours in
 *    dafa 1(घ) but leaves the aggregation formula to school practice) -
 *    applied only when EVERY subject records valid credit hours; otherwise
 *    the unweighted mean is shown, honestly labelled.
 *
 * Isolated fixtures only - no real student records, no personal data.
 */
class CdcGradingTest extends TestCase
{
    use RefreshDatabase;

    /** Boundary matrix straight from Annexure-1 (all 12 rows implemented). */
    public function test_annexure_1_band_boundaries(): void
    {
        $matrix = [
            [90, 'A+', 4.0],
            [89.99, 'A', 3.6],
            [80, 'A', 3.6],
            [79.99, 'B+', 3.2],
            [70, 'B+', 3.2],
            [69.99, 'B', 2.8],
            [60, 'B', 2.8],
            [59.99, 'C+', 2.4],
            [50, 'C+', 2.4],
            [49.99, 'C', 2.0],
            [40, 'C', 2.0],
            [39.99, 'D', 1.6],
            [35, 'D', 1.6],
            [34.99, 'NG', null],
            [0, 'NG', null],
            [100, 'A+', 4.0],
        ];

        foreach ($matrix as [$percent, $grade, $point]) {
            $g = GradeScale::forPercent($percent);
            $this->assertSame($grade, $g['grade'], "grade at {$percent}%");
            $this->assertSame($point, $g['point'], "grade point at {$percent}%");
        }
    }

    /** The official NG row shows "-" as the grade point, never a number. */
    public function test_ng_carries_no_grade_point(): void
    {
        $g = GradeScale::forPercent(34.9);
        $this->assertSame('NG', $g['grade']);
        $this->assertNull($g['point']);
    }

    /** Union result: 30/100 aggregate with individually passing components. */
    public function test_subject_below_35_percent_is_ng_despite_component_passes(): void
    {
        [$class, $exam, $student, $subjects] = $this->fixture(
            subjects: [['theory' => 100, 'pass' => 24, 'credit_hours' => 4]],
        );
        $this->mark($subjects[0], $student, 30); // 30% total, theory_pass 24 met

        $es = $subjects[0]->fresh();
        $r = (new ResultService)->subjectResult($es, $this->markRow($es, $student));

        $this->assertFalse($r['pass'], 'Aggregate <35% cannot pass, per Annexure-1.');
        $this->assertSame('NG', $r['grade']);
        $this->assertNull($r['point']);
        $this->assertEqualsWithDelta(30.0, $r['percent'], 0.001);
    }

    /** The owner-directed formula: GPA = Σ(CH × GP) / ΣCH. */
    public function test_weighted_gpa_matches_independent_calculation(): void
    {
        // Independent hand computation, done separately from the service:
        // CH 4/3/2 with points 4.0/3.6/3.2 => (16 + 10.8 + 6.4) / 9 = 3.69
        [$class, $exam, $student, $subjects] = $this->fixture(
            subjects: [
                ['theory' => 100, 'pass' => 35, 'credit_hours' => 4],
                ['theory' => 100, 'pass' => 35, 'credit_hours' => 3],
                ['theory' => 100, 'pass' => 35, 'credit_hours' => 2],
            ],
        );
        $this->mark($subjects[0], $student, 92); // A+ 4.0
        $this->mark($subjects[1], $student, 84); // A  3.6
        $this->mark($subjects[2], $student, 74); // B+ 3.2

        $data = (new ResultService)->classResults($exam, $class);
        $row = $data['rows'][0];

        $this->assertSame('credit_hour', $row['gpa_basis']);
        $this->assertEqualsWithDelta(3.69, $row['gpa'], 0.005);
        $this->assertSame('PASS', $row['result']);
    }

    /**
     * Hand-computed rounding check: (4×4.0 + 3×3.6) / 7 = 26.8/7
     * = 3.82857… → 3.83 at two decimals.
     */
    public function test_gpa_is_rounded_to_two_decimals_on_the_credit_hour_basis(): void
    {
        // Independent hand computation: (4×4.0 + 3×3.6) / 7 = 26.8/7 = 3.83
        [$class, $exam, $student, $subjects] = $this->fixture(
            subjects: [
                ['theory' => 100, 'pass' => 35, 'credit_hours' => 4],
                ['theory' => 100, 'pass' => 35, 'credit_hours' => 3],
            ],
        );
        $this->mark($subjects[0], $student, 92); // 4.0
        $this->mark($subjects[1], $student, 84); // 3.6

        $row = (new ResultService)->classResults($exam, $class)['rows'][0];

        $this->assertSame(3.83, $row['gpa']);
    }

    /** NG (point null) adds nothing to the numerator; its CH stays in the ΣCH. */
    public function test_ng_contributes_zero_to_numerator_but_full_weight_to_denominator(): void
    {
        // CH 4 (92% => 4.0) + CH 3 (20% => NG, no point): 16/7 = 2.2857 => 2.29
        [$class, $exam, $student, $subjects] = $this->fixture(
            subjects: [
                ['theory' => 100, 'pass' => 35, 'credit_hours' => 4],
                ['theory' => 100, 'pass' => 35, 'credit_hours' => 3],
            ],
        );
        $this->mark($subjects[0], $student, 92);
        $this->mark($subjects[1], $student, 20); // NG

        $data = (new ResultService)->classResults($exam, $class);
        $row = $data['rows'][0];

        $this->assertSame('credit_hour', $row['gpa_basis']);
        $this->assertSame(2.29, $row['gpa']);
        $this->assertSame('FAIL', $row['result']); // NG blocks overall PASS
    }

    /** AB contributes nothing to the numerator; its CH stays counted. */
    public function test_absent_contributes_zero_but_its_credit_hours_count(): void
    {
        // CH 4 (92% => 4.0) + CH 3 absent: 16/7 = 2.29
        [$class, $exam, $student, $subjects] = $this->fixture(
            subjects: [
                ['theory' => 100, 'pass' => 35, 'credit_hours' => 4],
                ['theory' => 100, 'pass' => 35, 'credit_hours' => 3],
            ],
        );
        $this->mark($subjects[0], $student, 92);
        ExamMark::create([
            'exam_subject_id' => $subjects[1]->id,
            'student_id' => $student->id,
            'is_absent' => true,
        ]);

        $row = (new ResultService)->classResults($exam, $class)['rows'][0];

        $this->assertSame(2.29, $row['gpa']);
        $this->assertSame('FAIL', $row['result']);
    }

    /** Incomplete credit-hour coverage => unweighted mean, honestly labelled. */
    public function test_partial_credit_hours_fall_back_to_unweighted_gpa(): void
    {
        [$class, $exam, $student, $subjects] = $this->fixture(
            subjects: [
                ['theory' => 100, 'pass' => 35, 'credit_hours' => 4],
                ['theory' => 100, 'pass' => 35, 'credit_hours' => null],
            ],
        );
        $this->mark($subjects[0], $student, 92); // 4.0
        $this->mark($subjects[1], $student, 84); // 3.6

        $row = (new ResultService)->classResults($exam, $class)['rows'][0];

        $this->assertSame('unweighted', $row['gpa_basis']);
        $this->assertEqualsWithDelta(3.8, $row['gpa'], 0.001, '(4.0 + 3.6) / 2');
    }

    /** Zero / negative credit hours are not authoritative data. */
    public function test_invalid_credit_hours_are_treated_as_missing(): void
    {
        [$class, $exam, $student, $subjects] = $this->fixture(
            subjects: [
                ['theory' => 100, 'pass' => 35, 'credit_hours' => 0],
                ['theory' => 100, 'pass' => 35, 'credit_hours' => null],
            ],
        );
        $this->mark($subjects[0], $student, 92);
        $this->mark($subjects[1], $student, 84);

        $row = (new ResultService)->classResults($exam, $class)['rows'][0];

        $this->assertSame('unweighted', $row['gpa_basis']);
        $this->assertEqualsWithDelta(3.8, $row['gpa'], 0.001);
    }

    /** Result page values and the marksheet PDF/HTML cannot disagree. */
    public function test_marksheet_renders_the_weighted_gpa_computed_by_the_service(): void
    {
        [$class, $exam, $student, $subjects] = $this->fixture(
            subjects: [
                ['theory' => 100, 'pass' => 35, 'credit_hours' => 4],
                ['theory' => 100, 'pass' => 35, 'credit_hours' => 3],
            ],
        );
        $this->mark($subjects[0], $student, 92);
        $this->mark($subjects[1], $student, 84);

        $row = (new ResultService)->classResults($exam, $class)['rows'][0];

        $this->actingAs($this->userWithRole('principal'))
            ->get(route('admin.results.marksheet', [$exam, $student]))
            ->assertOk()
            // Weighted basis: no "(unweighted)" suffix, exact service value.
            ->assertSee('GPA: 3.83')
            ->assertSee(number_format($row['percent'], 2).'%');
    }

    public function test_marksheet_labels_the_unweighted_fallback(): void
    {
        [$class, $exam, $student, $subjects] = $this->fixture(
            subjects: [
                ['theory' => 100, 'pass' => 35, 'credit_hours' => null],
            ],
        );
        $this->mark($subjects[0], $student, 92);

        $this->actingAs($this->userWithRole('principal'))
            ->get(route('admin.results.marksheet', [$exam, $student]))
            ->assertOk()
            ->assertSee('GPA (unweighted): 4.00');
    }

    /** The grading legend mirrors Annexure-1: NG prints "-" for its point. */
    public function test_legend_shows_dash_for_the_ng_grade_point(): void
    {
        [$class, $exam, $student, $subjects] = $this->fixture(
            subjects: [['theory' => 100, 'pass' => 35, 'credit_hours' => null]],
        );
        $this->mark($subjects[0], $student, 92);
        Setting::set('marksheet_layout', json_encode(['show_legend' => true]));

        $html = $this->actingAs($this->userWithRole('principal'))
            ->get(route('admin.results.marksheet', [$exam, $student]))
            ->assertOk()
            ->getContent();

        // The legend NG row, in order, without any numeric grade point.
        $this->assertStringContainsString('<td>Below 35%</td>', $html);
        $this->assertMatchesRegularExpression(
            '/<td>Below 35%<\/td>\s*<td>NG<\/td>\s*<td>-<\/td>/',
            $html,
        );
    }

    /** The marks table shows "-" (not 0.0) for an entered-but-NG subject. */
    public function test_entered_ng_subject_prints_dash_for_grade_point(): void
    {
        [$class, $exam, $student, $subjects] = $this->fixture(
            subjects: [['theory' => 100, 'pass' => 35, 'credit_hours' => null]],
        );
        $this->mark($subjects[0], $student, 20); // entered, NG

        $this->actingAs($this->userWithRole('principal'))
            ->get(route('admin.results.marksheet', [$exam, $student]))
            ->assertOk()
            ->assertSeeInOrder(['Grade Point (GP)', '-', 'NG'], false);
    }

    // ------------------------------------------------------------------
    // Fixture helpers (values modelled on the marksheet reference, with no
    // real names, numbers or any personal data whatsoever).
    // ------------------------------------------------------------------

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    /**
     * @param array<int, array{theory: int, pass: int, credit_hours: ?float|array}> $specs
     * @return array{0: SchoolClass, 1: Exam, 2: Student, 3: array<ExamSubject>}
     */
    private function fixture(array $subjects): array
    {
        $class = SchoolClass::create(['name' => 'Class 10-CDC', 'section' => 'A']);
        $exam = Exam::create([
            'name' => 'CDC Fixture Exam',
            'type' => 'first_terminal',
            'academic_year' => '2083-2084',
            'start_date' => '2083-03-01',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $student = Student::create([
            'adm_number' => 'CDC-001',
            'first_name' => 'Sample',
            'last_name' => 'Learner',
            'gender' => 'Male',
            'dob' => '2011-05-05',
            'class_applied' => 'Class 10-CDC A',
            'school_class_id' => $class->id,
            'roll_no' => 1,
        ]);

        $made = [];
        foreach ($subjects as $i => $spec) {
            $subject = Subject::create([
                'name' => 'Fixture Subject '.($i + 1),
                'code' => 'CDC'.($i + 1),
            ]);
            $made[] = ExamSubject::create([
                'exam_id' => $exam->id,
                'school_class_id' => $class->id,
                'subject_id' => $subject->id,
                'theory_full' => $spec['theory'],
                'theory_pass' => $spec['pass'],
                'practical_full' => 0,
                'practical_pass' => 0,
                'marks_status' => 'locked',
                'credit_hours' => $spec['credit_hours'],
            ]);
        }

        return [$class, $exam, $student, $made];
    }

    private function mark(ExamSubject $es, Student $student, int $theory): void
    {
        ExamMark::create([
            'exam_subject_id' => $es->id,
            'student_id' => $student->id,
            'theory_marks' => $theory,
            'practical_marks' => null,
            'is_absent' => false,
        ]);
    }

    private function markRow(ExamSubject $es, Student $student): ExamMark
    {
        return ExamMark::where('exam_subject_id', $es->id)
            ->where('student_id', $student->id)
            ->firstOrFail();
    }
}
