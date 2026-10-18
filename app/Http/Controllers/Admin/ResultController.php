<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\ResultService;
use Illuminate\Http\Request;

class ResultController extends Controller
{
    public function __construct(private ResultService $results) {}

    public function index(Request $request)
    {
        $exams = Exam::orderByDesc('start_date')->get();
        $exam = $exams->firstWhere('id', (int) $request->exam_id);

        $classes = $exam
            ? SchoolClass::whereIn('id', ExamSubject::where('exam_id', $exam->id)->pluck('school_class_id'))->orderBy('name')->get()
            : collect();
        $class = $classes->firstWhere('id', (int) $request->class_id);

        $data = ($exam && $class) ? $this->results->classResults($exam, $class) : null;

        return view('admin.results.index', compact('exams', 'exam', 'classes', 'class', 'data'));
    }

    public function ledger(Exam $exam, SchoolClass $schoolClass)
    {
        return view('admin.results.ledger', ['exam' => $exam, 'class' => $schoolClass] + $this->results->classResults($exam, $schoolClass));
    }

    public function marksheets(Exam $exam, SchoolClass $schoolClass)
    {
        return view('admin.results.marksheet', ['exam' => $exam, 'class' => $schoolClass] + $this->results->classResults($exam, $schoolClass));
    }

    public function marksheet(Exam $exam, Student $student)
    {
        $class = SchoolClass::findOrFail($student->school_class_id);
        $data = $this->results->classResults($exam, $class);
        $data['rows'] = array_values(array_filter($data['rows'], fn ($r) => $r['student']->id === $student->id));

        return view('admin.results.marksheet', ['exam' => $exam, 'class' => $class] + $data);
    }
}
