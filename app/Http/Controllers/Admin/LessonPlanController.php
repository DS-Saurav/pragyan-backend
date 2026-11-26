<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LessonPlan;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LessonPlanController extends Controller
{
    /**
     * Shared filters (search, class, teacher, date range). The status tab is applied separately
     * so the tab counts stay correct.
     */
    private function applyFilters($query, Request $request)
    {
        return $query
            ->when($request->filled('class_id'), fn ($q) => $q->where('school_class_id', $request->class_id))
            ->when($request->filled('teacher_id'), fn ($q) => $q->where('teacher_id', $request->teacher_id))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('lesson_date', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('lesson_date', '<=', $request->to))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->search;
                $q->where(function ($q) use ($s) {
                    $q->where('topic', 'like', "%{$s}%")
                        ->orWhere('unit', 'like', "%{$s}%")
                        ->orWhere('homework', 'like', "%{$s}%")
                        ->orWhereHas('teacher', fn ($t) => $t
                            ->where('first_name', 'like', "%{$s}%")
                            ->orWhere('last_name', 'like', "%{$s}%"))
                        ->orWhereHas('schoolClass', fn ($c) => $c->where('name', 'like', "%{$s}%"))
                        ->orWhereHas('subject', fn ($c) => $c->where('name', 'like', "%{$s}%"));
                });
            });
    }

    private function applyTab($query, string $tab)
    {
        return in_array($tab, LessonPlan::STATUSES, true)
            ? $query->where('status', $tab)
            : $query;
    }

    public function index(Request $request)
    {
        $tab = $request->get('tab', 'all');
        $perPage = in_array((int) $request->per_page, [10, 25, 50, 100], true) ? (int) $request->per_page : 10;

        $plans = $this->applyTab(
            $this->applyFilters(LessonPlan::with(['teacher', 'schoolClass', 'subject']), $request),
            $tab
        )
            ->orderByDesc('lesson_date')
            ->orderBy('period')
            ->paginate($perPage)
            ->withQueryString();

        $raw = $this->applyFilters(LessonPlan::query(), $request)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $counts = ['all' => (int) $raw->sum()];
        foreach (LessonPlan::STATUSES as $status) {
            $counts[$status] = (int) ($raw[$status] ?? 0);
        }

        return response()->json([
            'plans' => $plans,
            'counts' => $counts,
            'tab' => $tab,
            'perPage' => $perPage,
            'teachers' => Teacher::orderBy('first_name')->get(),
            'classes' => SchoolClass::orderBy('name')->get(),
            'subjects' => Subject::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        LessonPlan::create($request->validate($this->rules()));

        return response()->json(["message" => 'Lesson plan created successfully.']);
    }

    public function update(Request $request, LessonPlan $lessonPlan)
    {
        $lessonPlan->update($request->validate($this->rules()));

        return response()->json(["message" => 'Lesson plan updated successfully.']);
    }

    public function updateStatus(Request $request, LessonPlan $lessonPlan)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(LessonPlan::STATUSES)],
        ]);

        $lessonPlan->update($data);

        return response()->json(["message" => 'Lesson status updated.']);
    }

    public function destroy(LessonPlan $lessonPlan)
    {
        $lessonPlan->delete();

        return response()->json(["message" => 'Lesson plan deleted.']);
    }

    public function csv(Request $request): StreamedResponse
    {
        $tab = $request->get('tab', 'all');

        $query = $this->applyTab(
            $this->applyFilters(LessonPlan::with(['teacher', 'schoolClass', 'subject']), $request),
            $tab
        )->orderByDesc('lesson_date');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows Nepali text correctly

            fputcsv($out, [
                'Date', 'Period', 'Duration (min)', 'Teacher', 'Class', 'Subject', 'Unit', 'Topic',
                'Objectives', 'Teaching Method', 'Resources', 'Homework', 'Assessment', 'Status', 'Reflection',
            ]);

            $query->chunk(200, function ($rows) use ($out) {
                foreach ($rows as $p) {
                    fputcsv($out, [
                        $p->lesson_date->format('Y-m-d'),
                        $p->period,
                        $p->duration_minutes,
                        trim(($p->teacher->first_name ?? '').' '.($p->teacher->last_name ?? '')),
                        $p->schoolClass->name ?? '',
                        $p->subject->name ?? '',
                        $p->unit,
                        $p->topic,
                        $p->objectives,
                        $p->teaching_method,
                        $p->resources,
                        $p->homework,
                        $p->assessment,
                        $p->status,
                        $p->reflection,
                    ]);
                }
            });

            fclose($out);
        }, 'lesson-plans-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function rules(): array
    {
        return [
            'lesson_date' => ['required', 'date'],
            'period' => ['nullable', 'integer', 'min:1', 'max:12'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:240'],
            'teacher_id' => ['required', 'exists:teachers,id'],
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'unit' => ['nullable', 'string', 'max:255'],
            'topic' => ['required', 'string', 'max:255'],
            'objectives' => ['nullable', 'string'],
            'teaching_method' => ['nullable', 'string', 'max:255'],
            'resources' => ['nullable', 'string', 'max:255'],
            'homework' => ['nullable', 'string', 'max:255'],
            'assessment' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(LessonPlan::STATUSES)],
            'reflection' => ['nullable', 'string'],
        ];
    }
}
