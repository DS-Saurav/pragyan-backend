<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DisciplineIncident as Incident;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\ResultService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DisciplineController extends Controller
{
    /** Filters shared by the list, tab counts and CSV. The status tab is applied separately. */
    private function filtered(Request $request)
    {
        return Incident::with(['student', 'reporter'])
            ->when($request->filled('class_id'), fn ($q) => $q->whereHas('student', fn ($s) => $s->where('school_class_id', $request->class_id)))
            ->when($request->filled('severity') && isset(Incident::SEVERITIES[$request->severity]), fn ($q) => $q->where('severity', $request->severity))
            ->when($request->filled('category') && isset(Incident::CATEGORIES[$request->category]), fn ($q) => $q->where('category', $request->category))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('incident_date', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('incident_date', '<=', $request->to))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->search;
                $q->where(function ($q) use ($s) {
                    $q->where('title', 'like', "%{$s}%")
                        ->orWhere('description', 'like', "%{$s}%")
                        ->orWhereHas('student', fn ($st) => $st
                            ->where('first_name', 'like', "%{$s}%")
                            ->orWhere('last_name', 'like', "%{$s}%")
                            ->orWhere(ResultService::ADMISSION, 'like', "%{$s}%"));
                });
            });
    }

    private function applyTab($query, string $tab)
    {
        return in_array($tab, Incident::STATUSES, true) ? $query->where('status', $tab) : $query;
    }

    public function index(Request $request)
    {
        $tab = $request->get('tab', 'all');
        $perPage = in_array((int) $request->per_page, [10, 25, 50, 100], true) ? (int) $request->per_page : 10;

        $incidents = $this->applyTab($this->filtered($request), $tab)
            ->orderByRaw("FIELD(status, 'open', 'in_review', 'closed')")
            ->orderByDesc('incident_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        // Tab counts follow the other filters
        $raw = $this->filtered($request)->reorder()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');
        $counts = ['all' => (int) $raw->sum()];
        foreach (Incident::STATUSES as $s) {
            $counts[$s] = (int) ($raw[$s] ?? 0);
        }

        // How many incidents each listed student has in total (repeat-offender badge)
        $history = Incident::whereIn('student_id', $incidents->pluck('student_id'))
            ->select('student_id', DB::raw('count(*) as total'))
            ->groupBy('student_id')
            ->pluck('total', 'student_id');

        // Global stat cards
        $active = Incident::where('status', '!=', 'closed');
        $stats = [
            'active' => (clone $active)->count(),
            'serious' => (clone $active)->whereIn('severity', ['high', 'critical'])->count(),
            'not_notified' => (clone $active)->where('parent_notified', false)->count(),
            'overdue' => (clone $active)->whereNotNull('follow_up_date')->whereDate('follow_up_date', '<', now()->toDateString())->count(),
            'month' => Incident::whereBetween('incident_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])->count(),
        ];

        // Student picker data: class -> student dropdown without AJAX
        $studentsJs = Student::all()->map(fn ($s) => [
            'id' => $s->id,
            'name' => ResultService::studentName($s),
            'roll' => ResultService::roll($s),
            'class_id' => $s->school_class_id,
        ])->sortBy('name')->values();

        $classes = SchoolClass::orderBy('name')->get();

        return view('admin.discipline.index', [
            'incidents' => $incidents,
            'counts' => $counts,
            'stats' => $stats,
            'history' => $history,
            'tab' => $tab,
            'perPage' => $perPage,
            'classes' => $classes,
            'classNames' => $classes->pluck('name', 'id'),
            'teachers' => Teacher::orderBy('first_name')->get(),
            'studentsJs' => $studentsJs,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->normalize($request->validate($this->rules($request)));
        Incident::create($data);

        return redirect()->back()->with('success', 'Incident reported successfully.');
    }

    public function update(Request $request, Incident $incident)
    {
        $data = $this->normalize($request->validate($this->rules($request)), $incident);
        $incident->update($data);

        return redirect()->back()->with('success', 'Incident updated successfully.');
    }

    /** Close a case. A resolution note is required. */
    public function close(Request $request, Incident $incident)
    {
        $data = $request->validate(['resolution' => ['required', 'string']]);

        $incident->update($data + ['status' => 'closed', 'closed_at' => now()]);

        return redirect()->back()->with('success', 'Case closed.');
    }

    public function notify(Incident $incident)
    {
        $incident->update([
            'parent_notified' => true,
            'parent_notified_on' => $incident->parent_notified_on ?? now()->toDateString(),
        ]);

        return redirect()->back()->with('success', 'Marked as parent notified.');
    }

    public function destroy(Incident $incident)
    {
        $incident->delete();

        return redirect()->back()->with('success', 'Incident deleted.');
    }

    /** Printable letter to the parent / guardian. */
    public function notice(Incident $incident)
    {
        $incident->load('student', 'reporter');
        $class = SchoolClass::find($incident->student->school_class_id ?? 0);

        return view('admin.discipline.notice', compact('incident', 'class'));
    }

    public function csv(Request $request): StreamedResponse
    {
        $tab = $request->get('tab', 'all');
        $query = $this->applyTab($this->filtered($request), $tab)->orderByDesc('incident_date');
        $classNames = SchoolClass::pluck('name', 'id');

        return response()->streamDownload(function () use ($query, $classNames) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows Nepali text correctly

            fputcsv($out, [
                'Date', 'Student', 'Class', 'Roll', 'Category', 'Severity', 'Incident', 'Description', 'Location',
                'Witnesses', 'Reported By', 'Action Type', 'Action Taken', 'Parent Notified', 'Notified On',
                'Notified Via', 'Follow-up Date', 'Status', 'Resolution',
            ]);

            $query->chunk(200, function ($rows) use ($out, $classNames) {
                foreach ($rows as $i) {
                    fputcsv($out, [
                        $i->incident_date->format('Y-m-d'),
                        ResultService::studentName($i->student),
                        $classNames[$i->student->school_class_id ?? 0] ?? '',
                        ResultService::roll($i->student),
                        Incident::CATEGORIES[$i->category] ?? $i->category,
                        $i->severity,
                        $i->title,
                        $i->description,
                        $i->location,
                        $i->witnesses,
                        trim(($i->reporter->first_name ?? '').' '.($i->reporter->last_name ?? '')),
                        Incident::ACTIONS[$i->action_type] ?? '',
                        $i->action_taken,
                        $i->parent_notified ? 'Yes' : 'No',
                        $i->parent_notified_on?->format('Y-m-d'),
                        Incident::NOTIFY_METHODS[$i->notified_via] ?? '',
                        $i->follow_up_date?->format('Y-m-d'),
                        $i->status,
                        $i->resolution,
                    ]);
                }
            });

            fclose($out);
        }, 'discipline-incidents-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function rules(Request $request): array
    {
        $request->merge(['parent_notified' => $request->boolean('parent_notified')]);

        return [
            'student_id' => ['required', 'exists:students,id'],
            'reported_by' => ['nullable', 'exists:teachers,id'],
            'incident_date' => ['required', 'date', 'before_or_equal:today'],
            'category' => ['required', Rule::in(array_keys(Incident::CATEGORIES))],
            'severity' => ['required', Rule::in(array_keys(Incident::SEVERITIES))],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:120'],
            'witnesses' => ['nullable', 'string', 'max:255'],
            'action_type' => ['nullable', Rule::in(array_keys(Incident::ACTIONS))],
            'action_taken' => ['nullable', 'string'],
            'parent_notified' => ['boolean'],
            'parent_notified_on' => ['nullable', 'date'],
            'notified_via' => ['nullable', Rule::in(array_keys(Incident::NOTIFY_METHODS))],
            'follow_up_date' => ['nullable', 'date', 'after_or_equal:incident_date'],
            'status' => ['required', Rule::in(Incident::STATUSES)],
            'resolution' => ['nullable', 'required_if:status,closed', 'string'],
        ];
    }

    private function normalize(array $data, ?Incident $existing = null): array
    {
        if ($data['parent_notified']) {
            $data['parent_notified_on'] ??= now()->toDateString();
        } else {
            $data['parent_notified_on'] = null;
            $data['notified_via'] = null;
        }

        $data['closed_at'] = $data['status'] === 'closed' ? ($existing?->closed_at ?? now()) : null;

        return $data;
    }
}
