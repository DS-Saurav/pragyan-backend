<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Subject; // Or your actual class model name
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    public function index(Request $request)
    {
        $query = Subject::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $subjects = $query->latest()->paginate(10)->withQueryString();

        // Fetch classes to display in the create/edit modal checkboxes or dropdowns
        $classes = class_exists(SchoolClass::class) ? SchoolClass::all() : [];

        return view('admin.subjects.index', compact('subjects', 'classes'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:subjects,code',
            'max_marks' => 'required|integer|min:1',
            'passing_marks' => 'required|integer|min:0',
            'assigned_classes' => 'nullable|array',
        ]);

        Subject::create($validated);

        return back()->with('success', 'Subject created successfully!');
    }

    public function update(Request $request, Subject $subject)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:subjects,code,'.$subject->id,
            'max_marks' => 'required|integer|min:1',
            'passing_marks' => 'required|integer|min:0',
            'assigned_classes' => 'nullable|array',
        ]);

        $subject->update($validated);

        return back()->with('success', 'Subject updated successfully!');
    }

    public function destroy(Subject $subject)
    {
        $subject->delete();

        return back()->with('success', 'Subject deleted successfully!');
    }

    // Export Placeholders for CSV / Print
    public function exportCsv()
    {
        $fileName = 'subjects_'.date('Y-m-d').'.csv';
        $subjects = Subject::all();

        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=$fileName",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($subjects) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Subject Name', 'Code', 'Max Marks', 'Passing Marks']);

            foreach ($subjects as $subject) {
                fputcsv($file, [$subject->name, $subject->code, $subject->max_marks, $subject->passing_marks]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
