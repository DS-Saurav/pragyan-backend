<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Http\Request;

class TeacherController extends Controller
{
    public function index(Request $request)
    {
        $statusTab = $request->get('tab', 'all');

        $query = Teacher::query();

        if ($statusTab === 'active') {
            $query->where('status', 'active');
        } elseif ($statusTab === 'inactive') {
            $query->where('status', 'inactive');
        } elseif ($statusTab === 'suspended') {
            $query->where('status', 'suspended');
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('specialization', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        $teachers = $query->latest()->paginate(10)->withQueryString();

        $counts = [
            'all' => Teacher::count(),
            'active' => Teacher::where('status', 'active')->count(),
            'inactive' => Teacher::where('status', 'inactive')->count(),
            'suspended' => Teacher::where('status', 'suspended')->count(),
            'class_teachers' => Teacher::whereNotNull('class_teacher_of')->where('class_teacher_of', '!=', '')->count(),
        ];

        $totalAssignments = 0;
        foreach (Teacher::all() as $t) {
            if (is_array($t->assignments)) {
                $totalAssignments += count($t->assignments);
            }
        }
        $counts['assignments'] = $totalAssignments;

        $classes = SchoolClass::all();
        $subjects = Subject::all();

        return view('admin.teachers.index', compact('teachers', 'counts', 'classes', 'subjects', 'statusTab'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'username' => 'nullable|string|max:100|unique:teachers,username',
            'email' => 'required|email|unique:teachers,email',
            'phone' => 'nullable|string|max:20',
            'department' => 'required|string|max:100',
            'specialization' => 'nullable|string|max:100',
            'class_teacher_of' => 'nullable|string|max:255',
            'status' => 'required|in:active,inactive,suspended',
        ]);

        if (empty($validated['username'])) {
            $validated['username'] = '@'.strtolower($validated['first_name'].$validated['last_name']);
        }

        Teacher::create($validated);

        return back()->with('success', 'Teacher registered successfully!');
    }

    public function update(Request $request, Teacher $teacher)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'username' => 'nullable|string|max:100|unique:teachers,username,'.$teacher->id,
            'email' => 'required|email|unique:teachers,email,'.$teacher->id,
            'phone' => 'nullable|string|max:20',
            'department' => 'required|string|max:100',
            'specialization' => 'nullable|string|max:100',
            'class_teacher_of' => 'nullable|string|max:255',
            'status' => 'required|in:active,inactive,suspended',
        ]);

        $teacher->update($validated);

        return back()->with('success', 'Teacher details updated successfully!');
    }

    public function assign(Request $request, Teacher $teacher)
    {
        $validated = $request->validate([
            'class_name' => 'required|string',
            'subject_name' => 'required|string',
        ]);

        $assignments = $teacher->assignments ?? [];
        $assignments[] = [
            'id' => uniqid(),
            'class' => $validated['class_name'],
            'subject' => $validated['subject_name'],
        ];

        $teacher->update(['assignments' => $assignments]);

        return back()->with('success', 'Class & subject assigned successfully!');
    }

    public function removeAssignment(Request $request, Teacher $teacher, $assignmentId)
    {
        $assignments = $teacher->assignments ?? [];
        $assignments = array_values(array_filter($assignments, function ($a) use ($assignmentId) {
            return ($a['id'] ?? '') !== $assignmentId;
        }));

        $teacher->update(['assignments' => $assignments]);

        return back()->with('success', 'Assignment removed successfully!');
    }

    public function destroy(Teacher $teacher)
    {
        $teacher->delete();

        return back()->with('success', 'Teacher removed successfully!');
    }

    public function exportCsv()
    {
        $fileName = 'teachers_'.date('Y-m-d').'.csv';
        $teachers = Teacher::all();

        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=$fileName",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($teachers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['First Name', 'Last Name', 'Username', 'Email', 'Phone', 'Specialization', 'Status']);

            foreach ($teachers as $t) {
                fputcsv($file, [$t->first_name, $t->last_name, $t->username, $t->email, $t->phone, $t->specialization, $t->status]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
