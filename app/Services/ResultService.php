<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamMark;
use App\Models\ExamSubject;
use App\Models\SchoolClass;
use App\Models\Student;

class ResultService
{
    // Column names in YOUR students table. Change here if they differ.
    public const ROLL = 'roll_no';

    public const ADMISSION = 'admission_no';

    public static function students(int $classId)
    {
        return Student::where('school_class_id', $classId)
            ->orderByRaw('CAST('.self::ROLL.' AS UNSIGNED)')
            ->orderBy('id')
            ->get();
    }

    public static function studentName($s): string
    {
        $name = trim(($s->first_name ?? '').' '.($s->last_name ?? ''));

        return $name !== '' ? $name : (string) ($s->name ?? '');
    }

    public static function roll($s): string
    {
        return (string) ($s->{self::ROLL} ?? '');
    }

    public static function admission($s): string
    {
        return (string) ($s->{self::ADMISSION} ?? '');
    }

    /** 75.00 -> 75, 62.50 -> 62.5, null -> — */
    public static function n($v): string
    {
        if ($v === null || $v === '') {
            return '—';
        }

        return rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    }

    public function subjectResult(ExamSubject $es, ?ExamMark $mark): array
    {
        $absent = (bool) ($mark?->is_absent);
        $theory = $mark?->theory_marks;
        $practical = $mark?->practical_marks;
        $entered = $mark !== null && ($absent || $theory !== null || $practical !== null);

        $full = (float) $es->theory_full + (float) $es->practical_full;
        $total = (float) $theory + (float) $practical;
        $percent = $full > 0 ? $total / $full * 100 : 0;

        $theoryOk = (float) $es->theory_full <= 0 || ($theory !== null && $theory >= (float) $es->theory_pass);
        $practicalOk = (float) $es->practical_full <= 0 || ($practical !== null && $practical >= (float) $es->practical_pass);
        $pass = $entered && ! $absent && $theoryOk && $practicalOk;

        if ($pass) {
            $g = GradeScale::forPercent($percent);
            if ($g['grade'] === 'NG') {
                $g = GradeScale::lowest();
            }
        } else {
            $g = [
                'grade' => $absent ? 'AB' : ($entered ? 'NG' : '—'),
                'point' => 0.0,
                'remark' => $absent ? 'Absent' : ($entered ? 'Not Graded' : ''),
            ];
        }

        return [
            'theory' => $theory,
            'practical' => $practical,
            'total' => $total,
            'full' => $full,
            'percent' => $percent,
            'grade' => $g['grade'],
            'point' => $g['point'],
            'remark' => $g['remark'],
            'pass' => $pass,
            'absent' => $absent,
            'entered' => $entered,
        ];
    }

    public function classResults(Exam $exam, SchoolClass $class): array
    {
        $subjects = ExamSubject::with('subject')
            ->where('exam_id', $exam->id)
            ->where('school_class_id', $class->id)
            ->get()
            ->sortBy(fn ($e) => $e->subject->name ?? '')
            ->values();

        $students = self::students($class->id);
        $marks = ExamMark::whereIn('exam_subject_id', $subjects->pluck('id'))->get()->groupBy('student_id');

        $rows = [];
        foreach ($students as $s) {
            $mine = ($marks->get($s->id) ?? collect())->keyBy('exam_subject_id');
            $subs = [];
            $total = 0;
            $full = 0;
            $points = 0;
            $complete = $subjects->isNotEmpty();
            $allPass = true;
            $failed = [];

            foreach ($subjects as $es) {
                $r = $this->subjectResult($es, $mine->get($es->id));
                $subs[$es->id] = $r;
                $total += $r['total'];
                $full += $r['full'];
                $points += $r['point'];

                if (! $r['entered']) {
                    $complete = false;
                }
                if (! $r['pass']) {
                    $allPass = false;
                }
                if ($r['entered'] && ! $r['pass']) {
                    $failed[] = $es->subject->name ?? '';
                }
            }

            $percent = $full > 0 ? $total / $full * 100 : 0;
            $result = ! $complete ? 'INCOMPLETE' : ($allPass ? 'PASS' : 'FAIL');

            $rows[] = [
                'student' => $s,
                'name' => self::studentName($s),
                'roll' => self::roll($s),
                'admission' => self::admission($s),
                'subjects' => $subs,
                'total' => $total,
                'full' => $full,
                'percent' => $percent,
                'gpa' => $subjects->count() ? $points / $subjects->count() : 0,
                'overall_grade' => $complete ? GradeScale::forPercent($percent)['grade'] : '—',
                'division' => $result === 'PASS' ? $this->division($percent) : '—',
                'result' => $result,
                'failed' => $failed,
                'rank' => null,
            ];
        }

        // Rank among passed students only. Equal totals share a rank.
        $passed = collect($rows)->where('result', 'PASS')->sortByDesc('total')->values();
        $rankMap = [];
        $rank = 0;
        $pos = 0;
        $prev = null;
        foreach ($passed as $r) {
            $pos++;
            if ($prev === null || $r['total'] !== $prev) {
                $rank = $pos;
            }
            $prev = $r['total'];
            $rankMap[$r['student']->id] = $rank;
        }
        foreach ($rows as &$r) {
            $r['rank'] = $rankMap[$r['student']->id] ?? null;
        }
        unset($r);

        $collection = collect($rows);
        $appeared = $collection->where('result', '!=', 'INCOMPLETE')->count();
        $passedCount = $collection->where('result', 'PASS')->count();

        return [
            'subjects' => $subjects,
            'rows' => $rows,
            'summary' => [
                'students' => count($rows),
                'appeared' => $appeared,
                'passed' => $passedCount,
                'failed' => $collection->where('result', 'FAIL')->count(),
                'incomplete' => $collection->where('result', 'INCOMPLETE')->count(),
                'pass_percent' => $appeared ? round($passedCount / $appeared * 100, 1) : 0,
                'highest' => $collection->where('result', 'PASS')->max('total') ?? 0,
            ],
        ];
    }

    private function division(float $percent): string
    {
        return match (true) {
            $percent >= 80 => 'Distinction',
            $percent >= 60 => 'First Division',
            $percent >= 45 => 'Second Division',
            default => 'Third Division',
        };
    }
}
