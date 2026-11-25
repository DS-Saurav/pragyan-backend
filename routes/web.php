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
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicAdmissionsController;
use App\Http\Controllers\PublicCalendarController;
use App\Http\Controllers\PublicContactController;
use App\Http\Controllers\PublicDiscoveryController;
use App\Http\Controllers\PublicGalleryController;
use App\Http\Controllers\PublicHomepageController;
use App\Http\Controllers\PublicLocalePreferenceController;
use App\Http\Controllers\PublicNepaliController;
use App\Http\Controllers\PublicNewsController;
use App\Http\Controllers\PublicNoticeController;
use App\Http\Controllers\PublicPageController;
use App\Http\Controllers\TeamController;
use App\Services\PublicTranslations\PublicTranslationSource;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// Public content has no authenticated or form workflow. Removing the
// stateful web middleware keeps GET requests from persisting a session's
// previous URL or a database-backed session row.
$publicReadOnlyMiddleware = [
    StartSession::class,
    ShareErrorsFromSession::class,
    PreventRequestForgery::class,
];

// Editorial Media Library. Public derivatives remain deliberately separate
// from private originals and all mutation authorization is enforced in the
// MediaLibrary service itself.
Route::withoutMiddleware($publicReadOnlyMiddleware)->group(function (): void {
    Route::get('/media/{id}', [MediaFoundationController::class, 'published'])
        ->whereNumber('id')->name('media.published');

    // This is the sole locale preference write: a visitor must explicitly
    // choose a language. It stores only the two-letter locale in a cookie and
    // never infers a redirect from browser settings.
    Route::get('/language/{locale}', PublicLocalePreferenceController::class)
        ->where('locale', 'en|ne')
        ->name('public.locale.switch');
});
Route::middleware(['auth', 'verified', 'active', 'staff'])->group(function () {
    Route::get('/admin/media', [MediaFoundationController::class, 'status'])
        ->middleware('can:media.view')->name('admin.media.status');
    Route::get('/admin/media/{id}/original', [MediaFoundationController::class, 'original'])
        ->whereNumber('id')->middleware('can:media.view')->name('admin.media.original');

    Route::get('/admin/media/library', [MediaFoundationController::class, 'index'])
        ->name('admin.media.index');
    Route::post('/admin/media/library', [MediaFoundationController::class, 'upload'])
        ->name('admin.media.upload');
    Route::patch('/admin/media/library/{asset}/metadata', [MediaFoundationController::class, 'updateMetadata'])
        ->whereNumber('asset')->name('admin.media.metadata');
    Route::patch('/admin/media/library/{asset}/submit', [MediaFoundationController::class, 'submit'])
        ->whereNumber('asset')->name('admin.media.submit');
    Route::patch('/admin/media/library/{asset}/approve', [MediaFoundationController::class, 'approve'])
        ->whereNumber('asset')->name('admin.media.approve');
    Route::patch('/admin/media/library/{asset}/publish', [MediaFoundationController::class, 'publish'])
        ->whereNumber('asset')->name('admin.media.publish');
    Route::patch('/admin/media/library/{asset}/draft', [MediaFoundationController::class, 'draft'])
        ->whereNumber('asset')->name('admin.media.draft');
    Route::patch('/admin/media/library/{asset}/archive', [MediaFoundationController::class, 'archive'])
        ->whereNumber('asset')->name('admin.media.archive');
    Route::patch('/admin/media/library/{asset}/restore', [MediaFoundationController::class, 'restore'])
        ->whereNumber('asset')->name('admin.media.restore');
    Route::patch('/admin/media/library/{asset}/consent', [MediaFoundationController::class, 'consent'])
        ->whereNumber('asset')->name('admin.media.consent');
});

Route::withoutMiddleware($publicReadOnlyMiddleware)->middleware('public-locale:en')->group(function (): void {
    Route::get('/', [PublicHomepageController::class, 'index'])->name('home');

    // Search engines receive a read-only, canonical-only projection of the public
    // site. It is generated through the same public visibility services as pages.
    Route::get('/sitemap.xml', [PublicDiscoveryController::class, 'sitemap'])->name('public.sitemap');
    Route::get('/robots.txt', [PublicDiscoveryController::class, 'robots'])->name('public.robots');

    // Public Notices: only currently displayable public notices reach these routes.
    // Keep archive above the slug route because "archive" is reserved permanently.
    Route::get('/notices', [PublicNoticeController::class, 'index'])->name('public.notices');
    Route::get('/notices/archive', [PublicNoticeController::class, 'archive'])->name('public.notices.archive');
    Route::get('/notices/{slug}', [PublicNoticeController::class, 'show'])
        ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
        ->name('public.notices.show');

    // Public CMS: Team page (no login required)
    Route::get('/team', [TeamController::class, 'index'])->name('public.team');

    // Fixed, typed public information. These controllers resolve only currently
    // published revisions; direct routes remain available with honest empty states.
    Route::get('/about', [PublicPageController::class, 'about'])->name('public.about');

    // Public Admissions: approved information from the separately managed public
    // admissions-cycle workflow. It never reads internal applicant records.
    Route::get('/admissions', [PublicAdmissionsController::class, 'index'])->name('public.admissions');

    Route::get('/academics', [PublicPageController::class, 'academics'])->name('public.academics');

    // Public News: only currently displayable editorial stories ever reach these routes.
    // Keep archive above the slug route because "archive" is reserved permanently.
    Route::get('/news', [PublicNewsController::class, 'index'])->name('public.news');
    Route::get('/news/archive', [PublicNewsController::class, 'archive'])->name('public.news.archive');
    Route::get('/news/{slug}', [PublicNewsController::class, 'show'])
        ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
        ->name('public.news.show');

    // Public Gallery: only currently displayable editorial albums reach these routes.
    // Keep archive above the slug route because "archive" is reserved permanently.
    Route::get('/gallery', [PublicGalleryController::class, 'index'])->name('public.gallery');
    Route::get('/gallery/archive', [PublicGalleryController::class, 'archive'])->name('public.gallery.archive');
    Route::get('/gallery/{slug}', [PublicGalleryController::class, 'show'])
        ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
        ->name('public.gallery.show');

    // Verified Contact/Visit information is always projected through SiteIdentity.
    Route::get('/contact', [PublicContactController::class, 'index'])->name('public.contact');

    // The public calendar has its own bounded, read-only controller. It always
    // retains the model's existing `is_public` filter.
    Route::get('/calendar', [PublicCalendarController::class, 'index'])->name('public.calendar');
});

// Nepali routes are deliberately fixed under /ne rather than a catch-all
// locale parameter. This prevents a locale prefix from ever overlapping the
// authenticated application, media endpoints, or existing English URLs.
Route::withoutMiddleware($publicReadOnlyMiddleware)
    ->middleware('public-locale:ne')
    ->prefix('ne')
    ->name('public.ne.')
    ->group(function (): void {
        Route::get('/', [PublicNepaliController::class, 'home'])->name('home');
        Route::get('/about', [PublicNepaliController::class, 'about'])->name('about');
        Route::get('/academics', [PublicNepaliController::class, 'academics'])->name('academics');
        Route::get('/admissions', [PublicNepaliController::class, 'admissions'])->name('admissions');
        Route::get('/contact', [PublicNepaliController::class, 'contact'])->name('contact');
        Route::get('/calendar', [PublicNepaliController::class, 'calendar'])->name('calendar');
        Route::get('/team', [PublicNepaliController::class, 'team'])->name('team');

        Route::get('/news', [PublicNepaliController::class, 'news'])->name('news');
        Route::get('/news/archive', [PublicNepaliController::class, 'newsArchive'])->name('news.archive');
        Route::get('/news/{slug}', [PublicNepaliController::class, 'newsShow'])
            ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('news.show');

        Route::get('/gallery', [PublicNepaliController::class, 'gallery'])->name('gallery');
        Route::get('/gallery/archive', [PublicNepaliController::class, 'galleryArchive'])->name('gallery.archive');
        Route::get('/gallery/{slug}', [PublicNepaliController::class, 'galleryShow'])
            ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('gallery.show');

        Route::get('/notices', [PublicNepaliController::class, 'notices'])->name('notices');
        Route::get('/notices/archive', [PublicNepaliController::class, 'noticesArchive'])->name('notices.archive');
        Route::get('/notices/{slug}', [PublicNepaliController::class, 'noticeShow'])
            ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('notices.show');

        // Localised unknown URLs need the Nepali error shell as well.
        Route::any('/{missing}', static function (): void {
            abort(404);
        })->where('missing', '.*')->name('fallback');
    });

// Authenticated routes group
Route::middleware(['auth', 'verified', 'active'])->group(function () {

    // Role-based Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Student and parent accounts can only reach the support modules, calendar,
    // notices and their own profile.
    Route::middleware('staff')->group(function () {

        // -------------------------------------------------------------------------
        // Admin Module: Students Management
        // -------------------------------------------------------------------------
        Route::patch('admin/students/{student}/status', [StudentController::class, 'updateStatus'])
            ->name('admin.students.status');

        Route::resource('admin/students', StudentController::class)
            ->names('admin.students')
            ->except(['create', 'show', 'edit']);

        // Academic-year health measurements (Stage P3G): same staff-gated
        // students-module authorization as the student update itself.
        Route::post('admin/students/{student}/measurements', [StudentController::class, 'saveMeasurement'])
            ->name('admin.students.measurements');

        // -------------------------------------------------------------------------
        // Admin Module: Classes & Sections Management
        // -------------------------------------------------------------------------
        Route::get('/admin/classes', [SchoolClassController::class, 'index'])->name('admin.classes.index');
        Route::post('/admin/classes', [SchoolClassController::class, 'store'])->name('admin.classes.store');
        Route::delete('/admin/classes/{id}', [SchoolClassController::class, 'destroy'])->name('admin.classes.destroy');

        // -------------------------------------------------------------------------
        // Admin Module: Admissions Management
        // -------------------------------------------------------------------------
        Route::middleware('can:admissions.manage')->group(function () {
            Route::get('/admin/admissions', [AdmissionController::class, 'index'])->name('admin.admissions.index');
            Route::post('/admin/admissions', [AdmissionController::class, 'store'])->name('admin.admissions.store');
            Route::patch('/admin/admissions/{admission}/status', [AdmissionController::class, 'updateStatus'])->name('admin.admissions.status');
            Route::delete('/admin/admissions/{admission}', [AdmissionController::class, 'destroy'])->name('admin.admissions.destroy');
        });

        // -------------------------------------------------------------------------
        // Public Admissions Content
        // This intentionally uses a dedicated permission. It never grants access
        // to the internal applicant-records admissions module.
        // -------------------------------------------------------------------------
        Route::prefix('admin/public-admissions')->name('admin.public-admissions.')
            ->middleware('can:public_admissions.manage')->group(function () {
                Route::get('/', [PublicAdmissionCycleController::class, 'index'])->name('index');
                Route::get('/create', [PublicAdmissionCycleController::class, 'create'])->name('create');
                Route::post('/', [PublicAdmissionCycleController::class, 'store'])->name('store');
                Route::get('/{admissionCycle}/edit', [PublicAdmissionCycleController::class, 'edit'])->name('edit');
                Route::put('/{admissionCycle}', [PublicAdmissionCycleController::class, 'update'])->name('update');
                Route::patch('/{admissionCycle}/publish', [PublicAdmissionCycleController::class, 'publish'])->name('publish');
                Route::patch('/{admissionCycle}/withdraw', [PublicAdmissionCycleController::class, 'withdraw'])->name('withdraw');
                Route::patch('/{admissionCycle}/archive', [PublicAdmissionCycleController::class, 'archive'])->name('archive');
            });

        // -------------------------------------------------------------------------
        // Nepali public localisation editorial workflow
        // It reuses each existing public-content capability inside the
        // controller; this route group grants no new permission or role.
        // -------------------------------------------------------------------------
        Route::prefix('admin/public-localisations')->name('admin.public-localisations.')->group(function (): void {
            Route::get('/content/{translation}/revisions/{revision}/preview', [PublicTranslationController::class, 'previewContent'])->name('content.preview');
            Route::patch('/content/{translation}/revisions/{revision}/submit', [PublicTranslationController::class, 'submitContent'])->name('content.submit');
            Route::patch('/content/{translation}/revisions/{revision}/approve', [PublicTranslationController::class, 'approveContent'])->name('content.approve');
            Route::patch('/content/{translation}/revisions/{revision}/publish', [PublicTranslationController::class, 'publishContent'])->name('content.publish');
            Route::patch('/content/{translation}/withdraw', [PublicTranslationController::class, 'withdrawContent'])->name('content.withdraw');
            Route::patch('/content/{translation}/archive', [PublicTranslationController::class, 'archiveContent'])->name('content.archive');
            Route::patch('/content/{translation}/restore', [PublicTranslationController::class, 'restoreContent'])->name('content.restore');

            Route::get('/site/{translation}/revisions/{revision}/preview', [PublicTranslationController::class, 'previewSite'])->name('site.preview');
            Route::patch('/site/{translation}/revisions/{revision}/submit', [PublicTranslationController::class, 'submitSite'])->name('site.submit');
            Route::patch('/site/{translation}/revisions/{revision}/approve', [PublicTranslationController::class, 'approveSite'])->name('site.approve');
            Route::patch('/site/{translation}/revisions/{revision}/publish', [PublicTranslationController::class, 'publishSite'])->name('site.publish');
            Route::patch('/site/{translation}/withdraw', [PublicTranslationController::class, 'withdrawSite'])->name('site.withdraw');
            Route::patch('/site/{translation}/archive', [PublicTranslationController::class, 'archiveSite'])->name('site.archive');
            Route::patch('/site/{translation}/restore', [PublicTranslationController::class, 'restoreSite'])->name('site.restore');

            Route::get('/site/{scope}', [PublicTranslationController::class, 'editSite'])
                ->whereIn('scope', ['homepage', 'identity'])->name('site.edit');
            Route::put('/site/{scope}', [PublicTranslationController::class, 'saveSite'])
                ->whereIn('scope', ['homepage', 'identity'])->name('site.save');
            Route::get('/content/{contentType}/{contentId}', [PublicTranslationController::class, 'editContent'])
                ->whereIn('contentType', PublicTranslationSource::TYPES)
                ->whereNumber('contentId')->name('content.edit');
            Route::put('/content/{contentType}/{contentId}', [PublicTranslationController::class, 'saveContent'])
                ->whereIn('contentType', PublicTranslationSource::TYPES)
                ->whereNumber('contentId')->name('content.save');
        });

        // -------------------------------------------------------------------------
        // Public News Editorial Workflow
        // The controller authorizes each capability separately so a writer cannot
        // gain review or publishing authority merely by visiting this section.
        // -------------------------------------------------------------------------
        Route::prefix('admin/news')->name('admin.news.')->group(function () {
            Route::get('/', [NewsStoryController::class, 'index'])->name('index');
            Route::get('/create', [NewsStoryController::class, 'create'])->name('create');
            Route::post('/', [NewsStoryController::class, 'store'])->name('store');
            Route::get('/{newsStory}/edit', [NewsStoryController::class, 'edit'])->name('edit');
            Route::put('/{newsStory}', [NewsStoryController::class, 'update'])->name('update');
            Route::get('/{newsStory}/preview/{revision}', [NewsStoryController::class, 'preview'])->name('preview');
            Route::patch('/{newsStory}/revisions/{revision}/submit', [NewsStoryController::class, 'submit'])->name('submit');
            Route::patch('/{newsStory}/revisions/{revision}/approve', [NewsStoryController::class, 'approve'])->name('approve');
            Route::patch('/{newsStory}/revisions/{revision}/publish', [NewsStoryController::class, 'publish'])->name('publish');
            Route::patch('/{newsStory}/withdraw', [NewsStoryController::class, 'withdraw'])->name('withdraw');
            Route::patch('/{newsStory}/archive', [NewsStoryController::class, 'archive'])->name('archive');
            Route::patch('/{newsStory}/restore', [NewsStoryController::class, 'restore'])->name('restore');
            Route::patch('/{newsStory}/feature', [NewsStoryController::class, 'feature'])->name('feature');
            Route::delete('/{newsStory}/feature', [NewsStoryController::class, 'unfeature'])->name('unfeature');
        });

        // -------------------------------------------------------------------------
        // Public Gallery Editorial Workflow
        // Photo placement is deliberately handled by dedicated Gallery actions;
        // generic media attachments cannot add images to unrelated content.
        // -------------------------------------------------------------------------
        Route::prefix('admin/gallery')->name('admin.gallery.')->group(function () {
            Route::get('/', [GalleryAlbumController::class, 'index'])->name('index');
            Route::get('/create', [GalleryAlbumController::class, 'create'])->name('create');
            Route::post('/', [GalleryAlbumController::class, 'store'])->name('store');
            Route::get('/{galleryAlbum}/edit', [GalleryAlbumController::class, 'edit'])->name('edit');
            Route::put('/{galleryAlbum}', [GalleryAlbumController::class, 'update'])->name('update');
            Route::get('/{galleryAlbum}/preview/{revision}', [GalleryAlbumController::class, 'preview'])->name('preview');
            Route::patch('/{galleryAlbum}/revisions/{revision}/cover', [GalleryAlbumController::class, 'setCover'])->name('cover');
            Route::post('/{galleryAlbum}/revisions/{revision}/photos', [GalleryAlbumController::class, 'addPhoto'])->name('photos.store');
            Route::patch('/{galleryAlbum}/revisions/{revision}/photos/reorder', [GalleryAlbumController::class, 'reorderPhotos'])->name('photos.reorder');
            Route::patch('/{galleryAlbum}/revisions/{revision}/photos/{photo}', [GalleryAlbumController::class, 'updatePhoto'])->name('photos.update');
            Route::delete('/{galleryAlbum}/revisions/{revision}/photos/{photo}', [GalleryAlbumController::class, 'removePhoto'])->name('photos.destroy');
            Route::patch('/{galleryAlbum}/revisions/{revision}/submit', [GalleryAlbumController::class, 'submit'])->name('submit');
            Route::patch('/{galleryAlbum}/revisions/{revision}/approve', [GalleryAlbumController::class, 'approve'])->name('approve');
            Route::patch('/{galleryAlbum}/revisions/{revision}/publish', [GalleryAlbumController::class, 'publish'])->name('publish');
            Route::patch('/{galleryAlbum}/withdraw', [GalleryAlbumController::class, 'withdraw'])->name('withdraw');
            Route::patch('/{galleryAlbum}/archive', [GalleryAlbumController::class, 'archive'])->name('archive');
            Route::patch('/{galleryAlbum}/restore', [GalleryAlbumController::class, 'restore'])->name('restore');
            Route::patch('/{galleryAlbum}/feature', [GalleryAlbumController::class, 'feature'])->name('feature');
            Route::delete('/{galleryAlbum}/feature', [GalleryAlbumController::class, 'unfeature'])->name('unfeature');
        });

        // -------------------------------------------------------------------------
        // Fixed Public Pages: About and Academics only. The controller checks
        // each granular capability server-side; this route group grants none.
        // -------------------------------------------------------------------------
        Route::prefix('admin/public-pages')->name('admin.public-pages.')->group(function () {
            Route::get('/', [AdminPublicPageController::class, 'index'])->name('index');
            Route::get('/{type}/create', [AdminPublicPageController::class, 'create'])
                ->whereIn('type', ['about', 'academics'])->name('create');
            Route::post('/{type}', [AdminPublicPageController::class, 'store'])
                ->whereIn('type', ['about', 'academics'])->name('store');
            Route::get('/{publicPage}/edit', [AdminPublicPageController::class, 'edit'])->name('edit');
            Route::put('/{publicPage}', [AdminPublicPageController::class, 'update'])->name('update');
            Route::get('/{publicPage}/preview/{revision}', [AdminPublicPageController::class, 'preview'])->name('preview');
            Route::patch('/{publicPage}/revisions/{revision}/submit', [AdminPublicPageController::class, 'submit'])->name('submit');
            Route::patch('/{publicPage}/revisions/{revision}/approve', [AdminPublicPageController::class, 'approve'])->name('approve');
            Route::patch('/{publicPage}/revisions/{revision}/publish', [AdminPublicPageController::class, 'publish'])->name('publish');
            Route::patch('/{publicPage}/withdraw', [AdminPublicPageController::class, 'withdraw'])->name('withdraw');
            Route::patch('/{publicPage}/cancel-schedule', [AdminPublicPageController::class, 'cancelSchedule'])->name('cancel-schedule');
            Route::patch('/{publicPage}/archive', [AdminPublicPageController::class, 'archive'])->name('archive');
            Route::patch('/{publicPage}/restore', [AdminPublicPageController::class, 'restore'])->name('restore');
        });

        // -------------------------------------------------------------------------
        // Public Team uses a narrow, revision-backed projection rather than
        // exposing StaffMember records or legacy staff photo paths.
        // -------------------------------------------------------------------------
        Route::prefix('admin/public-team')->name('admin.public-team.')->group(function () {
            Route::get('/', [PublicTeamProfileController::class, 'index'])->name('index');
            Route::get('/create', [PublicTeamProfileController::class, 'create'])->name('create');
            Route::post('/', [PublicTeamProfileController::class, 'store'])->name('store');
            Route::get('/{publicTeamProfile}/edit', [PublicTeamProfileController::class, 'edit'])->name('edit');
            Route::put('/{publicTeamProfile}', [PublicTeamProfileController::class, 'update'])->name('update');
            Route::get('/{publicTeamProfile}/preview/{revision}', [PublicTeamProfileController::class, 'preview'])->name('preview');
            Route::patch('/{publicTeamProfile}/revisions/{revision}/submit', [PublicTeamProfileController::class, 'submit'])->name('submit');
            Route::patch('/{publicTeamProfile}/revisions/{revision}/approve', [PublicTeamProfileController::class, 'approve'])->name('approve');
            Route::patch('/{publicTeamProfile}/revisions/{revision}/publish', [PublicTeamProfileController::class, 'publish'])->name('publish');
            Route::patch('/{publicTeamProfile}/withdraw', [PublicTeamProfileController::class, 'withdraw'])->name('withdraw');
            Route::patch('/{publicTeamProfile}/cancel-schedule', [PublicTeamProfileController::class, 'cancelSchedule'])->name('cancel-schedule');
            Route::patch('/{publicTeamProfile}/archive', [PublicTeamProfileController::class, 'archive'])->name('archive');
            Route::patch('/{publicTeamProfile}/restore', [PublicTeamProfileController::class, 'restore'])->name('restore');
        });

        // Public Identity / Visit is deliberately separate from broad settings.
        Route::prefix('admin/public-identity')->name('admin.public-identity.')->group(function () {
            Route::get('/', [PublicIdentityController::class, 'index'])->name('index');
            Route::put('/', [PublicIdentityController::class, 'update'])->name('update');
        });

        Route::prefix('admin/public-photos')->name('admin.public-photos.')->group(function () {
            Route::get('/', [PublicPhotoSlotController::class, 'index'])->name('index');
            Route::put('/{slot}', [PublicPhotoSlotController::class, 'update'])->name('update');
        });

        // -------------------------------------------------------------------------
        // Admin Module: Parents Management
        // -------------------------------------------------------------------------
        Route::get('admin/parents/export-csv', [ParentController::class, 'exportCsv'])->name('admin.parents.export.csv');
        Route::get('admin/parents/export-pdf', [ParentController::class, 'exportPdf'])->name('admin.parents.export.pdf');

        Route::resource('admin/parents', ParentController::class)->names('admin.parents')
            ->except(['create', 'show', 'edit']);

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

        Route::resource('admin/assets', AssetController::class)
            ->names('admin.assets')
            ->except(['create', 'show', 'edit', 'update']);

        // -------------------------------------------------------------------------
        // Admin Module: Stocks/Inventory Ledger Management
        // -------------------------------------------------------------------------
        Route::prefix('admin')->name('admin.')->group(function () {
            Route::post('inventory/{inventoryItem}/restock', [InventoryController::class, 'restock'])->name('inventory.restock');
            Route::post('inventory/{inventoryItem}/dispense', [InventoryController::class, 'dispense'])->name('inventory.dispense');
            Route::resource('inventory', InventoryController::class)
                ->parameters(['inventory' => 'inventoryItem'])
                ->except(['create', 'edit']);
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
            Route::get('/create', [NoticeController::class, 'create'])->name('create');
            Route::post('/', [NoticeController::class, 'store'])->name('store');
            Route::get('/{notice}/edit', [NoticeController::class, 'edit'])->name('edit');
            Route::put('/{notice}', [NoticeController::class, 'update'])->name('update');
            Route::get('/{notice}/preview/{revision}', [NoticeController::class, 'preview'])->name('preview');
            Route::patch('/{notice}/revisions/{revision}/submit', [NoticeController::class, 'submit'])->name('submit');
            Route::patch('/{notice}/revisions/{revision}/approve', [NoticeController::class, 'approve'])->name('approve');
            Route::patch('/{notice}/revisions/{revision}/publish', [NoticeController::class, 'publish'])->name('publish');
            Route::patch('/{notice}/withdraw', [NoticeController::class, 'withdraw'])->name('withdraw');
            Route::patch('/{notice}/archive', [NoticeController::class, 'archive'])->name('archive');
            Route::patch('/{notice}/restore', [NoticeController::class, 'restore'])->name('restore');
            Route::delete('/{notice}', [NoticeController::class, 'destroy'])->middleware('can:notices.manage')->name('destroy');
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

    }); // end staff-only modules

    // -------------------------------------------------------------------------
    // Admin Module: School Calendar. Private event data is staff-only and
    // requires a dedicated viewing capability. Public calendar changes add the
    // controller-enforced public_calendar.manage boundary.
    // -------------------------------------------------------------------------
    Route::middleware(['staff', 'can:calendar.view'])->prefix('admin/calendar')->name('admin.calendar.')->group(function () {
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

        // Assessment-indicator entry (Stage P3I): same authorization shell
        // as the marks themselves (roster page = marks.view, save = marks.enter).
        Route::get('marks/assessments', [MarksEntryController::class, 'assessments'])->middleware('can:marks.view')->name('marks.assessments');
        Route::put('marks/{exam}/assessments', [MarksEntryController::class, 'saveAssessments'])->middleware('can:marks.enter')->name('marks.assessments.save');

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
    // Admin Module: Conduct  [conduct.view / conduct.manage]
    //   view   : see the conduct register
    //   manage : add, edit and delete conduct records
    // -------------------------------------------------------------------------
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::resource('conduct', ConductController::class)
            ->only(['index'])
            ->middleware('can:conduct.view');

        Route::resource('conduct', ConductController::class)
            ->only(['store', 'edit', 'update', 'destroy'])
            ->middleware('can:conduct.manage');
    });

    // -------------------------------------------------------------------------
    // Admin Module: Activities  [activities.view / activities.manage]
    //   view   : see ECA activities
    //   manage : add, edit and delete activities
    // -------------------------------------------------------------------------
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::resource('activities', ActivityController::class)
            ->only(['index'])
            ->middleware('can:activities.view');

        Route::resource('activities', ActivityController::class)
            ->only(['store', 'edit', 'update', 'destroy'])
            ->middleware('can:activities.manage');
    });

    // -------------------------------------------------------------------------
    // Admin Module: Fees Collection  [fees.*]
    // -------------------------------------------------------------------------
    Route::prefix('admin/fees')->name('admin.fees.')->group(function () {

        Route::middleware('can:fees.view')->group(function () {

            Route::get('/', [FeeController::class, 'index'])->name('index');
            Route::get('/csv', [FeeController::class, 'csv'])->name('csv');
            Route::get('/{receipt}/print', [FeeController::class, 'receipt'])->name('print');

        });

        Route::post('/', [FeeController::class, 'store'])->middleware('can:fees.collect')->name('store');
        Route::post('/{receipt}/pay', [FeeController::class, 'pay'])->middleware('can:fees.collect')->name('pay');
        Route::post('/{receipt}/refund', [FeeController::class, 'refund'])->middleware('can:fees.refund')->name('refund');
        Route::delete('/{receipt}', [FeeController::class, 'destroy'])->middleware('can:fees.delete')->name('destroy');
    });

    // -------------------------------------------------------------------------
    // Admin Module: Fee Structure  [fees.view / fees.structure / fees.generate]
    // -------------------------------------------------------------------------
    Route::prefix('admin/fee-structure')->name('admin.fee-structure.')->group(function () {

        Route::get('/', [FeeStructureController::class, 'index'])->middleware('can:fees.view')->name('index');

        Route::middleware('can:fees.structure')->group(function () {
            Route::put('/', [FeeStructureController::class, 'save'])->name('save');
            Route::post('/copy', [FeeStructureController::class, 'copy'])->name('copy');
            Route::post('/heads', [FeeStructureController::class, 'storeHead'])->name('heads.store');
            Route::put('/heads/{head}', [FeeStructureController::class, 'updateHead'])->name('heads.update');
            Route::delete('/heads/{head}', [FeeStructureController::class, 'destroyHead'])->name('heads.destroy');
        });

        Route::post('/generate', [FeeStructureController::class, 'generate'])->middleware('can:fees.generate')->name('generate');
    });

    // -------------------------------------------------------------------------
    // Admin Module: Payroll / Salary  [payroll.view / payroll.manage / payroll.pay]
    // -------------------------------------------------------------------------
    Route::prefix('admin/payroll')->name('admin.payroll.')->group(function () {

        Route::middleware('can:payroll.view')->group(function () {

            Route::get('/', [PayrollController::class, 'index'])->name('index');
            Route::get('/csv', [PayrollController::class, 'csv'])->name('csv');
            Route::get('/print', [PayrollController::class, 'printMonth'])->name('print-month');
            Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
            Route::get('/{slip}/print', [PayrollController::class, 'slip'])->name('slip');
        });

        Route::middleware('can:payroll.manage')->group(function () {
            Route::post('/', [PayrollController::class, 'store'])->name('store');
            Route::post('/run', [PayrollController::class, 'run'])->name('run');

            Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
            Route::post('/staff/import-teachers', [StaffController::class, 'importTeachers'])->name('staff.import');
            Route::put('/staff/{staff}', [StaffController::class, 'update'])->name('staff.update');
            Route::delete('/staff/{staff}', [StaffController::class, 'destroy'])->name('staff.destroy');

            Route::post('/components', [StaffController::class, 'storeComponent'])->name('components.store');
            Route::put('/components/{component}', [StaffController::class, 'updateComponent'])->name('components.update');
            Route::delete('/components/{component}', [StaffController::class, 'destroyComponent'])->name('components.destroy');

            Route::put('/{slip}', [PayrollController::class, 'update'])->name('update');
            Route::delete('/{slip}', [PayrollController::class, 'destroy'])->name('destroy');
        });

        Route::middleware('can:payroll.pay')->group(function () {
            Route::post('/pay-all', [PayrollController::class, 'payAll'])->name('pay-all');
            Route::post('/{slip}/pay', [PayrollController::class, 'pay'])->name('pay');
            Route::post('/{slip}/unpay', [PayrollController::class, 'unpay'])->name('unpay');
        });
    });

    // -------------------------------------------------------------------------
    // Admin Module: Users / Staff  [users.view / users.manage / users.delete]
    // -------------------------------------------------------------------------
    Route::prefix('admin/users')->name('admin.users.')->group(function () {

        Route::middleware('can:users.view')->group(function () {

            Route::get('/', [UserController::class, 'index'])->name('index');
            Route::get('/csv', [UserController::class, 'csv'])->name('csv');

        });

        Route::middleware('can:users.manage')->group(function () {
            Route::post('/', [UserController::class, 'store'])->name('store');
            Route::put('/{user}', [UserController::class, 'update'])->name('update');
            Route::post('/{user}/status', [UserController::class, 'toggleStatus'])->name('status');
            Route::post('/{user}/reset-password', [UserController::class, 'resetPassword'])->name('reset-password');
            Route::post('/{user}/website', [UserController::class, 'toggleWebsite'])->name('website');
            Route::get('/{user}/editorial-access', [UserController::class, 'editorialAccess'])->name('editorial-access');
            Route::put('/{user}/editorial-access', [UserController::class, 'updateEditorialAccess'])->name('editorial-access.update');
        });

        Route::delete('/{user}', [UserController::class, 'destroy'])->middleware('can:users.delete')->name('destroy');
    });

    // -------------------------------------------------------------------------
    // Admin Module: Daily Accounts  [accounts.view / accounts.manage]
    // -------------------------------------------------------------------------
    Route::prefix('admin/accounts')->name('admin.accounts.')->group(function () {

        Route::middleware('can:accounts.view')->group(function () {

            Route::get('/', [AccountController::class, 'index'])->name('index');
            Route::get('/csv', [AccountController::class, 'csv'])->name('csv');
            Route::get('/book', [AccountController::class, 'book'])->name('book');

        });

        Route::middleware('can:accounts.manage')->group(function () {
            Route::post('/', [AccountController::class, 'store'])->name('store');
            Route::put('/{entry}', [AccountController::class, 'update'])->name('update');
            Route::delete('/{entry}', [AccountController::class, 'destroy'])->name('destroy');
        });
    });

    // -------------------------------------------------------------------------
    // Admin Module: Helpdesk  [helpdesk.view / create / manage / delete]
    //   view   : see tickets (staff with manage see all, everyone else only their own) and reply
    //   create : raise a ticket
    //   manage : change status, priority and assignee, post internal notes
    //   delete : delete a ticket
    // Note: the fixed paths (/csv) must stay above the /{ticket} routes.
    // -------------------------------------------------------------------------
    Route::prefix('admin/helpdesk')->name('admin.helpdesk.')->group(function () {

        Route::middleware('can:helpdesk.view')->group(function () {

            Route::get('/', [HelpdeskController::class, 'index'])->name('index');
            Route::get('/csv', [HelpdeskController::class, 'csv'])->name('csv');
            Route::get('/{ticket}', [HelpdeskController::class, 'show'])->name('show');
            Route::post('/{ticket}/reply', [HelpdeskController::class, 'reply'])->name('reply');

        });

        Route::post('/', [HelpdeskController::class, 'store'])->middleware('can:helpdesk.create')->name('store');
        Route::patch('/{ticket}', [HelpdeskController::class, 'update'])->middleware('can:helpdesk.manage')->name('update');
        Route::delete('/{ticket}', [HelpdeskController::class, 'destroy'])->middleware('can:helpdesk.delete')->name('destroy');
    });

    // -------------------------------------------------------------------------
    // Admin Module: Complaints  [complaints.view / create / manage / delete]
    //   view   : see complaints (staff with manage see all, everyone else only their own) and reply
    //   create : file a complaint (optionally anonymous)
    //   manage : change status, severity and assignee, resolve/dismiss, escalate, internal notes
    //   delete : delete a complaint
    // Note: the fixed paths (/csv) must stay above the /{complaint} routes.
    // -------------------------------------------------------------------------
    Route::prefix('admin/complaints')->name('admin.complaints.')->group(function () {

        Route::middleware('can:complaints.view')->group(function () {

            Route::get('/', [ComplaintController::class, 'index'])->name('index');
            Route::get('/csv', [ComplaintController::class, 'csv'])->name('csv');
            Route::get('/{complaint}', [ComplaintController::class, 'show'])->name('show');
            Route::post('/{complaint}/reply', [ComplaintController::class, 'reply'])->name('reply');

        });

        Route::post('/', [ComplaintController::class, 'store'])->middleware('can:complaints.create')->name('store');

        Route::middleware('can:complaints.manage')->group(function () {
            Route::patch('/{complaint}', [ComplaintController::class, 'update'])->name('update');
            Route::post('/{complaint}/escalate', [ComplaintController::class, 'escalate'])->name('escalate');
        });

        Route::delete('/{complaint}', [ComplaintController::class, 'destroy'])->middleware('can:complaints.delete')->name('destroy');
    });

    // -------------------------------------------------------------------------
    // Admin Module: Documents  [documents.view / manage / delete]
    //   view   : see and download the documents meant for the user's own role
    //   manage : add, edit, replace files, publish/unpublish, see drafts and expired documents
    //   delete : delete a document
    // Note: the fixed path (/csv) must stay above the /{document} routes.
    // -------------------------------------------------------------------------
    Route::prefix('admin/documents')->name('admin.documents.')->group(function () {

        Route::middleware('can:documents.view')->group(function () {

            Route::get('/', [DocumentController::class, 'index'])->name('index');
            Route::get('/csv', [DocumentController::class, 'csv'])->name('csv');
            Route::get('/{document}/download', [DocumentController::class, 'download'])->name('download');

        });

        Route::middleware('can:documents.manage')->group(function () {
            Route::post('/', [DocumentController::class, 'store'])->name('store');
            Route::put('/{document}', [DocumentController::class, 'update'])->name('update');
            Route::patch('/{document}/publish', [DocumentController::class, 'publish'])->name('publish');
        });

        Route::delete('/{document}', [DocumentController::class, 'destroy'])->middleware('can:documents.delete')->name('destroy');
    });

    // -------------------------------------------------------------------------
    // Admin Module: Substitute Allocations
    //   index/store/destroy only - the create form lives on the index page.
    // -------------------------------------------------------------------------
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::resource('substitutes', SubstituteController::class)
            ->except(['create', 'show', 'edit', 'update'])
            ->middleware('staff');
    });

    // -------------------------------------------------------------------------
    // Admin Module: Settings  [settings.view / settings.manage]
    //   view   : open the settings page
    //   manage : save school details, branding and academic defaults
    // -------------------------------------------------------------------------
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('settings', [SettingController::class, 'index'])
            ->middleware('can:settings.view')
            ->name('settings.index');

        Route::put('settings', [SettingController::class, 'update'])
            ->middleware('can:settings.manage')
            ->name('settings.update');

        // Marksheet layout editor [settings.view / settings.manage]
        Route::get('settings/marksheet', [SettingController::class, 'marksheet'])
            ->middleware('can:settings.view')
            ->name('settings.marksheet');

        Route::put('settings/marksheet', [SettingController::class, 'updateMarksheet'])
            ->middleware('can:settings.manage')
            ->name('settings.marksheet.update');

        // -------------------------------------------------------------------------
        // Website & homepage content is public editorial work. It deliberately
        // does not inherit the principal's general settings.manage capability.
        // No role receives public_homepage.manage by default; the admin Gate
        // bypass is the only current access path.
        // -------------------------------------------------------------------------
        Route::prefix('settings/website')->name('settings.website.')->group(function () {
            Route::get('/', [WebsiteSettingsController::class, 'index'])
                ->middleware('can:public_homepage.manage')
                ->name('index');

            Route::middleware('can:public_homepage.manage')->group(function () {
                Route::put('/', [WebsiteSettingsController::class, 'update'])->name('update');
                Route::post('/images', [WebsiteSettingsController::class, 'uploadImages'])->name('images.upload');
                Route::post('/images/action', [WebsiteSettingsController::class, 'imageAction'])->name('images.action');
            });
        });

        Route::get('about', [AboutController::class, 'index'])->middleware('can:settings.manage')->name('about');
    });

    // -------------------------------------------------------------------------
    // Profile routes (generated by Breeze)
    // -------------------------------------------------------------------------
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

// Give unknown public addresses the same sessionless, safe 404 response.
// Authenticated portal routes remain explicitly registered above and retain
// their session middleware.
Route::fallback(static function (): void {
    abort(404);
})->withoutMiddleware($publicReadOnlyMiddleware)->middleware('public-locale:en');
