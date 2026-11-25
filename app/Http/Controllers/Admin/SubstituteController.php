<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubstituteAllocation;
use App\Models\User;
use Illuminate\Http\Request;

class SubstituteController extends Controller
{
    public function index(Request $request)
    {
        $query = SubstituteAllocation::with(['absentTeacher', 'substituteTeacher']);

        // Search filter
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->whereHas('absentTeacher', fn ($at) => $at->where('name', 'like', "%{$s}%"))
                    ->orWhereHas('substituteTeacher', fn ($st) => $st->where('name', 'like', "%{$s}%"))
                    ->orWhere('periods_covered', 'like', "%{$s}%");
            });
        }

        $entries = $query->latest('allocation_date')->paginate($request->get('per_page', 10));

        // Fetch staff list for the allocation modal dropdowns.
        $staffRoles = [
            'admin',
            'principal',
            'academic_coordinator',
            'discipline_coordinator',
            'eca_coordinator',
            'teacher',
            'librarian',
            'accountant',
        ];

        $teachers = User::whereHas('userRoles', fn ($query) => $query->whereIn('role', $staffRoles))
            ->orderBy('name')
            ->get();

        return view('substitutes.index', compact('entries', 'teachers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'allocation_date' => 'required|date',
            'day_of_week' => 'required|string',
            'absent_teacher_id' => 'required|exists:users,id',
            'substitute_teacher_id' => 'required|exists:users,id|different:absent_teacher_id',
            'periods_covered' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $validated['status'] = 'allocated';

        SubstituteAllocation::create($validated);

        return redirect()->route('admin.substitutes.index')
            ->with('success', 'Substitute teacher allocated successfully.');
    }

    public function destroy(SubstituteAllocation $substitute)
    {
        $substitute->delete();

        return redirect()->route('admin.substitutes.index')
            ->with('success', 'Substitute allocation removed successfully.');
    }
}
