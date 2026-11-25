# PROJECT_OVERVIEW

> Evidence basis: direct inspection of `composer.json`, `composer.lock` (via `composer show`),
> `package.json`, `node_modules`, `routes/*.php`, all of `app/`, `database/migrations`, `database/seeders`,
> `config/*.php`, `resources/views`, and `tests/`. Route counts obtained from `php artisan route:list`.
> The working tree has uncommitted modifications (see `git status`); this documentation reflects the
> current on-disk state, not necessarily the last commit.

## 1. Application purpose

A Laravel-based **school management system** with an embedded **public school website**. The public
branding resolves from the configured application name (`APP_NAME`, currently a school name; the value
lives in the git-ignored `.env` and is not reproduced here). The administration backend is implemented
across many modules; the public frontend is only partially implemented.

Intended users (evidence: `config/permissions.php` `role_labels`, `database/migrations/2026_10_18_111130_create_user_roles_table.php`,
`app/Http/Controllers/Auth/RegisteredUserController.php`):

- School administrators and office staff
- Principals and coordinators (academic, discipline, ECA)
- Teachers
- Librarians
- Accountants
- Students
- Parents/guardians
- Anonymous visitors on the public site (no login)

## 2. Technology stack and versions

| Component | Declared | Actually installed / resolved |
|---|---|---|
| PHP | `composer.json` requires `^8.3` | Local CLI is **PHP 8.4.22** (`php -v`) |
| Laravel framework | `laravel/framework: ^13.17` | **v13.36.0** (`composer show`) |
| PDF | `barryvdh/laravel-dompdf: ^3.1` | **v3.1.2** (dependency present; see note below) |
| Tinker | `laravel/tinker: ^3.0` | installed |
| Breeze (dev) | `laravel/breeze: ^2.4` | dev dependency (scaffolding source of auth views) |
| Pint (dev) | `laravel/pint: ^1.27` | dev dependency |
| Pail (dev) | `laravel/pail` | dev dependency |
| PHPUnit (dev) | `phpunit/phpunit: ^12.5.12` | dev dependency |
| Vite | `vite: ^8.0.0` | **8.3.4** |
| Tailwind CSS | `tailwindcss: ^3.1.0` | **3.4.19** |
| `@tailwindcss/vite` | `^4.0.0` (dev) | **4.3.3 installed but NOT referenced** by `vite.config.js` |
| Alpine.js | `^3.4.2` | installed, bootstrapped in `resources/js/app.js` |
| laravel-vite-plugin | `^3.1` | installed |

Notes / inconsistencies:

- `CLAUDE.md` (L8) claims the app runs on PHP 8.4, while `composer.json` (L12) requires `^8.3`. Both are
  satisfied by the local PHP 8.4.22.
- `barryvdh/laravel-dompdf` is installed but **no application code calls the PDF facade**. All
  "print/PDF" endpoints render HTML Blade views intended for the browser print dialog. Do not assume
  server-side PDF generation exists.
- `@tailwindcss/vite` v4 is installed but unused; the active pipeline is Tailwind v3 via
  `postcss.config.js` + `tailwind.config.js`.
- There is **no `routes/api.php`**, no `withRouting(api: ...)` registration in `bootstrap/app.php`, no
  Eloquent API Resources, and no `laravel/sanctum`/Passport. The application is server-rendered Blade
  only.

## 3. Main modules and implementation status

Status legend: **Implemented** = controllers + routes + views wired and reachable; **Partial** = some
functionality present but gaps/placeholders; **Unverified** = code exists but not exercised.

| Module | Route prefix / name | Status | Primary evidence |
|---|---|---|---|
| Dashboard | `/dashboard` | Implemented (role-aware) | `app/Http/Controllers/DashboardController.php` |
| Auth (Breeze) | `/login`, `/register`, password reset, verify email | Implemented | `routes/auth.php`, `app/Http/Controllers/Auth/*` |
| Students | `admin.students.*` | Implemented | `Admin/StudentController.php` |
| Admissions | `admin.admissions.*` | Implemented | `Admin/AdmissionController.php` |
| Classes & sections | `admin.classes.*` | Implemented (create/list/delete only) | `Admin/SchoolClassController.php` |
| Subjects | `admin.subjects.*` | Implemented | `Admin/SubjectController.php` |
| Teachers | `admin.teachers.*` | Implemented | `Admin/TeacherController.php` |
| Parents | `admin.parents.*` | Implemented (+CSV/print) | `Admin/ParentController.php` |
| Users & roles | `admin.users.*` | Implemented | `Admin/UserController.php` |
| Attendance | `admin.attendance.*` | Implemented | `Admin/AttendanceController.php` |
| Timetable | `admin.timetable.*` | Implemented | `Admin/TimetableController.php` |
| Exams | `admin.exams.*` | Implemented | `Admin/ExamController.php`, `Admin/ExamSetupController.php` |
| Marks entry | `admin.marks.*` | Implemented (draft/submit/lock) | `Admin/MarksEntryController.php` |
| Results / marksheets | `admin.results.*` | Implemented (HTML print) | `Admin/ResultController.php`, `app/Services/ResultService.php` |
| Hall tickets | `admin.hall-tickets.index` | Implemented (HTML print) | `Admin/Hallticketcontroller.php` |
| Library | `admin.library.*` | Implemented | `Admin/LibraryController.php` |
| Inventory | `admin.inventory.*` | Implemented (stock ledger) | `Admin/InventoryController.php` |
| Assets | `admin.assets.*` | Implemented (loans, barcode return) | `Admin/AssetController.php` |
| Fees | `admin.fees.*` | Implemented | `Admin/FeeController.php` |
| Fee structure | `admin.fee-structure.*` | Implemented | `Admin/FeeStructureController.php` |
| Payroll | `admin.payroll.*` | Implemented | `Admin/PayrollController.php`, `Admin/StaffController.php` |
| Daily accounts | `admin.accounts.*` | Implemented | `Admin/AccountController.php` |
| Notices | `admin.notices.*` (+ public `/notices`) | Implemented | `Admin/NoticeController.php`, `routes/web.php` closures |
| Calendar | `admin.calendar.*` | Implemented | `Admin/SchoolEventController.php` |
| PTM | `admin.ptm.*` | Implemented | `Admin/PtmController.php` |
| Lesson plans | `admin.lesson-plans.*` | Implemented | `Admin/LessonPlanController.php` |
| Teaching logs | `admin.teaching-logs.*` | Implemented | `Admin/TeachingLogController.php` |
| Discipline | `admin.discipline.*` | Implemented | `Admin/DisciplineController.php` |
| Conduct | `admin.conduct.*` | Implemented | `Admin/ConductController.php` |
| Activities (ECA) | `admin.activities.*` | Implemented | `Admin/ActivityController.php` |
| Helpdesk | `admin.helpdesk.*` | Implemented | `Admin/HelpdeskController.php` |
| Complaints | `admin.complaints.*` | Implemented (anonymous supported) | `Admin/ComplaintController.php` |
| Documents | `admin.documents.*` | Implemented (private disk) | `Admin/DocumentController.php` |
| Substitutes | `admin.substitutes.*` | Implemented | `Admin/SubstituteController.php` |
| Settings | `admin.settings.*` | Implemented | `Admin/SettingController.php` |
| About | `admin.about` | Implemented (static page) | `Admin/AboutController.php` |
| Public team | `/team` | Implemented | `TeamController.php`, `resources/views/frontend/team.blade.php` |
| Public home | `/` | Implemented (custom landing) | `routes/web.php:45`, `resources/views/welcome.blade.php` |
| Public notices | `/notices` | Implemented | `routes/web.php:56`, `resources/views/frontend/notices.blade.php` |
| Public website (broader) | — | **Partial** | Only 3 public pages exist; no shared public layout |

## 4. User roles and permissions (evidence-based)

Two role mechanisms coexist (see `AUTHENTICATION_AND_AUTHORIZATION.md` for detail):

1. **Legacy column** `users.role` — enum `('admin','teacher','student','parent')`, created in
   `0001_01_01_000000_create_users_table.php`.
2. **Active system** `user_roles` table (`user_id`, `role`) + `config/permissions.php` (roles →
   permissions) enforced through `App\Models\Concerns\HasSchoolRoles` and `Gate::define` in
   `app/Providers/AppServiceProvider.php:33-35`.

Roles defined in `config/permissions.php:5-16`:
`admin`, `principal`, `academic_coordinator`, `discipline_coordinator`, `eca_coordinator`, `teacher`,
`librarian`, `accountant`, `parent`, `student`.

`admin` has `['*']` (all permissions). `Gate::before` grants admin everything
(`AppServiceProvider.php:26`). Permission names are listed in `config/permissions.php:19-80` and are
enforced via `can:` middleware on routes.

## 5. Major business workflows (implemented)

- **Admission → student**: `AdmissionController` records enquiries; `StudentController@store` creates
  students with a generated `adm_number` and links `class_applied` → `school_class_id`
  (`Student::booted()`).
- **Attendance**: daily marking and "copy from yesterday" (`AttendanceController`).
- **Fees**: define heads/structure → generate bills for a class → collect payments → printable receipt
  (`FeeStructureController`, `FeeController`; `FeeReceipt::nextNumber`, `refreshTotals`).
- **Exams**: create exam → set up subjects per class → enter marks → lock → publish → results/marksheets/
  hall tickets (`ExamController`, `ExamSetupController`, `MarksEntryController`, `ResultController`,
  `HallTicketController`, `ResultService`, `GradeScale`).
- **Payroll**: staff master → salary components → generate slips → pay → printable slip
  (`StaffController`, `PayrollController`, `SalarySlip::compute`).
- **Support**: helpdesk tickets and complaints with assignment, internal notes, SLA/overdue detection,
  anonymous complaints (`HelpdeskController`, `ComplaintController`).
- **Documents**: role-targeted document library on the private disk with versions and expiry
  (`DocumentController`, `SchoolDocument` scopes).
- **Discipline/conduct**: incident register with parent notification letter, and a merit/demerit conduct
  register (`DisciplineController`, `ConductController`).

## 6. External integrations and third-party packages

- **No external service integrations are wired**: no payment gateway, SMS, or analytics integration
  exists in code. `config/services.php` retains default Postmark/Resend/SES/Slack placeholders that are
  unused.
- **Mail** defaults to the `log` driver (`.env`); Breeze password-reset/verification flows use it. No
  custom mailables or notifications exist.
- **Queue** defaults to `database`; there are no jobs, batches, or event listeners beyond framework
  auth events and the `Login` listener in `AppServiceProvider.php:28-30`.
- **Storage**: `public` disk for photos and most uploads; `local` (private) disk for documents.
- **CDN assets** (no local copies): Figtree via `fonts.bunny.net`, Bootstrap Icons via jsDelivr
  (`resources/views/layouts/guest.blade.php`, `layouts/admin.blade.php`).

## 7. Public website vs authenticated administration

- **Public (no login)**: `/` (home), `/notices`, `/team`, `/login`, `/register`,
  `/forgot-password`, `/reset-password/{token}`, plus health check `/up`.
- **Authenticated**: everything under `/dashboard`, `/profile`, and `/admin/*`, protected by
  `auth`, `verified`, `active` and per-route `can:` permission middleware.
- Community accounts (students/parents) are blocked from staff-only modules via the `staff` middleware
  (`EnsureUserIsStaff`) and by lacking permissions. They can still reach the calendar index and the
  support modules they hold permissions for.

## 8. Known limitations and incomplete features

- **Public website is incomplete**: only three public pages; no shared public layout; `welcome` and
  `frontend/notices` duplicate header/footer markup; `frontend/team` is a standalone page with inline
  CSS and no Vite/Tailwind.
- **No REST/JSON API** (except a few JSON-returning admin helpers such as `ActivityController@edit`,
  `ConductController@edit`, `InventoryController@show`).
- **No server-side PDF** despite dompdf being installed; printing relies on the browser.
- **Seed data is partially broken**: `DatabaseSeeder` inserts a `Student` using columns that do not
  exist (`user_id`, `roll_number`, `guardian_name`, `guardian_phone`) and omits required NOT NULL
  columns, so `php artisan db:seed` is expected to fail. `FeeHeadSeeder` and `SalaryComponentSeeder`
  are not called by `DatabaseSeeder`.
- **Dual role systems** (`users.role` vs `user_roles`) can diverge; the seeders create `users.role`
  values but no `user_roles` rows.
- **Placeholder content**: print views hardcode a placeholder school address; `admin/dashboard.blade.php`,
  `student/dashboard.blade.php` (empty), and `teacher/dashboard.blade.php` (empty) are orphaned.
- **Timetable `index` writes period slots during a GET** (side effect on read).
- **No scheduled tasks** are defined (`routes/console.php` only registers `inspire`).
- Several admin views lack `@section('title')`, so their browser tab shows the default "Dashboard".

See `IMPLEMENTATION_STATUS.md` for the full implemented / partial / unknown breakdown and
`VALIDATION_AND_ERROR_HANDLING.md` for validation and authorization gaps.
