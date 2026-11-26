<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolActivity;
use App\Models\User;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index(Request $request)
    {
        $query = SchoolActivity::with('inCharge');

        // Status tab filtering (scheduled, completed)
        if ($request->filled('status') && in_array($request->status, ['scheduled', 'completed'])) {
            $query->where('status', $request->status);
        }

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%");
            });
        }

        $entries = $query->latest('activity_date')->paginate($request->get('per_page', 10));

        $totalCount = SchoolActivity::count();
        $scheduledCount = SchoolActivity::where('status', 'scheduled')->count();
        $completedCount = SchoolActivity::where('status', 'completed')->count();

        $teachers = User::select('id', 'name')->get();

        return response()->json(compact(
            'entries', 'totalCount', 'scheduledCount', 'completedCount', 'teachers'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'activity_date' => 'required|date',
            'title' => 'required|string|max:255',
            'type' => 'required|string|max:100',
            'in_charge_id' => 'required|exists:users,id',
            'status' => 'required|in:scheduled,completed',
        ]);

        SchoolActivity::create($validated);

        return response()->json(["message" => 'Activity added successfully.']);
    }

    public function edit(SchoolActivity $activity)
    {
        $teachers = User::select('id', 'name')->get();

        return response()->json([
            'entry' => $activity,
            'teachers' => $teachers,
        ]);
    }

    public function update(Request $request, SchoolActivity $activity)
    {
        $validated = $request->validate([
            'activity_date' => 'required|date',
            'title' => 'required|string|max:255',
            'type' => 'required|string|max:100',
            'in_charge_id' => 'required|exists:users,id',
            'status' => 'required|in:scheduled,completed',
        ]);

        $activity->update($validated);

        return response()->json(["message" => 'Activity updated successfully.']);
    }

    public function destroy(SchoolActivity $activity)
    {
        $activity->delete();

        return response()->json(["message" => 'Activity deleted successfully.']);
    }
}
