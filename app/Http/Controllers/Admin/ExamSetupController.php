<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\SchoolClass;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ExamSetupController extends Controller
{
    public function show(Request $request, Exam $exam)
    {
        $classes = SchoolClass::orderBy('name')->get();
        $class = $classes->firstWhere('id', (int) $request->get('class_id', $classes->first()->id ?? 0));

        $rows = $class
            ? ExamSubject::with('subject')->where('exam_id', $exam->id)->where('school_class_id', $class->id)->get()
                ->sortBy(fn ($r) => $r->subject->name ?? '')->values()
            : collect();

        $available = Subject::whereNotIn('id', $rows->pluck('subject_id'))->orderBy('name')->get();

        return view('admin.exams.setup', compact('exam', 'classes', 'class', 'rows', 'available')
            + ['creditHoursReady' => $this->creditHoursReady()]);
    }

    /** Create rows for every subject assigned to the class (falls back to all subjects). */
    public function load(Request $request, Exam $exam)
    {
        if ($r = $this->blockIfPublished($exam)) {
            return $r;
        }

        $class = SchoolClass::findOrFail($request->validate(['class_id' => 'required|exists:school_classes,id'])['class_id']);

        $subjects = Subject::orderBy('name')->get()->filter(function ($s) use ($class) {
            $ac = $s->assigned_classes;
            if (is_string($ac)) {
                $ac = json_decode($ac, true);
            }

            return is_array($ac) && in_array($class->name, $ac, true);
        });

        if ($subjects->isEmpty()) {
            $subjects = Subject::orderBy('name')->get();
        }

        $added = 0;
        foreach ($subjects as $s) {
            $es = $this->firstOrCreateRow($exam, $class->id, $s);
            $added += $es->wasRecentlyCreated ? 1 : 0;
        }

        return redirect()->route('admin.exams.setup', [$exam, 'class_id' => $class->id])
            ->with('success', "{$added} subject(s) loaded. Adjust marks if needed, then click Save.");
    }

    public function add(Request $request, Exam $exam)
    {
        if ($r = $this->blockIfPublished($exam)) {
            return $r;
        }

        $data = $request->validate([
            'class_id' => 'required|exists:school_classes,id',
            'subject_id' => 'required|exists:subjects,id',
        ]);

        $this->firstOrCreateRow($exam, (int) $data['class_id'], Subject::findOrFail($data['subject_id']));

        return redirect()->route('admin.exams.setup', [$exam, 'class_id' => $data['class_id']])
            ->with('success', 'Subject added.');
    }

    public function save(Request $request, Exam $exam)
    {
        if ($r = $this->blockIfPublished($exam)) {
            return $r;
        }

        $data = $request->validate([
            'class_id' => 'required|exists:school_classes,id',
            'rows' => 'required|array',
            'rows.*.exam_date' => 'nullable|date',
            'rows.*.start_time' => 'nullable|date_format:H:i',
            'rows.*.end_time' => 'nullable|date_format:H:i',
            'rows.*.theory_full' => 'required|numeric|min:0|max:1000',
            'rows.*.theory_pass' => 'required|numeric|min:0|max:1000',
            'rows.*.practical_full' => 'nullable|numeric|min:0|max:1000',
            'rows.*.practical_pass' => 'nullable|numeric|min:0|max:1000',
            // Credit hours (CDC dafa 1(घ)) must be finite and strictly
            // positive, at most two decimals, fitting decimal(5,2).
            // Blank (null) means "unset" - never a confirmed zero.
            // Array syntax because the regex itself contains a pipe.
            'rows.*.credit_hours' => [
                'nullable', 'numeric', 'min:0.01', 'max:99.99',
                'regex:/^\d{1,2}\.\d{1,2}$|^\d{1,2}$/',
            ],
        ]);

        $creditHoursReady = $this->creditHoursReady();

        // Only when an administrator actually entered a credit hour: refuse
        // to save at all if the column does not exist yet. Silently
        // claiming success while discarding the input is forbidden.
        if (! $creditHoursReady) {
            foreach ($data['rows'] as $row) {
                if (array_key_exists('credit_hours', $row) && $row['credit_hours'] !== null) {
                    return back()->withErrors(
                        ['rows' => 'Credit hours cannot be saved yet: the pending exam_subjects.credit_hours migration is not applied. Nothing was saved - apply the migration first, then submit the setup again.']
                    );
                }
            }
        }

        foreach ($data['rows'] as $id => $row) {
            $es = ExamSubject::with('subject')->where('exam_id', $exam->id)->find($id);
            if (! $es) {
                continue;
            }

            $tf = (float) $row['theory_full'];
            $tp = (float) $row['theory_pass'];
            $pf = (float) ($row['practical_full'] ?? 0);
            $pp = (float) ($row['practical_pass'] ?? 0);

            if ($tp > $tf || $pp > $pf) {
                return back()->withErrors(['rows' => 'Pass marks cannot be higher than full marks ('.($es->subject->name ?? '').').']);
            }

            if (! empty($row['start_time']) && ! empty($row['end_time']) && $row['end_time'] <= $row['start_time']) {
                return back()->withErrors(['rows' => 'End time must be after start time ('.($es->subject->name ?? '').').']);
            }

            $update = [
                'exam_date' => $row['exam_date'] ?? null,
                'start_time' => $row['start_time'] ?? null,
                'end_time' => $row['end_time'] ?? null,
                'theory_full' => $tf,
                'theory_pass' => $tp,
                'practical_full' => $pf,
                'practical_pass' => $pp,
            ];

            if ($creditHoursReady) {
                // Blank input (null) = unset/clear; a value was validated
                // as finite and strictly positive above.
                $update['credit_hours'] = $row['credit_hours'] ?? null;
            }

            $es->update($update);
        }

        return redirect()->route('admin.exams.setup', [$exam, 'class_id' => $data['class_id']])
            ->with('success', 'Exam setup saved.');
    }

    /** Copy this class's setup to other classes. */
    public function copy(Request $request, Exam $exam)
    {
        if ($r = $this->blockIfPublished($exam)) {
            return $r;
        }

        $data = $request->validate([
            'class_id' => 'required|exists:school_classes,id',
            'target_ids' => 'required|array|min:1',
            'target_ids.*' => 'exists:school_classes,id',
        ]);

        $source = ExamSubject::where('exam_id', $exam->id)->where('school_class_id', $data['class_id'])->get();
        $created = 0;

        // Credit hours copy only when the authoritative column exists.
        $copyFields = ['exam_date', 'start_time', 'end_time', 'theory_full', 'theory_pass', 'practical_full', 'practical_pass'];
        if ($this->creditHoursReady()) {
            $copyFields[] = 'credit_hours';
        }

        foreach ($data['target_ids'] as $targetId) {
            if ((int) $targetId === (int) $data['class_id']) {
                continue;
            }
            foreach ($source as $row) {
                $es = ExamSubject::firstOrCreate(
                    ['exam_id' => $exam->id, 'school_class_id' => $targetId, 'subject_id' => $row->subject_id],
                    $row->only($copyFields)
                );
                $created += $es->wasRecentlyCreated ? 1 : 0;
            }
        }

        return redirect()->route('admin.exams.setup', [$exam, 'class_id' => $data['class_id']])
            ->with('success', "Copied. {$created} subject row(s) created in the other classes.");
    }

    public function remove(Exam $exam, ExamSubject $examSubject)
    {
        if ($r = $this->blockIfPublished($exam)) {
            return $r;
        }

        $classId = $examSubject->school_class_id;
        $examSubject->delete(); // also deletes marks entered for it

        return redirect()->route('admin.exams.setup', [$exam, 'class_id' => $classId])
            ->with('success', 'Subject removed from this exam.');
    }

    private function firstOrCreateRow(Exam $exam, int $classId, Subject $s): ExamSubject
    {
        $defaults = [
            'theory_full' => $s->max_marks ?: 100,
            'theory_pass' => $s->passing_marks ?: 35,
            'practical_full' => 0,
            'practical_pass' => 0,
        ];

        // New rows start with an UNSET credit hour (never 0) when the
        // authoritative column exists.
        if ($this->creditHoursReady()) {
            $defaults['credit_hours'] = null;
        }

        return ExamSubject::firstOrCreate(
            ['exam_id' => $exam->id, 'school_class_id' => $classId, 'subject_id' => $s->id],
            $defaults
        );
    }

    /**
     * True when the authoritative credit-hours column exists. The pending
     * exam_subjects migration adds it; until then every save/entry path
     * degrades safely instead of discarding input silently.
     */
    private function creditHoursReady(): bool
    {
        return Schema::hasColumn('exam_subjects', 'credit_hours');
    }

    private function blockIfPublished(Exam $exam)
    {
        return $exam->status === 'published'
            ? back()->withErrors(['exam' => 'Results are published. Unpublish the exam to change its setup.'])
            : null;
    }
}
