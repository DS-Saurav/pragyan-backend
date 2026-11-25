<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeeReceipt;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentMeasurement;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    private const STATUSES = ['active', 'inactive', 'transferred', 'passed_out', 'suspended'];

    public function index(Request $request)
    {
        $status = $request->get('status');

        // Default to current academic year if not explicitly filtered
        $academicYear = $request->get('academic_year', current_academic_year());

        $query = Student::query();

        // Apply Academic Year filter
        if ($academicYear) {
            $query->where('academic_year', $academicYear);
        }

        if ($status && in_array($status, self::STATUSES, true)) {
            $query->where('status', $status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('adm_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('class')) {
            $query->where('class_applied', $request->class);
        }

        if ($request->filled('gender')) {
            $query->where('gender', $request->gender);
        }

        if ($request->filled('house')) {
            $query->where('house', $request->house);
        }

        // GET filter form has no hidden field, so has() is correct here.
        if ($request->has('has_allergies')) {
            $query->where('has_allergies', true);
        }

        $students = $query->latest()->paginate(10)->withQueryString();

        // Scope counts to the selected academic year for precision
        $counts = [
            'all' => Student::where('academic_year', $academicYear)->count(),
            'active' => Student::where('academic_year', $academicYear)->where('status', 'active')->count(),
            'inactive' => Student::where('academic_year', $academicYear)->where('status', 'inactive')->count(),
            'transferred' => Student::where('academic_year', $academicYear)->where('status', 'transferred')->count(),
            'passed_out' => Student::where('academic_year', $academicYear)->where('status', 'passed_out')->count(),
            'suspended' => Student::where('academic_year', $academicYear)->where('status', 'suspended')->count(),
        ];

        // Fetch distinct academic years for the filter dropdown
        $academicYears = Student::distinct()->orderByDesc('academic_year')->pluck('academic_year')->filter()->values()->all();
        if (! in_array(current_academic_year(), $academicYears, true)) {
            array_unshift($academicYears, current_academic_year());
        }

        $classes = SchoolClass::query()
            ->orderBy('name')
            ->orderBy('section')
            ->get()
            ->map(function ($schoolClass) {
                return trim("{$schoolClass->name} {$schoolClass->section}");
            })
            ->filter()
            ->values()
            ->all();

        return view('admin.students.index', compact('students', 'counts', 'status', 'classes', 'academicYear', 'academicYears'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'gender' => 'required|string',
            'dob' => 'required|date',
            // Bikram Sambat DOB (Stage P3H): stored as a plain string and
            // validated independently of `dob` - saving one never overwrites
            // the other (the update below only writes validated keys).
            'dob_bs' => [$this->dobBsRule($request)],
            'class_applied' => 'required|string',
            'academic_year' => 'nullable|string', // <-- Added validation for academic year
            'roll_no' => 'nullable|integer',
            'languages_spoken' => 'nullable|string|max:255',
            'eal' => 'nullable|string',
            'house' => 'nullable|string',
            'admission_date' => 'nullable|date',
            'blood_group' => 'nullable|string|max:5',
            'emergency_contact' => 'nullable|string|max:255',
            'hobbies' => 'nullable|string|max:255',
            'transport_route' => 'nullable|string|max:255',
            'previous_school' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'has_allergies' => 'nullable|boolean',
            'allergy_details' => 'nullable|string',
        ]);

        // Fallback to dynamic academic year if not provided
        $validated['academic_year'] = $request->input('academic_year') ?: current_academic_year();

        $validated['adm_number'] = $this->nextAdmissionNumber();
        // The form sends a hidden has_allergies=0, so has() would always be true.
        $validated['has_allergies'] = $request->boolean('has_allergies');
        $validated['status'] = $validated['status'] ?? 'active';

        Student::create($validated);

        return redirect()->route('admin.students.index')->with('success', 'Student registered successfully!');
    }

    public function update(Request $request, Student $student)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'gender' => 'required|string',
            'dob' => 'required|date',
            // See store(): independent of `dob`; validation failure aborts
            // before update(), so the A.D. date and all other fields keep
            // their stored values untouched.
            'dob_bs' => [$this->dobBsRule($request)],
            'class_applied' => 'required|string',
            'academic_year' => 'nullable|string',
            'roll_no' => 'nullable|integer',
            'languages_spoken' => 'nullable|string|max:255',
            'eal' => 'nullable|string',
            'house' => 'nullable|string',
            'admission_date' => 'nullable|date',
            'blood_group' => 'nullable|string|max:5',
            'emergency_contact' => 'nullable|string|max:255',
            'hobbies' => 'nullable|string|max:255',
            'transport_route' => 'nullable|string|max:255',
            'previous_school' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'status' => ['required', Rule::in(self::STATUSES)],
            'has_allergies' => 'nullable|boolean',
            'allergy_details' => 'nullable|string',
        ]);

        // Fallback to dynamic academic year if not provided
        $validated['academic_year'] = $request->input('academic_year') ?: current_academic_year();

        $validated['has_allergies'] = $request->boolean('has_allergies');

        $student->update($validated);

        return redirect()->route('admin.students.index')->with('success', 'Student record updated successfully!');
    }

    public function updateStatus(Request $request, Student $student)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
        ]);

        $student->update(['status' => $validated['status']]);

        return back()->with('success', 'Student status updated successfully!');
    }

    public function promote(Request $request)
    {
        foreach ($request->student_ids as $studentId) {
            $student = Student::find($studentId);
            $student->update([
                'class_applied' => $request->target_class,
                'academic_year' => current_academic_year(), // Automatically picks up the new year!
            ]);
        }

        return back()->with('success', 'Students promoted successfully!');
    }

    /**
     * Create or update the ONE authoritative health-measurement record
     * for this student for a specific academic year (Stage P3G).
     *
     * - Bounds (and decimal precision) come from config/school.php
     *   "measurements" so they stay configurable.
     * - Blank input = the measurement stays UNSET (stored as null);
     *   zero is a real, rejectable value because min bounds are positive.
     * - The unique(student_id, academic_year) constraint plus
     *   updateOrCreate make this idempotent; records of other academic
     *   years are never touched, so history survives promotion.
     */
    public function saveMeasurement(Request $request, Student $student)
    {
        $bounds = config('school.measurements', []);
        $heightMin = (float) ($bounds['height_cm_min'] ?? 30);
        $heightMax = (float) ($bounds['height_cm_max'] ?? 230);
        $weightMin = (float) ($bounds['weight_kg_min'] ?? 1);
        $weightMax = (float) ($bounds['weight_kg_max'] ?? 200);
        $places = max(0, (int) ($bounds['decimal_places'] ?? 1));
        // Regex anchors the exact decimal precision: ^\d+(\.\d{0,N})?$
        // (chars after the last allowed decimal place are rejected).
        $decimalPattern = $places > 0
            ? 'regex:/^\d+(\.\d{1,'.$places.'})?$/'
            : 'regex:/^\d+$/';

        $validated = $request->validateWithBag('measurements', [
            'academic_year' => 'required|string|max:20',
            // Blank (absent) means unset - never zero (mins are positive
            // and finite), negatives (leading minus does not match the
            // regex) and bad precision are rejected.
            'height_cm' => ['nullable', 'numeric', "min:$heightMin", "max:$heightMax", $decimalPattern],
            'weight_kg' => ['nullable', 'numeric', "min:$weightMin", "max:$weightMax", $decimalPattern],
            // Recorded only when it fits the existing workflow; optional.
            'measured_on' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        // updateOrCreate keyed on the unique pair - idempotent either way.
        // The form submits an authoritative snapshot for the chosen year:
        // blank fields clear to unset (null), they are never treated as 0
        // and they never inherit a previous value.
        StudentMeasurement::updateOrCreate(
            ['student_id' => $student->id, 'academic_year' => $validated['academic_year']],
            [
                'height_cm' => $validated['height_cm'] ?? null,
                'weight_kg' => $validated['weight_kg'] ?? null,
                'measured_on' => $validated['measured_on'] ?? null,
            ]
        );

        return back()
            ->with('measurements_academic_year', $validated['academic_year'])
            ->with('success', "Health measurements for {$validated['academic_year']} saved successfully!");
    }

    public function destroy(Student $student)
    {
        // fee_receipts.student_id is ON DELETE RESTRICT: deleting the student
        // would raise a FK error (HTTP 500) while receipts exist, so block it
        // here with a friendly message instead (receipts are soft-deleted,
        // so they still count).
        if (FeeReceipt::withTrashed()->where('student_id', $student->id)->exists()) {
            return back()->withErrors([
                'student' => 'This student has fee receipts, so they cannot be deleted. Set their status to Transferred or Passed out instead.',
            ]);
        }

        $student->delete();

        return back()->with('success', 'Student record removed successfully!');
    }

    /**
     * Validation rule for the student's Bikram Sambat date of birth
     * (Stage P3H). The strongest honest validation without calendar data:
     *
     * - Format `YYYY-MM-DD` (e.g. 2069-01-15), optional month/day padding.
     * - Year within configurable bounds (config "school.dob_bs").
     * - Month 1-12; day 1-32, because B.S. months historically have between
     *   29 and 32 days depending on the published calendar (patro).
     *   WHICH days a given B.S. year/month actually contains is NOT
     *   validated - the app has no verified Nepali-calendar dependency and
     *   none is built here (an incomplete converter would be dishonest).
     * - When the A.D. `dob` is also present and parseable, the B.S. year must
     *   be 56 or 57 years after the A.D. birth year (a wide-margin sanity
     *   check: the B.S. new year falls around mid-April). Claims nothing
     *   beyond that.
     *
     * Runs BEFORE any write; a failure aborts the request, so the A.D.
     * `dob` and unrelated fields can never be partially overwritten.
     */
    private function dobBsRule(Request $request): \Closure
    {
        $minYear = (int) config('school.dob_bs.min_year', 2000);
        $maxYear = (int) config('school.dob_bs.max_year', 2100);
        $dob = $request->input('dob');

        return function (string $attribute, mixed $value, \Closure $fail) use ($dob, $minYear, $maxYear) {
            $value = is_string($value) ? trim($value) : '';

            if ($value === '') {
                return; // nullable: a blank field keeps the B.S. date unset
            }

            if (! preg_match('/^(\d{4})-(0?[1-9]|1[0-2])-(0?[1-9]|[12]\d|3[0-2])$/', $value, $m)) {
                $fail('The B.S. date of birth must be given as year-month-day in the Bikram Sambat calendar, e.g. 2069-01-15.');

                return;
            }

            [$year, $month, $day] = [(int) $m[1], (int) $m[2], (int) $m[3]];

            if ($year < $minYear || $year > $maxYear) {
                $fail("The B.S. year must be between {$minYear} and {$maxYear}.");

                return;
            }

            $adYear = null;
            if (is_string($dob) && preg_match('/^(\d{4})-/', $dob, $dm)) {
                $adYear = (int) $dm[1];
                if (! in_array($year - $adYear, [56, 57], true)) {
                    $fail("The B.S. year {$year} does not plausibly match the A.D. birth year {$adYear}: the B.S. year must be 56 or 57 years later.");

                    return;
                }
            }
        };
    }

    /**
     * Next admission number based on the highest existing one, so deleting
     * a student never causes a duplicate (unlike Student::count() + 1).
     */
    private function nextAdmissionNumber(): string
    {
        $prefix = '2024-';

        $last = Student::where('adm_number', 'like', $prefix.'%')
            ->orderByDesc('adm_number')
            ->value('adm_number');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad($next, 5, '0', STR_PAD_LEFT);
    }
}
