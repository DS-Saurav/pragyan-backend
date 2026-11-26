<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;

use App\Http\Controllers\Controller;
use App\Models\SchoolEvent;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SchoolEventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeCalendarViewing();

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

        return response()->json(compact(
            'events', 'tab', 'totalCount', 'holidayCount', 'eventCount', 'examCount'
        ));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeCalendarManagement();
        $this->authorizePublicCalendarMutation($request->boolean('is_public'));

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'date' => 'required|date',
            'type' => 'required|in:holiday,event,exam',
            'description' => 'nullable|string',
        ]);

        // The checkbox is only present in the request when ticked; an
        // untouched checkbox must normalize to false, never to the old value.
        SchoolEvent::create([
            ...$validated,
            'is_public' => $request->boolean('is_public'),
        ]);

        return response()->json(["message" => 'Calendar event added successfully!']);
    }

    public function update(Request $request, SchoolEvent $schoolEvent): JsonResponse
    {
        $this->authorizeCalendarManagement();
        $this->authorizePublicCalendarMutation($schoolEvent->is_public || $request->boolean('is_public'));

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'date' => 'required|date',
            'type' => 'required|in:holiday,event,exam',
            'description' => 'nullable|string',
        ]);

        $schoolEvent->update([
            ...$validated,
            'is_public' => $request->boolean('is_public'),
        ]);

        return response()->json(["message" => 'Calendar event updated successfully!']);
    }

    public function destroy(SchoolEvent $schoolEvent): JsonResponse
    {
        $this->authorizeCalendarManagement();
        $this->authorizePublicCalendarMutation($schoolEvent->is_public);

        $schoolEvent->delete();

        return response()->json(["message" => 'Event deleted successfully!']);
    }

    private function authorizeCalendarViewing(): void
    {
        $this->requireStaffActor();
        Gate::authorize('calendar.view');
    }

    private function authorizeCalendarManagement(): void
    {
        $this->requireStaffActor();
        Gate::authorize('calendar.manage');
    }

    /**
     * Calendar coordinators retain management of private internal dates. Any
     * action that can create, alter, unpublish, or remove a public date stays
     * with the administrator until the owner explicitly changes that policy.
     */
    private function authorizePublicCalendarMutation(bool $touchesPublicContent): void
    {
        if ($touchesPublicContent) {
            Gate::authorize('public_calendar.manage');
        }
    }

    private function requireStaffActor(): User
    {
        $user = auth()->user();
        $staffRoles = array_values(array_diff(
            array_keys((array) config('permissions.role_labels', [])),
            ['student', 'parent'],
        ));

        abort_unless($user instanceof User && $user->hasAnyRole($staffRoles), 403);

        return $user;
    }
}
