# ARCHITECTURE

> Evidence basis: `bootstrap/app.php`, `routes/*.php`, `app/Providers/AppServiceProvider.php`,
> `config/*.php`, all controllers/models/services, `composer.json`. This document describes the
> architecture **as built**, not an idealized design.

## 1. Architectural style

A **server-rendered (Blade) monolithic Laravel application** with:

- one HTTP entry point and a single `web` route file (`routes/web.php`) plus Breeze's
  `routes/auth.php`;
- no JSON API layer, no SPA, no Inertia/Livewire;
- thin controllers that query Eloquent models directly and return `view(...)` or `redirect(...)`;
- a small amount of domain logic extracted into service/support classes (`ResultService`,
  `GradeScale`, `Money`, `Photo`, `AcademicYear`);
- a custom role/permission system layered on Eloquent plus Laravel Gates;
- database-backed sessions, cache and queues (see `CONFIGURATION_AND_INTEGRATIONS.md`).

## 2. Request lifecycle

1. `public/index.php` → `bootstrap/app.php` (`Application::configure`).
2. `withRouting(web: routes/web.php, commands: routes/console.php, health: '/up')`.
   There is **no `api:` registration** and **no `auth:` registration** (`routes/auth.php` is
   `require`d at the bottom of `routes/web.php:561`).
3. Global web middleware stack (framework default) → session, CSRF, cookies, etc.
4. Route middleware: the `auth`, `verified`, and `active` aliases plus per-route `can:` gates.
   Aliases registered in `bootstrap/app.php:17-20`:
   - `active` → `App\Http\Middleware\EnsureUserIsActive`
   - `staff` → `App\Http\Middleware\EnsureUserIsStaff`
5. Controller action → Eloquent → Blade view (or `redirect`/CSV stream/JSON incidentally).
6. `AppServiceProvider::boot()` registers `Gate::before` (admin bypass), one `Gate::define` per
   permission, and a `Login` event listener that stamps `users.last_login_at`.

`withExceptions` configures JSON rendering only for `api/*` or requests expecting JSON
(`bootstrap/app.php:22-25`); the app has no `api/*` routes, so this is effectively for AJAX calls.

## 3. Layer responsibilities

| Layer | Location | Responsibility |
|---|---|---|
| HTTP | `app/Http/Controllers` | Request handling, inline validation, Eloquent queries, view/redirect responses |
| Middleware | `app/Http/Middleware` | `EnsureUserIsActive` (status gate), `EnsureUserIsStaff` (blocks students/parents) |
| Domain services | `app/Services` | `ResultService` (grade computation), `GradeScale` (grading bands) |
| Support utilities | `app/Support` | `AcademicYear` (year/period options), `Money` (modes + amount-in-words), `Photo` (upload/delete) |
| Helpers | `app/Helpers/SystemHelper.php` | `current_academic_year()` global helper (Bikram Sambat), autoloaded via composer `files` |
| Models | `app/Models` | Eloquent models + `Concerns\HasSchoolRoles` trait |
| Authorization | `config/permissions.php` + `AppServiceProvider` + `HasSchoolRoles` | Roles, permissions, Gates |
| Views | `resources/views` | Blade templates (admin + public + Breeze auth) |
| Frontend build | `resources/js`, `resources/css`, `vite.config.js` | Alpine.js + Tailwind v3 via Vite |
| Persistence | `database/migrations`, `database/seeders` | Schema + seed data |

There is **no repository layer**, **no DTO layer**, **no Form Request coverage beyond two classes**
(`Auth/LoginRequest`, `ProfileUpdateRequest`), **no events/jobs/listeners of our own**, and
**no policies** (`app/Policies` does not exist).

## 4. Authorization architecture (key design decision)

Authorization is **centralized in configuration** rather than in policies:

```
config/permissions.php
  ├── role_labels   (role key -> human label)
  ├── permissions   (permission key -> description)   <-- drives Gate::define loop
  └── roles         (role key -> permission list, '*' = all)

App\Models\Concerns\HasSchoolRoles  (on User model)
  ├── userRoles()      hasMany(UserRole)
  ├── roleNames()      cached pluck('role')
  ├── hasRole(...)     array_intersect
  └── hasPermission()  reads config('permissions.roles.{role}')

App\Providers\AppServiceProvider::boot()
  ├── Gate::before(admin => true)
  └── Gate::define($permission, fn($user) => $user->hasPermission($permission))
```

Routes then use `->middleware('can:<permission>')`, and views/controllers can use
`@can`/`$user->can()`. Admins bypass everything via `Gate::before`. Note: because of
`Gate::before`, a **seeded admin with no `user_roles` row is NOT admin** (it has no role at all),
even though `users.role` says `admin` — see `MODELS_AND_DATABASE.md` §Notable issues D.

Two role sources coexist and can diverge:
- `users.role` enum column (legacy; used only by `HelpdeskController::staff()` helper).
- `user_roles` table (authoritative for `hasRole`/`hasPermission`).

## 5. Domain model architecture

The entity graph (full detail in `MODELS_AND_DATABASE.md`):

- **People/auth**: `User` 1:1 `Teacher`, 1:1 `StaffMember`, 1:N `UserRole`.
- **Academics**: `SchoolClass` ← `Student`; `Subject`, `Teacher` feed `Timetable`,
  `LessonPlan`, `TeachingLog`, `ExamSubject`.
- **Assessment**: `Exam` → `ExamSubject` → `ExamMark`; grading via `GradeScale`/`ResultService`.
- **Finance**: `FeeHead` → `FeeStructure`; `FeeReceipt` → `FeeReceiptItem` + `FeePayment`;
  `StaffMember` ↔ `SalaryComponent` → `SalarySlip` → `SalarySlipLine`; `AccountEntry`.
- **Operations**: `Asset`/`AssetLoan`, `Book`/`BookIssue`, `InventoryItem`/`InventoryMovement`.
- **Support/content**: `HelpdeskTicket`/`HelpdeskReply`, `Complaint`/`ComplaintUpdate`,
  `SchoolDocument`, `Notice`, `SchoolEvent`, `PtmMeeting`, `SchoolActivity`,
  `DisciplineIncident`, `ConductRegister`, `SubstituteAllocation`.

Key architectural observations:
- Several models carry **no relationship methods** despite being heavily referenced
  (`Teacher`, `Subject`, `SchoolClass`, `ExamMark`, `UserRole`, `Setting`). Joins are done by FK
  columns in queries instead.
- `Student` is the **only model with a `booted()` hook** (syncs `school_class_id` from
  `class_applied` via `SchoolClass::findByLabel()`).
- Soft deletes are used selectively (`FeeReceipt`, `SalarySlip`, `AccountEntry`,
  `HelpdeskTicket`, `Complaint`, `SchoolDocument`).
- Number generation is done in model static helpers with `lockForUpdate`:
  `FeeReceipt::nextNumber`, `SalarySlip::nextNumber`, `HelpdeskTicket::nextNumber`,
  `Complaint::nextNumber`; other numbers are generated ad-hoc in controllers (`StudentController`,
  `AdmissionController`, `AssetController`, `LibraryController`).

## 6. Frontend architecture

- **Two visual systems**:
  1. **Admin**: `resources/views/layouts/admin.blade.php` — a sidebar/topbar shell using Tailwind
     utility classes + Bootstrap Icons (CDN) + Alpine; `resources/views/components/ui/*` provides
     `badge`, `empty-state`, `flash`, `page-header`, `stat-card`.
  2. **Breeze/auth**: `layouts/guest.blade.php` and `layouts/app.blade.php` with the standard
     Breeze component set (`components/*.blade.php`), Tailwind-styled.
- **Public site**: partial custom Blade (`welcome.blade.php`, `frontend/notices.blade.php`,
  `frontend/team.blade.php`); `frontend/team.blade.php` uses inline CSS and does **not** load Vite.
- **Build**: `vite.config.js` inputs `resources/css/app.css` and `resources/js/app.js` only.
  Tailwind v3 via PostCSS. Alpine.js initialized in `app.js`. No Vue/React.

## 7. Cross-cutting concerns

- **Validation**: overwhelmingly **inline `$request->validate([...])`** inside controllers.
  `Auth/LoginRequest` and `ProfileUpdateRequest` are the only Form Requests.
- **Error handling**: no custom exception renderer beyond the JSON-when-expected rule. Validation
  errors surface via `$errors` in views; some controllers return `->withErrors([...])` for
  business-rule messages (e.g. fee duplicates, disciplined date ordering, marks validation).
- **Transactions**: used in the money/inventory/marks flows (`DB::transaction`), but **not**
  everywhere (e.g. `LibraryController::issueBook`, `PayrollController::payAll`,
  `ExamSetupController::save`, `FeeController::refund`).
- **File storage**: `public` disk for photos (via `Photo`), complaint/helpdesk attachments,
  book covers; `local` (private) disk for `SchoolDocument` files.
- **Exports**: many CSV streamed endpoints with a UTF-8 BOM; "PDF" endpoints and receipts are
  **HTML print views** (`window.print()` in the browser). dompdf is installed but unused.
- **Localization**: `APP_LOCALE=en`; no `lang/` translation usage in code; several views target a
  Nepali-context school (Bikram Sambat year helper, `Rs`/`Rs.` currency strings, Nepali-style
  class names EL1–EL3, Class 1–10).

## 8. Environment topology

- Runtime DB is **MySQL** per `.env` (`DB_CONNECTION=mysql`), while `.env.example` and
  `phpunit.xml` default to **SQLite** (`database/database.sqlite` / `:memory:`).
- Sessions/cache/queue all use the `database` connection, so the app writes to `sessions`,
  `cache`, `jobs` tables.
- Some query code is **MySQL-specific** (`ResultService::students()` uses
  `CAST(... AS UNSIGNED)`; `DisciplineController` uses MySQL `FIELD(...)` ordering), which would
  break on the SQLite config used by tests. See `VALIDATION_AND_ERROR_HANDLING.md` and
  `IMPLEMENTATION_STATUS.md`.
