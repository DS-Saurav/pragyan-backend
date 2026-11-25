# AUTHENTICATION_AND_AUTHORIZATION

> Evidence basis: `routes/auth.php`, `app/Http/Controllers/Auth/*`, `app/Http/Middleware/*`,
> `app/Models/User.php`, `app/Models/UserRole.php`, `app/Models/Concerns/HasSchoolRoles.php`,
> `config/permissions.php`, `app/Providers/AppServiceProvider.php`, `bootstrap/app.php`,
> `config/auth.php`, `routes/web.php`.
> **Update (Stage D1, 2026-11-01):** §5 rows 11–13 added from fresh source inspection of the
> notice store/destroy routes, `/admin/about`, and the payroll staff editor; classification
> convention and the D1.5 scheduling note are stated above the table. No authorization code
> was modified during D1.

## 1. Authentication

- **Guard/provider:** framework default `web` guard, `users` Eloquent provider (`config/auth.php`).
- **Scaffold:** Laravel Breeze (installed as a dev dependency, source copied into the app).
- **Session driver:** `database` (per `.env`), so `sessions` table is used.
- **Password hashing:** `password => 'hashed'` cast on the `User` model.

### Supported flows

| Flow | Routes | Notes |
|---|---|---|
| Login | `GET/POST /login` | `LoginRequest` rate limits to 5 attempts; `Login` event stamps `last_login_at` |
| Logout | `POST /logout` | invalidates session + regenerates CSRF token |
| Registration | `GET/POST /register` | **public self-registration**, creates a role-less user |
| Forgot/reset password | `/forgot-password`, `/reset-password/{token}` | mail driver is `log` by default |
| Email verification | `/verify-email`, signed verify link, resend | `MustVerifyEmail` on `User` |
| Confirm password | `/confirm-password` | standard Breeze |
| Update password | `PUT /password` | standard Breeze |
| Profile | `/profile` edit/update/delete | delete requires current password |

### Post-login / verification gates

- The authenticated route group requires `auth`, `verified`, `active`
  (`routes/web.php:69`).
- `EnsureUserIsActive` logs out and redirects to login if `users.status !== 'active'`
  (null is treated as active).
- Public registration produces an **unverified, role-less** account. After verifying, such a user
  has no configured staff role and is denied by current `staff` middleware. The dashboard remains accessible; `/admin/about` now requires `settings.manage` (D1.5).

## 2. Authorization model

### 2.1 Data sources

1. **`config/permissions.php`** — the single source of truth:
   - `role_labels`: 10 roles.
   - `permissions`: ~45 permission keys with descriptions.
   - `roles`: role → permission list; `admin => ['*']`.
2. **`user_roles` table** — a user can hold multiple roles.
3. **`users.role` enum** — legacy, only read by `HelpdeskController@staff()`.

### 2.2 Roles and effective permissions (from `config/permissions.php`)

| Role | Permissions |
|---|---|
| `admin` | `*` (everything; also bypasses all gates via `Gate::before`) |
| `principal` | view lesson plans/teaching logs; view exams/marks/results + publish; view/manage discipline; view conduct; view activities; view fees; helpdesk view/create/manage; complaints view/create/manage; documents view/manage; settings view/manage |
| `academic_coordinator` | lesson plans view/manage; teaching logs view; exams view/manage; marks view/enter/lock; results view; helpdesk view/create; complaints view/create; documents view/manage |
| `discipline_coordinator` | discipline view/report/manage; conduct view/manage; helpdesk view/create; complaints view/create; documents view |
| `eca_coordinator` | activities view/manage; calendar manage; helpdesk view/create; complaints view/create; documents view |
| `teacher` | lesson plans view/manage; teaching logs view/manage; exams view; marks view/enter; discipline view/report; helpdesk view/create; complaints view/create; documents view |
| `librarian` | library manage; helpdesk view/create; complaints view/create; documents view |
| `accountant` | fees view/collect/refund/structure/generate; payroll view/manage/pay; accounts view/manage; helpdesk view/create/manage; complaints view/create; documents view |
| `student` | helpdesk view/create; complaints view/create; documents view |
| `parent` | helpdesk view/create; complaints view/create; documents view |

### 2.3 Enforcement points

- **Gates:** `AppServiceProvider::boot()` loops over `config('permissions.permissions')` and calls
  `Gate::define($permission, fn ($user) => $user->hasPermission($permission))`.
- **Admin bypass:** `Gate::before(fn ($user) => $user->hasRole('admin') ? true : null)`.
- **Routes:** `->middleware('can:<permission>')` (the dominant pattern; see
  `ROUTES_AND_ENDPOINTS.md`).
- **Middleware:** `staff` blocks `student`/`parent` roles from most modules.
- **In-controller checks:** `ComplaintController`/`HelpdeskController` (`manager()` =
  `can('<module>.manage')`, record visibility for non-managers) and `DocumentController` (role-based
  visibility, `can('documents.manage')`). `UserController` guards role changes and last-admin.
- **Views:** `@can` / `$user->can()` used in some admin nav/views (not exhaustively audited).

There are **no policies** (`app/Policies` absent). D1.5 adds explicit `Gate::authorize()` checks to Notice create/delete and About index, in addition to their route permission middleware.

## 3. Role assignment

- Assignment happens only in `Admin\UserController`:
  - create/update user → `resolveRoles()` (must have `users.roles` to change roles; only an admin
    may grant/remove `admin`; cannot remove own admin; last active admin protected).
  - `syncRoles()` deletes removed roles and `firstOrCreate`s new ones in `user_roles`.
- `RegisteredUserController` does **not** assign a role.
- `DatabaseSeeder` does **not** create `user_roles` rows.

## 4. Account lifecycle

- `UserController@toggleStatus` suspends/reactivates; suspension deletes the user's sessions
  (`DB::table('sessions')`). Self-suspend blocked; last active admin protected.
- `UserController@resetPassword` forces a random password and invalidates the target's sessions,
  flashing the new credentials once.
- `UserController@destroy` prevents self-delete and last-admin deletion; deletes the login but keeps
  `teachers`/`staff_members` records for payroll history.
- `Photo` cleanup happens on user delete and photo replacement.
- There is **no self-service account deletion restriction** beyond Breeze's password confirmation.

## 5. Security observations (for future review)

Severity is suggestive, not a formal audit.

| # | Observation | Location |
|---|---|---|
| 1 | **Public self-registration** creates role-less accounts. They land in the `staff` path post-verification. Not privilege escalation by itself (gates deny), but it is an unintended open-signup surface for a school system. | `Auth/RegisteredUserController.php` |
| 2 | **Two role sources** (`users.role` vs `user_roles`) can diverge; helpdesk staff filtering uses the legacy column. | `HelpdeskController::staff()`, `users` migration |
| 3 | **`marks.enter` can lock marks** (action=lock on the same route), bypassing the intent of `marks.lock`. | `MarksEntryController@save`, `routes/web.php:284` |
| 4 | **`AdmissionController@updateStatus` does not validate** `status`/`reason`. | `AdmissionController.php` |
| 5 | **D1.5 corrected:** `/admin/about` now requires `settings.manage` in route middleware and `AboutController::index`. Historical missing-permission finding retained in row 12 below. | `routes/web.php` (`admin.about`); `AboutController::index` |
| 6 | **Seeded accounts have no roles**, so a freshly seeded "admin" cannot administer anything (and a manually promoted `users.role='admin'` is ignored by `hasRole`). | `DatabaseSeeder`, `HasSchoolRoles` |
| 7 | **`Gate::before` is role-based**, not `users.role`-based; document this to operators. | `AppServiceProvider` |
| 8 | **CSV export formula injection** is mitigated only in `ParentController`; other exporters do not prefix dangerous cells. | multiple controllers |
| 9 | **Compound visibility** for complaints/helpdesk/documents is implemented inline and duplicated; no policy tests. | `ComplaintController`, `HelpdeskController`, `DocumentController` |
| 10 | **`users.role` legacy column** may mislead operators into thinking it grants access. | schema |

**Historical evidence, superseded for current enforcement by the D1.5 record below. Rows 11–13 added in Stage D1 (2026-11-01), from source re-verification.** Classification convention used: **Confirmed route-level protection** / **Confirmed controller-level protection** / **Missing authorization** / **Unclear; needs inspection** — every finding below was inspected against current middleware, routes, controller code, and the permission catalog before classification; nothing carried over from U1.1 unverified. Rows 11–12 feed **Stage D1.5**; row 13 is a separate functional/ownership decision excluded from that stage. D1.5 (`IMPLEMENTATION_PLAN.md` Track 1.2s) is scheduled before the D3 delegated-permissions model; no authorization code was altered in D1.

| # | Observation | Location |
|---|---|---|
| 11 | **Notice `store`/`destroy` — Missing authorization (permission-level).** Confirmed route-level protection: the group sits inside `auth + verified + active` and the `staff` role gate (students/parents get 403 via `EnsureUserIsStaff`, which permits every non-student/parent role regardless of permissions). **No permission middleware** on the group and **no controller-level authorization** in `NoticeController` (no policy, no `authorize`). The permission catalog has **no notices permission at all** — `config/permissions.php` defines nothing like `notices.view/manage`. Expected permission (to define in D1.5): proposed `notices.manage` gating create/delete; any index-access change needs an explicit rationale within those defect boundaries. Net effect today: every staff-role account can create and delete school notices. | `routes/web.php:318–325`; `app/Http/Controllers/Admin/NoticeController.php`; `config/permissions.php`; `app/Http/Middleware/EnsureUserIsStaff.php` |
| 12 | **`/admin/about` — Missing authorization (confirmed).** Confirmed route-level protection: `auth + verified + active` + `staff` gate (as row 5 noted). **No permission middleware** and **no controller-level check** (`AboutController::index` only reads settings). It sits beside routes that are permission-gated (`can:settings.view`/`can:settings.manage` on the settings and settings/website groups, `routes/web.php:699–733`), making the omission conspicuous. Proposed D1.5 default: existing `settings.manage`, because About belongs to school identity/website settings; no compelling source-based reason for a new permission was found. Row 5 was corrected from the historical line 550 to current line 735 in D1.1 (route name `about`). Route/controller authorization verification and positive/negative tests are required. | `routes/web.php:735`; `app/Http/Controllers/Admin/AboutController.php`; `routes/web.php:699–733` (passthrough context) |
| 13 | **Payroll staff editor website-profile fields — functional/validation gap, not missing authorization.** Confirmed route-level protection: the payroll staff routes (`admin/payroll/staff.*`) sit inside `can:payroll.manage` (`routes/web.php:528–552`); no controller-level re-check was found (route gate is the protection). The gap: `staff_members` carries website profile fields — `photo`, `qualification`, `bio`, `show_on_website`, `team_group`, `display_order` (migration `2026_10_19_140405`) — but the payroll editor (`resources/views/admin/payroll/staff.blade.php`) exposes none of them and `StaffController::rules()` validates none; the users module (`resources/views/admin/users/index.blade.php`, `can:users.manage`) already exposes/showcases `show_on_website`. Classification: **Confirmed route-level protection + confirmed functional gap**; the U1.1 point stands as a *separation-of-responsibilities* issue (staff website-profile ownership is undocumented), not as an evident privilege escalation. Separate owner decision, excluded from D1.5 by default: should Users/Staff exclusively own these fields, should Payroll Staff expose any, and which module owns validation/editing? Users already manages website-profile fields; avoid duplicate ownership and inconsistent updates. Any Payroll form change requires separate explicit scope approval. | `app/Http/Controllers/Admin/StaffController.php:142–158`; `resources/views/admin/payroll/staff.blade.php`; `resources/views/admin/users/index.blade.php`; migration `2026_10_19_140405_add_profile_fields_to_users_staff_and_teachers.php` |

### D1.1 proposed permission decisions

Use exact `resource.action` names: `website.view`, `website.manage`, `media.view`, `media.manage`, `notices.manage`, `news.manage`, `events.manage`, `gallery.manage`, `admissions.manage`, `publications.manage`. `admissions.manage` already exists for application review; do not silently extend it to public-page editors. The role catalog gives admin `*` and principal `settings.manage`; notice assignments must be inspected and deliberately chosen in D1.5, preserving intended admin access while denying staff without notice-management responsibility. Do not retain all-staff notice access for compatibility. Conditional publish proposals and their D1-O7 gate are in the content spec §3.3. No permission is created by this documentation.

Notices (operational school, exam, fee, holiday and attendance announcements) use proposed `notices.manage`; future News (achievements, competition/event coverage, academic activities and editorial updates/articles with images) uses proposed `news.manage`. These remain separate responsibilities.

### D1.5 implemented and verified (2026-11-01)

- **Notice writes:** `POST /admin/notices` and `DELETE /admin/notices/{notice}` use `can:notices.manage`; `NoticeController::store/destroy` also call `Gate::authorize('notices.manage')` before validation or writes. No alternate Notice write action was found. Listing remains under its existing authentication/staff gates; public publication/audience filters are unchanged. Create/delete controls are visible only to permitted users.
- **Registration and assignments:** `notices.manage` is added to `config/permissions.php`; `AppServiceProvider` registers it through the existing catalog loop. Effective permissions come from static role lists plus `user_roles` membership. Existing `admin => ['*']` and admin Gate bypass retain Notice management. No non-admin role has an established Notice-management responsibility, so no new grant was invented: principal, coordinators, teachers, librarian and accountant now lose Notice create/delete access. Students, parents and role-less accounts are denied. A separate notice-manager role is not approved or added. No migration, seeding or persisted membership update is required or performed; no setup awaits approval.
- **About:** the sole admin action is read-only `GET /admin/about` (`admin.about`), now protected by `can:settings.manage` and `Gate::authorize('settings.manage')` in `AboutController::index`. There is no About-specific update endpoint to add or test. School identity updates continue through the already-protected `admin.settings.update`; authorized and denied writes there are tested. Admin and existing principal retain access without new grants; all other configured roles are denied. **Source correction:** About is in the authenticated admin settings group, outside the `staff` subgroup, contrary to D1 row 12's earlier description. Its permission check denies students/parents; authentication, verification and active middleware are unchanged. Current staff middleware also denies role-less accounts, contrary to older D1 descriptions.
- **Tests:** new `ContentAccessAuthorizationTest` has **16 tests / 135 assertions passing** covering real HTTP writes and persisted outcomes, every configured role, role-less users, guests, controller defenses with route authorization disabled, direct denied controller calls, Notice listing/public filters, retained About content, and existing settings updates (including view-only permission denial). `UiSmokeTest` changes one student About expectation from 200 to 403; no tests removed. No separate Notice manager is tested because none is approved/configured; admin is the authorized Notice manager.
- **Execution:** the initial restricted runner failed to read the bootstrap; normal local access executed the pre-change suite at **289 tests / 1,426 assertions passing**, matching U1.1. Post-change full suite: **305 tests / 1,561 assertions passing**, delta **+16 tests / +135 assertions**, zero failures, errors or skips. Existing role, Library, settings/hero, UI and unrelated-module tests are included. PHP syntax checks passed for all six modified/new PHP source/test files and both touched Blade files. Targeted Pint passed; no broad formatting of unrelated files was performed.
- **Environment:** PHP 8.4.27, Composer 2.10.2, Laravel 13.36.0; Boost 2.10.3 was already installed. Composer autoload regeneration was attempted while diagnosing restricted access, without scripts or dependency/version changes; Composer reported existing case-name warnings for `Hallticketcontroller.php` and `Assetloan.php`. The suite uses the existing isolated test configuration; no application database/schema/role changes were performed. No separate live-browser/server verification was completed; HTTP behavior was verified through Laravel feature tests. No server was started, stopped or restarted, and PHP/server configuration was unchanged.
- **Scope:** Payroll Staff/profile editing and its separate owner decision remain unchanged. No News/CMS/Media Library work or unrelated permission changes. D1.5 is complete for owner review; D2 has not started.

### D2-A media foundation permissions (2026-11-01)

`media.view` and `media.manage` are registered in the existing static catalog/Gate loop. No staff grants, new roles, persisted memberships, seeding or `media.publish`. Existing admin wildcard grants both. Future non-admin assignments need owner approval. No D1.5 Notice/About enforcement or grants changed.

`MediaLibrary` authorizes every mutation via explicit actor and `Gate::forUser(...)->authorize('media.manage')`. Approval and publication additionally require a different uploader/reviewer; only the existing admin role with configured `*` bypasses self-approval, never published-reference integrity. Ordinary managers cannot bypass it. `media.view` alone permits status/private-original inspection, not mutations. Full owner/type/path allowlists and workflow rules: ADR-001 §9.

Two authenticated GET-only foundation routes (`/admin/media`, `/admin/media/{id}/original`) use auth/verified/active/staff/media.view with controller rechecks. No mutation routes or UI/navigation. Public `/media/{id}` serves only a published, non-archived approved derivative; never original/private-record paths. Missing/incomplete media schema yields fixed friendly JSON 503 before media queries or writes, with no SQL/paths/trace even in debug mode. D2-A permissions and separation are tested with temporary test-only grants, not persisted production assignments.

## 6. Session & CSRF

- CSRF protection is enabled by default (all POST/PUT/PATCH/DELETE forms include `@csrf` in Blade).
- Sessions are database-backed; `sessions.user_id` has no FK, but `UserController` deletes rows by
  `user_id` on suspend/reset.
- `remember_token` is rotated on admin password reset.
