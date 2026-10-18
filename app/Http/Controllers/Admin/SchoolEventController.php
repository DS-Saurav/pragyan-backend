<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolEvent;
use Illuminate\Http\Request;

class SchoolEventController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'all');

        $query = SchoolEvent::orderBy('date', 'asc');

        if ($tab === 'holidays') {
            $query->where('type', 'holiday');
        } elseif ($tab === 'events') {
            $query->where('type', 'event');
        } elseif ($tab === 'exams') {
            $query->where('type', 'exam');
        }

        $events = $query->get();
        $totalCount = SchoolEvent::count();
        $holidayCount = SchoolEvent::where('type', 'holiday')->count();
        $eventCount = SchoolEvent::where('type', 'event')->count();
        $examCount = SchoolEvent::where('type', 'exam')->count();

        return view('admin.calendar.index', compact(
            'events', 'tab', 'totalCount', 'holidayCount', 'eventCount', 'examCount'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'date' => 'required|date',
            'type' => 'required|in:holiday,event,exam',
            'description' => 'nullable|string',
        ]);

        SchoolEvent::create($validated);

        return back()->with('success', 'Calendar event added successfully!');
    }

    public function update(Request $request, SchoolEvent $schoolEvent)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'date' => 'required|date',
            'type' => 'required|in:holiday,event,exam',
            'description' => 'nullable|string',
        ]);

        $schoolEvent->update($validated);

        return back()->with('success', 'Calendar event updated successfully!');
    }

    public function destroy(SchoolEvent $schoolEvent)
    {
        $schoolEvent->delete();

        return back()->with('success', 'Event deleted successfully!');
    }
}
