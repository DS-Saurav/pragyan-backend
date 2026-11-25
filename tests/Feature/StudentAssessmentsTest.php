<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\SchoolClass;
use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentAssessment;
use App\Models\Subject;
use App\Models\User;
use App\Models\UserRole;
use App\Services\MarksheetLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stage P3I: assessment-indicator ratings per student and examination.
 *
 * - Fixed scale (machine keys): excellent / good / satisfactory /
 *   needs_improvement; human labels come from config at render time.
 * - STORAGE identity is the layout's STABLE indicator key - renaming a
 *   label can never orphan or misassign a saved rating.
 * - Unset stays unset (never auto-assigned); each exam keeps its own
 *   ratings; published exams are read-only, mirroring marks.
 * Isolated fixture data only - no real students or personal data.
 */
class StudentAssessmentsTest extends TestCase
{
    use RefreshDatabase;

    private Exam $exam2083;

    private Exam $exam2084;

    private SchoolClass $class;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->class = SchoolClass::create(['name' => 'Class 4-AS', 'section' => 'A']);
        $subject = Subject::create(['name' => 'Fixture Subject', 'code' => 'AS4']);

        $this->exam2083 = Exam::create([
            'name' => 'AS Terminal 2083', 'type' => 'first_terminal',
            'academic_year' => '2083-2084', 'start_date' => '2083-03-01',
            'status' => 'draft',
        ]);
        ExamSubject::create([
            'exam_id' => $this->exam2083->id, 'school_class_id' => $this->class->id,
            'subject_id' => $subject->id, 'theory_full' => 100, 'theory_pass' => 35,
            'practical_full' => 0, 'practical_pass' => 0, 'marks_status' => 'locked',
        ]);

        $this->exam2084 = Exam::create([
            'name' => 'AS Terminal 2084', 'type' => 'first_terminal',
            'academic_year' => '2084-2085', 'start_date' => '2084-03-01',
            'status' => 'draft',
        ]);
        ExamSubject::create([
            'exam_id' => $this->exam2084->id, 'school_class_id' => $this->class->id,
            'subject_id' => $subject->id, 'theory_full' => 100, 'theory_pass' => 35,
            'practical_full' => 0, 'practical_pass' => 0, 'marks_status' => 'locked',
        ]);

        $this->student = Student::create([
            'adm_number' => 'AS-001', 'first_name' => 'Fixture', 'last_name' => 'Critic',
            'gender' => 'Male', 'dob' => '2016-01-01',
            'class_applied' => 'Class 4-AS A', 'school_class_id' => $this->class->id,
            'roll_no' => 1, 'academic_year' => '2083-2084',
        ]);

        $this->setLayout(['handwriting' => 'Handwriting', 'sportsmanship' => 'Sportsmanship']);
    }

    private function setLayout(array $keyLabelPairs, array $extra = []): void
    {
        Setting::set('marksheet_layout', json_encode($extra + [
            'assessment_rows' => collect($keyLabelPairs)
                ->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()->all(),
        ]));
    }

    private function staff(): User
    {
        // marks.enter (teacher per config/permissions.php).
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role' => 'teacher']);

        return $user;
    }

    private function saveRatings(Exam $exam, array $ratings)
    {
        return $this->actingAs($this->staff())
            ->put(route('admin.marks.assessments.save', $exam), [
                'class_id' => $this->class->id,
                'ratings' => $ratings,
            ]);
    }

    private function renderMarksheet(Exam $exam)
    {
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role' => 'principal']); // results.view

        return $this->actingAs($user)->get(route('admin.results.marksheet', [$exam, $this->student]));
    }

    // ------------------------------------------------------------------
    // Saving and updating
    // ------------------------------------------------------------------

    public function test_ratings_save_per_student_and_indicator_and_update_cleanly(): void
    {
        $this->saveRatings($this->exam2083, [
            $this->student->id => ['handwriting' => 'good', 'sportsmanship' => ''],
        ])->assertRedirect();

        $this->assertSame(1, StudentAssessment::count());
        $row = StudentAssessment::first();
        $this->assertSame('good', $row->rating);
        $this->assertSame('handwriting', $row->indicator_key, 'The STABLE key is stored, never the display label.');
        $this->assertSame($this->exam2083->id, $row->exam_id);
        $this->assertSame($this->student->id, $row->student_id);
        $this->assertSame(
            null,
            StudentAssessment::where('indicator_key', 'sportsmanship')->first(),
            'A blank cell deletes/never creates a rating: unset stays unset.'
        );

        // Re-save: updates existing rows, adds the new one, never duplicates.
        $this->saveRatings($this->exam2083, [
            $this->student->id => ['handwriting' => 'excellent', 'sportsmanship' => 'satisfactory'],
        ])->assertRedirect();

        $this->assertSame(2, StudentAssessment::count());
        $this->assertSame('excellent', StudentAssessment::where('indicator_key', 'handwriting')->first()->rating);
    }

    public function test_ratings_for_separate_examinations_stay_independent(): void
    {
        $this->saveRatings($this->exam2083, [
            $this->student->id => ['handwriting' => 'good'],
        ])->assertRedirect();
        $this->saveRatings($this->exam2084, [
            $this->student->id => ['handwriting' => 'needs_improvement'],
        ])->assertRedirect();

        $this->assertSame(2, StudentAssessment::count());
        $this->assertSame('good', StudentAssessment::where('exam_id', $this->exam2083->id)->first()->rating);
        $this->assertSame('needs_improvement', StudentAssessment::where('exam_id', $this->exam2084->id)->first()->rating);
    }

    // ------------------------------------------------------------------
    // Validation
    // ------------------------------------------------------------------

    public function test_unsupported_values_unknown_indicators_and_tampered_students_are_rejected(): void
    {
        $this->saveRatings($this->exam2083, [
            $this->student->id => ['handwriting' => 'good'],
        ])->assertRedirect();

        foreach ([
            'the unsupported rating value' => [$this->student->id => ['handwriting' => 'outstanding']],
            'the unknown indicator key' => [$this->student->id => ['mischief_key' => 'good']],
            'the out-of-roster student' => [999 => ['handwriting' => 'good']],
        ] as $what => $ratings) {
            $this->saveRatings($this->exam2083, $ratings)->assertSessionHasErrors();

            $this->assertSame(1, StudentAssessment::count(), "'$what' must have been rejected with nothing stored.");
        }

        $this->assertSame(
            'good',
            StudentAssessment::first()->rating,
            'A failed submission must not alter the stored rating.'
        );
    }

    public function test_the_entry_page_lists_indicators_and_current_ratings(): void
    {
        $this->saveRatings($this->exam2083, [
            $this->student->id => ['handwriting' => 'good'],
        ])->assertRedirect();

        $html = $this->actingAs($this->staff())
            ->get(route('admin.marks.assessments', ['exam_id' => $this->exam2083->id, 'class_id' => $this->class->id]))
            ->assertOk()
            ->getContent();

        // The rating's exam is prominently identified.
        $this->assertStringContainsString('AS Terminal 2083', $html);
        $this->assertStringContainsString('Handwriting', $html);
        $this->assertStringContainsString('Sportsmanship', $html);
        $this->assertStringContainsString("ratings[{$this->student->id}][handwriting]", $html);
        $this->assertStringContainsString('Excellent', $html);
        $this->assertStringContainsString('Needs Improvement', $html);
        $this->assertMatchesRegularExpression(
            '/value="good"\s*selected/',
            $html,
            'The saved rating is preselected on the entry page.'
        );
    }

    // ------------------------------------------------------------------
    // Marksheet rendering
    // ------------------------------------------------------------------

    public function test_marksheet_shows_the_saved_ratings_of_the_exams_own_year(): void
    {
        $this->saveRatings($this->exam2083, [
            $this->student->id => ['handwriting' => 'good', 'sportsmanship' => 'excellent'],
        ])->assertRedirect();
        $this->saveRatings($this->exam2084, [
            $this->student->id => ['handwriting' => 'needs_improvement'],
        ])->assertRedirect();

        $html2083 = $this->renderMarksheet($this->exam2083)->assertOk()->getContent();
        $this->assertStringContainsString('Handwriting', $html2083);
        $this->assertStringContainsString('Good', $html2083);
        $this->assertStringContainsString('Excellent', $html2083);

        // Raw machine values must never leak onto the sheet as-is; and the
        // 2084-only rating must not leak into the 2083 sheet.
        $this->assertStringNotContainsString('needs_improvement', $html2083);
        $this->assertStringNotContainsString('satisfactory', $html2083);

        $html2084 = $this->renderMarksheet($this->exam2084)->assertOk()->getContent();
        $this->assertStringContainsString('Needs Improvement', $html2084);
        $this->assertStringNotContainsString('Good', $html2084, 'The 2083 rating must not appear on the 2084 sheet.');
        $this->assertStringContainsString('Not recorded', $html2084, "2084's missing Sportsmanship prints the honest state.");
    }

    public function test_missing_ratings_print_the_not_recorded_state(): void
    {
        $html = $this->renderMarksheet($this->exam2083)->assertOk()->getContent();

        $this->assertStringContainsString('Handwriting', $html);
        $this->assertStringContainsString('Sportsmanship', $html);
        $this->assertStringContainsString('Not recorded', $html);
        // Never invent a rating: no scale label may appear without data.
        foreach (['Excellent', 'Good', 'Satisfactory', 'Needs Improvement'] as $label) {
            $this->assertStringNotContainsString($label, $html);
        }
    }

    public function test_a_later_exams_rating_never_leaks_into_an_earlier_marksheet(): void
    {
        $this->saveRatings($this->exam2084, [
            $this->student->id => ['handwriting' => 'excellent'],
        ])->assertRedirect();

        $html2083 = $this->renderMarksheet($this->exam2083)->assertOk()->getContent();

        $this->assertStringContainsString('Not recorded', $html2083);
        $this->assertStringNotContainsString('Excellent', $html2083);
    }

    // ------------------------------------------------------------------
    // Stable keys across label renames
    // ------------------------------------------------------------------

    public function test_renaming_an_indicator_label_keeps_historical_ratings_assigned(): void
    {
        $this->saveRatings($this->exam2083, [
            $this->student->id => ['handwriting' => 'good'],
        ])->assertRedirect();

        // Administrator renames the label through the marksheet layout
        // editor; the hidden stable key travels unchanged with the row.
        $editor = User::factory()->create();
        UserRole::create(['user_id' => $editor->id, 'role' => 'principal']); // settings.manage
        $this->actingAs($editor)
            ->put(route('admin.settings.marksheet.update'), [
                'sections_order' => implode("\n", MarksheetLayout::SECTIONS),
                'toggles' => ['show_assessment' => '1'],
                'assessment_labels' => ['Penmanship', 'Sportsmanship'],
                'assessment_keys' => ['handwriting', 'sportsmanship'],
            ])->assertRedirect();

        $rows = MarksheetLayout::load()->assessmentRows;
        $this->assertSame('Penmanship', $rows[0]['label']);
        $this->assertSame('handwriting', $rows[0]['key'], 'A rename must not change the stable key.');

        $html = $this->renderMarksheet($this->exam2083)->assertOk()->getContent();
        $this->assertStringContainsString('Penmanship', $html);
        $this->assertStringContainsString('Good', $html, 'The historical rating stays attached after the rename.');
        $this->assertSame('handwriting', StudentAssessment::first()->indicator_key);
    }

    public function test_a_deleted_indicators_stale_rows_are_not_rendered(): void
    {
        $this->saveRatings($this->exam2083, [
            $this->student->id => ['handwriting' => 'good'],
        ])->assertRedirect();

        // The indicator is removed from the layout: the rating row stays in
        // the database (history) but cannot render as an unknown label.
        $editor = User::factory()->create();
        UserRole::create(['user_id' => $editor->id, 'role' => 'principal']);
        $this->actingAs($editor)
            ->put(route('admin.settings.marksheet.update'), [
                'sections_order' => implode("\n", MarksheetLayout::SECTIONS),
                'toggles' => ['show_assessment' => '1'],
                'assessment_labels' => ['Sportsmanship'],
                'assessment_keys' => ['sportsmanship'],
            ])->assertRedirect();

        $html = $this->renderMarksheet($this->exam2083)->assertOk()->getContent();
        $this->assertStringNotContainsString('Handwriting', $html);
        $this->assertStringContainsString('Sportsmanship', $html);
        $this->assertSame(1, StudentAssessment::count(), 'History rows are preserved, not deleted.');
    }

    // ------------------------------------------------------------------
    // Published-exam integrity + authorization
    // ------------------------------------------------------------------

    public function test_published_exams_reject_assessment_saves(): void
    {
        $this->exam2083->update(['status' => 'published', 'published_at' => now()]);

        $this->saveRatings($this->exam2083, [
            $this->student->id => ['handwriting' => 'good'],
        ])->assertRedirect()->assertSessionHasErrors();

        $this->assertSame(0, StudentAssessment::count(), 'Published results must be read-only, like the marks.');
    }

    public function test_users_without_marks_permissions_cannot_enter_or_save(): void
    {
        foreach ([null, 'parent', 'librarian'] as $forbiddenRole) {
            $user = User::factory()->create();
            if ($forbiddenRole !== null) {
                UserRole::create(['user_id' => $user->id, 'role' => $forbiddenRole]);
            }

            $this->actingAs($user)
                ->put(route('admin.marks.assessments.save', $this->exam2083), [
                    'class_id' => $this->class->id,
                    'ratings' => [$this->student->id => ['handwriting' => 'good']],
                ])->assertForbidden();

            $this->actingAs($user)
                ->get(route('admin.marks.assessments', ['exam_id' => $this->exam2083->id]))
                ->assertForbidden();

            $this->assertSame(0, StudentAssessment::count(), "'".($forbiddenRole ?? 'role-less')."' must not save ratings.");
        }
    }

    public function test_regular_staff_cannot_change_indicator_definitions(): void
    {
        $this->actingAs($this->staff())
            ->put(route('admin.settings.marksheet.update'), [
                'sections_order' => implode("\n", MarksheetLayout::SECTIONS),
                'assessment_labels' => ['Handwriting'],
                'assessment_keys' => ['handwriting'],
            ])->assertForbidden();

        // The principal (settings.manage) can.
        $editor = User::factory()->create();
        UserRole::create(['user_id' => $editor->id, 'role' => 'principal']);
        $this->actingAs($editor)
            ->put(route('admin.settings.marksheet.update'), [
                'sections_order' => implode("\n", MarksheetLayout::SECTIONS),
                'assessment_labels' => ['Handwriting'],
                'assessment_keys' => ['handwriting'],
            ])->assertRedirect();
    }

    // ------------------------------------------------------------------
    // Editor round trip: keys preserved, new rows get stable keys
    // ------------------------------------------------------------------

    public function test_the_layout_editor_preserves_keys_and_derives_new_ones(): void
    {
        $layout = MarksheetLayout::fromForm([
            'assessment_labels' => ['Penmanship', '', 'Teamwork'],
            'assessment_keys' => ['handwriting', '', ''],
            'toggles' => [],
        ]);

        // Only non-blank labels survive, aligned keys intact.
        $this->assertSame(
            [['key' => 'handwriting', 'label' => 'Penmanship'], ['key' => 'teamwork', 'label' => 'Teamwork']],
            $layout->assessmentRows
        );

        // Legacy documents (plain strings) derive deterministic keys.
        Setting::set('marksheet_layout', json_encode(['assessment_rows' => ['Handwriting', 'Teamwork']]));
        $legacy = MarksheetLayout::load();
        $this->assertSame('handwriting', $legacy->assessmentRows[0]['key']);
        $this->assertSame('Handwriting', $legacy->assessmentRows[0]['label']);
        $this->assertSame('teamwork', $legacy->assessmentRows[1]['key']);
    }
}
