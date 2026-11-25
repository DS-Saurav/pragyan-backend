# Testing & Deployment

This document records the test suite as it currently exists, the test tooling and configuration, how the project is set up/run, and the deployment-related state of the repository. It is descriptive only.

---

## 1. Test suite overview

Framework: **PHPUnit 12** (`phpunit/phpunit ^12.5.12`, `require-dev`) with `nunomaduro/collision`, `mockery/mockery`, and `fakerphp/faker`. No Pest (`pestphp/pest` is not installed; the `allow-plugins` entry for `pestphp/pest-plugin` exists in `composer.json`, but Pest is not a dependency and no `Pest.php` exists).

Test files (12 total, in `tests/` — **count stale, see §1.1**):

| File | Type | Trait | Assertions/coverage focus |
|---|---|---|---|
| `tests/TestCase.php` | base | — | Extends `Illuminate\Foundation\Testing\TestCase`; body empty |
| `tests/Feature/ExampleTest.php` | Feature | `RefreshDatabase` | `GET /` returns 200 |
| `tests/Feature/ProfileTest.php` | Feature | `RefreshDatabase` | Profile page renders; name/email update; verification status unchanged when email unchanged; account delete; wrong password rejected |
| `tests/Feature/UiSmokeTest.php` | Feature | `RefreshDatabase` | Public pages render (`/`, `/notices`, `/team`, `/login`); admin pages render across ~12 routes; student cannot see management links and gets 403 on `/admin/students`; teacher can open `/admin/students` |
| `tests/Feature/Auth/AuthenticationTest.php` | Feature | `RefreshDatabase` | Login screen; authenticate; reject bad password; logout |
| `tests/Feature/Auth/EmailVerificationTest.php` | Feature | `RefreshDatabase` | Verification screen; verify via signed URL (dispatches `Verified`); invalid hash not verified |
| `tests/Feature/Auth/PasswordConfirmationTest.php` | Feature | `RefreshDatabase` | Confirmation screen; confirm with correct/incorrect password |
| `tests/Feature/Auth/PasswordResetTest.php` | Feature | `RefreshDatabase` + `Notification::fake()` | Reset link screen; request link (asserts `ResetPassword` notification); reset screen via token; reset with valid token |
| `tests/Feature/Auth/PasswordUpdateTest.php` | Feature | `RefreshDatabase` | Update password; reject wrong current password |
| `tests/Feature/Auth/RegistrationTest.php` | Feature | `RefreshDatabase` | Register screen; register redirects to dashboard |
| `tests/Unit/ExampleTest.php` | Unit | — | `assertTrue(true)` placeholder (extends PHPUnit's `TestCase`, not the app base) |
| `tests/Unit/Helpers/SystemHelperTest.php` | Unit | — | `current_academic_year()` boundaries using `travelTo()` (Apr 13 → previous BS year, Apr 14 → rollover, Oct 16 → current BS year) |

Factories: only `database/factories/UserFactory.php` exists (with an `unverified()` state). There are **no factories for any domain model** (Student, Teacher, SchoolClass, FeeReceipt, ExamMark, etc.), so feature tests currently only build `User` (and create `UserRole` rows directly in `UiSmokeTest`).

Seeders: `database/seeders/DatabaseSeeder.php`, `FeeHeadSeeder.php`, `SalaryComponentSeeder.php`. `DatabaseSeeder` is known to be inconsistent with current model schemas (references non-existent `Student` columns and non-fillable `User` attributes, and does not create `user_roles` rows). The two component seeders are not invoked by `DatabaseSeeder`. This means the standard `migrate --seed` path is not a reliable source of test/dev data — see `MODELS_AND_DATABASE.md` and `IMPLEMENTATION_STATUS.md`.

### Coverage gaps (historical; superseded by U1.1 where applicable)

The claims below describe the earlier audit, not current coverage. H-0.2 must reconcile them with U1.1 and the current test files; do not infer present gaps from this historical list.

- No tests for any admin domain module (students, classes, attendance, exams, marks, fees, payroll, library, inventory, discipline, helpdesk, etc.) beyond the render-only assertions in `UiSmokeTest`.
- No tests for the custom RBAC beyond three role-based route checks in `UiSmokeTest` (`student` forbidden from `/admin/students`, `teacher` allowed).
- No tests for authorization boundaries on most permission-gated routes.
- No tests for validation rules, transactions, CSV export, or print/PDF-by-print views.
- No browser/Dusk tests.

### 1.1 Stage-D1 update (2026-11-01) — file quantities and baseline

- **File inventory is stale above.** As of the 2026-11-01 tree, `tests/` contains **40 `*Test.php` test files plus `TestCase.php`** (41 test-related PHP files), grown by permission-verification and fix work recorded elsewhere (U1.1 and prior stages). The 12-row table above reflects an earlier point in time and is retained only as history until H-0.2 reconciliation rewrites it against current source.
- **The suite has been executed at least once.** The U1.1 stage (before D1) ran the full suite: **289 tests / 1,426 assertions, all passing**. Everything below that live-preserved here for its historical accuracy — the "never executed" claims in this document and `IMPLEMENTATION_STATUS.md` no longer hold. Stage **D1 — documentation only — did not run any tests**; nothing in D1 claims test execution. Implementation stages must report test deltas against the 289/1,426 baseline, explaining additions and removals (`IMPLEMENTATION_PLAN.md` P-0.1).

---

**D1.1 correction-pass note:** no tests were run in D1.1. The baseline remains the **U1.1-reported executed result: 289 tests / 1,426 assertions passing**. Older counts and unexecuted-suite claims in `IMPLEMENTATION_STATUS.md` remain reconciliation work under H-0.2 (roadmap discrepancy register rows 12–13); preserve historical evidence.

## 2. PHPUnit configuration (`phpunit.xml`)

- Bootstrap `vendor/autoload.php`; `colors=true`.
- Test suites: `Unit` → `tests/Unit`, `Feature` → `tests/Feature`.
- `<source>` includes only the `app` directory (coverage scope).
- Environment overrides for tests: `APP_ENV=testing`, `APP_MAINTENANCE_DRIVER=file`, `BCRYPT_ROUNDS=4`, `BROADCAST_CONNECTION=null`, `CACHE_STORE=array`, **`DB_CONNECTION=sqlite`**, **`DB_DATABASE=:memory:`**, `DB_URL` empty, `MAIL_MAILER=array`, **`QUEUE_CONNECTION=sync`**, `SESSION_DRIVER=array`, and `PULSE_ENABLED/TELESCOPE_ENABLED/NIGHTWATCH_ENABLED=false`.
- Because `RefreshDatabase` is used, migrations run against the in-memory SQLite DB per test.

Implication: tests execute against **SQLite**, while local runtime uses **MySQL** (`.env`). Any MySQL-specific SQL in application code (`CAST(... AS UNSIGNED)`, `FIELD()`) that is reachable from tested paths would not be exercised the same way by the SQLite suite.

---

## 3. Running tests

Project-defined commands:

- `composer test` → runs `php artisan config:clear --ansi` then `php artisan test`.
- `php artisan test --compact` (documented in `CLAUDE.md`), optionally with a path or `--filter=testName`.
- `vendor/bin/phpunit` (direct runner, same arguments).

Standing instruction from `.claude`/`CLAUDE.md` guidelines: run the narrowest set covering a change, and run `vendor/bin/pint --dirty --format agent` after modifying PHP files. A `pint.json` config file is **not** present, so Pint uses its default preset.

**Were tests executed as part of producing this documentation?** No. Per the constraints for this documentation task (no state-changing commands, no DB operations), the suite was read but not run. Whether the suite currently passes is therefore unverified from the code alone. Two known risks that would affect a run are: (a) the SQLite/MySQL split, and (b) the broken `DatabaseSeeder` (not used by tests, which rely on factories instead). **Update (Stage D1, §3.1):** the suite's current pass state is no longer "unverified" — see §1.1/§3.1 (U1.1's executed run).

### 3.1 Stage-D1 record: baseline attribution and upload-limit environment findings

**Baseline attribution.** The verified reference baseline for regression reporting is **289 tests / 1,426 assertions passing**, executed by the **U1.1 stage** (which also added the Library guard fix, the Document failure-safety fix, and a hero render test, and removed its artifacts). Stage D1 (2026-11-01) executed **no tests**: none are claimed. Any D-series implementation stage changing tests must state its own executed figures against this baseline and justify additions/removals.

**Upload-limit environment findings (current vs target; nothing changed in D1).** From U1.1's live environment inspection:

| Item | Current (U1.1-observed local) | Target |
|---|---|---|
| `upload_max_filesize` | **2M** — files above 2 MB are rejected by PHP before Laravel validation ever runs | 8 MB per image (product target, FR-097) |
| `post_max_size` | **8M** — aggregate request bodies above 8 MB fail regardless of per-file checks | ≈ 40 MB aggregate POST, incl. multipart overhead, sized for up to 4 images per request |
| UI advertisement | Website Content UI advertises up to **8 MB per image** | UI, Laravel validation, and runtime limits must be brought into agreement |

Implication: the advertised limit is currently untruthful at the runtime level — individual 3 MB images are rejected before validation, and no matching field-level error is produced because PHP-level rejection bypasses Laravel. Fixing runtime limits is **D2 work with D2 config changes** (a PHP-settings change, not an application-code change); no PHP settings, configuration, or code was touched during D1.

**Windows dev-server finding (local-only; not a production answer).** U1.1 reproduced a `php artisan serve` failure mode on Windows: in the affected mode `ServeCommand` omits `TEMP` and `TMP` from the child process's passthrough environment, PHP may then fall back to a non-writable directory, and multipart uploads fail with `UPLOAD_ERR_NO_TMP_DIR`. The verified local workaround is `php artisan serve --no-reload` (verified with a live multipart upload in U1.1). This is a **development-server issue on the local Windows environment** — it must not be assumed relevant to production, and the workaround is not offered as a production answer. Production upload correctness belongs to the deployment/hardening work (D-series and R-7.x items).

---

## 4. Local setup and development workflow

From `composer.json` scripts:

- **`composer setup`**: `composer install` → copy `.env.example` to `.env` if missing → `php artisan key:generate` → `php artisan migrate --force` → `npm install --ignore-scripts` → `npm run build`.
- **`composer dev`**: disables the process timeout and runs `php artisan dev` (the framework's dev-process runner; `php artisan dev` / `dev:list` are available).
- **`composer test`**: as above.
- Composer lifecycle hooks: `post-autoload-dump` (package discover), `post-update-cmd` (publish Laravel assets), `post-root-package-install` (copy `.env`), `post-create-project-cmd` (key generate, create `database/database.sqlite`, `migrate --graceful`).

Frontend (`package.json` scripts):

- `npm run dev` → Vite dev server (HMR).
- `npm run build` → production asset build (required before serving without the Vite dev server; the layouts use `@vite(...)`).

There is no root `Makefile`, no `docker-compose`, and no task runner beyond the Composer/npm scripts above.

### Filesystem/symlink requirement

`config/filesystems.php` declares `public/storage → storage/app/public`. The symlink currently exists in `public/`; `php artisan storage:link` recreates it. Public uploads (photos, documents) rely on it.

---

## 5. Code style & static analysis

- **Laravel Pint** is installed (`laravel/pint ^1.27`) with no `pint.json`; the documented invocation is `vendor/bin/pint --dirty --format agent`.
- **No static analysis** tooling is installed (no PHPStan, Psalm, Rector, or Larastan; no config files for them).
- `.editorconfig` enforces UTF-8, LF, 4-space indent (2 for YAML, 4 for docker-compose), final newline, trimmed trailing whitespace (except Markdown).
- `.gitattributes` forces `eol=lf` and marks `.blade.php`/`.php` diffs.
- `.npmrc`: `ignore-scripts=true`, `audit=true`.

---

## 6. Version control conventions

- `.gitignore` excludes `.env*` (except `.env.example`), `.phpunit.result.cache`, `/node_modules`, `/public/build`, `/public/storage`, `/vendor`, IDE folders (`.vscode`, `.idea`, `.nova`, `.zed`, `.cursor/`, `.codex`), `/auth.json`, `*.log`, `/storage/*.key`, `/storage/pail`, `_ide_helper.php`.
- `docs/` is **not** ignored (the documents in this folder are versionable).
- A `.phpunit.result.cache` file is present in the working tree (ignored).
- The repository is a git repo with a working tree that currently contains uncommitted modifications (documentation reflects the on-disk state).

---

## 7. Deployment state

**No deployment configuration exists in the repository.** Confirmed absent:

- No `Dockerfile`, `docker-compose.*`, `Procfile`, `fly.toml`, `vercel.json`, `deploy.php`, or similar.
- No `.github/` directory or CI workflows.
- No `.env.production`, `env.staging`, or environment-specific env templates beyond `.env.example`.
- No Laravel Cloud configuration files, though `boost.json` sets `cloud: true` and the `deploying-to-cloud` skill is present (tooling readiness, not actual deployment).

There is therefore no committed, automated deployment pipeline. `README.md` is the stock Laravel README and contains no project-specific deployment instructions.

### Deployment-relevant characteristics observed

- `composer.json` `require` targets `php ^8.3` and `laravel/framework ^13.17`; local runtime observed as PHP 8.4.x / Laravel 13.36.0.
- The application relies on `php artisan migrate` and, for public storage, `php artisan storage:link`.
- Session, cache, and queue all default to the **database** driver, so a database must be reachable and migrated before the app can serve traffic.
- `MAIL_MAILER=log` means password-reset emails are written to logs, not delivered, in the default configuration — relevant to any environment used for real users.
- `BROADCAST_CONNECTION=log` and no broadcasting code.
- `frontend/team.blade.php` bypasses Vite (inline CSS), while all other pages require a built Vite manifest to render.
- **PSR-4 portability risk**: `app/Http/Controllers/Admin/Hallticketcontroller.php` defines class `HallTicketController`, and `app/Models/Assetloan.php` defines class `AssetLoan`. PSR-4 autoloading is case-sensitive on Linux, so these files may fail to autoload on case-sensitive filesystems (e.g. typical Linux production hosts) even though they work on Windows. This is a deployment portability concern to verify before shipping to Linux.
- MySQL-only SQL in some code paths (e.g. `ResultService`, `DisciplineController`) means the production database engine choice matters; the project's `.env` uses MySQL, while `.env.example`/tests use SQLite.

### Suggested deployment inputs (informational, based on actual dependencies)

To run this app somewhere, the following are *required by the current configuration*: PHP ≥ 8.3 with the PDO MySQL (or chosen DB) extension, Composer install of `require` packages, `APP_KEY` generation, a migrated database, `npm install` + `npm run build` for assets, and `php artisan storage:link`. No secrets should be committed; env values are provided at deploy time.

---

## 8. Gaps and items for further inspection

- **Confirm the suite passes.** It was not executed for this documentation; run `composer test` (or `php artisan test`) to establish a baseline.
- **SQLite/MySQL divergence**: tests run on SQLite in memory, local on MySQL; MySQL-only SQL in app code is not proven portable by the current suite.
- **Broken seeder**: `DatabaseSeeder` is inconsistent with model schemas and does not seed `user_roles` or the two component seeders; `migrate --seed` and `db:seed` should be treated as unreliable until inspected.
- **Only one factory** (`UserFactory`); broader feature coverage would require additional factories (per `CLAUDE.md` guidance to create factories with models).
- **No CI**: there is no automated test/lint pipeline. `composer test` + `vendor/bin/pint` are the documented manual checks.
- **PSR-4 case mismatches** (`Hallticketcontroller.php`, `Assetloan.php`) should be validated on a case-sensitive filesystem before deploying to Linux.
- **No deployment artifacts** in-repo; if Laravel Cloud or another platform is intended, corresponding configuration is not yet committed (the `deploying-to-cloud` skill/tooling is available but unused).
- **`.env.example` lacks `SCHOOL_*` and several `AUTH_*`/`SESSION_*` keys** documented in `CONFIGURATION_AND_INTEGRATIONS.md`; a fresh clone will start with empty school details and default cookie/secure settings.

## D2-A foundation verification (2026-11-01)

Owner-reviewed starting baseline: **305 tests / 1,561 assertions passing** (D1.5). D2-A actual result: **339 tests / 1,795 assertions passing (+34 tests / +234 assertions; focused: 34 tests / 234 assertions passing; zero failures/errors/skips)**. No old test changed/removed; no skips introduced. Focused coverage includes schema/index/FK/reversibility, morph aliases and independent context/focal values, private originals and checked derivatives, all service permissions, reviewer separation/admin override, live-use/archive/restore protections including legacy hero JSON, missing/incomplete schema 503, traversal/private-record rejection, failed processing/storage/DB cleanup, upload limits, checksum integrity and deterministic read-only backfill planning.

Database/storage safety: explicit process environment selects `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, empty DB_URL; the new test class checks SQLite isolation **before** migrations. Its memory database is disposed after each test. The new migrations are also rolled back/reapplied explicitly in isolated SQLite. The first run exposed a pre-existing legacy rollback defect (`2026_10_19_140405`, teachers_user_id_unique when dropping user_id); no legacy migration was changed or test skipped. Teardown was corrected to dispose the memory database rather than rolling back unrelated old schema. Fake disks cover originals, derivatives and legacy public heroes; no school uploads are written/moved/deleted.

No SQLite file database, isolated browser server or live-browser verification was used. HTTP behavior was exercised through Laravel automated feature tests. **No test/request/migration/backfill reached the school MySQL server.** No normal .env, PHP configuration, vendor file or dependency change. No live hero backfill; command tests ran against isolated records only. Syntax and targeted formatting checks apply to all new/changed PHP. D2-A does not establish MySQL SQL/concurrency parity or release readiness; those require later owner-authorized verification.

Schema review/execution boundary: migration files exist but must not be run on the configured MySQL database without explicit owner approval. Media routes return friendly 503 until the complete schema exists; no navigation link is added. D2-B/C/D and D3+ remain unstarted. Storage/path/workflow details and outstanding integration trade-offs: ADR-001 §9. Existing upload limits and `php artisan serve --no-reload` local-only workaround above are unchanged.

## D2-A.1 focal/placement regression verification (2026-11-01)

Starting baseline: **339 tests / 1,795 assertions passing**. Actual result: **focused MediaFoundationTest: 46 tests / 751 assertions passing; full suite: 351 tests / 2,312 assertions passing, versus baseline 339 / 1,795 (+12 tests / +517 assertions), zero failures/errors/skips**. Twelve tests added to MediaFoundationTest, no tests removed. One original duplicate-slot test was strengthened to require service validation before persistence and verify record count; a new direct-model test independently preserves the original database unique-constraint check. All other original media tests were preserved.

Coverage: creation-only 50/50 defaults; supplied 0/50/100 boundaries; valid integer/float/string decimals; independent axes; omitted coordinates preserved on partial/empty updates; invalid blank/null/negative/out-of-range/type/exponent/whitespace/over-precision values rejected on create and update; unchanged metadata and files after failures; ASCII lowercase placement normalization and 60-character slug validation; mixed-case duplicates rejected before INSERT across different assets; update self-exclusion/conflicts; distinct placements/orders/owners allowed; metadata-update authorization and immutable owner/asset identity. Existing workflows, self-review/admin override, published-reference guards, archive/restore, privacy/serving, hero planning/rendering and D1.5 tests remain in the passing suite.

Only SQLite `:memory:` and fake storage were used. The process explicitly selects SQLite and the media test setup verifies isolation before schema setup. No MySQL connection (including read-only), scratch database, live migration, .env/configuration/dependency change, or live school upload/storage write occurred. Existing SQLite migration/reversal and dry-run hero command checks ran only as automated isolated regressions; no live/apply backfill ran. SQLite results do not verify MySQL DDL, collation execution or concurrent row-lock behavior. PHP syntax and targeted Pint checks passed for the two modified PHP files.

The initial placement-object regression exposed a validator length-check TypeError; adding bail before string/length/regex rules corrected it rather than removing the test. An encoding error during test editing was recovered to the exact initial test-file hash before additions were reapplied as UTF-8.

Approved semantics and audit details: ADR-001 §10. D2-A.2 scratch MySQL verification and D2-B/C/D remain unstarted, requiring separate owner authorization. D2-B should warn on likely checksum duplicates and offer existing-asset reuse versus intentional separate upload; do not enforce checksum uniqueness or block intentional duplicates. No warning UI was added in this stage.
