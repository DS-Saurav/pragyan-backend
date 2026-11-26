<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\SchoolClass;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Http\Request;

class HallTicketController extends Controller
{
    /** Students below this attendance % are flagged on the selection list. */
    private const MIN_ATTENDANCE = 75;

    public function index(Request $request)
    {
        $request->validate([
            'exam_id' => 'nullable|exists:exams,id',
            'class_id' => 'nullable|exists:school_classes,id',
        ]);

        $exams = Exam::orderByDesc('id')->get();
        $classes = SchoolClass::orderBy('name')->orderBy('section')->get();

        $classLabels = $classes->mapWithKeys(fn ($c) => [$c->id => $this->classLabel($c)]);

        $exam = $request->filled('exam_id') ? $exams->firstWhere('id', (int) $request->exam_id) : null;
        $selectedClassId = $request->get('class_id');

        $tickets = collect();
        $classesWithoutSetup = collect();
        $examMeta = null;

        if ($exam) {
            $examMeta = [
                'id' => $exam->id,
                'name' => $exam->name ?? 'Examination',
                'year' => $this->field($exam, ['academic_year', 'year']),
                'start' => $this->fmtDate($this->field($exam, ['start_date', 'starts_on', 'from_date']), 'd M Y'),
                'end' => $this->fmtDate($this->field($exam, ['end_date', 'ends_on', 'to_date']), 'd M Y'),
            ];

            $students = Student::query()
                ->where('status', 'active')
                ->whereNotNull('school_class_id')
                ->when($selectedClassId, fn ($q) => $q->where('school_class_id', $selectedClassId))
                ->orderBy('school_class_id')
                ->orderBy('roll_no')
                ->get();

            // Exam subjects grouped by class
            $subjectsByClass = ExamSubject::where('exam_id', $exam->id)
                ->with('subject')
                ->get()
                ->groupBy('school_class_id');

            $attendance = $this->attendancePercent($students->pluck('id')->all());

            $tickets = $students->map(function ($s) use ($subjectsByClass, $classLabels, $attendance) {
                $rows = ($subjectsByClass[$s->school_class_id] ?? collect())
                    ->sortBy(fn ($es) => [$this->fmtDate($this->field($es, ['exam_date', 'date']), 'Y-m-d') ?? '9999-12-31', $es->id])
                    ->values()
                    ->map(function ($es) {
                        $date = $this->field($es, ['exam_date', 'date']);
                        $start = $this->field($es, ['start_time', 'time_from', 'time']);
                        $end = $this->field($es, ['end_time', 'time_to']);

                        $time = $this->fmtDate($start, 'h:i A');
                        if ($time && $end) {
                            $time .= ' - '.$this->fmtDate($end, 'h:i A');
                        }

                        return [
                            'name' => $es->subject->name ?? ($es->subject_name ?? 'Subject'),
                            'date' => $this->fmtDate($date, 'd M Y'),
                            'day' => $this->fmtDate($date, 'l'),
                            'time' => $time,
                        ];
                    })
                    ->all();

                return [
                    'id' => $s->id,
                    'name' => trim($s->first_name.' '.$s->last_name),
                    'class' => $classLabels[$s->school_class_id] ?? ($s->class_applied ?? '-'),
                    'class_id' => $s->school_class_id,
                    'roll' => $s->roll_no ?? '-',
                    'adm' => $s->adm_number ?? ('ADM-'.$s->id),
                    'dob' => $this->fmtDate($s->dob, 'd M Y'),
                    'gender' => $s->gender,
                    'photo' => ! empty($s->photo) ? asset('storage/'.$s->photo) : null,
                    'subjects' => $rows,
                    'attendance' => $attendance[$s->id] ?? null,
                ];
            })->values();

            // Classes that have students but no exam subjects set up yet
            $classesWithoutSetup = $tickets
                ->filter(fn ($t) => empty($t['subjects']))
                ->pluck('class')
                ->unique()
                ->values();
        }

        $school = [
            'name' => config('app.name'),
            'address' => config('school.address'),
            'phone' => config('school.phone'),
            'email' => config('school.email'),
            'logo' => config('school.logo') ? asset(config('school.logo')) : null,
        ];

        $instructions = config('school.hall_ticket_instructions', [
            'Bring this hall ticket to every examination. Entry is not allowed without it.',
            'Be seated in the examination hall 15 minutes before the exam begins.',
            'Mobile phones, smart watches and other electronic devices are strictly prohibited.',
            'Use of unfair means will lead to cancellation of the examination.',
            'Do not write anything on this hall ticket.',
        ]);

        $minAttendance = self::MIN_ATTENDANCE;

        return response()->json(compact(
            'exams', 'classes', 'classLabels', 'exam', 'examMeta', 'selectedClassId',
            'tickets', 'classesWithoutSetup', 'school', 'instructions', 'minAttendance'
        ));
    }

    /** Attendance % per student (present / late / half-day count as attended). */
    private function attendancePercent(array $studentIds): array
    {
        if (empty($studentIds)) {
            return [];
        }

        $rows = Attendance::whereIn('student_id', $studentIds)
            ->selectRaw("student_id, COUNT(*) AS total,
                SUM(CASE WHEN status IN ('present','late','half_day') THEN 1 ELSE 0 END) AS attended")
            ->groupBy('student_id')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[$r->student_id] = $r->total > 0 ? (int) round($r->attended / $r->total * 100) : null;
        }

        return $out;
    }

    private function classLabel($class): string
    {
        $label = trim(($class->name ?? '').' '.($class->section ?? ''));
        $year = $this->field($class, ['academic_year']);

        return $year ? "{$label} ({$year})" : $label;
    }

    /** First non-empty value among several possible column names. */
    private function field($model, array $keys)
    {
        foreach ($keys as $key) {
            $value = $model->{$key} ?? null;
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function fmtDate($value, string $format): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->format($format);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
