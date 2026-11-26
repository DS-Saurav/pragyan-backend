<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentMeasurement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    private const STATUSES = ['active', 'inactive', 'transferred', 'passed_out', 'suspended'];

    public function index(Request $request): JsonResponse
    {
        $status = $request->get('status');
        $academicYear = trim((string) $request->get('academic_year', ''));

        $query = Student::query();

        if ($academicYear !== '' && $academicYear !== 'all') {
            $query->where('academic_year', $academicYear);
        }
        if ($status && in_array($status, self::STATUSES, true)) {
            $query->where('status', $status);
        }
        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('adm_number', 'like', "%{$search}%");
            });
        }
        if ($request->filled('class')) {
            $query->where('class_applied', $request->string('class')->toString());
        }
        if ($request->filled('gender')) {
            $query->where('gender', $request->string('gender')->toString());
        }
        if ($request->filled('house')) {
            $query->where('house', $request->string('house')->toString());
        }
        if ($request->boolean('has_allergies')) {
            $query->where('has_allergies', true);
        }

        $students = $query->latest()->get()->map(fn (Student $s) => $this->formatStudent($s));

        $countsBase = Student::query();
        if ($academicYear !== '' && $academicYear !== 'all') {
            $countsBase->where('academic_year', $academicYear);
        }
        $counts = [
            'all' => (clone $countsBase)->count(),
            'active' => (clone $countsBase)->where('status', 'active')->count(),
            'inactive' => (clone $countsBase)->where('status', 'inactive')->count(),
            'transferred' => (clone $countsBase)->where('status', 'transferred')->count(),
            'passed_out' => (clone $countsBase)->where('status', 'passed_out')->count(),
            'suspended' => (clone $countsBase)->where('status', 'suspended')->count(),
        ];

        $defaultYear = function_exists('current_academic_year') ? current_academic_year() : '2082-2083';
        $academicYears = Student::distinct()->orderByDesc('academic_year')->pluck('academic_year')->filter()->values()->all();
        if (! in_array($defaultYear, $academicYears, true)) {
            array_unshift($academicYears, $defaultYear);
        }

        $classes = SchoolClass::query()
            ->orderBy('name')
            ->orderBy('section')
            ->get()
            ->map(fn ($schoolClass) => trim("{$schoolClass->name} {$schoolClass->section}"))
            ->filter()
            ->values()
            ->all();

        return response()->json([
            'students' => $students,
            'counts' => $counts,
            'classes' => $classes,
            'academicYears' => $academicYears,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateStudent($request);
        $nextNumber = (Student::max('id') ?? 0) + 1;
        $validated['adm_number'] = 'ADM-'.date('Y').'-'.str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT);
        $validated['status'] = $validated['status'] ?? 'active';
        $validated['academic_year'] = $validated['academic_year'] ?: (function_exists('current_academic_year') ? current_academic_year() : '2082-2083');

        $student = Student::create($validated);

        return response()->json(['student' => $this->formatStudent($student)], 201);
    }

    public function show(Student $student): JsonResponse
    {
        return response()->json(['student' => $this->formatStudent($student)]);
    }

    public function update(Request $request, Student $student): JsonResponse
    {
        $validated = $this->validateStudent($request);
        $student->update($validated);

        return response()->json(['student' => $this->formatStudent($student->fresh())]);
    }

    public function updateStatus(Request $request, Student $student): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
        ]);
        $student->update($validated);

        return response()->json(['student' => $this->formatStudent($student->fresh())]);
    }

    public function saveMeasurement(Request $request, Student $student): JsonResponse
    {
        $validated = $request->validate([
            'academic_year' => 'required|string|max:20',
            'height_cm' => 'nullable|numeric|min:0',
            'weight_kg' => 'nullable|numeric|min:0',
            'measured_on' => 'nullable|date',
        ]);

        $measurement = StudentMeasurement::updateOrCreate(
            ['student_id' => $student->id, 'academic_year' => $validated['academic_year']],
            $validated,
        );

        return response()->json(['measurement' => $measurement]);
    }

    public function destroy(Student $student): JsonResponse
    {
        $student->delete();

        return response()->json(['deleted' => true]);
    }

    private function validateStudent(Request $request): array
    {
        return $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'gender' => 'required|string',
            'dob' => 'required|date',
            'dob_bs' => 'nullable|string|max:30',
            'languages_spoken' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'class_applied' => 'required|string',
            'roll_no' => 'nullable|integer',
            'academic_year' => 'nullable|string|max:20',
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'house' => 'nullable|string|max:50',
            'eal' => 'nullable|string|max:50',
            'admission_date' => 'nullable|date',
            'previous_school' => 'nullable|string|max:255',
            'blood_group' => 'nullable|string|max:10',
            'emergency_contact' => 'nullable|string|max:255',
            'hobbies' => 'nullable|string|max:255',
            'has_allergies' => 'nullable|boolean',
        ]);
    }

    private function formatStudent(Student $s): array
    {
        return [
            'id' => $s->id,
            'admNumber' => $s->adm_number,
            'firstName' => $s->first_name,
            'lastName' => $s->last_name,
            'gender' => $s->gender,
            'dob' => $s->dob ? (string) $s->dob : '',
            'dobBs' => (string) ($s->dob_bs ?? ''),
            'languagesSpoken' => (string) ($s->languages_spoken ?? ''),
            'address' => (string) ($s->address ?? ''),
            'classApplied' => (string) ($s->class_applied ?? ''),
            'rollNo' => (int) ($s->roll_no ?? 1),
            'academicYear' => (string) ($s->academic_year ?? ''),
            'status' => $s->status ?? 'active',
            'house' => $s->house ?? 'Red',
            'eal' => $s->eal ?? 'Native',
            'admissionDate' => $s->admission_date ? (string) $s->admission_date : '',
            'previousSchool' => (string) ($s->previous_school ?? ''),
            'bloodGroup' => (string) ($s->blood_group ?? ''),
            'emergencyContact' => (string) ($s->emergency_contact ?? ''),
            'hobbies' => (string) ($s->hobbies ?? ''),
            'hasAllergies' => (bool) $s->has_allergies,
        ];
    }
}
