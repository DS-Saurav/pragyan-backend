<?php

// config/school.php
// Used on hall tickets (and anything else that needs school details).
// Put the values in .env, or type them directly here.

return [
    'address' => env('SCHOOL_ADDRESS', ''),
    'phone' => env('SCHOOL_PHONE', ''),
    'email' => env('SCHOOL_EMAIL', ''),

    // Path inside /public, e.g. 'images/logo.png'. Leave empty to show 🏫
    'logo' => env('SCHOOL_LOGO', ''),

    'hall_ticket_instructions' => [
        'Bring this hall ticket to every examination. Entry is not allowed without it.',
        'Be seated in the examination hall 15 minutes before the exam begins.',
        'Mobile phones, smart watches and other electronic devices are strictly prohibited.',
        'Use of unfair means will lead to cancellation of the examination.',
        'Do not write anything on this hall ticket.',
    ],

    // Student health-measurement bounds (Stage P3G). Change here or via
    // .env; validation rejects anything outside the range or outside the
    // decimal precision the columns (decimal(4,1)) and these bounds allow.
    'measurements' => [
        'height_cm_min' => env('MEASUREMENT_HEIGHT_CM_MIN', 30),
        'height_cm_max' => env('MEASUREMENT_HEIGHT_CM_MAX', 230),
        'weight_kg_min' => env('MEASUREMENT_WEIGHT_KG_MIN', 1),
        'weight_kg_max' => env('MEASUREMENT_WEIGHT_KG_MAX', 200),
        // Max decimal places for height_cm/weight_kg (matches decimal(4,1)).
        'decimal_places' => 1,
    ],

    // Bikram Sambat date-of-birth validation (Stage P3H). The stored B.S.
    // date is only format/range-checked (see the controller): full
    // chronological validation would require published B.S. calendar
    // data, which no installed dependency provides.
    'dob_bs' => [
        'min_year' => env('DOB_BS_MIN_YEAR', 2000),
        'max_year' => env('DOB_BS_MAX_YEAR', 2100),
    ],

    // Assessment-indicator rating scale (Stage P3I). Machine keys are the
    // stable storage identity (student_assessments.rating); their labels
    // are configurable here and rendered at display time. These ratings
    // are NOT subject grades, CDC grade points or GPA inputs.
    'assessments' => [
        'scale' => [
            'excellent' => 'Excellent',
            'good' => 'Good',
            'satisfactory' => 'Satisfactory',
            'needs_improvement' => 'Needs Improvement',
        ],
    ],
];
