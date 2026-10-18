<?php

namespace App\Services;

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

    public static function forPercent(float $percent): array
    {
        foreach (self::BANDS as [$min, $grade, $point, $remark]) {
            if ($percent >= $min) {
                return ['grade' => $grade, 'point' => $point, 'remark' => $remark];
            }
        }

        return ['grade' => 'NG', 'point' => 0.0, 'remark' => 'Not Graded'];
    }

    public static function lowest(): array
    {
        [, $grade, $point, $remark] = self::BANDS[count(self::BANDS) - 1];

        return ['grade' => $grade, 'point' => $point, 'remark' => $remark];
    }
}
