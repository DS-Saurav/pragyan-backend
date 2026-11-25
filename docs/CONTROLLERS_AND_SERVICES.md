# CONTROLLERS_AND_SERVICES

> Evidence basis: direct reading of every file under `app/Http/Controllers/` (root, `Auth/`,
> `Admin/`), `app/Http/Middleware/`, `app/Providers/AppServiceProvider.php`, `app/Services/`,
> `app/Support/`, `app/Helpers/`.

## 1. Base classes and infrastructure

### `App\Http\Controllers\Controller` (`app/Http/Controllers/Controller.php`)
Abstract base with an empty body. Provides no shared behavior — no `authorize()` helper usage,
no shared traits.

### `App\Http\Middleware\EnsureUserIsActive`
Alias `active`. If authenticated and `status !== 'active'`, logs out, invalidates the session and
redirects to `login` with an email error. Otherwise passes through.

### `App\Http\Middleware\EnsureUserIsStaff`
Alias `staff`. If the user `hasRole('student', 'parent')` → `abort(403)`. A **role-less** account is
not blocked (it is neither student nor parent) — the security boundary here is coarse.

### `App\Providers\AppServiceProvider`
- `Gate::before(fn ($user) => $user->hasRole('admin') ? true : null)` — admin bypass.
- `Event::listen(Login::class, ...)` — sets `last_login_at` via `saveQuietly()`.
- Loops `config('permissions.permissions')` and defines one gate per permission.
- `register()` is empty.

### Services

**`App\Services\GradeScale`**
- `BANDS` constant: 90→A+ (4.0), 80→A (3.6), 70→B+ (3.2), 60→B (2.8), 50→C+ (2.4), 40→C (2.0),
  35→D (1.6). Below 35 → `NG` (0.0).
- `forPercent(float): array` and `lowest(): array`.

**`App\Services\ResultService`**
- Constants `ROLL = 'roll_no'`, `ADMISSION = 'admission_no'`.
- `students(int $classId)` — orders by `CAST(roll_no AS UNSIGNED)` (**MySQL-specific**).
- `studentName()`, `roll()`, `admission()`, `n()` formatting helpers.
- `subjectResult(ExamSubject, ?ExamMark): array` — computes theory/practical totals, percent, pass
  (theory/practical pass thresholds), grade/point/remark, absent/entered flags.
- `classResults(Exam, SchoolClass): array` — builds per-student rows, ranks passed students (ties
  share rank), and a summary (appeared/passed/failed/incomplete/pass%/highest).
- `division(float)` — Distinction ≥80, First ≥60, Second ≥45, else Third.
- **Schema drift:** `ADMISSION = 'admission_no'` but the actual `students` column is `adm_number`
  (and `ROLL = 'roll_no'` matches). `admission()` will therefore return empty strings.

### Support utilities

**`App\Support\AcademicYear`** — year options and `current()`; `PERIODS` constant used by fee generation.
**`App\Support\Money`** — `MODES` (cash/bank/cheque/esewa/khalti/fonepay/other), `words()` amount-in-words.
**`App\Support\Photo`** — store/delete-if-unused images on the `public` disk.
**`app/Helpers/SystemHelper.php`** — global `current_academic_year()` returning a Bikram Sambat year;
autoloaded through `composer.json` `autoload.files`.

## 2. Root controllers

| Controller | Methods | Summary |
|---|---|---|
| `DashboardController` | `index` | Role/permission-aware stat cards (`Student`, `Admission`, `DisciplineIncident`, `FeeReceipt`, `User`, `SchoolClass` counts) + audience-filtered published `Notice` feed (limit 4). Returns `dashboard`. |
| `TeamController` | `index` | `StaffMember::website()` grouped by `team_group` → `frontend.team` (public). |
| `ProfileController` | `edit`, `update`, `destroy` | Breeze profile; `update` uses `ProfileUpdateRequest`, clears `email_verified_at` if email changes; `destroy` validates current password then deletes the user and logs out. |

## 3. Auth controllers (`app/Http/Controllers/Auth/`)

Breeze-generated, largely unmodified:

- `AuthenticatedSessionController` — login/logout; uses `LoginRequest::authenticate()` with a
  5-attempt rate limiter.
- `RegisteredUserController` — public self-registration; creates a **role-less** `User` (no
  `user_roles` row, no `role`) and logs in.
- `PasswordResetLinkController`, `NewPasswordController`, `PasswordController`,
  `ConfirmablePasswordController`, `EmailVerificationPromptController`,
  `EmailVerificationNotificationController`, `VerifyEmailController` — standard Breeze flows.

**Authorization gap:** `RegisteredUserController@store` is a public route that creates an account
with no role. After email verification, such an account passes `EnsureUserIsStaff` and reaches the
role-aware dashboard; it is denied individual modules by `can:` gates, but this is a loophole in the
otherwise invitation-style account model (see `AUTHENTICATION_AND_AUTHORIZATION.md`).

## 4. Admin controllers (`app/Http/Controllers/Admin/`)

### `AboutController`
`index` → `Setting::pluck('value','key')` → `admin.about`. No permission middleware.

### `AccountController`
- `index` — unified ledger from `FeePayment`, refunded `FeeReceipt`, paid `SalarySlip`,
  `AccountEntry`, with category/mode/search filters and pagination → `admin.accounts.index`.
- `store`/`update`/`destroy` — CRUD `AccountEntry` (`validated()` rules, `Money::MODES`,
  income/expense categories); soft delete.
- `book` — printable day book with running/opening balances → `admin.accounts.book`.
- `csv` — streamed CSV (UTF-8 BOM) of the filtered ledger.
- Private helpers build the ledger and `balanceBefore()`.

### `ActivityController`
- `index` — `SchoolActivity` with `inCharge`, status/search filters, counts → `admin.activities.index`.
- `store`/`update`/`destroy` — CRUD; `edit` returns **JSON** `{entry, teachers}` (`User` list).

### `AdmissionController`
- `index` — `Admission` list with status filter + counts; classes list for the form.
- `store` — validates applicant fields; sets `academic_year` fallback `current_academic_year()`;
  generates `reg_number = 'REG-'.date('Y').'-'.str_pad(count+1, 5, '0')`; `status='registered'`.
- `updateStatus` — **no validation**; writes `status` and `rejection_reason` directly from the
  request. Bug: arbitrary status values accepted.
- `destroy` — deletes.

### `AssetController`
- consts `CATEGORIES`, `PER_PAGE_OPTIONS`.
- `index` — `Asset` with `loans.student`, tab/search/per-page filters, category counts.
- `store` — creates asset; generates `asset_tag` via `nextAssetTag(prefix)`; `available_quantity=quantity`.
- `issueItem` / `returnItem` — `DB::transaction` with `lockForUpdate`; create/close `AssetLoan`,
  adjust `available_quantity` and `status`; return handles `needs_loan`/`multiple`/`full` cases.
- `barcodeReturn` — returns a single active loan by `asset_tag`; errors on zero/multiple.
- `destroy` — deletes. `exportCsv` — streamed CSV.
- Bug: reads `$loan->student->roll_number` (column does not exist → null).

### `AttendanceController`
- `index` — validates GET `class_id`/`date`; fetches students ordered by `roll_no` and existing
  marks; default `Attendance::PRESENT`.
- `store` — `DB::transaction` `updateOrCreate` per student within `Attendance::STATUSES`.
- `copyFromYesterday` — copies the latest earlier date's rows in a transaction.

### `ComplaintController`
- const `FILE_RULE` (jpg/jpeg/png/pdf/doc/docx ≤5MB).
- `index` — visibility-scoped (`filed_by` unless manager) with severity/against/assigned/
  escalated/overdue/date/search filters and tabs; counts/stats → `admin.complaints.index`.
- `store` — `DB::transaction`; `complaint_no = Complaint::nextNumber()`; public-disk attachment;
  `filed_by = auth()->id()`.
- `show`/`reply` — `abort_unless(manager || filer, 403)`; internal notes hidden from non-managers;
  reply can reopen or move to investigating.
- `update` — status/severity/assignee/resolution; requires a note when resolving; creates updates.
- `escalate` — sets `is_escalated`/`escalated_at` + system updates.
- `destroy` — soft delete. `csv` — streamed CSV.
- `manager()` = `auth()->user()?->can('complaints.manage')`. Anonymous filer hidden via model helper.

### `ConductController`
- `index` — `ConductRegister` with student/recorder, type tab, search; counts → `admin.conduct.index`.
- `store` — validates student/date/type/points/note; `recorded_by=auth()->id()`.
- `edit` — JSON; `update`; `destroy`.

### `DisciplineController`
- constants `CATEGORIES`, `SEVERITIES`, `ACTIONS`, `NOTIFY_METHODS`, `STATUSES`.
- `index` — filters + tabs; ordering uses MySQL `FIELD(status, ...)` (**not portable**); stats and
  per-student history; provides students/classes/teachers JS payloads.
- `store`/`update` — `normalize()` sets/clears notification fields and `closed_at`.
- `close` — requires resolution; sets status closed + timestamp.
- `notify` — marks parent notified. `destroy`; `notice` → printable letter; `csv`.

### `DocumentController`
- consts `DISK = 'local'` (private), `MIMES`.
- `index` — role-visible filter + tabs (active/draft/expiring/expired) → `admin.documents.index`.
- `store`/`update` — file OR external URL required; private-disk store; `version` increments.
- `publish` — toggles `is_published`. `destroy` — soft delete (file kept).
- `download` — authorization check then **increments `download_count`/`last_downloaded_at`**
  (write during GET).
- `csv`. `manager()` = `can('documents.manage')`; `roles()` from `user_roles`.

### `ExamController`
- `index` — year/search filters, status counts, setup/submitted counts.
- `store`/`update`/`destroy` — CRUD (`update` unsets status if published; `destroy` refuses published).
- `publish` — refuses unless all `marks_status` are submitted/locked; sets `published_at`.
- `unpublish` — sets `completed`, clears `published_at`.

### `ExamSetupController`
- `show` — subject rows per exam/class + available subjects.
- `load` — creates rows for subjects assigned to the class (via `assigned_classes` JSON), falls back
  to all subjects.
- `add` — adds a subject row. `save` — updates dates/times/full/pass marks; validates pass ≤ full
  and end > start **mid-loop** (partial updates possible, no transaction). `copy` — copies a class's
  setup to other classes. `remove` — deletes a row (cascades marks).
- All mutations blocked when the exam is published (`blockIfPublished`).

### `FeeController`
- `index` — receipts with student/payments/items; year/class/mode/date/search filters, tabs, stat
  cards (collected, outstanding, today, month, defaulters) → `admin.fees.index`.
- `store` — validates items; rejects duplicate billed head+period (`FeeReceipt::alreadyBilled`);
  `DB::transaction`; `receipt_no = FeeReceipt::nextNumber()`; optional immediate payment.
- `pay` — `DB::transaction` + `lockForUpdate`; rejects refunded/overpayment; `refreshTotals()`.
- `refund` — rejects if refunded/unpaid; sets status `refunded` (no transaction).
- `destroy` — only unpaid/refunded; soft delete. `receipt` — printable. `csv`.

### `FeeStructureController`
- `index` — year/class matrix + heads + usage → `admin.fee-structure.index`.
- `save` — per-head `updateOrCreate` within a transaction (blank/0 deletes).
- `copy` — copies a year/class structure to targets with optional percentage increase.
- `generate` — issues bills for a class/period/head set; per-student transaction; skips already-billed.
- `storeHead`/`updateHead`/`destroyHead` — fee head CRUD (blocks delete if used).

### `HallTicketController` ⚠️ (file `Hallticketcontroller.php`)
- `index` — validates GET exam/class; builds per-student ticket data with subject schedule and
  attendance percentage (`MIN_ATTENDANCE = 75`) using a raw `SUM(CASE WHEN ...)` query.
- `field()` probes multiple candidate column names defensively.
- School meta from `config('app.name')` / `config('school.*')`.
- **PSR-4 risk:** class `HallTicketController` lives in `Hallticketcontroller.php` — autoload fails
  on case-sensitive filesystems.

### `HelpdeskController`
- const `FILE_RULE`. `index` — visibility-scoped, filters/tabs, counts.
- `store` — `DB::transaction`; `ticket_no = HelpdeskTicket::nextNumber()`; public-disk attachment.
- `show`/`reply` — `abort_unless(manager || raiser, 403)`; auto in-progress on assignment; reopen.
- `update` — status/priority/assignee + system replies. `destroy` — soft delete. `csv`.
- `staff()` helper filters by **legacy `users.role`** (guarded by `Schema::hasColumn`) — inconsistent
  with the `user_roles` table used everywhere else.

### `InventoryController`
- const `CATEGORIES`. `index` — items with low-stock tab and search; counts.
- `store` — item + initial `InventoryMovement` (`type='in'`) in a transaction.
- `restock`/`dispense` — transactions + `lockForUpdate`; movements track `balance_after`;
  `dispense` aborts 422 when quantity exceeds stock.
- `show` — **JSON** with movements. `update`/`destroy` — CRUD.

### `LessonPlanController`
- `index` — filtered (class/teacher/date/search) + tabs → `admin.lesson-plans.index`.
- `store`/`update`/`updateStatus`/`destroy` — CRUD; `csv`.

### `LibraryController`
- const `FINE_PER_DAY = 5`.
- `index` — books, available copies, overdue issues, stats → `admin.library.index`.
- `storeBook`/`updateBook`/`destroyBook` — CRUD with cover upload on `public`; generates
  `acc_no = 'LIB-'.str_pad(max(id)+1, 5, '0')`; adjusts `available_copies`.
- `circulation` — issues tab/counts. `issueBook` — resolves member, creates issue, decrements
  copies (**no transaction — race possible**). `returnBook` — computes fine, marks returned,
  increments copies (message uses `₹`).

### `MarksEntryController`
- `index` — resolves exam/class/exam-subject and existing marks.
- `save` — action `draft|submit|lock`; refuses published/locked; validates each mark within range;
  non-draft requires all marks/absent; `DB::transaction` upserts marks, sets `marks_status`.
- `unlock` — refuses published; sets `draft`.
- **Authz gap:** `lock` is allowed under the `marks.enter` permission.

### `NoticeController`
- `index` — list with published/draft tab. `store` — creates notice (`posted_by = user name`,
  lowercased audience/status). `destroy`.

### `ParentController`
- `index` — `ParentModel` search + `students` pivot → `admin.parents.index`.
- `store`/`update`/`destroy` — CRUD.
- `linkChild`/`unlinkChild` — `syncWithoutDetaching`/`detach` on the `parent_student` pivot.
- `exportCsv` — chunked streamed CSV **with `csvSafe()` formula-injection protection** (the only
  exporter doing so). `exportPdf` — HTML print view.

### `PayrollController`
- consts `WORKING_DAYS = 26`, `MONTH_RULE`.
- `index` — slips with staff/lines; month/type/status/search filters, tabs, sums → `admin.payroll.index`.
- `store`/`update` — validate + `calculate()`; `DB::transaction`; `slip_no = SalarySlip::nextNumber()`.
- `run` — generates slips for all active staff with basic > 0 via `SalarySlip::compute`.
- `pay` — marks paid (`payRules()`); `payAll` — bulk mark paid (**no transaction**); `unpay` reverts.
- `destroy` — only generated; soft delete. `slip`/`printMonth` — printable. `csv`.

### `PtmController`
`index`/`store`/`update`/`destroy` — PTM meeting CRUD with status tabs.

### `ResultController`
Injects `ResultService`. `index`, `ledger`, `marksheets` (class), `marksheet` (student) —
all read-only, gated by `results.view`.

### `SchoolClassController`
`index`, `store`, `destroy($id)` (uses raw id, not route-model binding). No permission middleware.

### `SchoolEventController`
`index` (calendar, tabs) → `admin.calendar.index`; `store`/`update`/`destroy`.

### `SettingController`
`index` → `admin.settings.index`; `update` validates school name/address/contact/branding/
currency/academic-year and writes each via `Setting::set()`.

### `StaffController`
- `index` — staff with components + component usage → `admin.payroll.staff`.
- `store`/`update` — `DB::transaction`; sync `staff_salary_components` pivot.
- `destroy` — blocks if salary slips exist.
- `importTeachers` — copies teachers lacking a payroll record (basic 0).
- `storeComponent`/`updateComponent`/`destroyComponent` — salary component CRUD (blocks delete if used).

### `StudentController`
- `index` — academic-year/status/search/class/gender/house/allergy filters, per-status counts,
  distinct years, class labels → `admin.students.index`.
- `store` — validates; `academic_year` fallback; `adm_number = nextAdmissionNumber()` (prefix
  `2024-`, derived from the max existing number); `has_allergies` via `boolean()`.
- `update`, `updateStatus`. `destroy` — blocks when fee receipts exist (`withTrashed` check).
- `promote()` — **no route** (dead method).

### `SubjectController`
`index`/`store`/`update`/`destroy`; `exportCsv`.

### `SubstituteController`
- `index` — allocations with absent/substitute teachers + search; staff `User` list → note returns
  `substitutes.index` (view lives at `resources/views/substitutes/index.blade.php`, outside `admin/`).
- `store` — validates (substitute ≠ absent); `status='allocated'`. `destroy`.

### `TeacherController`
`index` (tabs/search/counts including assignment totals); `store` (auto-generates `username` as
`@firstnamelastname` when blank); `update`; `assign`/`removeAssignment` (mutate `assignments` JSON);
`destroy`; `exportCsv`.

### `TeachingLogController`
`index` (filters + recent lesson plans); `store`/`update` (also `syncLessonPlan()` marks the linked
plan completed when coverage is `completed`); `destroy`; `csv`.

### `TimetableController`
- `index` — loads classes, **creates default `PeriodSlot` rows on first access (write during GET)**,
  timetable grid.
- `store` — `updateOrCreate`/delete cells per day/period.
- `storePeriodSlots` — upserts/removes period slots. `destroyPeriodSlot`.

### `UserController`
The most security-conscious controller:
- `index` — filters (role/none/gender/staff), tabs, stats, teacher/staff JS payloads.
- `store`/`update` — `save()` in a `DB::transaction`: create/update user, `syncRoles()`,
  `linkTeacher()`, `applyPhoto()`, `applyStaff()`. Role changes guarded: only an admin can grant/
  remove `admin`; cannot remove own admin; at least one active admin required.
- `toggleStatus` — prevents self-suspend and removing the last active admin; kills sessions.
- `resetPassword` — forces a random password, kills the target's sessions, flashes credentials.
- `toggleWebsite` — flips `staff_members.show_on_website`.
- `destroy` — prevents self-delete / last-admin; deletes login but keeps staff/teacher records.
- `csv` — streamed CSV with role labels.

## 5. Cross-cutting observations

- **Validation style:** inline `$request->validate([...])` almost everywhere; only
  `Auth/LoginRequest` and `ProfileUpdateRequest` are Form Requests. No `authorize()` usage.
- **Authorization style:** route middleware `can:` for module access; a handful of inline
  `can()`/`abort_unless` checks in the complaint/helpdesk/document controllers for per-record
  visibility. No policies.
- **Writes during GET:** `TimetableController@index` (creates period slots) and
  `DocumentController@download` (increments download counters).
- **Missing validation (bugs):** `AdmissionController@updateStatus` accepts an unvalidated status;
  `LibraryController@issueBook` and `PayrollController@payAll` write without transactions.
- **Non-portable SQL:** `ResultService::students()` (`CAST AS UNSIGNED`), `DisciplineController`
  (`FIELD(...)`), `HallTicketController` (raw `SUM(CASE ...)`).
- **CSV exports:** consistent UTF-8 BOM; only `ParentController` guards against CSV injection.
- **`HallTicketController`** class/file case mismatch.
- **Unused dompdf:** no controller references the PDF facade.
