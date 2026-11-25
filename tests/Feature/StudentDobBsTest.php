<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\SchoolClass;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stage P3H: the student's Bikram Sambat (B.S.) date of birth.
 *
 * - Stored as a plain string (e.g. "2069-01-15"), never parsed as a
 *   Gregorian date; fully independent of the A.D. `dob`.
 * - Validation is format/range only: there is NO verified Nepali-calendar
 *   dependency in composer.json, so no day-per-month chronology is claimed
 *   (documented limitation, see the stage P3H report).
 * - Marksheet prints the stored B.S. value, or an explicit unavailable
 *   state - never a converted or guessed date.
 * Isolated fixture data only - no real students or personal data.
 */
class StudentDobBsTest extends TestCase
{
    use RefreshDatabase;

    private const AD_DOB = '2012-04-24';

    private const BS_DOB = '2069-01-15';

    private User $teacher;

    private SchoolClass $class;

    private Exam $exam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::factory()->create();
        UserRole::create(['user_id' => $this->teacher->id, 'role' => 'teacher']);

        $this->class = SchoolClass::create(['name' => 'Class 8-BS', 'section' => 'A']);
        $this->exam = Exam::create([
            'name' => 'BS Terminal Exam', 'type' => 'first_terminal',
            'academic_year' => '2083-2084', 'start_date' => '2083-03-01',
            'status' => 'published', 'published_at' => now(),
        ]);
    }

    private function act(User $user)
    {
        return $this->actingAs($user);
    }

    private function createStudent(): Student
    {
        return Student::create([
            'adm_number' => 'BS-001', 'first_name' => 'Fixture', 'last_name' => 'Taker',
            'gender' => 'Male', 'dob' => self::AD_DOB, 'dob_bs' => self::BS_DOB,
            'class_applied' => 'Class 8-BS A', 'school_class_id' => $this->class->id,
            'roll_no' => 1, 'academic_year' => '2083-2084',
        ]);
    }

    private function renderIdeal(Exam $exam, Student $student)
    {
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role' => 'principal']);

        return $this->act($user)
            ->get(route('admin.results.marksheet', [$exam, $student]));
    }

    // ------------------------------------------------------------------
    // Independent storage
    // ------------------------------------------------------------------

    public function test_creating_a_student_stores_both_dates_independently(): void
    {
        $this->act($this->teacher)
            ->post(route('admin.students.store'), [
                'first_name' => 'Fixture', 'last_name' => 'Entry', 'gender' => 'Female',
                'dob' => self::AD_DOB, 'dob_bs' => self::BS_DOB,
                'class_applied' => 'Class 8-BS A', 'academic_year' => '2083-2084',
            ])->assertRedirect();

        $student = Student::where('first_name', 'Fixture')->where('last_name', 'Entry')->first();
        $this->assertNotNull($student);
        $this->assertSame(self::AD_DOB, $student->dob);
        $this->assertSame(self::BS_DOB, $student->dob_bs, 'The B.S. date is stored exactly as entered.');
    }

    public function test_creating_without_a_bs_date_leaves_it_unset(): void
    {
        $this->act($this->teacher)
            ->post(route('admin.students.store'), [
                'first_name' => 'Fixture', 'last_name' => 'Plain', 'gender' => 'Male',
                'dob' => self::AD_DOB,
                'class_applied' => 'Class 8-BS A', 'academic_year' => '2083-2084',
            ])->assertRedirect();

        $student = Student::where('last_name', 'Plain')->first();
        $this->assertNotNull($student);
        $this->assertSame(self::AD_DOB, $student->dob);
        $this->assertNull($student->dob_bs, 'A missing B.S. entry stays unset, not zero or derived.');
    }

    public function test_updating_the_ad_date_does_not_overwrite_the_bs_date_and_vice_versa(): void
    {
        $student = $this->createStudent();

        // B.S. field OMITTED from the request: the stored B.S. date must
        // survive; only the A.D. date changes.
        $this->act($this->teacher)
            ->put(route('admin.students.update', $student), [
                'first_name' => 'Fixture', 'last_name' => 'Taker', 'gender' => 'Male',
                'dob' => '2013-06-10',
                'class_applied' => 'Class 8-BS A', 'academic_year' => '2083-2084',
                'status' => 'active',
            ])->assertRedirect();

        $this->assertSame('2013-06-10', $student->fresh()->dob);
        $this->assertSame(self::BS_DOB, $student->fresh()->dob_bs, 'The B.S. date must not be overwritten by an A.D. edit.');

        // Now change only the B.S. date: the A.D. date stays untouched.
        $this->act($this->teacher)
            ->put(route('admin.students.update', $student), [
                'first_name' => 'Fixture', 'last_name' => 'Taker', 'gender' => 'Male',
                'dob' => '2013-06-10', 'dob_bs' => '2070-02-15',
                'class_applied' => 'Class 8-BS A', 'academic_year' => '2083-2084',
                'status' => 'active',
            ])->assertRedirect();

        $this->assertSame('2013-06-10', $student->fresh()->dob, 'The A.D. date must not be overwritten by a B.S. edit.');
        $this->assertSame('2070-02-15', $student->fresh()->dob_bs);
    }

    public function test_a_blanked_bs_field_clears_it_without_touching_the_ad_date(): void
    {
        $student = $this->createStudent();

        $this->act($this->teacher)
            ->put(route('admin.students.update', $student), [
                'first_name' => 'Fixture', 'last_name' => 'Taker', 'gender' => 'Male',
                'dob' => self::AD_DOB, 'dob_bs' => '',
                'class_applied' => 'Class 8-BS A', 'academic_year' => '2083-2084',
                'status' => 'active',
            ])->assertRedirect();

        $this->assertSame(self::AD_DOB, $student->fresh()->dob);
        $this->assertNull($student->fresh()->dob_bs, 'An emptied B.S. field clears only the B.S. date.');
    }

    // ------------------------------------------------------------------
    // Validation
    // ------------------------------------------------------------------

    public function test_invalid_bs_dates_are_rejected_and_overwrite_nothing(): void
    {
        $student = $this->createStudent();

        foreach ([
            '2069-13-01',  // month 13 does not exist
            '2069-01-33',  // day 33 (B.S. months top out at 32)
            'not-a-date',  // wrong format entirely
            '69-01-15',    // year not four digits
            '2101-01-15',  // outside the configurable year ceiling
            '2083-01-15',  // B.S. year is not 56/57 after the A.D. birth year
        ] as $bad) {
            $this->act($this->teacher)
                ->put(route('admin.students.update', $student), [
                    'first_name' => 'Fixture', 'last_name' => 'Taker', 'gender' => 'Male',
                    'dob' => self::AD_DOB, 'dob_bs' => $bad,
                    'class_applied' => 'Class 8-BS A', 'academic_year' => '2083-2084',
                    'status' => 'active',
                ])->assertSessionHasErrors(['dob_bs']);

            $this->assertSame(self::AD_DOB, $student->fresh()->dob, "Rejected input '$bad' must not have overwritten the A.D. date.");
            $this->assertSame(self::BS_DOB, $student->fresh()->dob_bs, "Rejected input '$bad' must not have been stored.");
        }
    }

    public function test_a_bs_year_56_after_the_ad_year_is_accepted(): void
    {
        // A.D. Jan 2012 births fall in B.S. 2068 (56-year gap). Both gaps
        // (56/57) are accepted - wide margins only, no chronology claims.
        $student = $this->createStudent();

        $this->act($this->teacher)
            ->put(route('admin.students.update', $student), [
                'first_name' => 'Fixture', 'last_name' => 'Taker', 'gender' => 'Male',
                'dob' => self::AD_DOB, 'dob_bs' => '2068-01-15',
                'class_applied' => 'Class 8-BS A', 'academic_year' => '2083-2084',
                'status' => 'active',
            ])->assertRedirect();

        $this->assertSame('2068-01-15', $student->fresh()->dob_bs);
    }

    public function test_a_bs_date_with_day_32_is_accepted_without_chronology_claims(): void
    {
        // Some B.S. months legitimately have 32 days; with no calendar
        // dependency the system only checks format and range, so 32 passes.
        $student = $this->createStudent();

        $this->act($this->teacher)
            ->put(route('admin.students.update', $student), [
                'first_name' => 'Fixture', 'last_name' => 'Taker', 'gender' => 'Male',
                'dob' => self::AD_DOB, 'dob_bs' => '2069-01-32',
                'class_applied' => 'Class 8-BS A', 'academic_year' => '2083-2084',
                'status' => 'active',
            ])->assertRedirect();

        $this->assertSame('2069-01-32', $student->fresh()->dob_bs);
    }

    // ------------------------------------------------------------------
    // Authorization
    // ------------------------------------------------------------------

    public function test_a_user_without_a_staff_role_cannot_create_students(): void
    {
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role' => 'parent']);

        $before = Student::count();

        $this->act($user)
            ->post(route('admin.students.store'), [
                'first_name' => 'Fixture', 'last_name' => 'Nope', 'gender' => 'Male',
                'dob' => self::AD_DOB, 'dob_bs' => self::BS_DOB,
                'class_applied' => 'Class 8-BS A',
            ])->assertForbidden();

        $this->assertSame($before, Student::count(), 'Forbidden users must not create records.');
    }

    // ------------------------------------------------------------------
    // Admission workflow compatibility
    // ------------------------------------------------------------------

    public function test_admission_registration_keeps_working_without_bs_input(): void
    {
        $coordinator = User::factory()->create();
        UserRole::create(['user_id' => $coordinator->id, 'role' => 'academic_coordinator']); // admissions.manage

        $this->act($coordinator)
            ->post(route('admin.admissions.store'), [
                'first_name' => 'Fixture', 'last_name' => 'Applier', 'gender' => 'Female',
                'dob' => self::AD_DOB,
                'class_applied' => 'Class 8-BS A', 'academic_year' => '2083-2084',
                'parent_name' => 'Fixture Guardian', 'mobile' => '9841000000',
            ])->assertRedirect();

        $this->assertDatabaseHas('admissions', [
            'first_name' => 'Fixture', 'last_name' => 'Applier', 'dob' => self::AD_DOB,
            'status' => 'registered',
        ]);
    }

    // ------------------------------------------------------------------
    // Marksheet rendering
    // ------------------------------------------------------------------

    private function enableBsFlagOnMarksheet(): void
    {
        Setting::set('marksheet_layout', json_encode([
            'identity' => ['dob_bs' => 1],
            'show_height_weight' => 0, // keep this test's HTML assertions scoped to the identity line
        ]));
    }

    public function test_marksheet_prints_the_stored_bs_date_next_to_the_ad_date(): void
    {
        $student = $this->createStudent();
        $this->enableBsFlagOnMarksheet();

        $html = $this->renderIdeal($this->exam, $student)->assertOk()->getContent();

        $this->assertStringContainsString('DOB (A.D.):', $html);
        $this->assertStringContainsString(self::AD_DOB, $html, 'The A.D. date still renders in its own slot.');
        $this->assertStringContainsString('DOB (B.S.):', $html);
        $this->assertStringContainsString(self::BS_DOB, $html, 'The stored B.S. date renders exactly as recorded.');
        $this->assertStringNotContainsString('(not recorded)', $html);
        $this->assertStringNotContainsString('conversion is not supported', $html, 'The old placeholder note is gone.');
    }

    public function test_marksheet_shows_an_honest_unavailable_state_without_a_bs_date(): void
    {
        $student = $this->createStudent();
        $student->update(['dob_bs' => null]);
        $this->enableBsFlagOnMarksheet();

        $html = $this->renderIdeal($this->exam, $student)->assertOk()->getContent();

        $this->assertStringContainsString('DOB (B.S.):', $html);
        $this->assertStringContainsString('(not recorded)', $html);
        $this->assertStringNotContainsString(self::BS_DOB, $html);
        // Never fabricate a date: the stored A.D. DOB must not be recycled
        // into the B.S. slot in raw or converted form.
        $this->assertStringNotContainsString('2069', $html);
        $this->assertStringNotContainsString('conversion is not supported', $html);
    }

    public function test_marksheet_never_guesses_a_bs_date_from_the_ad_date(): void
    {
        $student = $this->createStudent();
        $student->update(['dob_bs' => null]);
        $this->enableBsFlagOnMarksheet();

        $html = $this->renderIdeal($this->exam, $student)->assertOk()->getContent();

        // With no stored B.S. value (and bs flag left off in the layout), the
        // A.D. DOB is the only date on the sheet.
        Setting::set('marksheet_layout', json_encode(['show_height_weight' => 0, 'identity' => ['dob_bs' => 0]]));
        $htmlNoFlag = $this->renderIdeal($this->exam, $student)->assertOk()->getContent();

        $this->assertStringNotContainsString('DOB (B.S.):', $htmlNoFlag);
        $this->assertStringContainsString(self::AD_DOB, $htmlNoFlag);
    }
}
