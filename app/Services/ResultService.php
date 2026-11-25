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

        // CDC Letter Grading Directive, 2083, Annexure-1: below 35% is NG
        // ("no grade point") regardless of component passes, so a subject
        // only counts as passed when the aggregate reaches 35% as well.
        $pass = $entered && ! $absent && $theoryOk && $practicalOk
            && $percent >= GradeScale::NG_MIN_PERCENT;

        if ($pass) {
            $g = GradeScale::forPercent($percent);
        } else {
            // NG/absent/unentered carry no grade point (Annexure-1: NG row
            // shows "-"). They must never feed a numeric GPA contribution.
            $g = [
                'grade' => $absent ? 'AB' : ($entered ? 'NG' : '—'),
                'point' => null,
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
            // Display-only per-component (TH/IN) breakdown for the separate
            // component rows on the marksheet (Stage P3J). Adding this key
            // changes NO aggregate behaviour: the grade/point above, pass
            // logic and GPA math are exactly as before.
            'components' => $this->componentResults($es, $theory, $practical, $absent),
        ];
    }

    /**
     * Display-only TH/IN component breakdown (Stage P3J), derived purely
     * from the stored marks and the SAME GradeScale bands used everywhere:
     * each component's own percentage = obtained/full*100, graded through
     * GradeScale::forPercent (below 35% is NG with no grade point, per the
     * CDC directive). Components the exam subject does not offer
     * (full marks 0) get no row. Absence is preserved honestly: an absent
     * student shows AB per component; a component with NO recorded mark
     * shows an em dash (—) with NO grade - a missing mark is never
     * fabricated as zero or as NG.
     *
     * Component grades are display details only: they never feed pass/
     * fail, the aggregate subject grade, or any GPA calculation (those
     * stay in subjectResult()/classResults() exactly as they were).
     */
    private function componentResults(ExamSubject $es, $theory, $practical, bool $absent): array
    {
        $specs = [
            ['label' => 'TH', 'obtained' => $theory, 'full' => (float) $es->theory_full, 'pass' => $es->theory_pass],
            ['label' => 'IN', 'obtained' => $practical, 'full' => (float) $es->practical_full, 'pass' => $es->practical_pass],
        ];

        $out = [];
        foreach ($specs as $spec) {
            if ($spec['full'] <= 0) {
                continue; // this exam subject has no such component
            }

            if ($absent) {
                $out[] = [
                    'label' => $spec['label'], 'obtained' => null,
                    'full' => $spec['full'], 'pass' => $spec['pass'],
                    'percent' => null, 'grade' => 'AB', 'point' => null, 'remark' => 'Absent',
                ];

                continue;
            }

            $obtained = $spec['obtained'];
            if ($obtained === null || $obtained === '') {
                $out[] = [
                    'label' => $spec['label'], 'obtained' => null,
                    'full' => $spec['full'], 'pass' => $spec['pass'],
                    'percent' => null, 'grade' => '—', 'point' => null, 'remark' => '',
                ];

                continue;
            }

            $percent = (float) $obtained / $spec['full'] * 100;
            $g = GradeScale::forPercent($percent);
            $out[] = [
                'label' => $spec['label'], 'obtained' => (float) $obtained,
                'full' => $spec['full'], 'pass' => $spec['pass'],
                'percent' => $percent, 'grade' => $g['grade'], 'point' => $g['point'], 'remark' => $g['remark'],
            ];
        }

        return $out;
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

        // Credit-hour (weighted) basis per the owner's marksheet spec:
        // GPA = Σ(CH × GP) / ΣCH. It applies ONLY when every subject carries
        // valid credit-hours data — the admin marksheet-layout map is a
        // display-only artefact and must never feed this calculation.
        $chList = $subjects->mapWithKeys(fn (ExamSubject $es) => [$es->id => $this->creditHours($es)]);
        $weighted = $subjects->isNotEmpty() && $chList->every(fn ($ch) => $ch !== null);
        $chSum = (float) $chList->sum();

        $rows = [];
        foreach ($students as $s) {
            $mine = ($marks->get($s->id) ?? collect())->keyBy('exam_subject_id');
            $subs = [];
            $total = 0;
            $full = 0;
            $points = 0;       // Σ grade points (unweighted basis)
            $weightedSum = 0.0; // Σ(CH × GP) (weighted basis)
            $complete = $subjects->isNotEmpty();
            $allPass = true;
            $failed = [];

            foreach ($subjects as $es) {
                $r = $this->subjectResult($es, $mine->get($es->id));
                $subs[$es->id] = $r;
                $total += $r['total'];
                $full += $r['full'];

                // NG/absent/unentered have no grade point: they contribute 0.
                if ($r['point'] !== null) {
                    $points += $r['point'];
                    $weightedSum += $r['point'] * $chList[$es->id];
                }

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

            // Weighted only on the credit-hour basis; else the honest
            // unweighted mean. Two decimals so every screen agrees.
            if ($weighted) {
                $gpa = $chSum > 0 ? round($weightedSum / $chSum, 2) : 0.0;
            } else {
                $gpa = $subjects->count() ? round($points / $subjects->count(), 2) : 0.0;
            }

            $rows[] = [
                'student' => $s,
                'name' => self::studentName($s),
                'roll' => self::roll($s),
                'admission' => self::admission($s),
                'subjects' => $subs,
                'total' => $total,
                'full' => $full,
                'percent' => $percent,
                'gpa' => $gpa,
                'gpa_basis' => $weighted ? 'credit_hour' : 'unweighted',
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

    /**
     * Authoritative credit hours for a subject row, or null when absent or
     * invalid. Pre-migration the column does not exist, so this null-safe
     * getter is the only access point (never read it directly elsewhere).
     * A value must be numeric and strictly positive to be usable for
     * GPA = Σ(CH × GP) / ΣCH.
     */
    private function creditHours(ExamSubject $es): ?float
    {
        $ch = $es->getAttribute('credit_hours');

        if ($ch === null || $ch === '' || ! is_numeric($ch)) {
            return null;
        }

        $ch = (float) $ch;

        return $ch > 0 ? $ch : null;
    }
}
