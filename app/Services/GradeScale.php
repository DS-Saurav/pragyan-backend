<?php

namespace App\Services;

/**
 * Grade determination per the CDC "Letter Grading Directive, 2083"
 * (लेटर ग्रेडिङ निर्देशिका, २०८३), Annexure-1 (dafa 5(1)), Curriculum
 * Development Centre, Sanothimi — school education (Grades 1-12):
 *
 *   ≥90%  → A+ → 4.0 → Outstanding    |   50-60% → C+ → 2.4 → Satisfactory
 *   80-90 → A  → 3.6 → Excellent      |   40-50% → C  → 2.0 → Acceptable
 *   70-80 → B+ → 3.2 → Very Good      |   35-40% → D  → 1.6 → Basic
 *   60-70 → B  → 2.8 → Good           |   below 35% → NG → no grade point
 *
 * Annexure-1 records NG (below 35%) with NO grade point ("-"), so
 * `point` is null there — it must never contribute a value to GPA math.
 * (Annexure-2's D+/D/E bands apply only to grade-increment exams for the
 * SEE-2079 cohort and are not used by this application's exam types.)
 */
class GradeScale
{
    // [minimum percent, grade, grade point, remark]. Edit to match your school.
    public const BANDS = [
        [90, 'A+', 4.0, 'Outstanding'],
        [80, 'A', 3.6, 'Excellent'],
        [70, 'B+', 3.2, 'Very Good'],
        [60, 'B', 2.8, 'Good'],
        [50, 'C+', 2.4, 'Satisfactory'],
        [40, 'C', 2.0, 'Acceptable'],
        [35, 'D', 1.6, 'Basic'],
    ];

    /** The percent below which a subject is Not Graded (Annexure-1 row 8). */
    public const NG_MIN_PERCENT = 35.0;

    public static function forPercent(float $percent): array
    {
        foreach (self::BANDS as [$min, $grade, $point, $remark]) {
            if ($percent >= $min) {
                return ['grade' => $grade, 'point' => $point, 'remark' => $remark];
            }
        }

        // NG carries no grade point per the directive's Annexure-1.
        return ['grade' => 'NG', 'point' => null, 'remark' => 'Not Graded'];
    }

    public static function lowest(): array
    {
        [, $grade, $point, $remark] = self::BANDS[count(self::BANDS) - 1];

        return ['grade' => $grade, 'point' => $point, 'remark' => $remark];
    }
}
