# Validation & Error Handling

This document describes how input validation, authorization failures, and runtime errors are handled in the application, based strictly on the code in `app/`, `bootstrap/app.php`, `routes/`, and `resources/views/`. It records what is implemented, where the patterns are inconsistent, and which behaviors need further inspection.

---

## 1. Validation approach

### 1.1 Dominant pattern: inline `$request->validate()`

Validation is performed almost entirely **inline inside controllers** using `$request->validate([...])`. Measured across `app/Http/Controllers/`:

| Pattern | Count | Notes |
|---|---|---|
| `$request->validate(` | **89 calls** | Spread across 37 of 49 controllers |
| `Validator::make(...)` | 0 | Not used |
| Form Request classes | **2** | `ProfileUpdateRequest`, `Auth\LoginRequest` |
| Controllers using Form Requests | Breeze auth/profile only | Admin controllers do not |
| `Rules\Password` | 4 references | Breeze register/reset/change-password paths |

Controllers with the most inline validation calls: `PayrollController` (6), `FeeStructureController` (5), `AssetController` (4), `ExamSetupController` (4), `InventoryController` (4), `StaffController` (4), `ComplaintController` (4). A full per-controller count is in `CONTROLLERS_AND_SERVICES.md`.

The two Form Requests:

- `app/Http/Requests/ProfileUpdateRequest.php` — `name` (required, string, max:255), `email` (required, lowercase, email, max:255, `Rule::unique('users')->ignore($this->user()->id)`).
- `app/Http/Requests/Auth/LoginRequest.php` — `email` (required, email), `password` (required). Also implements rate limiting via `RateLimiter` (5 attempts; see §5).

### 1.2 Rule styles and building blocks

Both array-syntax (`['required', Rule::in([...])]`) and pipe-syntax (`'required|integer|min:1'`) are used, sometimes within the same controller. Recurring rule usage (approximate counts): `'required'` ~113, `'nullable'` ~92, `'exists:'` 23, `'unique:'` 3.

Frequently used validation primitives:

- **Enum-like membership via model constants**: validation frequently delegates to model/controller constant maps with `Rule::in(...)`, e.g.
  - `AttendanceController`: `'attendance.*' => ['required', Rule::in(Attendance::STATUSES)]`
  - `ComplaintController`: `Rule::in(array_keys(Complaint::AGAINST))`, `Rule::in(array_keys(Complaint::SEVERITIES))`, `Rule::in(Complaint::STATUSES)`
  - `DisciplineController`: `Rule::in(array_keys(Incident::CATEGORIES))`, `Incident::SEVERITIES`, `Incident::ACTIONS`, `Incident::NOTIFY_METHODS`, `Incident::STATUSES`
  - `DocumentController`: `SchoolDocument::CATEGORIES`, `SchoolDocument::AUDIENCES`
  - `HelpdeskController`: `HelpdeskTicket::CATEGORIES`, `PRIORITIES`, `STATUSES`
  - `ExamController`: `Exam::TYPES`; `FeeController`/`PayrollController`/`AccountController`: `Money::MODES`; `FeeStructureController`: `AcademicYear::PERIODS`, `FeeHead::FREQUENCIES`; `StaffController`: `SalaryComponent::TYPES`/`CALCS`, `StaffMember::TYPES`; `UserController`: `StaffMember::TYPES`, `StaffMember::GROUPS`
  - `LessonPlanController`: `LessonPlan::STATUSES`; `TeachingLogController`: `TeachingLog::COVERAGES`
- **Conditional required**: `FeeController` uses `Rule::requiredIf(fn () => (float) $request->input('paying_now') > 0)` for `mode`.
- **Uniqueness with ignore**: `FeeStructureController` (`fee_heads.name`), `StaffController` (`salary_components.name`), `UserController` (`users.email`).
- **Date constraints**: e.g. `AccountController@validated`: `'entry_date' => ['required', 'date', 'before_or_equal:today']`.
- **Numeric bounds**: e.g. `'amount' => ['required', 'numeric', 'min:0.01', 'max:100000000']`.

### 1.3 Password validation

Breeze-managed auth paths use `Rules\Password::defaults()`:

- `RegisteredUserController`: `'password' => ['required', 'confirmed', Rules\Password::defaults()]`
- `NewPasswordController`: same
- `PasswordController` (change password): `['required', Password::defaults(), 'confirmed']`

Admin-created/edited user passwords in `UserController` are validated only as `['nullable', 'string', 'min:8', 'max:72']` (no `Password::defaults()`, no confirmation rule); the plaintext is hashed on save. This is a deliberate-looking difference between self-service and admin-managed passwords.

### 1.4 Validation concerns identified in code (for future review)

These are factual observations about control flow, not prescriptions:

- `AdmissionController@updateStatus` — no `$request->validate(...)` call around the status update (contrast with other status transitions that do validate).
- `MarksEntryController@save` — validation for the `lock` action is gated only by the `marks.enter` permission, so a user with entry rights can lock marks through the same endpoint.
- Validation `Rule::in` relies on model constants; if the constant arrays change but the DB enum/migration does not (or vice versa), mismatches are not detected at validation time.
- No use of `Validator::after()`/`withValidator()` custom hooks; cross-field rules are limited to `requiredIf` and simple `after_or_equal`/`before_or_equal` style rules.

---

## 2. Authorization failures

Authorization is **not** enforced with `$this->authorize()` or `Gate::authorize()` inside controllers — both counts are **0** across `app/Http/Controllers/`.

Instead, authorization is enforced at the **route layer**:

- Route groups use `can:<permission>` middleware generated from `config/permissions.php` (see `AUTHENTICATION_AND_AUTHORIZATION.md`).
- Global gates are defined in `AppServiceProvider::boot()` (one `Gate::define` per permission plus a `Gate::before` admin bypass).

Failure behavior:

- A failed `can:` check produces a **403 Forbidden** (Laravel's default `AuthorizationException` handling).
- `EnsureUserIsStaff` (`app/Http/Middleware/EnsureUserIsStaff.php`) explicitly calls `abort(403, 'This area is only available to staff accounts.')` when a `student`/`parent` reaches a staff-only route.
- `EnsureUserIsActive` (`app/Http/Middleware/EnsureUserIsActive.php`) does **not** abort; it logs the user out, invalidates and regenerates the session token, and redirects to `login` with `withErrors(['email' => 'Your account has been suspended...'])`.

There are **no** custom 403/404/500 error views: `resources/views/errors/` does not exist, so Laravel's default error rendering applies (see §4).

---

## 3. Exception handling configuration

`bootstrap/app.php` configures a single exception behavior:

```php
->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->shouldRenderJsonWhen(
        fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
    );
})
```

- There is **no** `app/Exceptions/Handler.php` override and **no** custom `render`/`report`/`dontReport` closures.
- JSON is only rendered for `api/*` paths or requests that explicitly expect JSON. Since the application has no `routes/api.php`, this rule effectively applies only to `expectsJson()` requests.
- Validation failures therefore redirect back with errors and populate `$errors` for normal HTML requests.

---

## 4. Error views and user-facing error surfaces

- **No custom error pages.** Laravel's default error handling renders the framework's built-in error views (which vary by `APP_ENV`/`APP_DEBUG`).
- **Flash messages** are the primary success/error surface for normal flows. The layout renders `resources/views/components/ui/flash.blade.php` once per admin page (`layouts/admin.blade.php:291`). It handles session keys:
  - `success` → `ui-alert-success`
  - `error` → `ui-alert-error`
  - `warning` → `ui-alert-warning`
  - `status` → `ui-alert-info`
  - plus `@if ($errors->any())` listing all validation messages.
- Controllers predominantly flash `success` (133 `with('success', ...)` occurrences), with `error` (7) and `status` (5) used less often. The `warning` key is supported by the component but is not emitted by any controller.
- Many individual admin views additionally render their own ad-hoc `session('success')`/`session('error')` blocks **in addition to** `x-ui.flash`, so some pages can display duplicate messages. See `BLADE_AND_PUBLIC_WEBSITE.md` §6.
- Redirect style: results are returned mostly via `back()` (165 occurrences); explicit `redirect()->route(...)` appears 27 times.

---

## 5. Authentication-related error handling

- **Login rate limiting**: `Auth\LoginRequest` limits to 5 attempts per `Str::lower(email)|ip` key, fires a `Lockout` event, and throws a `ValidationException` with `auth.throttle` messaging; a failed credential attempt throws with `auth.failed`.
- **Password confirmation**: `ConfirmablePasswordController` throws `ValidationException::withMessages(...)` on failure and stores `auth.password_confirmed_at` on success.
- **Suspended accounts**: handled by `EnsureUserIsActive` as described in §2.

---

## 6. Runtime error handling in controllers

- **`try`/`catch` is rare**: only **2** occurrences in `app/Http/Controllers/`, both **defensive parsing**, not business-error handling:
  - `AccountController::dateOr()` (`catch (\Throwable) { return $default; }`) — tolerates unparsable date query params.
  - `Hallticketcontroller::fmtDate()` (`catch (\Throwable $e) { return null; }`) — tolerates unparsable dates while formatting.
- **`abort()` is used once**: `InventoryController@dispense` calls `abort(422, 'Cannot dispense more than available stock!')` inside a `DB::transaction`. Because the request is not JSON, this surfaces as a 422 HTTP error page rather than a validation error redirect.
- **Explicit domain-guard exceptions**: `UserController` throws `ValidationException::withMessages([...])` for guard conditions such as:
  - only an admin may grant/remove the Admin role,
  - a user cannot remove their own Admin role,
  - there must remain at least one active admin,
  - a `teacher_id` / `staff_member_id` already linked to another login.
- **No global DB exception handling**: `QueryException`/`PDOException` are not caught; they fall through to Laravel's default rendering (so a DB error is a 500 or, with debug on, a detailed error page).
- **Transactions**: `DB::transaction` is used in **27** places. Where `abort()` is called inside a transaction (InventoryController), the transaction rolls back. Several state-changing operations do **not** wrap work in a transaction (see `IMPLEMENTATION_STATUS.md` / `CONTROLLERS_AND_SERVICES.md`, e.g. `LibraryController@issueBook`, `PayrollController@payAll`).

---

## 7. CSRF and request-integrity handling

- The `web` middleware group (default) provides CSRF verification. Forms in Blade use `@csrf` (e.g. the logout form in `layouts/admin.blade.php`). The layout exposes `<meta name="csrf-token">` for any JS-driven requests.
- No custom CSRF exception paths were found (no `api/*` routes exist).

---

## 8. Summary of strengths and inconsistencies (as implemented)

**Consistent / implemented**
- Widespread server-side validation via `$request->validate()` (89 call sites).
- Enum-like fields validated against shared model/controller constants with `Rule::in`.
- Route-level `can:` + global gates provide blanket authorization, with an admin `Gate::before` bypass.
- Centralized flash/validation rendering via `x-ui.flash`.
- Login rate limiting and account-suspension handling.

**Inconsistent / needs inspection**
- Two validation philosophies coexist: inline validation dominates; Form Requests exist only for Breeze auth/profile.
- Validation concerns are duplicated per controller instead of shared rules.
- Mixed array/pipe rule syntax and mixed redirect styles (`back()` vs `redirect()->route`).
- Duplicate flash rendering (component + per-view blocks) can show messages twice.
- One unvalidated status transition (`AdmissionController@updateStatus`); `lock` reachable with `marks.enter`.
- No custom error views; `abort(422)` produces a raw error page rather than a flash/validation redirect.
- No controller-level `authorize()` calls — authorization correctness depends entirely on route definitions and gate names staying in sync.
- Password policy differs between Breeze flows (`Password::defaults()`) and admin-managed users (`min:8|max:72` only).
