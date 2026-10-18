<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'class_id' => 'nullable|exists:school_classes,id',
            'date' => 'nullable|date',
        ]);

        $classes = SchoolClass::orderBy('name')->get();
        $selectedClassId = $request->get('class_id');
        $date = $request->filled('date')
            ? Carbon::parse($request->get('date'))->toDateString()
            : Carbon::today()->toDateString();

        $students = collect();
        $attendanceMap = [];
        $isSaved = false;

        if ($selectedClassId) {
            $students = Student::where('school_class_id', $selectedClassId)
                ->orderBy('roll_no')
                ->get();

            $existing = Attendance::whereIn('student_id', $students->pluck('id'))
                ->whereDate('date', $date)
                ->pluck('status', 'student_id');

            $isSaved = $existing->isNotEmpty();

            // Saved status if there is one, otherwise default to present
            foreach ($students as $student) {
                $attendanceMap[$student->id] = $existing[$student->id] ?? Attendance::PRESENT;
            }
        }

        return view('admin.attendance.index', compact(
            'classes', 'selectedClassId', 'date', 'students', 'attendanceMap', 'isSaved'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'school_class_id' => 'required|exists:school_classes,id',
            'date' => 'required|date',
            'attendance' => 'required|array|min:1',
            'attendance.*' => ['required', Rule::in(Attendance::STATUSES)],
        ]);

        $classId = $validated['school_class_id'];
        $date = Carbon::parse($validated['date'])->toDateString();

        // Only accept students who really belong to this class
        $validStudentIds = Student::where('school_class_id', $classId)->pluck('id');

        DB::transaction(function () use ($validated, $classId, $date, $validStudentIds) {
            foreach ($validated['attendance'] as $studentId => $status) {
                if (! $validStudentIds->contains((int) $studentId)) {
                    continue;
                }

                Attendance::updateOrCreate(
                    ['student_id' => $studentId, 'date' => $date],
                    ['school_class_id' => $classId, 'status' => $status]
                );
            }
        });

        return redirect()
            ->route('admin.attendance.index', ['class_id' => $classId, 'date' => $date])
            ->with('success', 'Attendance saved successfully!');
    }

    public function copyFromYesterday(Request $request)
    {
        $validated = $request->validate([
            'school_class_id' => 'required|exists:school_classes,id',
            'date' => 'required|date',
        ]);

        $classId = $validated['school_class_id'];
        $targetDate = Carbon::parse($validated['date'])->toDateString();

        // Most recent earlier day that has records (handles weekends / holidays)
        $previousDate = Attendance::where('school_class_id', $classId)
            ->whereDate('date', '<', $targetDate)
            ->max('date');

        if (! $previousDate) {
            return back()->withErrors(['date' => 'No earlier attendance records found to copy from.']);
        }

        $validStudentIds = Student::where('school_class_id', $classId)->pluck('id');

        $records = Attendance::where('school_class_id', $classId)
            ->whereDate('date', $previousDate)
            ->whereIn('student_id', $validStudentIds)
            ->get();

        DB::transaction(function () use ($records, $classId, $targetDate) {
            foreach ($records as $rec) {
                Attendance::updateOrCreate(
                    ['student_id' => $rec->student_id, 'date' => $targetDate],
                    ['school_class_id' => $classId, 'status' => $rec->status]
                );
            }
        });

        return redirect()
            ->route('admin.attendance.index', ['class_id' => $classId, 'date' => $targetDate])
            ->with('success', 'Attendance copied from '.Carbon::parse($previousDate)->format('d M Y').' successfully!');
    }
}
