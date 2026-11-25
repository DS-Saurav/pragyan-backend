<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConductRegister;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConductController extends Controller
{
    public function index(Request $request)
    {
        $query = ConductRegister::with(['student', 'recorder']);

        // Filter by tab type (all, merit, demerit)
        if ($request->filled('type') && in_array($request->type, ['merit', 'demerit'])) {
            $query->where('type', $request->type);
        }

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('note', 'like', "%{$search}%")
                    ->orWhereHas('student', function ($stQuery) use ($search) {
                        $stQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        $entries = $query->latest('entry_date')->paginate($request->get('per_page', 10));

        $totalCount = ConductRegister::count();
        $meritCount = ConductRegister::where('type', 'merit')->count();
        $demeritCount = ConductRegister::where('type', 'demerit')->count();

        $students = Student::select('id', 'first_name', 'last_name')->get();

        return view('admin.conduct.index', compact('entries', 'totalCount', 'meritCount', 'demeritCount', 'students'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'entry_date' => 'required|date',
            'type' => 'required|in:merit,demerit',
            'points' => 'required|integer|min:1',
            'note' => 'required|string|max:500',
        ]);

        $validated['recorded_by'] = Auth::id();

        ConductRegister::create($validated);

        return redirect()->route('admin.conduct.index')
            ->with('success', 'Conduct register entry recorded successfully.');
    }

    public function destroy(ConductRegister $conduct)
    {
        $conduct->delete();

        return redirect()->route('admin.conduct.index')
            ->with('success', 'Conduct entry deleted successfully.');
    }

    public function edit(ConductRegister $conduct)
    {
        $students = Student::select('id', 'first_name', 'last_name')->get();
        return response()->json([
            'entry' => $conduct,
            'students' => $students
        ]);
    }

    public function update(Request $request, ConductRegister $conduct)
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'entry_date' => 'required|date',
            'type'       => 'required|in:merit,demerit',
            'points'     => 'required|integer|min:1',
            'note'       => 'required|string|max:500',
        ]);

        $conduct->update($validated);

        return redirect()->route('admin.conduct.index')
            ->with('success', 'Conduct register entry updated successfully.');
    }
}
