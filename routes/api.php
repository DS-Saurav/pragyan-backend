<?php

use App\Http\Controllers\Admin\AboutController;
use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\AdmissionController;
use App\Http\Controllers\Admin\AssetController;
use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\ComplaintController;
use App\Http\Controllers\Admin\ConductController;
use App\Http\Controllers\Admin\DisciplineController;
use App\Http\Controllers\Admin\DocumentController;
use App\Http\Controllers\Admin\ExamController;
use App\Http\Controllers\Admin\ExamSetupController;
use App\Http\Controllers\Admin\FeeController;
use App\Http\Controllers\Admin\FeeStructureController;
use App\Http\Controllers\Admin\GalleryAlbumController;
use App\Http\Controllers\Admin\HallTicketController;
use App\Http\Controllers\Admin\HelpdeskController;
use App\Http\Controllers\Admin\HeroSlideController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\LessonPlanController;
use App\Http\Controllers\Admin\LibraryController;
use App\Http\Controllers\Admin\MarksEntryController;
use App\Http\Controllers\Admin\MediaFoundationController;
use App\Http\Controllers\Admin\NewsStoryController;
use App\Http\Controllers\Admin\NoticeController;
use App\Http\Controllers\Admin\ParentController;
use App\Http\Controllers\Admin\PayrollController;
use App\Http\Controllers\Admin\PtmController;
use App\Http\Controllers\Admin\PublicAdmissionCycleController;
use App\Http\Controllers\Admin\PublicIdentityController;
use App\Http\Controllers\Admin\PublicPageController as AdminPublicPageController;
use App\Http\Controllers\Admin\PublicPhotoSlotController;
use App\Http\Controllers\Admin\PublicTeamProfileController;
use App\Http\Controllers\Admin\PublicTranslationController;
use App\Http\Controllers\Admin\ResultController;
use App\Http\Controllers\Admin\SchoolClassController;
use App\Http\Controllers\Admin\SchoolEventController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\SubjectController;
use App\Http\Controllers\Admin\SubstituteController;
use App\Http\Controllers\Admin\TeacherController;
use App\Http\Controllers\Admin\TeachingLogController;
use App\Http\Controllers\Admin\TimetableController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WebsiteSettingsController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicAdmissionsController;
use App\Http\Controllers\PublicCalendarController;
use App\Http\Controllers\PublicContactController;
use App\Http\Controllers\PublicGalleryController;
use App\Http\Controllers\PublicHomepageController;
use App\Http\Controllers\PublicNewsController;
use App\Http\Controllers\PublicNoticeController;
use App\Http\Controllers\PublicPageController;
use App\Http\Controllers\TeamController;
use App\Services\PublicTranslations\PublicTranslationSource;
use Illuminate\Support\Facades\Route;

// -----------------------------------------------------------------------------
// Authentication (Sanctum Token API)
// -----------------------------------------------------------------------------
Route::post('/login', [AuthenticatedSessionController::class, 'store']);

// -----------------------------------------------------------------------------
// Public Read-Only JSON Endpoints
// -----------------------------------------------------------------------------
Route::get('/media/{id}', [MediaFoundationController::class, 'published'])
    ->whereNumber('id')
    ->name('media.published');
Route::get('/hero-slides/{heroSlide}/image', [HeroSlideController::class, 'image'])->whereNumber('heroSlide');

Route::prefix('public')->group(function (): void {
    Route::get('/homepage', [PublicHomepageController::class, 'index']);
    Route::get('/notices', [PublicNoticeController::class, 'index']);
    Route::get('/notices/archive', [PublicNoticeController::class, 'archive']);
    Route::get('/notices/{slug}', [PublicNoticeController::class, 'show'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*');
    Route::get('/team', [TeamController::class, 'index']);
    Route::get('/about', [PublicPageController::class, 'about']);
    Route::get('/academics', [PublicPageController::class, 'academics']);
    Route::get('/admissions', [PublicAdmissionsController::class, 'index']);
    Route::get('/news', [PublicNewsController::class, 'index']);
    Route::get('/news/archive', [PublicNewsController::class, 'archive']);
    Route::get('/news/{slug}', [PublicNewsController::class, 'show'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*');
    Route::get('/gallery', [PublicGalleryController::class, 'index']);
    Route::get('/gallery/archive', [PublicGalleryController::class, 'archive']);
    Route::get('/gallery/{slug}', [PublicGalleryController::class, 'show'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*');
    Route::get('/contact', [PublicContactController::class, 'index']);
    Route::get('/calendar', [PublicCalendarController::class, 'index']);
});

// -----------------------------------------------------------------------------
// Authenticated Portal & Admin JSON API
// -----------------------------------------------------------------------------
Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
    Route::get('/me', [AuthenticatedSessionController::class, 'me']);
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);

    // Dashboard (also aliased at /api/admin/dashboard for Angular frontend)
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/admin/dashboard', [DashboardController::class, 'index']);

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit']);
    Route::patch('/profile', [ProfileController::class, 'update']);
    Route::delete('/profile', [ProfileController::class, 'destroy']);

    // Staff-only modules
    Route::middleware('staff')->group(function (): void {
        // Media Library
        Route::get('/admin/media', [MediaFoundationController::class, 'status'])->middleware('can:media.view');
        Route::get('/admin/media/{id}/original', [MediaFoundationController::class, 'original'])->whereNumber('id')->middleware('can:media.view');
        Route::get('/admin/media/library', [MediaFoundationController::class, 'index']);
        Route::post('/admin/media/library', [MediaFoundationController::class, 'upload']);
        Route::patch('/admin/media/library/{asset}/metadata', [MediaFoundationController::class, 'updateMetadata'])->whereNumber('asset');
        Route::patch('/admin/media/library/{asset}/submit', [MediaFoundationController::class, 'submit'])->whereNumber('asset');
        Route::patch('/admin/media/library/{asset}/approve', [MediaFoundationController::class, 'approve'])->whereNumber('asset');
        Route::patch('/admin/media/library/{asset}/publish', [MediaFoundationController::class, 'publish'])->whereNumber('asset');
        Route::patch('/admin/media/library/{asset}/draft', [MediaFoundationController::class, 'draft'])->whereNumber('asset');
        Route::patch('/admin/media/library/{asset}/archive', [MediaFoundationController::class, 'archive'])->whereNumber('asset');
        Route::patch('/admin/media/library/{asset}/restore', [MediaFoundationController::class, 'restore'])->whereNumber('asset');
        Route::patch('/admin/media/library/{asset}/consent', [MediaFoundationController::class, 'consent'])->whereNumber('asset');

        // Students
        Route::patch('/admin/students/{student}/status', [StudentController::class, 'updateStatus']);
        Route::post('/admin/students/{student}/measurements', [StudentController::class, 'saveMeasurement']);
        Route::apiResource('/admin/students', StudentController::class);

        // Classes & Sections
        Route::get('/admin/classes', [SchoolClassController::class, 'index']);
        Route::post('/admin/classes', [SchoolClassController::class, 'store']);
        Route::delete('/admin/classes/{id}', [SchoolClassController::class, 'destroy']);

        // Admissions
        Route::middleware('can:admissions.manage')->group(function (): void {
            Route::get('/admin/admissions', [AdmissionController::class, 'index']);
            Route::post('/admin/admissions', [AdmissionController::class, 'store']);
            Route::patch('/admin/admissions/{admission}/status', [AdmissionController::class, 'updateStatus']);
            Route::delete('/admin/admissions/{admission}', [AdmissionController::class, 'destroy']);
        });

        // Public Admissions Cycles
        Route::prefix('admin/public-admissions')->middleware('can:public_admissions.manage')->group(function (): void {
            Route::get('/', [PublicAdmissionCycleController::class, 'index']);
            Route::post('/', [PublicAdmissionCycleController::class, 'store']);
            Route::put('/{admissionCycle}', [PublicAdmissionCycleController::class, 'update']);
            Route::patch('/{admissionCycle}/publish', [PublicAdmissionCycleController::class, 'publish']);
            Route::patch('/{admissionCycle}/withdraw', [PublicAdmissionCycleController::class, 'withdraw']);
            Route::patch('/{admissionCycle}/archive', [PublicAdmissionCycleController::class, 'archive']);
        });

        // Public Localisations
        Route::prefix('admin/public-localisations')->group(function (): void {
            Route::patch('/content/{translation}/revisions/{revision}/submit', [PublicTranslationController::class, 'submitContent']);
            Route::patch('/content/{translation}/revisions/{revision}/approve', [PublicTranslationController::class, 'approveContent']);
            Route::patch('/content/{translation}/revisions/{revision}/publish', [PublicTranslationController::class, 'publishContent']);
            Route::patch('/content/{translation}/withdraw', [PublicTranslationController::class, 'withdrawContent']);
            Route::patch('/content/{translation}/archive', [PublicTranslationController::class, 'archiveContent']);
            Route::patch('/content/{translation}/restore', [PublicTranslationController::class, 'restoreContent']);
            Route::patch('/site/{translation}/revisions/{revision}/submit', [PublicTranslationController::class, 'submitSite']);
            Route::patch('/site/{translation}/revisions/{revision}/approve', [PublicTranslationController::class, 'approveSite']);
            Route::patch('/site/{translation}/revisions/{revision}/publish', [PublicTranslationController::class, 'publishSite']);
            Route::patch('/site/{translation}/withdraw', [PublicTranslationController::class, 'withdrawSite']);
            Route::patch('/site/{translation}/archive', [PublicTranslationController::class, 'archiveSite']);
            Route::patch('/site/{translation}/restore', [PublicTranslationController::class, 'restoreSite']);
            Route::get('/site/{scope}', [PublicTranslationController::class, 'editSite'])->whereIn('scope', ['homepage', 'identity']);
            Route::put('/site/{scope}', [PublicTranslationController::class, 'saveSite'])->whereIn('scope', ['homepage', 'identity']);
            Route::get('/content/{contentType}/{contentId}', [PublicTranslationController::class, 'editContent'])
                ->whereIn('contentType', PublicTranslationSource::TYPES)->whereNumber('contentId');
            Route::put('/content/{contentType}/{contentId}', [PublicTranslationController::class, 'saveContent'])
                ->whereIn('contentType', PublicTranslationSource::TYPES)->whereNumber('contentId');
        });

        // Public News
        Route::prefix('admin/news')->group(function (): void {
            Route::get('/', [NewsStoryController::class, 'index']);
            Route::post('/', [NewsStoryController::class, 'store']);
            Route::put('/{newsStory}', [NewsStoryController::class, 'update']);
            Route::patch('/{newsStory}/transition', [NewsStoryController::class, 'transition']);
            Route::patch('/{newsStory}/feature', [NewsStoryController::class, 'feature']);
            Route::delete('/feature', [NewsStoryController::class, 'unfeature']);
        });

        // Public Gallery
        Route::prefix('admin/gallery')->group(function (): void {
            Route::get('/', [GalleryAlbumController::class, 'index']);
            Route::post('/', [GalleryAlbumController::class, 'store']);
            Route::put('/{galleryAlbum}', [GalleryAlbumController::class, 'update']);
            Route::patch('/{galleryAlbum}/revisions/{revision}/cover', [GalleryAlbumController::class, 'setCover']);
            Route::post('/{galleryAlbum}/revisions/{revision}/photos', [GalleryAlbumController::class, 'addPhoto']);
            Route::patch('/{galleryAlbum}/revisions/{revision}/photos/reorder', [GalleryAlbumController::class, 'reorderPhotos']);
            Route::patch('/{galleryAlbum}/revisions/{revision}/photos/{photo}', [GalleryAlbumController::class, 'updatePhoto']);
            Route::delete('/{galleryAlbum}/revisions/{revision}/photos/{photo}', [GalleryAlbumController::class, 'removePhoto']);
            Route::patch('/{galleryAlbum}/revisions/{revision}/submit', [GalleryAlbumController::class, 'submit']);
            Route::patch('/{galleryAlbum}/revisions/{revision}/approve', [GalleryAlbumController::class, 'approve']);
            Route::patch('/{galleryAlbum}/revisions/{revision}/publish', [GalleryAlbumController::class, 'publish']);
            Route::patch('/{galleryAlbum}/withdraw', [GalleryAlbumController::class, 'withdraw']);
            Route::patch('/{galleryAlbum}/archive', [GalleryAlbumController::class, 'archive']);
            Route::patch('/{galleryAlbum}/restore', [GalleryAlbumController::class, 'restore']);
            Route::patch('/{galleryAlbum}/feature', [GalleryAlbumController::class, 'feature']);
            Route::delete('/{galleryAlbum}/feature', [GalleryAlbumController::class, 'unfeature']);
        });

        // Public Pages
        Route::prefix('admin/public-pages')->group(function (): void {
            Route::get('/', [AdminPublicPageController::class, 'index']);
            Route::post('/{type}', [AdminPublicPageController::class, 'store'])->whereIn('type', ['about', 'academics']);
            Route::put('/{publicPage}', [AdminPublicPageController::class, 'update']);
            Route::patch('/{publicPage}/revisions/{revision}/submit', [AdminPublicPageController::class, 'submit']);
            Route::patch('/{publicPage}/revisions/{revision}/approve', [AdminPublicPageController::class, 'approve']);
            Route::patch('/{publicPage}/revisions/{revision}/publish', [AdminPublicPageController::class, 'publish']);
            Route::patch('/{publicPage}/withdraw', [AdminPublicPageController::class, 'withdraw']);
            Route::patch('/{publicPage}/cancel-schedule', [AdminPublicPageController::class, 'cancelSchedule']);
            Route::patch('/{publicPage}/archive', [AdminPublicPageController::class, 'archive']);
            Route::patch('/{publicPage}/restore', [AdminPublicPageController::class, 'restore']);
        });

        // Public Team
        Route::prefix('admin/public-team')->group(function (): void {
            Route::get('/', [PublicTeamProfileController::class, 'index']);
            Route::post('/', [PublicTeamProfileController::class, 'store']);
            Route::put('/{publicTeamProfile}', [PublicTeamProfileController::class, 'update']);
            Route::patch('/{publicTeamProfile}/revisions/{revision}/submit', [PublicTeamProfileController::class, 'submit']);
            Route::patch('/{publicTeamProfile}/revisions/{revision}/approve', [PublicTeamProfileController::class, 'approve']);
            Route::patch('/{publicTeamProfile}/revisions/{revision}/publish', [PublicTeamProfileController::class, 'publish']);
            Route::patch('/{publicTeamProfile}/withdraw', [PublicTeamProfileController::class, 'withdraw']);
            Route::patch('/{publicTeamProfile}/cancel-schedule', [PublicTeamProfileController::class, 'cancelSchedule']);
            Route::patch('/{publicTeamProfile}/archive', [PublicTeamProfileController::class, 'archive']);
            Route::patch('/{publicTeamProfile}/restore', [PublicTeamProfileController::class, 'restore']);
        });

        // Public Identity & Photos
        Route::get('/admin/public-identity', [PublicIdentityController::class, 'index']);
        Route::put('/admin/public-identity', [PublicIdentityController::class, 'update']);
        Route::get('/admin/public-photos', [PublicPhotoSlotController::class, 'index']);
        Route::put('/admin/public-photos/{slot}', [PublicPhotoSlotController::class, 'update']);

        // Hero Slider Images
        Route::get('/admin/hero-slides', [HeroSlideController::class, 'index']);
        Route::post('/admin/hero-slides', [HeroSlideController::class, 'store']);
        Route::patch('/admin/hero-slides/reorder', [HeroSlideController::class, 'reorder']);
        Route::post('/admin/hero-slides/{heroSlide}', [HeroSlideController::class, 'update'])->whereNumber('heroSlide');
        Route::patch('/admin/hero-slides/{heroSlide}', [HeroSlideController::class, 'update'])->whereNumber('heroSlide');
        Route::delete('/admin/hero-slides/{heroSlide}', [HeroSlideController::class, 'destroy'])->whereNumber('heroSlide');

        // Parents
        Route::get('/admin/parents/export-csv', [ParentController::class, 'exportCsv']);
        Route::post('/admin/parents/{parent}/link-child', [ParentController::class, 'linkChild']);
        Route::delete('/admin/parents/{parent}/unlink-child/{student}', [ParentController::class, 'unlinkChild']);
        Route::apiResource('/admin/parents', ParentController::class);

        // Subjects
        Route::get('/admin/subjects/export-csv', [SubjectController::class, 'exportCsv']);
        Route::apiResource('/admin/subjects', SubjectController::class);

        // Teachers
        Route::get('/admin/teachers/export-csv', [TeacherController::class, 'exportCsv']);
        Route::post('/admin/teachers/{teacher}/assign', [TeacherController::class, 'assign']);
        Route::delete('/admin/teachers/{teacher}/assignment/{assignmentId}', [TeacherController::class, 'removeAssignment']);
        Route::apiResource('/admin/teachers', TeacherController::class);

        // Assets
        Route::post('/admin/assets/{asset}/issue', [AssetController::class, 'issueItem']);
        Route::post('/admin/assets/{asset}/return', [AssetController::class, 'returnItem']);
        Route::get('/admin/assets/export-csv', [AssetController::class, 'exportCsv']);
        Route::apiResource('/admin/assets', AssetController::class)->except(['update']);

        // Inventory
        Route::post('/admin/inventory/{inventoryItem}/restock', [InventoryController::class, 'restock']);
        Route::post('/admin/inventory/{inventoryItem}/dispense', [InventoryController::class, 'dispense']);
        Route::apiResource('/admin/inventory', InventoryController::class)->parameters(['inventory' => 'inventoryItem']);

        // Library
        Route::prefix('admin/library')->middleware('can:library.manage')->group(function (): void {
            Route::get('/books', [LibraryController::class, 'index']);
            Route::post('/books', [LibraryController::class, 'storeBook']);
            Route::put('/books/{book}', [LibraryController::class, 'updateBook']);
            Route::delete('/books/{book}', [LibraryController::class, 'destroyBook']);
            Route::get('/circulation', [LibraryController::class, 'circulation']);
            Route::post('/circulation/issue', [LibraryController::class, 'issueBook']);
            Route::post('/circulation/return/{issue}', [LibraryController::class, 'returnBook']);
        });

        // Timetable
        Route::prefix('admin/timetable')->group(function (): void {
            Route::get('/', [TimetableController::class, 'index']);
            Route::post('/', [TimetableController::class, 'store']);
            Route::post('/periods', [TimetableController::class, 'storePeriodSlots']);
            Route::delete('/periods/{period}', [TimetableController::class, 'destroyPeriodSlot']);
        });

        // Attendance
        Route::prefix('admin/attendance')->group(function (): void {
            Route::get('/', [AttendanceController::class, 'index']);
            Route::post('/', [AttendanceController::class, 'store']);
            Route::post('/copy-yesterday', [AttendanceController::class, 'copyFromYesterday']);
        });

        // Notices
        Route::prefix('admin/notices')->group(function (): void {
            Route::get('/', [NoticeController::class, 'index']);
            Route::post('/', [NoticeController::class, 'store']);
            Route::put('/{notice}', [NoticeController::class, 'update']);
            Route::patch('/{notice}/transition', [NoticeController::class, 'transition']);
            Route::delete('/{notice}', [NoticeController::class, 'destroy'])->middleware('can:notices.manage');
        });

        // PTM
        Route::prefix('admin/ptm')->group(function (): void {
            Route::get('/', [PtmController::class, 'index']);
            Route::post('/', [PtmController::class, 'store']);
            Route::put('/{ptm}', [PtmController::class, 'update']);
            Route::delete('/{ptm}', [PtmController::class, 'destroy']);
        });

        // Substitutes
        Route::apiResource('/admin/substitutes', SubstituteController::class)->except(['show', 'update']);
    });

    // Calendar
    Route::middleware(['staff', 'can:calendar.view'])->prefix('admin/calendar')->group(function (): void {
        Route::get('/', [SchoolEventController::class, 'index']);
        Route::middleware('can:calendar.manage')->group(function (): void {
            Route::post('/', [SchoolEventController::class, 'store']);
            Route::put('/{schoolEvent}', [SchoolEventController::class, 'update']);
            Route::delete('/{schoolEvent}', [SchoolEventController::class, 'destroy']);
        });
    });

    // Lesson Plans
    Route::prefix('admin')->group(function (): void {
        Route::middleware('can:lessonplans.view')->group(function (): void {
            Route::get('/lesson-plans', [LessonPlanController::class, 'index']);
            Route::get('/lesson-plans/csv', [LessonPlanController::class, 'csv']);
        });
        Route::middleware('can:lessonplans.manage')->group(function (): void {
            Route::post('/lesson-plans', [LessonPlanController::class, 'store']);
            Route::put('/lesson-plans/{lessonPlan}', [LessonPlanController::class, 'update']);
            Route::patch('/lesson-plans/{lessonPlan}/status', [LessonPlanController::class, 'updateStatus']);
            Route::delete('/lesson-plans/{lessonPlan}', [LessonPlanController::class, 'destroy']);
        });
    });

    // Teaching Logs
    Route::prefix('admin')->group(function (): void {
        Route::middleware('can:teachinglogs.view')->group(function (): void {
            Route::get('/teaching-logs', [TeachingLogController::class, 'index']);
            Route::get('/teaching-logs/csv', [TeachingLogController::class, 'csv']);
        });
        Route::middleware('can:teachinglogs.manage')->group(function (): void {
            Route::post('/teaching-logs', [TeachingLogController::class, 'store']);
            Route::put('/teaching-logs/{teachingLog}', [TeachingLogController::class, 'update']);
            Route::delete('/teaching-logs/{teachingLog}', [TeachingLogController::class, 'destroy']);
        });
    });

    // Exams, Marks, Results & Hall Tickets
    Route::prefix('admin')->group(function (): void {
        Route::get('/exams', [ExamController::class, 'index'])->middleware('can:exams.view');
        Route::middleware('can:exams.manage')->group(function (): void {
            Route::post('/exams', [ExamController::class, 'store']);
            Route::put('/exams/{exam}', [ExamController::class, 'update']);
            Route::delete('/exams/{exam}', [ExamController::class, 'destroy']);
            Route::get('/exams/{exam}/setup', [ExamSetupController::class, 'show']);
            Route::put('/exams/{exam}/setup', [ExamSetupController::class, 'save']);
            Route::post('/exams/{exam}/setup/load', [ExamSetupController::class, 'load']);
            Route::post('/exams/{exam}/setup/add', [ExamSetupController::class, 'add']);
            Route::post('/exams/{exam}/setup/copy', [ExamSetupController::class, 'copy']);
            Route::delete('/exams/{exam}/setup/{examSubject}', [ExamSetupController::class, 'remove']);
        });
        Route::middleware('can:results.publish')->group(function (): void {
            Route::post('/exams/{exam}/publish', [ExamController::class, 'publish']);
            Route::post('/exams/{exam}/unpublish', [ExamController::class, 'unpublish']);
        });

        Route::get('/marks', [MarksEntryController::class, 'index'])->middleware('can:marks.view');
        Route::put('/marks/{examSubject}', [MarksEntryController::class, 'save'])->middleware('can:marks.enter');
        Route::post('/marks/{examSubject}/unlock', [MarksEntryController::class, 'unlock'])->middleware('can:marks.lock');
        Route::get('/marks/assessments', [MarksEntryController::class, 'assessments'])->middleware('can:marks.view');
        Route::put('/marks/{exam}/assessments', [MarksEntryController::class, 'saveAssessments'])->middleware('can:marks.enter');

        Route::middleware('can:results.view')->group(function (): void {
            Route::get('/results', [ResultController::class, 'index']);
            Route::get('/results/{exam}/class/{schoolClass}/ledger', [ResultController::class, 'ledger']);
            Route::get('/results/{exam}/class/{schoolClass}/marksheets', [ResultController::class, 'marksheets']);
            Route::get('/results/{exam}/student/{student}/marksheet', [ResultController::class, 'marksheet']);
        });

        Route::get('/hall-tickets', [HallTicketController::class, 'index'])->middleware('can:exams.manage');
    });

    // Discipline
    Route::prefix('admin')->group(function (): void {
        Route::middleware('can:discipline.view')->group(function (): void {
            Route::get('/discipline', [DisciplineController::class, 'index']);
            Route::get('/discipline/csv', [DisciplineController::class, 'csv']);
            Route::get('/discipline/{incident}/notice', [DisciplineController::class, 'notice']);
        });
        Route::post('/discipline', [DisciplineController::class, 'store'])->middleware('can:discipline.report');
        Route::middleware('can:discipline.manage')->group(function (): void {
            Route::put('/discipline/{incident}', [DisciplineController::class, 'update']);
            Route::patch('/discipline/{incident}/close', [DisciplineController::class, 'close']);
            Route::patch('/discipline/{incident}/notify', [DisciplineController::class, 'notify']);
        });
        Route::delete('/discipline/{incident}', [DisciplineController::class, 'destroy'])->middleware('can:discipline.delete');
    });

    // Conduct & Activities
    Route::prefix('admin')->group(function (): void {
        Route::get('/conduct', [ConductController::class, 'index'])->middleware('can:conduct.view');
        Route::apiResource('/conduct', ConductController::class)->only(['store', 'update', 'destroy'])->middleware('can:conduct.manage');

        Route::get('/activities', [ActivityController::class, 'index'])->middleware('can:activities.view');
        Route::apiResource('/activities', ActivityController::class)->only(['store', 'update', 'destroy'])->middleware('can:activities.manage');
    });

    // Fees & Fee Structure
    Route::prefix('admin/fees')->group(function (): void {
        Route::middleware('can:fees.view')->group(function (): void {
            Route::get('/', [FeeController::class, 'index']);
            Route::get('/csv', [FeeController::class, 'csv']);
            Route::get('/{receipt}/print', [FeeController::class, 'receipt']);
        });
        Route::post('/', [FeeController::class, 'store'])->middleware('can:fees.collect');
        Route::post('/{receipt}/pay', [FeeController::class, 'pay'])->middleware('can:fees.collect');
        Route::post('/{receipt}/refund', [FeeController::class, 'refund'])->middleware('can:fees.refund');
        Route::delete('/{receipt}', [FeeController::class, 'destroy'])->middleware('can:fees.delete');
    });

    Route::prefix('admin/fee-structure')->group(function (): void {
        Route::get('/', [FeeStructureController::class, 'index'])->middleware('can:fees.view');
        Route::middleware('can:fees.structure')->group(function (): void {
            Route::put('/', [FeeStructureController::class, 'save']);
            Route::post('/copy', [FeeStructureController::class, 'copy']);
            Route::post('/heads', [FeeStructureController::class, 'storeHead']);
            Route::put('/heads/{head}', [FeeStructureController::class, 'updateHead']);
            Route::delete('/heads/{head}', [FeeStructureController::class, 'destroyHead']);
        });
        Route::post('/generate', [FeeStructureController::class, 'generate'])->middleware('can:fees.generate');
    });

    // Payroll & Staff
    Route::prefix('admin/payroll')->group(function (): void {
        Route::middleware('can:payroll.view')->group(function (): void {
            Route::get('/', [PayrollController::class, 'index']);
            Route::get('/csv', [PayrollController::class, 'csv']);
            Route::get('/print', [PayrollController::class, 'printMonth']);
            Route::get('/staff', [StaffController::class, 'index']);
            Route::get('/{slip}/print', [PayrollController::class, 'slip']);
        });
        Route::middleware('can:payroll.manage')->group(function (): void {
            Route::post('/', [PayrollController::class, 'store']);
            Route::post('/run', [PayrollController::class, 'run']);
            Route::post('/staff', [StaffController::class, 'store']);
            Route::post('/staff/import-teachers', [StaffController::class, 'importTeachers']);
            Route::put('/staff/{staff}', [StaffController::class, 'update']);
            Route::delete('/staff/{staff}', [StaffController::class, 'destroy']);
            Route::post('/components', [StaffController::class, 'storeComponent']);
            Route::put('/components/{component}', [StaffController::class, 'updateComponent']);
            Route::delete('/components/{component}', [StaffController::class, 'destroyComponent']);
            Route::put('/{slip}', [PayrollController::class, 'update']);
            Route::delete('/{slip}', [PayrollController::class, 'destroy']);
        });
        Route::middleware('can:payroll.pay')->group(function (): void {
            Route::post('/pay-all', [PayrollController::class, 'payAll']);
            Route::post('/{slip}/pay', [PayrollController::class, 'pay']);
            Route::post('/{slip}/unpay', [PayrollController::class, 'unpay']);
        });
    });

    // Users
    Route::prefix('admin/users')->group(function (): void {
        Route::middleware('can:users.view')->group(function (): void {
            Route::get('/', [UserController::class, 'index']);
            Route::get('/csv', [UserController::class, 'csv']);
        });
        Route::middleware('can:users.manage')->group(function (): void {
            Route::post('/', [UserController::class, 'store']);
            Route::put('/{user}', [UserController::class, 'update']);
            Route::post('/{user}/status', [UserController::class, 'toggleStatus']);
            Route::post('/{user}/reset-password', [UserController::class, 'resetPassword']);
            Route::post('/{user}/website', [UserController::class, 'toggleWebsite']);
            Route::get('/{user}/editorial-access', [UserController::class, 'editorialAccess']);
            Route::put('/{user}/editorial-access', [UserController::class, 'updateEditorialAccess']);
        });
        Route::delete('/{user}', [UserController::class, 'destroy'])->middleware('can:users.delete');
    });

    // Daily Accounts
    Route::prefix('admin/accounts')->group(function (): void {
        Route::middleware('can:accounts.view')->group(function (): void {
            Route::get('/', [AccountController::class, 'index']);
            Route::get('/csv', [AccountController::class, 'csv']);
            Route::get('/book', [AccountController::class, 'book']);
        });
        Route::middleware('can:accounts.manage')->group(function (): void {
            Route::post('/', [AccountController::class, 'store']);
            Route::put('/{entry}', [AccountController::class, 'update']);
            Route::delete('/{entry}', [AccountController::class, 'destroy']);
        });
    });

    // Helpdesk
    Route::prefix('admin/helpdesk')->group(function (): void {
        Route::middleware('can:helpdesk.view')->group(function (): void {
            Route::get('/', [HelpdeskController::class, 'index']);
            Route::get('/csv', [HelpdeskController::class, 'csv']);
            Route::get('/{ticket}', [HelpdeskController::class, 'show']);
            Route::post('/{ticket}/reply', [HelpdeskController::class, 'reply']);
        });
        Route::post('/', [HelpdeskController::class, 'store'])->middleware('can:helpdesk.create');
        Route::patch('/{ticket}', [HelpdeskController::class, 'update'])->middleware('can:helpdesk.manage');
        Route::delete('/{ticket}', [HelpdeskController::class, 'destroy'])->middleware('can:helpdesk.delete');
    });

    // Complaints
    Route::prefix('admin/complaints')->group(function (): void {
        Route::middleware('can:complaints.view')->group(function (): void {
            Route::get('/', [ComplaintController::class, 'index']);
            Route::get('/csv', [ComplaintController::class, 'csv']);
            Route::get('/{complaint}', [ComplaintController::class, 'show']);
            Route::post('/{complaint}/reply', [ComplaintController::class, 'reply']);
        });
        Route::post('/', [ComplaintController::class, 'store'])->middleware('can:complaints.create');
        Route::middleware('can:complaints.manage')->group(function (): void {
            Route::patch('/{complaint}', [ComplaintController::class, 'update']);
            Route::post('/{complaint}/escalate', [ComplaintController::class, 'escalate']);
        });
        Route::delete('/{complaint}', [ComplaintController::class, 'destroy'])->middleware('can:complaints.delete');
    });

    // Documents
    Route::prefix('admin/documents')->group(function (): void {
        Route::middleware('can:documents.view')->group(function (): void {
            Route::get('/', [DocumentController::class, 'index']);
            Route::get('/csv', [DocumentController::class, 'csv']);
            Route::get('/{document}/download', [DocumentController::class, 'download']);
        });
        Route::middleware('can:documents.manage')->group(function (): void {
            Route::post('/', [DocumentController::class, 'store']);
            Route::put('/{document}', [DocumentController::class, 'update']);
            Route::patch('/{document}/publish', [DocumentController::class, 'publish']);
        });
        Route::delete('/{document}', [DocumentController::class, 'destroy'])->middleware('can:documents.delete');
    });

    // Settings & About
    Route::prefix('admin')->group(function (): void {
        Route::get('/settings', [SettingController::class, 'index'])->middleware('can:settings.view');
        Route::put('/settings', [SettingController::class, 'update'])->middleware('can:settings.manage');
        Route::get('/settings/marksheet', [SettingController::class, 'marksheet'])->middleware('can:settings.view');
        Route::put('/settings/marksheet', [SettingController::class, 'updateMarksheet'])->middleware('can:settings.manage');

        Route::prefix('settings/website')->middleware('can:public_homepage.manage')->group(function (): void {
            Route::get('/', [WebsiteSettingsController::class, 'index']);
            Route::put('/', [WebsiteSettingsController::class, 'update']);
            Route::post('/images', [WebsiteSettingsController::class, 'uploadImages']);
            Route::post('/images/action', [WebsiteSettingsController::class, 'imageAction']);
        });

        Route::get('/about', [AboutController::class, 'index'])->middleware('can:settings.manage');
    });
});
