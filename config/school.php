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
];
