<?php

if (! function_exists('current_academic_year')) {
    /**
     * Current academic year expressed as the current Bikram Sambat year, e.g. "2083".
     *
     * The Nepali academic session rolls over at Baishakh 1 (approx. April 14),
     * which is also the day the Bikram Sambat year increments.
     */
    function current_academic_year(): string
    {
        $now = now(); // Carbon-aware, so the app and tests share one clock.

        $year = (int) $now->year;
        $month = (int) $now->month;
        $day = (int) $now->day;

        // Before Baishakh 1 the BS year is the Gregorian year + 56 (e.g. Jan 2026 -> 2082);
        // from April 14 onward it is + 57 (e.g. Oct 2026 -> 2083).
        $bsYear = ($month < 4 || ($month === 4 && $day < 14)) ? $year + 56 : $year + 57;

        return (string) $bsYear;
    }
}
