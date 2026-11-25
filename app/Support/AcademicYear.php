<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AcademicYear
{
    // Billing periods used by fees (and later payroll)
    public const PERIODS = [
        'Baisakh', 'Jestha', 'Ashadh', 'Shrawan', 'Bhadra', 'Ashwin',
        'Kartik', 'Mangsir', 'Poush', 'Magh', 'Falgun', 'Chaitra',
        'Term 1', 'Term 2', 'Term 3', 'Annual',
    ];

    public static function current(): string
    {
        $y = now()->month >= 4 ? now()->year : now()->year - 1; // school year starts around April

        return $y.'-'.($y + 1);
    }

    public static function next(): string
    {
        [$a] = explode('-', self::current());

        return ($a + 1).'-'.($a + 2);
    }

    /** Every academic year already used in the system, plus the current and next one. Newest first. */
    public static function options(): array
    {
        $years = collect([self::current(), self::next()]);

        foreach (['fee_receipts', 'fee_structures', 'exams'] as $table) {
            if (Schema::hasTable($table)) {
                $years = $years->merge(DB::table($table)->distinct()->pluck('academic_year'));
            }
        }

        return $years->filter()->unique()->sortDesc()->values()->all();
    }
}
