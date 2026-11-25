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
 * Marksheet rendering and layout-editor authorization, on isolated fixture
 * data only (no real school records, no personal data from the reference).
 */
class MarksheetRenderTest extends TestCase
{
    use RefreshDatabase;

    private Exam $exam;

    private SchoolClass $class;

    private Student $student;

    private ExamSubject $examSubject;

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    /** One class, one student, one exam subject with entered marks. */
    private function fixture(): void
    {
        $this->class = SchoolClass::create(['name' => 'Class 9-Test', 'section' => 'A']);
        $subject = Subject::create(['name' => 'Fixture Subject', 'code' => 'FIX9']);
        $this->exam = Exam::create([
            'name' => 'Fixture Terminal Exam',
            'type' => 'first_terminal',
            'academic_year' => '2083-2084',
            'start_date' => '2083-03-01',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $this->student = Student::create([
            'adm_number' => 'FT-001',
            'first_name' => 'Sample',
            'last_name' => 'Learner',
            'gender' => 'Male',
            'dob' => '2011-01-01',
            'class_applied' => 'Class 9-Test A',
            'school_class_id' => $this->class->id,
            'roll_no' => 1,
        ]);
        $this->examSubject = ExamSubject::create([
            'exam_id' => $this->exam->id,
            'school_class_id' => $this->class->id,
            'subject_id' => $subject->id,
            'theory_full' => 80,
            'theory_pass' => 28,
            'practical_full' => 20,
            'practical_pass' => 7,
            'marks_status' => 'locked',
        ]);
        ExamMark::create([
            'exam_subject_id' => $this->examSubject->id,
            'student_id' => $this->student->id,
            'theory_marks' => 72,
            'practical_marks' => 16,
            'is_absent' => false,
        ]);
    }

    private function renderMarksheet(): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->userWithRole('principal'))
            ->get(route('admin.results.marksheet', [$this->exam, $this->student]));
    }

    public function test_guest_cannot_open_marksheet_or_save_layout(): void
    {
        $this->get(route('admin.results.marksheets', [1, 1]))->assertRedirect();
        $this->put(route('admin.settings.marksheet.update'), [])->assertRedirect();
    }

    public function test_role_less_user_is_denied_marksheet_and_layout_saving(): void
    {
        $this->fixture();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.results.marksheet', [$this->exam, $this->student]))
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('admin.settings.marksheet.update'), ['sections_order' => ''])
            ->assertForbidden();
    }

    public function test_marksheet_renders_identity_and_result_from_fixture_data(): void
    {
        $this->fixture();

        $this->renderMarksheet()
            ->assertOk()
            ->assertSee('Sample Learner')
            ->assertSee('Fixture Terminal Exam')
            ->assertSee('Fixture Subject')
            ->assertSee('FIX9')
            ->assertSee('2011-01-01')
            // 88/100 => A+ per the untouched GradeScale bands.
            ->assertSee('A+')
            ->assertSee('PASS')
            ->assertSee('Date of Issue');
    }

    public function test_column_toggle_hides_and_restores_the_credit_hour_column(): void
    {
        $this->fixture();

        Setting::set('marksheet_layout', json_encode([
            'columns' => ['sn' => true, 'subject_code' => true, 'credit_hour' => false,
                'grade_point' => true, 'grade' => true, 'final_grade' => true, 'remarks' => true],
        ]));

        $this->renderMarksheet()
            ->assertOk()
            ->assertSee('Fixture Subject')
            ->assertDontSee('Credit Hour (CH)');

        Setting::set('marksheet_layout', json_encode([
            'columns' => ['credit_hour' => true],
            'credit_hours' => ['FIX9' => 4],
        ]));

        $this->renderMarksheet()
            ->assertOk()
            ->assertSee('Credit Hour (CH)')
            ->assertSeeInOrder(['Credit Hour (CH)', '4'], false);
    }

    public function test_component_rows_render_only_when_enabled(): void
    {
        $this->fixture();

        $this->renderMarksheet()->assertOk()->assertDontSee('>TH<');

        Setting::set('marksheet_layout', json_encode(['show_component_marks' => true]));

        $html = $this->renderMarksheet()
            ->assertOk()
            // Separate TH/IN rows straight from the fixture marks
            // (72/80 theory = 90% A+ 4.0, 16/20 internal = 80% A 3.6).
            ->assertSee('TH/IN')
            ->assertSee('<td rowspan="2">', false)
            ->getContent();

        $this->assertStringContainsString('Full Marks', $html);
        $this->assertStringContainsString('Pass Marks', $html);
        $this->assertStringContainsString('Marks Obtained', $html);
        $this->assertStringContainsString('A+', $html); // final grade merged
        $this->assertStringContainsString('PASS', $html);
    }

    public function test_reordering_sections_moves_gpa_before_identity(): void
    {
        $this->fixture();
        Setting::set('marksheet_layout', json_encode([
            'order' => ['header', 'gpa_result', 'identity', 'subjects'],
        ]));

        $html = $this->renderMarksheet()->assertOk()->getContent();

        $this->assertLessThan(
            strpos($html, 'Examination:'),
            strpos($html, 'GPA (unweighted):') ?: strpos($html, 'GPA ('),
            'GPA block (labelled by basis) should now render before the identity line.',
        );
        // Result integrity content survives reordering:
        $this->assertStringContainsString('PASS', $html);
        $this->assertStringContainsString('Sample Learner', $html);
    }

    public function test_absent_and_ungraded_marks_render_honestly(): void
    {
        $this->fixture();
        // Replace the passing mark with an absent record - nothing invented.
        ExamMark::where('exam_subject_id', $this->examSubject->id)
            ->where('student_id', $this->student->id)
            ->delete();
        ExamMark::create([
            'exam_subject_id' => $this->examSubject->id,
            'student_id' => $this->student->id,
            'is_absent' => true,
        ]);

        $this->renderMarksheet()
            ->assertOk()
            ->assertSee('AB')
            ->assertSee('Absent')
            ->assertSee('FAIL');
    }

    public function test_layout_editor_page_renders_for_permitted_user(): void
    {
        $this->actingAs($this->userWithRole('principal'))
            ->get(route('admin.settings.marksheet'))
            ->assertOk()
            ->assertSee('Marksheet layout')
            ->assertSee('Student information');
    }

    public function test_layout_editor_page_denied_without_view_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.settings.marksheet'))
            ->assertForbidden();
    }

    public function test_layout_saving_persists_and_reports_success(): void
    {
        $this->actingAs($this->userWithRole('principal'))
            ->from(route('admin.settings.marksheet'))
            ->put(route('admin.settings.marksheet.update'), [
                'sections_order' => "Certification knot\nGPA & result summary\nPrint header & branding\nStudent information\nSubject & result table\nAssessment indicators\nAttendance / height & weight\nNotes\nGrading legend table\nDate of issue\nSignature blocks",
                'toggles' => [
                    'header_show_logo' => '1',
                    'header_show_photo' => '1',
                    'show_watermark' => '1',
                    'show_legend' => '1',
                    'show_signatures' => '1',
                ],
                'watermark_text' => 'Fiscal Note',
                'credit_hours' => 'FIX9=4',
                'notes' => "Line one\nLine two",
                'signatures' => "Prepared By\nChecked By\nController of Examination",
            ])
            ->assertRedirect(route('admin.settings.marksheet'))
            ->assertSessionHas('success');

        $layout = \App\Services\MarksheetLayout::load();
        $this->assertTrue($layout->showWatermark);
        $this->assertSame('Fiscal Note', $layout->watermarkText);
        $this->assertSame(['FIX9' => '4'], $layout->creditHours);
        $this->assertSame(['Line one', 'Line two'], $layout->notes);
        // Unknown section names ("Certification knot") silently ignored.
        $this->assertSame('gpa_result', $layout->order[0]);
        $this->assertContains('subjects', $layout->order);

        // Watermark now appears on the sheet.
        $this->fixture();
        $this->renderMarksheet()->assertOk()->assertSee('Fiscal Note');
    }
}
