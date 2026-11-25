# Implementation Status

This document classifies the application's features by their actual state on disk. Statuses are assigned strictly from code evidence — routes registered, controllers implementing behavior, models/migrations present, and views returned by controllers. No features are inferred or assumed.

**Status legend**

- **Implemented** — full request→business-logic→persistence→UI path exists and is wired end to end.
- **Partially implemented** — the feature exists but with identified gaps (missing validation, missing transaction, incomplete wiring, no tests, or a broken companion piece).
- **Present but unverified** — code exists but its runtime correctness could not be confirmed from static inspection (tests not executed, external dependency, or environment-specific behavior).
- **Planned / scaffolding only** — artifacts exist (installed dependency, empty file, or unused config) with no functional wiring.
- **Broken** — code that references non-existent columns/attributes or otherwise cannot execute as written.

---

## 1. Platform & infrastructure

| Area | Status | Evidence |
|---|---|---|
| Laravel 13 web application (server-rendered Blade) | Implemented | `bootstrap/app.php` web routing; ~217 routes in `routes/web.php` |
| Database schema | Implemented | 47 migrations in `database/migrations/` |
| Session auth (Breeze) | Implemented | `config/auth.php`, `routes/auth.php`, `Auth/*` controllers |
| Email verification | Implemented | `auth/verify-email.blade.php`, `EmailVerification` flow in `routes/auth.php` |
| Password reset | Partially implemented | Flow + tests exist, but default `MAIL_MAILER=log` (no delivery) |
| Custom RBAC (roles/permissions/gates) | Implemented | `config/permissions.php`, `HasSchoolRoles`, `AppServiceProvider` gates |
| API layer | Not implemented | No `routes/api.php`, no Sanctum/Passport, no API Resources |
| PDF generation | Planned / scaffolding only | `barryvdh/laravel-dompdf` installed; no code calls the PDF facade |
| Background jobs / queues | Planned / scaffolding only | `QUEUE_CONNECTION=database` configured; no `app/Jobs`, no dispatches |
| Broadcasting / realtime | Not implemented | `BROADCAST_CONNECTION=log`; no events/broadcast code |
| Outbound mail/notifications | Not implemented (beyond auth reset) | `config/services.php` keys unused; no notifications |
| Object storage (S3) | Planned / scaffolding only | `s3` disk configured; no code selects it (uses `local`/`public`) |
| Automated deployment / CI | Not implemented | No Dockerfile/CI/workflow/Procfile in repo |
| Automated tests | Partially implemented | 12 test files; only auth/profile/smoke/academic-year; not executed during this audit |

---

## 2. Authentication & access control

| Feature | Status | Notes |
|---|---|---|
| Login / logout | Implemented | `AuthenticationTest` covers |
| Registration | Implemented | Breeze `RegisteredUserController` |
| Profile view / update / delete | Implemented | `ProfileTest` covers; `ProfileUpdateRequest` |
| Change password / confirm password | Implemented | `PasswordUpdateTest`, `PasswordConfirmationTest` |
| Login rate limiting | Implemented | `Auth\LoginRequest` (5 attempts) |
| Account suspension handling | Implemented | `EnsureUserIsActive` middleware (`active` alias) |
| Staff-only route protection | Implemented | `EnsureUserIsStaff` middleware (`staff` alias), `abort(403)` |
| Role system (`user_roles` + `HasSchoolRoles`) | Implemented | Consumed by gates and sidebar |
| Legacy `users.role` column | Partially implemented / vestigial | Only `HelpdeskController::staff()` reads it; dual source of truth |
| Two-factor authentication | Not implemented | Not present |
| API tokens / personal access tokens | Not implemented | No Sanctum |
| Custom 403/404/500 views | Not implemented | `resources/views/errors/` absent |

---

## 3. Core school data modules

Each module below has a controller, routes, view(s), and migration(s) unless noted. "Gaps" lists identified issues, not fixes.

| Module | Status | Gaps / notes |
|---|---|---|
| Admissions | Partially implemented | `AdmissionController@updateStatus` performs an unvalidated status transition |
| Students | Implemented | Full CRUD-ish index/edit; `status` validated |
| Classes & Sections | Implemented | `SchoolClassController` |
| Subjects | Implemented | `SubjectController` |
| Teachers | Implemented | `TeacherController` |
| Parents | Partially implemented | `ParentController@exportCsv` is the only exporter with CSV-injection guarding; PDF/parents view is a print page |
| Staff / Staff Members | Implemented | `StaffController` incl. salary components |
| Users & logins | Partially implemented | Rich guard rules (admin-role protections); admin password rule differs from Breeze (`min:8|max:72`, no confirmation) |
| Timetable | Partially implemented | `TimetableController@index` writes `PeriodSlot` rows during a GET request |
| Attendance | Implemented | `AttendanceController`, statuses from `Attendance::STATUSES` |
| Notices | Implemented | `NoticeController` + public notice board |
| PTM | Implemented | `PtmController` |
| Calendar / Events | Implemented | `SchoolEventController` |
| Lesson plans | Implemented | `LessonPlanController`; `lessonplans.*` permissions |
| Teaching logs | Implemented | `TeachingLogController`; `teachinglogs.*` permissions |
| Substitutes | Implemented | `SubstituteController`; view at `substitutes/index.blade.php` (outside `admin/`) |
| Assets | Implemented | `AssetController` (+ `AssetLoan` model) |
| Inventory / Stock | Partially implemented | `InventoryController@dispense` uses `abort(422)` inside a transaction (renders raw error); other flows vary |
| Library | Partially implemented | `LibraryController@issueBook` lacks a transaction; catalog + circulation views |
| Exams | Implemented | `ExamController`, `ExamSetupController` |
| Marks entry | Partially implemented | `MarksEntryController@save` allows the `lock` action under the `marks.enter` permission |
| Results / Marksheets | Partially implemented | `ResultService` contains `ADMISSION = 'admission_no'` while the column is `adm_number`; `ResultService::students()` uses MySQL-only `CAST(... AS UNSIGNED)` |
| Hall tickets | Partially implemented | Print-based (`_ticket` partial); file/class PSR-4 case mismatch (`Hallticketcontroller.php` → `HallTicketController`) |
| Discipline | Partially implemented | `DisciplineController` uses MySQL-only `FIELD()` |
| Conduct | Implemented | `ConductController` |
| Activities (ECA) | Implemented | `ActivityController` |
| Fees collection | Implemented | `FeeController`; receipt is a print view |
| Fee structure | Implemented | `FeeStructureController`; `FeeHead` model |
| Payroll / Salary | Partially implemented | `PayrollController@payAll` lacks a transaction; salary slip is a print view |
| Daily accounts | Implemented | `AccountController`; CSV export + print book view |
| Helpdesk | Implemented | `HelpdeskController`; reads legacy `users.role` in `staff()` |
| Complaints | Implemented | `ComplaintController` |
| Documents | Partially implemented | `DocumentController@download` increments counters during a GET (also increments on the download link) |
| School settings | Implemented | `SettingController`; key/value settings |
| About | Partially implemented | `AboutController` has no permission middleware; view reads `Setting::pluck('value','key')` |
| Dashboard | Implemented | `dashboard.blade.php` with `$isCommunity` split for student/parent vs staff |

---

## 4. Public website

| Page | Status | Notes |
|---|---|---|
| Home / landing (`welcome`) | Implemented | Static marketing page; auth-aware CTA |
| Notice board (`frontend/notices`) | Implemented | Lists published notices |
| Our team (`frontend/team`) | Partially implemented | Bypasses Vite/Tailwind (inline CSS); reads `StaffMember::GROUPS` directly in the view; no shared public layout |

The public site has **no shared layout**; header/footer markup is duplicated across the three pages (see `BLADE_AND_PUBLIC_WEBSITE.md`).

---

## 5. Known defects / inconsistencies (as found)

These are code-level observations recorded for a later review; none were modified.

| Item | Location | Nature |
|---|---|---|
| Broken `DatabaseSeeder` | `database/seeders/DatabaseSeeder.php` | References non-existent `Student` columns (`user_id`, `roll_number`, `guardian_name`, `guardian_phone`), omits NOT NULL columns, assigns non-fillable `User` attributes (`role`, `address`), creates no `user_roles` rows |
| Component seeders not invoked | `FeeHeadSeeder.php`, `SalaryComponentSeeder.php` | Not called by `DatabaseSeeder` |
| Result admission field mismatch | `app/Services/ResultService.php` (`ADMISSION = 'admission_no'`) vs column `adm_number` | Data lookup mismatch |
| MySQL-only SQL | `ResultService::students()` (`CAST AS UNSIGNED`), `DisciplineController` (`FIELD()`) | Not portable to SQLite (test env) |
| Unvalidated status transition | `AdmissionController@updateStatus` | No `validate()` |
| Marks lock permission scope | `MarksEntryController@save` | `lock` reachable with `marks.enter` |
| Write-on-GET | `TimetableController@index` (creates `PeriodSlot`), `DocumentController@download` (increments counters) | Side effects during GET |
| Missing transactions | `LibraryController@issueBook`, `PayrollController@payAll` | Multi-step writes not atomic |
| PSR-4 case mismatch | `app/Http/Controllers/Admin/Hallticketcontroller.php` (class `HallTicketController`), `app/Models/Assetloan.php` (class `AssetLoan`) | Autoload breaks on case-sensitive filesystems |
| Mass-assignment guard | `app/Models/ExamMark.php` (`$guarded = ['*']`) | Latent mass-assignment behavior |
| Dual role source of truth | legacy `users.role` vs `user_roles` | Inconsistent role reads |
| DB topology mismatch | `.env` (MySQL) vs `.env.example`/`phpunit.xml` (SQLite) | Environment-dependent behavior |
| dompdf unused | `composer.json` | "PDF" endpoints render HTML print views |
| Duplicate flash rendering | many admin views + `x-ui.flash` | Messages can appear twice |
| Missing `@section('title')` | ~28 of 42 admin views | Tab/header show default `Dashboard` |
| Orphaned views | `admin/dashboard.blade.php`, empty `teacher/dashboard.blade.php`, empty `student/dashboard.blade.php` | Not returned by controllers |

---

## 6. Feature surface vs. verification matrix

| Layer | Implemented | Tested |
|---|---|---|
| Routing (~217 routes) | Yes | Smoke only (`UiSmokeTest`) |
| Auth (login/register/reset/verify/profile) | Yes | Yes (Breeze tests) |
| RBAC gates & `can:` middleware | Yes | 3 assertions in `UiSmokeTest` |
| Domain modules (30+) | Mostly yes | No direct tests |
| Public pages | Yes | 4 route assertions |
| Print/PDF-by-print views | Yes | No |
| CSV export | Yes (some modules) | No |
| Seeding | Broken/incomplete | No |
| Deployment | Not present | N/A |

---

## 7. Summary counts

- Controllers inspected: **49** (`app/Http/Controllers/` incl. `Admin/` and `Auth/`); ~36 admin.
- Models: **~44** (`app/Models/`) plus trait `Concerns/HasSchoolRoles`.
- Migrations: **47**.
- Routes: **~217** (from `php artisan route:list --except-vendor`) across `web.php` + `auth.php` + `console.php`.
- Views: **91** Blade files; **3** orphaned/empty.
- Services: **2** (`ResultService`, `GradeScale`); Support: **3** (`AcademicYear`, `Money`, `Photo`); Helper: **1** (`SystemHelper`).
- Tests: **12** files (auth/profile/smoke/academic-year).

---

## 8. Items requiring further inspection before any change

1. Confirm whether the broken `DatabaseSeeder` and unused component seeders are intentionally abandoned or must be repaired.
2. Verify the production database engine (MySQL assumed by local `.env`) before touching `CAST`/`FIELD` usages.
3. Decide the intended fate of dompdf, `@tailwindcss/vite`, queues/broadcasting, and `config/services.php` credentials.
4. Confirm Linux case-sensitive autoload for `Hallticketcontroller.php` and `Assetloan.php`.
5. Establish a passing test baseline (`composer test`), which was not run for this documentation.
6. Confirm intended authorization for `AboutController` (currently no permission middleware).
7. Determine whether the dual role sources (`users.role` vs `user_roles`) should converge.
8. Confirm the GET-side-effect behaviors (timetable slot creation, document download counters) are intentional.
9. Confirm whether the three public pages' duplicated markup and the non-Vite team page are acceptable.
10. Confirm print/PDF expectations for marksheets, receipts, slips, ledgers, discipline notices, and parent lists.
