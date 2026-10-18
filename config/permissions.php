<?php

return [

    // permission => what it allows
    'permissions' => [
        'lessonplans.view' => 'View lesson plans',
        'lessonplans.manage' => 'Create, edit and delete lesson plans',
        'teachinglogs.view' => 'View teaching logbook',
        'teachinglogs.manage' => 'Add, edit and delete log entries',

        'exams.view' => 'View exams',
        'exams.manage' => 'Create exams and set up subjects and marks',
        'marks.view' => 'Open the marks entry page',
        'marks.enter' => 'Enter, save and submit marks',
        'marks.lock' => 'Lock and unlock marks',
        'results.view' => 'View results and marksheets',
        'results.publish' => 'Publish and unpublish results',

        'discipline.view' => 'View discipline incidents',
        'discipline.report' => 'Report a new incident',
        'discipline.manage' => 'Edit, notify parents and close cases',
        'discipline.delete' => 'Delete incident records',
        'conduct.manage' => 'Manage conduct records',

        'activities.view' => 'View ECA activities',
        'activities.manage' => 'Manage ECA activities',
        'calendar.manage' => 'Manage calendar and school events',

        'library.manage' => 'Manage library',
        'fees.manage' => 'Manage fees and accounts',
    ],

    // role => permissions. '*' means everything.
    'roles' => [

        'admin' => ['*'],

        'principal' => [
            'lessonplans.view', 'teachinglogs.view',
            'exams.view', 'marks.view', 'results.view', 'results.publish',
            'discipline.view', 'discipline.manage',
            'activities.view',
        ],

        'academic_coordinator' => [
            'lessonplans.view', 'lessonplans.manage',
            'teachinglogs.view',
            'exams.view', 'exams.manage',
            'marks.view', 'marks.enter', 'marks.lock',
            'results.view',
        ],

        'discipline_coordinator' => [
            'discipline.view', 'discipline.report', 'discipline.manage',
            'conduct.manage',
        ],

        'eca_coordinator' => [
            'activities.view', 'activities.manage',
            'calendar.manage',
        ],

        'teacher' => [
            'lessonplans.view', 'lessonplans.manage',
            'teachinglogs.view', 'teachinglogs.manage',
            'exams.view', 'marks.view', 'marks.enter',
            'discipline.view', 'discipline.report',
        ],

        'librarian' => ['library.manage'],
        'accountant' => ['fees.manage'],
    ],
];
