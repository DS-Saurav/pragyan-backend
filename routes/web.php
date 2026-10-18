<?php

use App\Http\Controllers\Admin\AdmissionController;
use App\Http\Controllers\Admin\AssetController;
use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\DisciplineController;
use App\Http\Controllers\Admin\ExamController;
use App\Http\Controllers\Admin\ExamSetupController;
use App\Http\Controllers\Admin\HallTicketController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\LessonPlanController;
use App\Http\Controllers\Admin\LibraryController;
use App\Http\Controllers\Admin\MarksEntryController;
use App\Http\Controllers\Admin\NoticeController;
use App\Http\Controllers\Admin\ParentController;
use App\Http\Controllers\Admin\PtmController;
use App\Http\Controllers\Admin\ResultController;
use App\Http\Controllers\Admin\SchoolClassController;
use App\Http\Controllers\Admin\SchoolEventController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\SubjectController;
use App\Http\Controllers\Admin\TeacherController;
use App\Http\Controllers\Admin\TeachingLogController;
use App\Http\Controllers\Admin\TimetableController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Models\Notice;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Public CMS: Frontend Notices View
Route::get('/notices', function () {
    $notices = Notice::where('status', 'published')
        ->whereIn('audience', ['all', 'public'])
        ->orderBy('date', 'desc')
        ->get();

    return view('frontend.notices', compact('notices'));
})->name('public.notices');

// Authenticated routes group
Route::middleware(['auth', 'verified'])->group(function () {

    // Role-based Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // -------------------------------------------------------------------------
    // Admin Module: Students Management
    // -------------------------------------------------------------------------
    Route::patch('admin/students/{student}/status', [StudentController::class, 'updateStatus'])
        ->name('admin.students.status');

    Route::resource('admin/students', StudentController::class)
        ->names('admin.students')
        ->except(['create', 'show', 'edit']);

    // -------------------------------------------------------------------------
    // Admin Module: Classes & Sections Management
    // -------------------------------------------------------------------------
    Route::get('/admin/classes', [SchoolClassController::class, 'index'])->name('admin.classes.index');
    Route::post('/admin/classes', [SchoolClassController::class, 'store'])->name('admin.classes.store');
    Route::delete('/admin/classes/{id}', [SchoolClassController::class, 'destroy'])->name('admin.classes.destroy');

    // -------------------------------------------------------------------------
    // Admin Module: Admissions Management
    // -------------------------------------------------------------------------
    Route::get('/admin/admissions', [AdmissionController::class, 'index'])->name('admin.admissions.index');
    Route::post('/admin/admissions', [AdmissionController::class, 'store'])->name('admin.admissions.store');
    Route::patch('/admin/admissions/{admission}/status', [AdmissionController::class, 'updateStatus'])->name('admin.admissions.status');
    Route::delete('/admin/admissions/{admission}', [AdmissionController::class, 'destroy'])->name('admin.admissions.destroy');

    // -------------------------------------------------------------------------
    // Admin Module: Parents Management
    // -------------------------------------------------------------------------
    Route::get('admin/parents/export-csv', [ParentController::class, 'exportCsv'])->name('admin.parents.export.csv');
    Route::get('admin/parents/export-pdf', [ParentController::class, 'exportPdf'])->name('admin.parents.export.pdf');

    Route::resource('admin/parents', ParentController::class)->names('admin.parents');

    Route::post('admin/parents/{parent}/link-child', [ParentController::class, 'linkChild'])->name('admin.parents.link');
    Route::delete('admin/parents/{parent}/unlink-child/{student}', [ParentController::class, 'unlinkChild'])->name('admin.parents.unlink');

    // -------------------------------------------------------------------------
    // Admin Module: Subjects Management
    // -------------------------------------------------------------------------
    Route::get('admin/subjects/export-csv', [SubjectController::class, 'exportCsv'])->name('admin.subjects.csv');
    Route::resource('admin/subjects', SubjectController::class)->names('admin.subjects')->except(['create', 'show', 'edit']);

    // -------------------------------------------------------------------------
    // Admin Module: Teachers Management
    // -------------------------------------------------------------------------
    Route::get('admin/teachers/export-csv', [TeacherController::class, 'exportCsv'])->name('admin.teachers.csv');
    Route::post('admin/teachers/{teacher}/assign', [TeacherController::class, 'assign'])->name('admin.teachers.assign');
    Route::delete('admin/teachers/{teacher}/assignment/{assignmentId}', [TeacherController::class, 'removeAssignment'])->name('admin.teachers.remove-assignment');

    Route::resource('admin/teachers', TeacherController::class)
        ->names('admin.teachers')
        ->except(['create', 'show', 'edit']);

    // -------------------------------------------------------------------------
    // Admin Module: Unified Inventory / Assets Management
    // -------------------------------------------------------------------------
    Route::post('admin/assets/{asset}/issue', [AssetController::class, 'issueItem'])->name('admin.assets.issue');
    Route::post('admin/assets/{asset}/return', [AssetController::class, 'returnItem'])->name('admin.assets.return');
    Route::get('admin/assets/export-csv', [AssetController::class, 'exportCsv'])->name('admin.assets.csv');
    Route::post('admin/assets/barcode-return', [AssetController::class, 'barcodeReturn'])->name('admin.assets.barcode-return');

    Route::resource('admin/assets', AssetController::class)
        ->names('admin.assets')
        ->except(['create', 'show', 'edit']);

    // -------------------------------------------------------------------------
    // Admin Module: Stocks/Inventory Ledger Management
    // -------------------------------------------------------------------------
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::post('inventory/{inventoryItem}/restock', [InventoryController::class, 'restock'])->name('inventory.restock');
        Route::post('inventory/{inventoryItem}/dispense', [InventoryController::class, 'dispense'])->name('inventory.dispense');
        Route::resource('inventory', InventoryController::class);
    });

    // -------------------------------------------------------------------------
    // Admin Module: Library & Circulation Management  [library.manage]
    // -------------------------------------------------------------------------
    Route::prefix('admin/library')->name('admin.library.')->middleware('can:library.manage')->group(function () {
        Route::get('books', [LibraryController::class, 'index'])->name('books.index');
        Route::post('books', [LibraryController::class, 'storeBook'])->name('books.store');
        Route::put('books/{book}', [LibraryController::class, 'updateBook'])->name('books.update');
        Route::delete('books/{book}', [LibraryController::class, 'destroyBook'])->name('books.destroy');

        Route::get('circulation', [LibraryController::class, 'circulation'])->name('circulation.index');
        Route::post('circulation/issue', [LibraryController::class, 'issueBook'])->name('circulation.issue');
        Route::post('circulation/return/{issue}', [LibraryController::class, 'returnBook'])->name('circulation.return');
    });

    // -------------------------------------------------------------------------
    // Admin Module: Timetable Management
    // -------------------------------------------------------------------------
    Route::prefix('admin/timetable')->name('admin.timetable.')->group(function () {
        Route::get('/', [TimetableController::class, 'index'])->name('index');
        Route::post('/', [TimetableController::class, 'store'])->name('store');
        Route::post('/periods', [TimetableController::class, 'storePeriodSlots'])->name('periods.store');
        Route::delete('/periods/{period}', [TimetableController::class, 'destroyPeriodSlot'])->name('periods.destroy');
    });

    // -------------------------------------------------------------------------
    // Admin Module: Attendance Management
    // -------------------------------------------------------------------------
    Route::prefix('admin/attendance')->name('admin.attendance.')->group(function () {
        Route::get('/', [AttendanceController::class, 'index'])->name('index');
        Route::post('/', [AttendanceController::class, 'store'])->name('store');
        Route::post('/copy-yesterday', [AttendanceController::class, 'copyFromYesterday'])->name('copy-yesterday');
    });

    // -------------------------------------------------------------------------
    // Admin Module: School Notices
    // -------------------------------------------------------------------------
    Route::prefix('admin/notices')->name('admin.notices.')->group(function () {
        Route::get('/', [NoticeController::class, 'index'])->name('index');
        Route::post('/', [NoticeController::class, 'store'])->name('store');
        Route::delete('/{notice}', [NoticeController::class, 'destroy'])->name('destroy');
    });

    // -------------------------------------------------------------------------
    // Admin Module: Parent-Teacher Meetings (PTM)
    // -------------------------------------------------------------------------
    Route::prefix('admin/ptm')->name('admin.ptm.')->group(function () {
        Route::get('/', [PtmController::class, 'index'])->name('index');
        Route::post('/', [PtmController::class, 'store'])->name('store');
        Route::put('/{ptm}', [PtmController::class, 'update'])->name('update');
        Route::delete('/{ptm}', [PtmController::class, 'destroy'])->name('destroy');
    });

    // -------------------------------------------------------------------------
    // Admin Module: School Calendar  (everyone can view, [calendar.manage] to edit)
    // -------------------------------------------------------------------------
    Route::prefix('admin/calendar')->name('admin.calendar.')->group(function () {
        Route::get('/', [SchoolEventController::class, 'index'])->name('index');

        Route::middleware('can:calendar.manage')->group(function () {
            Route::post('/', [SchoolEventController::class, 'store'])->name('store');
            Route::put('/{schoolEvent}', [SchoolEventController::class, 'update'])->name('update');
            Route::delete('/{schoolEvent}', [SchoolEventController::class, 'destroy'])->name('destroy');
        });
    });

    // -------------------------------------------------------------------------
    // Admin Module: Lesson Planning  [lessonplans.*]
    // -------------------------------------------------------------------------
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::middleware('can:lessonplans.view')->group(function () {
            Route::get('lesson-plans', [LessonPlanController::class, 'index'])->name('lesson-plans.index');
            Route::get('lesson-plans/csv', [LessonPlanController::class, 'csv'])->name('lesson-plans.csv');
        });

        Route::middleware('can:lessonplans.manage')->group(function () {
            Route::post('lesson-plans', [LessonPlanController::class, 'store'])->name('lesson-plans.store');
            Route::put('lesson-plans/{lessonPlan}', [LessonPlanController::class, 'update'])->name('lesson-plans.update');
            Route::patch('lesson-plans/{lessonPlan}/status', [LessonPlanController::class, 'updateStatus'])->name('lesson-plans.status');
            Route::delete('lesson-plans/{lessonPlan}', [LessonPlanController::class, 'destroy'])->name('lesson-plans.destroy');
        });
    });

    // -------------------------------------------------------------------------
    // Admin Module: Teaching Logs  [teachinglogs.*]
    // -------------------------------------------------------------------------
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::middleware('can:teachinglogs.view')->group(function () {
            Route::get('teaching-logs', [TeachingLogController::class, 'index'])->name('teaching-logs.index');
            Route::get('teaching-logs/csv', [TeachingLogController::class, 'csv'])->name('teaching-logs.csv');
        });

        Route::middleware('can:teachinglogs.manage')->group(function () {
            Route::post('teaching-logs', [TeachingLogController::class, 'store'])->name('teaching-logs.store');
            Route::put('teaching-logs/{teachingLog}', [TeachingLogController::class, 'update'])->name('teaching-logs.update');
            Route::delete('teaching-logs/{teachingLog}', [TeachingLogController::class, 'destroy'])->name('teaching-logs.destroy');
        });
    });

    // -------------------------------------------------------------------------
    // Admin Module: Exams, Marks, Results & Hall Tickets
    // -------------------------------------------------------------------------
    Route::prefix('admin')->name('admin.')->group(function () {

        // Exams
        Route::get('exams', [ExamController::class, 'index'])->middleware('can:exams.view')->name('exams.index');

        Route::middleware('can:exams.manage')->group(function () {
            Route::post('exams', [ExamController::class, 'store'])->name('exams.store');
            Route::put('exams/{exam}', [ExamController::class, 'update'])->name('exams.update');
            Route::delete('exams/{exam}', [ExamController::class, 'destroy'])->name('exams.destroy');

            // Exam setup (subjects, dates, times, full/pass marks per class)
            Route::get('exams/{exam}/setup', [ExamSetupController::class, 'show'])->name('exams.setup');
            Route::put('exams/{exam}/setup', [ExamSetupController::class, 'save'])->name('exams.setup.save');
            Route::post('exams/{exam}/setup/load', [ExamSetupController::class, 'load'])->name('exams.setup.load');
            Route::post('exams/{exam}/setup/add', [ExamSetupController::class, 'add'])->name('exams.setup.add');
            Route::post('exams/{exam}/setup/copy', [ExamSetupController::class, 'copy'])->name('exams.setup.copy');
            Route::delete('exams/{exam}/setup/{examSubject}', [ExamSetupController::class, 'remove'])->name('exams.setup.remove');
        });

        Route::middleware('can:results.publish')->group(function () {
            Route::post('exams/{exam}/publish', [ExamController::class, 'publish'])->name('exams.publish');
            Route::post('exams/{exam}/unpublish', [ExamController::class, 'unpublish'])->name('exams.unpublish');
        });

        // Marks entry
        Route::get('marks', [MarksEntryController::class, 'index'])->middleware('can:marks.view')->name('marks.index');
        Route::put('marks/{examSubject}', [MarksEntryController::class, 'save'])->middleware('can:marks.enter')->name('marks.save');
        Route::post('marks/{examSubject}/unlock', [MarksEntryController::class, 'unlock'])->middleware('can:marks.lock')->name('marks.unlock');

        // Results and printable sheets
        Route::middleware('can:results.view')->group(function () {
            Route::get('results', [ResultController::class, 'index'])->name('results.index');
            Route::get('results/{exam}/class/{schoolClass}/ledger', [ResultController::class, 'ledger'])->name('results.ledger');
            Route::get('results/{exam}/class/{schoolClass}/marksheets', [ResultController::class, 'marksheets'])->name('results.marksheets');
            Route::get('results/{exam}/student/{student}/marksheet', [ResultController::class, 'marksheet'])->name('results.marksheet');
        });

        // Hall tickets
        Route::get('hall-tickets', [HallTicketController::class, 'index'])->middleware('can:exams.manage')->name('hall-tickets.index');
    });

    // -------------------------------------------------------------------------
    // Admin Module: Discipline  [discipline.*]
    // -------------------------------------------------------------------------
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::middleware('can:discipline.view')->group(function () {
            Route::get('discipline', [DisciplineController::class, 'index'])->name('discipline.index');
            Route::get('discipline/csv', [DisciplineController::class, 'csv'])->name('discipline.csv');
            Route::get('discipline/{incident}/notice', [DisciplineController::class, 'notice'])->name('discipline.notice');
        });

        Route::post('discipline', [DisciplineController::class, 'store'])->middleware('can:discipline.report')->name('discipline.store');

        Route::middleware('can:discipline.manage')->group(function () {
            Route::put('discipline/{incident}', [DisciplineController::class, 'update'])->name('discipline.update');
            Route::patch('discipline/{incident}/close', [DisciplineController::class, 'close'])->name('discipline.close');
            Route::patch('discipline/{incident}/notify', [DisciplineController::class, 'notify'])->name('discipline.notify');
        });

        Route::delete('discipline/{incident}', [DisciplineController::class, 'destroy'])->middleware('can:discipline.delete')->name('discipline.destroy');
    });

    // -------------------------------------------------------------------------
    // Profile routes (generated by Breeze)
    // -------------------------------------------------------------------------
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
