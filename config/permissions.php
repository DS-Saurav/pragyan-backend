<?php

return [

    'role_labels' => [
        'admin' => 'Admin',
        'principal' => 'Principal',
        'academic_coordinator' => 'Academic coordinator',
        'discipline_coordinator' => 'Discipline coordinator',
        'eca_coordinator' => 'ECA coordinator',
        'teacher' => 'Teacher',
        'librarian' => 'Librarian',
        'accountant' => 'Accountant',
        'parent' => 'Parent',
        'student' => 'Student',
    ],

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
        'conduct.view' => 'View conduct records',
        'conduct.manage' => 'Manage conduct records',

        'activities.view' => 'View ECA activities',
        'activities.manage' => 'Manage ECA activities',
        'calendar.view' => 'View the internal school calendar',
        'calendar.manage' => 'Manage calendar and school events',
        'public_calendar.manage' => 'Publish or change public school calendar content',

        'admissions.manage' => 'Review admission applications and change their status',
        'public_admissions.manage' => 'Manage public admissions cycles and content',
        'library.manage' => 'Manage library',
        'fees.view' => 'View fee receipts and dues',
        'fees.collect' => 'Record fee payments',
        'fees.refund' => 'Refund fee receipts',
        'fees.delete' => 'Delete fee receipts',
        'fees.structure' => 'Edit the fee structure and fee heads',
        'fees.generate' => 'Generate fee bills for a class',

        'payroll.view' => 'View salary slips',
        'payroll.manage' => 'Generate and edit salary slips',
        'payroll.pay' => 'Mark salary as paid',

        'accounts.view' => 'View daily accounts',
        'accounts.manage' => 'Record other income and expenses',

        'helpdesk.view' => 'View own helpdesk tickets and reply (all tickets with manage)',
        'helpdesk.create' => 'Raise a helpdesk ticket',
        'helpdesk.manage' => 'Assign tickets, change status and priority, add internal notes',
        'helpdesk.delete' => 'Delete helpdesk tickets',

        'complaints.view' => 'View own complaints and reply (all complaints with manage)',
        'complaints.create' => 'File a complaint, optionally anonymously',
        'complaints.manage' => 'Assign, resolve, dismiss and escalate complaints',
        'complaints.delete' => 'Delete complaints',

        'documents.view' => 'View and download documents meant for your role',
        'documents.manage' => 'Add, edit, publish and replace documents',
        'documents.delete' => 'Delete documents',

        'users.view' => 'View users and staff',
        'users.manage' => 'Create and edit users, reset passwords, suspend',
        'users.roles' => 'Assign roles to users',
        'users.delete' => 'Delete users',

        'media.view' => 'Inspect public editorial media in the Media Library',
        'media.manage' => 'Upload, review, publish and archive public editorial media',
        'media.upload' => 'Upload and prepare public editorial media',
        'media.review' => 'Review public editorial media before publication',
        'media.publish' => 'Publish, withdraw and archive approved public editorial media',

        'news.author' => 'Create and edit draft public news stories',
        'news.review' => 'Review public news stories before publication',
        'news.publish' => 'Publish, schedule, withdraw and archive public news stories',

        'gallery.author' => 'Create and edit draft public Gallery albums',
        'gallery.review' => 'Review public Gallery albums before publication',
        'gallery.publish' => 'Publish, schedule, withdraw and archive public Gallery albums',

        'public_pages.author' => 'Create and edit draft About and Academics content',
        'public_pages.review' => 'Review About and Academics content before publication',
        'public_pages.publish' => 'Publish, schedule, withdraw and archive About and Academics content',

        'public_team.author' => 'Create and edit draft public Team profiles',
        'public_team.review' => 'Review public Team profiles before publication',
        'public_team.publish' => 'Publish, schedule, withdraw and archive public Team profiles',

        'public_identity.manage' => 'Update verified public contact and visit details',
        'public_homepage.manage' => 'Update public homepage content and legacy hero settings',

        'notices.manage' => 'Create and delete school notices',
        'notices.author' => 'Create and edit draft public notices',
        'notices.review' => 'Review public notices before publication',
        'notices.publish' => 'Publish, schedule, withdraw and archive public notices',

        'settings.view' => 'View school settings',
        'settings.manage' => 'Update school details, branding and academic defaults',
    ],

    // role => permissions. '*' means everything.
    'roles' => [

        'admin' => ['*'],

        'principal' => [
            'lessonplans.view', 'teachinglogs.view',
            'exams.view', 'marks.view', 'results.view', 'results.publish',
            'discipline.view', 'discipline.manage',
            'conduct.view',
            'activities.view', 'fees.view',
            'admissions.manage',
            'helpdesk.view', 'helpdesk.create', 'helpdesk.manage',
            'complaints.view', 'complaints.create', 'complaints.manage',
            'documents.view', 'documents.manage',
            'settings.view', 'settings.manage',
        ],

        'academic_coordinator' => [
            'lessonplans.view', 'lessonplans.manage',
            'teachinglogs.view',
            'exams.view', 'exams.manage',
            'marks.view', 'marks.enter', 'marks.lock',
            'results.view',
            'admissions.manage',
            'helpdesk.view', 'helpdesk.create',
            'complaints.view', 'complaints.create',
            'documents.view', 'documents.manage',
        ],

        'discipline_coordinator' => [
            'discipline.view', 'discipline.report', 'discipline.manage',
            'conduct.view', 'conduct.manage',
            'helpdesk.view', 'helpdesk.create',
            'complaints.view', 'complaints.create',
            'documents.view',
        ],

        'eca_coordinator' => [
            'activities.view', 'activities.manage',
            'calendar.view', 'calendar.manage',
            'helpdesk.view', 'helpdesk.create',
            'complaints.view', 'complaints.create',
            'documents.view',
        ],

        'teacher' => [
            'lessonplans.view', 'lessonplans.manage',
            'teachinglogs.view', 'teachinglogs.manage',
            'exams.view', 'marks.view', 'marks.enter',
            'discipline.view', 'discipline.report',
            'helpdesk.view', 'helpdesk.create',
            'complaints.view', 'complaints.create',
            'documents.view',
        ],

        'librarian' => [
            'library.manage',
            'helpdesk.view', 'helpdesk.create',
            'complaints.view', 'complaints.create',
            'documents.view',
        ],

        'accountant' => [
            'fees.view', 'fees.collect', 'fees.refund', 'fees.delete', 'fees.structure', 'fees.generate',
            'payroll.view', 'payroll.manage', 'payroll.pay',
            'accounts.view', 'accounts.manage',
            'helpdesk.view', 'helpdesk.create', 'helpdesk.manage',
            'complaints.view', 'complaints.create',
            'documents.view',
        ],

        // Added: students and parents can use the support modules, and nothing else here.
        'student' => [
            'helpdesk.view', 'helpdesk.create',
            'complaints.view', 'complaints.create',
            'documents.view',
        ],

        'parent' => [
            'helpdesk.view', 'helpdesk.create',
            'complaints.view', 'complaints.create',
            'documents.view',
        ],

    ],
];
