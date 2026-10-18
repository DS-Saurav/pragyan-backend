<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Student;
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

    public function destroy(Student $student)
    {
        $student->delete();

        return back()->with('success', 'Student record removed successfully!');
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
