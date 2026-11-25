# Documentation Index

Technical documentation for the **Pragyan Pathashala** School Management System (Laravel 13, PHP 8.3+, server-rendered Blade).

These documents describe the application **as it exists on disk**. They are implementation-based: every claim is grounded in the actual code, routes, migrations, and views in this repository. No features are invented, and no recommendations or rewrites are included.

> **Important context**
> - The repository had **uncommitted working-tree changes** when this documentation was produced; content reflects the current on-disk state, not necessarily the last commit.
> - **Tests were not executed** while producing these documents (read-only audit). Test pass/fail status is unverified. *(Stage-D1 update, 2026-11-01: the suite has since been executed once — U1.1 stage, 289 tests / 1,426 assertions passing; see `TESTING_AND_DEPLOYMENT.md` §3.1.)*
> - Environment values are referenced by **variable name only**; no secret values are reproduced.
> - **Stage-D1 note (2026-11-01):** rows 14–17 are planning/documents-of-record rather than "as-on-disk" implementation docs; they are indexed here so all project documentation stays discoverable from one place. Quick-facts rows below predate the Stage-A/U1.1/D1 workflow and several figures are stale (e.g. "Tests: 12 files" → see §1.1 of `TESTING_AND_DEPLOYMENT.md`; views/public-page counts have grown) — treat the per-document contents as authoritative, not this snapshot.

---

## Documents

| # | Document | What it covers |
|---|---|---|
| 1 | [`PROJECT_OVERVIEW.md`](./PROJECT_OVERVIEW.md) | What the application is, stack versions, high-level purpose and scope |
| 2 | [`ARCHITECTURE.md`](./ARCHITECTURE.md) | Request lifecycle, layering, service/provider wiring, design decisions |
| 3 | [`PROJECT_STRUCTURE.md`](./PROJECT_STRUCTURE.md) | Directory-by-directory inventory of `app/`, `routes/`, `database/`, `resources/` |
| 4 | [`ROUTES_AND_ENDPOINTS.md`](./ROUTES_AND_ENDPOINTS.md) | Full route inventory (~217), middleware groups, naming conventions |
| 5 | [`CONTROLLERS_AND_SERVICES.md`](./CONTROLLERS_AND_SERVICES.md) | Controller-by-controller responsibilities, services, support classes, helpers |
| 6 | [`MODELS_AND_DATABASE.md`](./MODELS_AND_DATABASE.md) | ~44 Eloquent models, 47 migrations, relationships, tables, schema notes |
| 7 | [`AUTHENTICATION_AND_AUTHORIZATION.md`](./AUTHENTICATION_AND_AUTHORIZATION.md) | Breeze auth, custom RBAC (roles/permissions/gates), middleware |
| 8 | [`BLADE_AND_PUBLIC_WEBSITE.md`](./BLADE_AND_PUBLIC_WEBSITE.md) | Layouts, Blade components, design-system CSS, public pages, print views |
| 9 | [`VALIDATION_AND_ERROR_HANDLING.md`](./VALIDATION_AND_ERROR_HANDLING.md) | Inline validation patterns, authorization failures, exception handling |
| 10 | [`CONFIGURATION_AND_INTEGRATIONS.md`](./CONFIGURATION_AND_INTEGRATIONS.md) | Config files, env var names, packages, third-party/tooling integrations |
| 11 | [`TESTING_AND_DEPLOYMENT.md`](./TESTING_AND_DEPLOYMENT.md) | Test suite (12 files), PHPUnit config, setup/run commands, deployment state |
| 12 | [`IMPLEMENTATION_STATUS.md`](./IMPLEMENTATION_STATUS.md) | Feature-by-feature status, known defects, verification matrix |
| 13 | `DOCUMENTATION_INDEX.md` | This file — navigation and quick facts |
| 14 | [`PRD.md`](./PRD.md) *(planning document; added to the index in Stage D1)* | Product requirements, owner decisions M-01–M-10, revisions 0.1–0.5 (D2-A foundation and approved media decisions; D1: Option C direction, FR-091–FR-098, D1-O1…O10 proposals) |
| 15 | [`IMPLEMENTATION_PLAN.md`](./IMPLEMENTATION_PLAN.md) *(planning document; added to the index in Stage D1)* | Phased roadmap — Phase 1–3 tracks, security backlog, D-series public-website stages (D1→D1.5→D2–D6) |
| 16 | [`architecture/ADR-001-hybrid-public-media-management.md`](./architecture/ADR-001-hybrid-public-media-management.md) *(created in Stage D1)* | Architecture Decision Record: Option C — public Media Library with separate private records, conceptual entities, contextual metadata rules, hero-JSON backfill plan |
| 17 | [`PUBLIC_WEBSITE_CONTENT_SPEC.md`](./PUBLIC_WEBSITE_CONTENT_SPEC.md) *(created in Stage D1)* | 19-page public-website content specification: common capabilities, permissions concepts, page matrix, verified route inventory and the `/academics` split/redirect requirements, D1-O1…O10 decision context |
| 18 | [`PUBLIC_WEBSITE_LAUNCH_READINESS.md`](./PUBLIC_WEBSITE_LAUNCH_READINESS.md) *(Phase 5A operating guide)* | Proposed editorial responsibility matrix, content workflow, scheduling, privacy, deployment, and backup/restore readiness checklist; it does not configure or claim a production launch |

---

## Quick facts

| Item | Value |
|---|---|
| Application name | `Pragyan Pathashala` (`APP_NAME`) |
| Framework | `laravel/framework ^13.17` (observed v13.36.0) |
| PHP | `^8.3` required (local runtime observed 8.4.x) |
| Frontend | Blade + Tailwind v3 (PostCSS) + Alpine.js + Vite |
| Auth scaffolding | Laravel Breeze |
| Authorization | Custom RBAC (`config/permissions.php`), 10 roles, ~45 permissions |
| Database | 47 migrations; `.env` uses MySQL, `.env.example`/tests use SQLite |
| API | None (no `routes/api.php`, no Sanctum/Passport) |
| Routing | Web + console only; health check `/up` |
| Routes | ~217 (mixed `auth`, `verified`, `active`, `staff`, `can:` middleware) |
| Controllers | 49 (incl. ~36 admin, 9 auth) |
| Models | ~44 + `Concerns/HasSchoolRoles` trait |
| Views | 91 Blade files (3 layouts, 18 components, 3 public pages) |
| Tests | 12 files (auth, profile, UI smoke, academic-year helper); not run during audit |
| PDF | `barryvdh/laravel-dompdf` installed but unused; "PDF" output is browser-print HTML |

---

## Where to look for a given question

| Question | Document |
|---|---|
| "What does this app do?" | `PROJECT_OVERVIEW.md` |
| "How is the code organized?" | `PROJECT_STRUCTURE.md` → `ARCHITECTURE.md` |
| "What URL handles X?" | `ROUTES_AND_ENDPOINTS.md` |
| "Which controller/view handles X?" | `CONTROLLERS_AND_SERVICES.md` |
| "What table/column backs X?" | `MODELS_AND_DATABASE.md` |
| "Who can access X?" | `AUTHENTICATION_AND_AUTHORIZATION.md` |
| "How is page X rendered?" | `BLADE_AND_PUBLIC_WEBSITE.md` |
| "How are inputs validated?" | `VALIDATION_AND_ERROR_HANDLING.md` |
| "What config/env/package does X use?" | `CONFIGURATION_AND_INTEGRATIONS.md` |
| "How are tests set up / can I deploy?" | `TESTING_AND_DEPLOYMENT.md` |
| "Is feature X finished?" | `IMPLEMENTATION_STATUS.md` |

---

## Status vocabulary used across these documents

- **Implemented** — end-to-end path exists.
- **Partially implemented** — works, but with identified gaps.
- **Present but unverified** — exists but not proven at runtime by this audit.
- **Planned / scaffolding only** — artifacts exist without functional wiring.
- **Broken** — cannot execute as written.

---

## Cross-cutting items flagged for future review

Consolidated from all documents; these are observations, not action items.

1. **Broken `DatabaseSeeder`** — references non-existent `Student` columns and non-fillable `User` attributes; does not seed `user_roles`; component seeders unused. (`MODELS_AND_DATABASE.md`, `IMPLEMENTATION_STATUS.md`)
2. **DB topology mismatch** — MySQL (`.env`) vs SQLite (`.env.example`/`phpunit.xml`), alongside MySQL-only SQL in `ResultService` and `DisciplineController`. (`CONFIGURATION_AND_INTEGRATIONS.md`, `MODELS_AND_DATABASE.md`)
3. **PSR-4 case mismatches** — `Hallticketcontroller.php` / `Assetloan.php` may fail on case-sensitive filesystems. (`TESTING_AND_DEPLOYMENT.md`)
4. **Unused/vestigial infrastructure** — dompdf, `@tailwindcss/vite` v4, queues, broadcasting, `config/services.php` credentials. (`CONFIGURATION_AND_INTEGRATIONS.md`)
5. **Side effects on GET** — `TimetableController@index`, `DocumentController@download`. (`CONTROLLERS_AND_SERVICES.md`, `IMPLEMENTATION_STATUS.md`)
6. **Authorization at route layer only** — no controller-level `authorize()`; `AboutController` has no permission middleware. (`VALIDATION_AND_ERROR_HANDLING.md`, `AUTHENTICATION_AND_AUTHORIZATION.md`)
7. **Dual role source of truth** — legacy `users.role` vs `user_roles`. (`AUTHENTICATION_AND_AUTHORIZATION.md`)
8. **Missing transactions** — `LibraryController@issueBook`, `PayrollController@payAll`. (`VALIDATION_AND_ERROR_HANDLING.md`)
9. **Public site duplication** — three standalone pages, no shared layout; `frontend/team.blade.php` bypasses Vite. (`BLADE_AND_PUBLIC_WEBSITE.md`)
10. **Orphaned/empty views** — `admin/dashboard.blade.php`, empty `student/dashboard.blade.php`, empty `teacher/dashboard.blade.php`. (`BLADE_AND_PUBLIC_WEBSITE.md`)

---

## Maintenance note

If the codebase changes, these documents must be regenerated from the source of truth (routes, controllers, models, migrations, views). Do not edit them by hand to describe behavior that has not been verified against the code.

## D2-A foundation checkpoint (2026-11-01)

The actual two-table schema, migration execution/rollback order, complete stable morph map, controlled categories, focal coordinates, private originals/checked public derivatives, workflow/service safeguards and dry-run hero architecture are recorded in [ADR-001 §9](architecture/ADR-001-hybrid-public-media-management.md#9-d2-a-implemented-schema-checkpoint-2026-11-01-owner-review-required-before-d2-b). PRD v0.5 records approved O3/O5/O6/O7; roadmap separates D2-A from unstarted D2-B/C/D. Authorization and testing docs record actual enforcement and isolated results (**339 tests / 1,795 assertions passing (+34 tests / +234 assertions; focused: 34 tests / 234 assertions passing; zero failures/errors/skips)**). Earlier D1 content-spec proposals are historical and superseded by the approved D2-A media decisions; remaining owner questions stay open.

D1/D1.1/D1.5 evidence is preserved. No school MySQL migration, live backfill, UI/navigation, CMS, hero change or private-record integration is claimed. Owner schema review and explicit continuation approval are required before D2-B.
