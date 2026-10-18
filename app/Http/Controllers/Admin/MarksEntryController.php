<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamMark;
use App\Models\ExamSubject;
use App\Models\SchoolClass;
use App\Services\ResultService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        return view('admin.marks.index', compact('exams', 'exam', 'classes', 'class', 'examSubjects', 'current', 'students', 'marks'));
    }

    public function save(Request $request, ExamSubject $examSubject)
    {
        $examSubject->load('exam', 'subject');
        $action = $request->validate(['action' => 'required|in:draft,submit,lock'])['action'];

        if ($examSubject->exam->status === 'published') {
            return back()->withErrors(['marks' => 'Results are published. Unpublish the exam to change marks.']);
        }
        if ($examSubject->marks_status === 'locked') {
            return back()->withErrors(['marks' => 'These marks are locked. Unlock them first.']);
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
            return back()->withErrors($problems)->withInput();
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

        return redirect()->route('admin.marks.index', [
            'exam_id' => $examSubject->exam_id,
            'class_id' => $examSubject->school_class_id,
            'exam_subject_id' => $examSubject->id,
        ])->with('success', $message);
    }

    public function unlock(ExamSubject $examSubject)
    {
        if ($examSubject->exam->status === 'published') {
            return back()->withErrors(['marks' => 'Unpublish the exam first.']);
        }

        $examSubject->update(['marks_status' => 'draft']);

        return back()->with('success', 'Marks unlocked. They are back in draft.');
    }
}
