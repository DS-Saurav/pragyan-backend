<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamMark;
use App\Models\ExamSubject;
use App\Models\SchoolClass;
use App\Models\StudentAssessment;
use App\Services\MarksheetLayout;
use App\Services\ResultService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class MarksEntryController extends Controller
{
    public function index(Request $request)
    {
        $exams = Exam::orderByDesc('start_date')->get();
        $exam = $exams->firstWhere('id', (int) $request->exam_id);

        $classes = $exam
            ? SchoolClass::whereIn('id', ExamSubject::where('exam_id', $exam->id)->pluck('school_class_id'))->orderBy('name')->get()
            : collect();
        $class = $classes->firstWhere('id', (int) $request->class_id);

        $examSubjects = ($exam && $class)
            ? ExamSubject::with('subject')->where('exam_id', $exam->id)->where('school_class_id', $class->id)->get()
                ->sortBy(fn ($e) => $e->subject->name ?? '')->values()
            : collect();
        $current = $examSubjects->firstWhere('id', (int) $request->exam_subject_id);

        $students = collect();
        $marks = collect();
        if ($current) {
            $students = ResultService::students($class->id);
            $marks = ExamMark::where('exam_subject_id', $current->id)->get()->keyBy('student_id');
        }

        return response()->json(compact('exams', 'exam', 'classes', 'class', 'examSubjects', 'current', 'students', 'marks'));
    }

    public function save(Request $request, ExamSubject $examSubject)
    {
        $examSubject->load('exam', 'subject');
        $action = $request->validate(['action' => 'required|in:draft,submit,lock'])['action'];

        if ($action === 'lock') {
            Gate::authorize('marks.lock');
        }

        if ($examSubject->exam->status === 'published') {
            return response()->json(["message" => "Validation failed."], 422);
        }
        if ($examSubject->marks_status === 'locked') {
            return response()->json(["message" => "Validation failed."], 422);
        }

        $students = ResultService::students($examSubject->school_class_id);
        $hasTheory = (float) $examSubject->theory_full > 0;
        $hasPractical = (float) $examSubject->practical_full > 0;

        $problems = [];
        $payload = [];
        $missing = 0;

        foreach ($students as $s) {
            $row = $request->input("marks.{$s->id}", []);
            $absent = ! empty($row['absent']);
            $label = ResultService::studentName($s);

            $theory = $hasTheory ? ($row['theory'] ?? null) : null;
            $practical = $hasPractical ? ($row['practical'] ?? null) : null;

            if ($absent) {
                $theory = $practical = null;
            } else {
                foreach ([['theory', $theory, $examSubject->theory_full], ['practical', $practical, $examSubject->practical_full]] as [$kind, $v, $max]) {
                    if ($v === null || $v === '') {
                        continue;
                    }
                    if (! is_numeric($v) || (float) $v < 0 || (float) $v > (float) $max) {
                        $problems[] = "{$label}: {$kind} marks must be between 0 and ".ResultService::n($max).'.';
                    }
                }

                if ($action !== 'draft') {
                    $noTheory = $hasTheory && ($theory === null || $theory === '');
                    $noPractical = $hasPractical && ($practical === null || $practical === '');
                    if ($noTheory || $noPractical) {
                        $missing++;
                    }
                }
            }

            $payload[$s->id] = [
                $absent,
                ($theory === null || $theory === '') ? null : (float) $theory,
                ($practical === null || $practical === '') ? null : (float) $practical,
            ];
        }

        if ($missing > 0) {
            $problems[] = "{$missing} student(s) still have missing marks. Enter marks or tick Absent before submitting.";
        }
        if ($problems) {
            return response()->json(["message" => "Validation failed."], 422);
        }

        DB::transaction(function () use ($payload, $examSubject, $action) {
            foreach ($payload as $studentId => [$absent, $theory, $practical]) {
                if (! $absent && $theory === null && $practical === null) {
                    ExamMark::where('exam_subject_id', $examSubject->id)->where('student_id', $studentId)->delete();

                    continue;
                }

                ExamMark::updateOrCreate(
                    ['exam_subject_id' => $examSubject->id, 'student_id' => $studentId],
                    ['theory_marks' => $theory, 'practical_marks' => $practical, 'is_absent' => $absent]
                );
            }

            $examSubject->update(['marks_status' => ['draft' => 'draft', 'submit' => 'submitted', 'lock' => 'locked'][$action]]);
        });

        $message = ['draft' => 'Draft saved.', 'submit' => 'Marks submitted for admin publishing.', 'lock' => 'Marks locked.'][$action];

        return response()->json(["message" => $message]);
    }

    public function unlock(ExamSubject $examSubject)
    {
        if ($examSubject->exam->status === 'published') {
            return response()->json(["message" => "Validation failed."], 422);
        }

        $examSubject->update(['marks_status' => 'draft']);

        return response()->json(["message" => 'Marks unlocked. They are back in draft.']);
    }

    /**
     * Assessment-indicator entry (Stage P3I): select an examination and a
     * class, then record one rating per indicator per student. Indicators
     * and their STABLE keys come from the administrator-managed marksheet
     * layout; the rating scale is config "school.assessments.scale".
     * Read page requires marks.view; saving requires marks.enter (route
     * middleware), mirroring the marks themselves.
     */
    public function assessments(Request $request)
    {
        $exams = Exam::orderByDesc('start_date')->get();
        $exam = $exams->firstWhere('id', (int) $request->exam_id);

        $classes = $exam
            ? SchoolClass::whereIn('id', ExamSubject::where('exam_id', $exam->id)->pluck('school_class_id'))->orderBy('name')->get()
            : collect();
        $class = $classes->firstWhere('id', (int) $request->class_id);

        $students = $class ? ResultService::students($class->id) : collect();
        $indicators = MarksheetLayout::load()->assessmentRows; // [key, label] pairs

        $ratings = collect();
        if ($exam && $students->isNotEmpty() && $indicators !== []) {
            $ratings = StudentAssessment::where('exam_id', $exam->id)
                ->whereIn('student_id', $students->pluck('id'))
                ->get()
                ->groupBy('student_id')
                ->map(fn ($rows) => $rows->pluck('rating', 'indicator_key'));
        }

        return response()->json(compact(
            'exams', 'exam', 'classes', 'class', 'students', 'indicators', 'ratings',
        ));
    }

    /**
     * Save assessment ratings for one examination from the entry form
     * (Stage P3I).
     *
     * Reuses the marks-entry authorization (can:marks.enter) and accepts
     * only:
     * - students of the chosen class (roster members; tampered student IDs
     *   are rejected);
     * - indicator keys currently configured in the marksheet layout
     *   (unknown keys are rejected);
     * - rating values from config "school.assessments.scale" (unsupported
     *   values are rejected); blank cells DELETE the saved rating, i.e.
     *   unset stays unset and no rating is ever auto-assigned.
     *
     * Like the marks themselves, a published exam must be unpublished
     * first, so issuing mark sheets never changes underneath them.
     */
    public function saveAssessments(Request $request, Exam $exam)
    {
        Gate::authorize('marks.enter');

        $validated = $request->validate([
            'class_id' => ['required', 'integer', Rule::exists('school_classes', 'id')],
        ]);

        if ($exam->status === 'published') {
            return response()->json(["message" => "Validation failed."], 422);
        }

        $indicators = MarksheetLayout::load()->assessmentRows;
        if ($indicators === []) {
            return response()->json(["message" => "Validation failed."], 422);
        }
        $indicatorKeys = array_column($indicators, 'key');
        $scaleKeys = array_keys(config('school.assessments.scale', []));

        $students = ResultService::students($validated['class_id'])
            ->map(fn ($s) => (int) $s->id)
            ->all();

        $problems = [];
        $payload = []; // [studentId][indicatorKey] => rating, or null = delete

        foreach ((array) $request->input('ratings', []) as $studentId => $cells) {
            $studentId = (int) $studentId;
            if (! in_array($studentId, $students, true)) {
                $problems[] = "Assessments can only be saved for students of the selected class.";

                continue;
            }

            foreach ((array) $cells as $indicatorKey => $rating) {
                $indicatorKey = trim((string) $indicatorKey);
                if (! in_array($indicatorKey, $indicatorKeys, true)) {
                    $problems[] = "Unknown assessment indicator '{$indicatorKey}'.";

                    continue;
                }

                $rating = trim((string) $rating);
                if ($rating === '') {
                    $payload[$studentId][$indicatorKey] = null; // explicit unset

                    continue;
                }

                if (! in_array($rating, $scaleKeys, true)) {
                    $problems[] = "Unsupported rating '{$rating}' (allowed: ".implode(', ', $scaleKeys).').';

                    continue;
                }

                $payload[$studentId][$indicatorKey] = $rating;
            }
        }

        if ($problems) {
            return response()->json(["message" => "Validation failed."], 422);
        }

        DB::transaction(function () use ($payload, $exam) {
            foreach ($payload as $studentId => $cells) {
                foreach ($cells as $indicatorKey => $rating) {
                    if ($rating === null) {
                        StudentAssessment::where('exam_id', $exam->id)
                            ->where('student_id', $studentId)
                            ->where('indicator_key', $indicatorKey)
                            ->delete();

                        continue;
                    }

                    StudentAssessment::updateOrCreate(
                        ['exam_id' => $exam->id, 'student_id' => $studentId, 'indicator_key' => $indicatorKey],
                        ['rating' => $rating]
                    );
                }
            }
        });

        return response()->json(["message" => "Assessments for {$exam->name} ({$exam->academic_year}) saved successfully!"]);
    }
}
