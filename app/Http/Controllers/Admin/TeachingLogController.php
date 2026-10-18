<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LessonPlan;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeachingLogController extends Controller
{
    private function filtered(Request $request)
    {
        return TeachingLog::with(['teacher', 'schoolClass', 'subject'])
            ->when($request->filled('class_id'), fn ($q) => $q->where('school_class_id', $request->class_id))
            ->when($request->filled('teacher_id'), fn ($q) => $q->where('teacher_id', $request->teacher_id))
            ->when(
                $request->filled('coverage') && in_array($request->coverage, TeachingLog::COVERAGES, true),
                fn ($q) => $q->where('coverage', $request->coverage)
            )
            ->when($request->filled('from'), fn ($q) => $q->whereDate('log_date', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('log_date', '<=', $request->to))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->search;
                $q->where(function ($q) use ($s) {
                    $q->where('topic_covered', 'like', "%{$s}%")
                        ->orWhere('homework_given', 'like', "%{$s}%")
                        ->orWhere('remarks', 'like', "%{$s}%")
                        ->orWhereHas('teacher', fn ($t) => $t
                            ->where('first_name', 'like', "%{$s}%")
                            ->orWhere('last_name', 'like', "%{$s}%"))
                        ->orWhereHas('schoolClass', fn ($c) => $c->where('name', 'like', "%{$s}%"))
                        ->orWhereHas('subject', fn ($c) => $c->where('name', 'like', "%{$s}%"));
                });
            });
    }

    public function index(Request $request)
    {
        $perPage = in_array((int) $request->per_page, [10, 25, 50, 100], true) ? (int) $request->per_page : 10;

        $logs = $this->filtered($request)
            ->orderByDesc('log_date')
            ->orderBy('period')
            ->paginate($perPage)
            ->withQueryString();

        // Recent lesson plans that can be linked to a log entry
        $lessonPlans = LessonPlan::with(['schoolClass', 'subject'])
            ->where('lesson_date', '>=', now()->subDays(30)->toDateString())
            ->orderByDesc('lesson_date')
            ->limit(100)
            ->get();

        return view('admin.teaching-logs.index', [
            'logs' => $logs,
            'perPage' => $perPage,
            'lessonPlans' => $lessonPlans,
            'teachers' => Teacher::orderBy('first_name')->get(),
            'classes' => SchoolClass::orderBy('name')->get(),
            'subjects' => Subject::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $log = TeachingLog::create($request->validate($this->rules()));

        $this->syncLessonPlan($log);

        return redirect()->back()->with('success', 'Log entry added successfully.');
    }

    public function update(Request $request, TeachingLog $teachingLog)
    {
        $teachingLog->update($request->validate($this->rules()));

        $this->syncLessonPlan($teachingLog);

        return redirect()->back()->with('success', 'Log entry updated successfully.');
    }

    public function destroy(TeachingLog $teachingLog)
    {
        $teachingLog->delete();

        return redirect()->back()->with('success', 'Log entry deleted.');
    }

    public function csv(Request $request): StreamedResponse
    {
        $query = $this->filtered($request)->orderByDesc('log_date');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows Nepali text correctly

            fputcsv($out, [
                'Date', 'Period', 'Teacher', 'Class', 'Subject', 'Topic Covered', 'Coverage',
                'Students Present', 'Students Absent', 'Homework Given', 'Remarks',
            ]);

            $query->chunk(200, function ($rows) use ($out) {
                foreach ($rows as $l) {
                    fputcsv($out, [
                        $l->log_date->format('Y-m-d'),
                        $l->period,
                        trim(($l->teacher->first_name ?? '').' '.($l->teacher->last_name ?? '')),
                        $l->schoolClass->name ?? '',
                        $l->subject->name ?? '',
                        $l->topic_covered,
                        $l->coverage,
                        $l->students_present,
                        $l->students_absent,
                        $l->homework_given,
                        $l->remarks,
                    ]);
                }
            });

            fclose($out);
        }, 'teaching-logbook-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** A fully covered lesson marks its linked lesson plan as completed. */
    private function syncLessonPlan(TeachingLog $log): void
    {
        if ($log->lesson_plan_id && $log->coverage === 'completed') {
            LessonPlan::whereKey($log->lesson_plan_id)->update(['status' => 'completed']);
        }
    }

    private function rules(): array
    {
        return [
            'log_date' => ['required', 'date'],
            'period' => ['nullable', 'integer', 'min:1', 'max:12'],
            'teacher_id' => ['required', 'exists:teachers,id'],
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'lesson_plan_id' => ['nullable', 'exists:lesson_plans,id'],
            'topic_covered' => ['required', 'string', 'max:255'],
            'coverage' => ['required', Rule::in(TeachingLog::COVERAGES)],
            'homework_given' => ['nullable', 'string', 'max:255'],
            'students_present' => ['nullable', 'integer', 'min:0', 'max:500'],
            'students_absent' => ['nullable', 'integer', 'min:0', 'max:500'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
