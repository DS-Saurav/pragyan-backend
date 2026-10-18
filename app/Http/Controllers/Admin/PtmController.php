<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PtmMeeting;
use App\Models\SchoolClass;
use Illuminate\Http\Request;

class PtmController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'all');

        $query = PtmMeeting::with('schoolClass')->orderBy('meeting_date', 'desc');

        if ($tab === 'scheduled') {
            $query->where('status', 'scheduled');
        } elseif ($tab === 'completed') {
            $query->where('status', 'completed');
        } elseif ($tab === 'cancelled') {
            $query->where('status', 'cancelled');
        }

        $meetings = $query->get();
        $classes = SchoolClass::orderBy('name', 'asc')->get();

        $totalCount = PtmMeeting::count();
        $scheduledCount = PtmMeeting::where('status', 'scheduled')->count();
        $completedCount = PtmMeeting::where('status', 'completed')->count();
        $cancelledCount = PtmMeeting::where('status', 'cancelled')->count();

        return view('admin.ptm.index', compact(
            'meetings', 'classes', 'tab', 'totalCount', 'scheduledCount', 'completedCount', 'cancelledCount'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'school_class_id' => 'nullable|exists:school_classes,id',
            'meeting_date' => 'required|date',
            'time_slot' => 'required|string|max:100',
            'venue' => 'required|string|max:255',
            'agenda' => 'nullable|string',
            'status' => 'required|in:scheduled,completed,cancelled',
        ]);

        PtmMeeting::create($validated);

        return back()->with('success', 'PTM Meeting scheduled successfully!');
    }

    public function update(Request $request, PtmMeeting $ptm)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'school_class_id' => 'nullable|exists:school_classes,id',
            'meeting_date' => 'required|date',
            'time_slot' => 'required|string|max:100',
            'venue' => 'required|string|max:255',
            'agenda' => 'nullable|string',
            'status' => 'required|in:scheduled,completed,cancelled',
        ]);

        $ptm->update($validated);

        return back()->with('success', 'PTM Meeting updated successfully!');
    }

    public function destroy(PtmMeeting $ptm)
    {
        $ptm->delete();

        return back()->with('success', 'Meeting record deleted successfully!');
    }
}
