# Configuration & Integrations

This document inventories the application's configuration surface and third-party integrations exactly as configured in `config/`, `.env.example`, `composer.json`, `package.json`, and the root tooling files. Environment variable **names** are listed; secret *values* are never reproduced.

---

## 1. Environment variables (names only)

`.env.example` is the tracked template; `.env` (gitignored) is the live local file. The categories of variables and their defaults:

| Category | Variables | Notes |
|---|---|---|
| Application | `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL` | `.env.example` defaults `APP_NAME=Laravel`; the local `.env` sets `APP_NAME="Pragyan Pathashala"`, `APP_ENV=local`, `APP_URL=http://localhost:8000` |
| Locales | `APP_LOCALE`, `APP_FALLBACK_LOCALE`, `APP_FAKER_LOCALE` | `en`, `en`, `en_US` |
| Maintenance | `APP_MAINTENANCE_DRIVER`, optional `APP_MAINTENANCE_STORE` | `file`; store default `database` |
| Hashing | `BCRYPT_ROUNDS` | `12` |
| Logging | `LOG_CHANNEL`, `LOG_STACK`, `LOG_DEPRECATIONS_CHANNEL`, `LOG_LEVEL`, plus `LOG_SLACK_*`, `LOG_STDERR_FORMATTER`, `LOG_SYSLOG_FACILITY`, `LOG_DAILY_DAYS` | `stack`, `single`, `null`, `debug` |
| Database | `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_URL`, `DB_SOCKET`, `DB_CHARSET`, `DB_COLLATION`, `DB_FOREIGN_KEYS`, `MYSQL_ATTR_SSL_CA` | `.env.example` uses `DB_CONNECTION=sqlite`; the local `.env` uses `DB_CONNECTION=mysql` |
| Cache | `CACHE_STORE`, `CACHE_PREFIX`, plus `DB_CACHE_CONNECTION`, `DB_CACHE_TABLE`, `DB_CACHE_LOCK_*`, `CACHE_STORAGE_DISK`, `CACHE_STORAGE_PATH` | `database` |
| Session | `SESSION_DRIVER`, `SESSION_LIFETIME`, `SESSION_ENCRYPT`, `SESSION_PATH`, `SESSION_DOMAIN`, `SESSION_EXPIRE_ON_CLOSE`, `SESSION_SECURE_COOKIE`, `SESSION_HTTP_ONLY`, `SESSION_SAME_SITE`, `SESSION_PARTITIONED_COOKIE` | `database`, `120`, `false` |
| Queue | `QUEUE_CONNECTION`, `DB_QUEUE_CONNECTION`, `DB_QUEUE_TABLE`, `DB_QUEUE`, `DB_QUEUE_RETRY_AFTER`, `QUEUE_FAILED_DRIVER`, plus `BEANSTALKD_*`, `SQS_*`, `REDIS_QUEUE_*` | `database` |
| Filesystem | `FILESYSTEM_DISK` | `local` |
| Mail | `MAIL_MAILER`, `MAIL_SCHEME`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, `MAIL_URL`, `MAIL_SENDMAIL_PATH`, `MAIL_EHLO_DOMAIN`, `MAIL_LOG_CHANNEL` | `MAIL_MAILER=log` |
| Broadcasting | `BROADCAST_CONNECTION` | `log` |
| Redis | `REDIS_CLIENT`, `REDIS_HOST`, `REDIS_PASSWORD`, `REDIS_PORT`, plus `REDIS_CACHE_*`, `REDIS_QUEUE_*` | `phpredis` |
| AWS / S3 | `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_URL`, `AWS_ENDPOINT`, `AWS_USE_PATH_STYLE_ENDPOINT` | For the `s3` disk; no application code currently selects it |
| Memcached | `MEMCACHED_HOST`, `MEMCACHED_PORT`, `MEMCACHED_*` | Present for the `memcached` store |
| Frontend | `VITE_APP_NAME` | `"${APP_NAME}"` |
| School (custom) | `SCHOOL_ADDRESS`, `SCHOOL_PHONE`, `SCHOOL_EMAIL`, `SCHOOL_LOGO` | Read by `config/school.php`; **not present in `.env.example`**, and not set in the local `.env` (so all default to empty strings) |
| Third-party service keys | `POSTMARK_API_KEY`, `RESEND_API_KEY`, `SLACK_BOT_USER_OAUTH_TOKEN`, `SLACK_BOT_USER_DEFAULT_CHANNEL` | Declared in `config/services.php`; **not referenced by any application code** |

Environment topology mismatch (factual): the tracked `.env.example` and `phpunit.xml` both use `sqlite`, while the local `.env` uses `mysql`. Tests therefore run against SQLite in memory while local development runs against MySQL; see `TESTING_AND_DEPLOYMENT.md` for portability implications.

---

## 2. Application configuration (`config/app.php`)

- `name` ← `APP_NAME` (fallback `Laravel`).
- `env`, `debug`, `url` ← env vars.
- **`timezone` is hardcoded to `'UTC'`** (not env-driven) — relevant for date-heavy features (attendance, fees, academic year).
- `locale`/`fallback_locale`/`faker_locale` ← env vars.
- `cipher` = `AES-256-CBC`; `key` ← `APP_KEY`; `previous_keys` ← `APP_PREVIOUS_KEYS`.
- `maintenance.driver` default `file`, `maintenance.store` default `database`.

## 3. Authentication configuration (`config/auth.php`)

- Single guard `web` (driver `session`, provider `users`).
- Provider `users` → `App\Models\User` (Eloquent), overridable via `AUTH_MODEL`.
- Default guard/broker env names: `AUTH_GUARD`, `AUTH_PASSWORD_BROKER`.
- Password broker `users`: table `password_reset_tokens`, `expire` 60 min, `throttle` 60s.
- `password_timeout` ← `AUTH_PASSWORD_TIMEOUT` (default 10800s / 3h).
- No API guard, no Sanctum/Passport (consistent with the absence of `routes/api.php`).

## 4. Database (`config/database.php`)

- Default connection ← `DB_CONNECTION` (config fallback `sqlite`).
- Connections defined: `sqlite`, `mysql`, `mariadb`, `pgsql`, `sqlsrv` with standard Laravel defaults.
- SQLite: `foreign_key_constraints` ← `DB_FOREIGN_KEYS` (default true), `transaction_mode` `DEFERRED`.
- MySQL/MariaDB: `strict` true, `utf8mb4` / `utf8mb4_unicode_ci`, optional `MYSQL_ATTR_SSL_CA`.
- The application contains MySQL-specific SQL in several places (e.g. `CAST(... AS UNSIGNED)`, `FIELD()`), which is relevant given the SQLite/MySQL split — see `MODELS_AND_DATABASE.md` and `IMPLEMENTATION_STATUS.md`.

## 5. Cache (`config/cache.php`)

- Default store ← `CACHE_STORE` (`database`).
- Stores configured: `array`, `database`, `file`, `storage`, `memcached`, `redis`, `dynamodb`, `octane`, `failover` (database→array).
- `prefix` defaults to `Str::slug(APP_NAME).'-cache-'`.
- **`serializable_classes` is `false`** — no PHP classes are unserialized from cache (hardening against gadget chains).

## 6. Session (`config/session.php`)

- Driver `database`; lifetime 120 min; `expire_on_close` false; `encrypt` false.
- Cookie: `http_only` true, `same_site` `lax`, `secure` ← `SESSION_SECURE_COOKIE` (unset locally), `domain` ← `SESSION_DOMAIN`.
- `serialization` = `json` (not `php`).

## 7. Queue (`config/queue.php`)

- Default connection ← `QUEUE_CONNECTION` (`database`), table `jobs`.
- Connections: `sync`, `database`, `beanstalkd`, `sqs`, `redis`, `deferred`, `background`, `failover` (database→deferred).
- Batching table `job_batches`; failed driver `database-uuids`, table `failed_jobs`.
- Note: no `app/Jobs` directory and no queued jobs were found; `QUEUE_CONNECTION=database` is configured but no producer code was identified (email uses `MAIL_MAILER=log`).

## 8. Filesystem (`config/filesystems.php`)

- Default disk `local` → `storage/app/private` (with `serve` true).
- `public` disk → `storage/app/public`, public URL `APP_URL/storage`.
- `s3` disk present but unused by application code.
- Symlink mapping: `public/storage` → `storage/app/public` (the symlink currently exists in `public/`).

## 9. Logging (`config/logging.php`)

- Default channel `stack` → `single` (`storage/logs/laravel.log`), level `LOG_LEVEL` (debug).
- Channels: `stack`, `single`, `daily`, `monthly`, `slack`, `papertrail`, `stderr`, `syslog`, `errorlog`, `null`, `emergency`.

## 10. Mail (`config/mail.php`)

- Default mailer `log` (writes to the log channel). No real transport is configured in `.env.example` or the local `.env`.
- Mailers available: `smtp`, `ses`, `postmark`, `resend`, `sendmail`, `log`, `array`, `failover` (smtp→log), `roundrobin` (ses/postmark).
- Global `from` ← `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME`.

---

## 11. Custom configuration files

### 11.1 `config/permissions.php` — custom RBAC (not a package)

Defines the application's role/permission model (see `AUTHENTICATION_AND_AUTHORIZATION.md` for wiring):

- `role_labels`: 10 roles — `admin`, `principal`, `academic_coordinator`, `discipline_coordinator`, `eca_coordinator`, `teacher`, `librarian`, `accountant`, `parent`, `student`.
- `permissions`: a label map of ~45 permissions (e.g. `exams.view`, `marks.enter`, `marks.lock`, `results.publish`, `discipline.report`, `library.manage`, `fees.view`, `payroll.view`, `accounts.view`, `users.view`, `settings.view`, `helpdesk.view`, `complaints.view`, `documents.view`, `calendar.manage`, `lessonplans.*`, `teachinglogs.*`, `conduct.*`, `activities.*`, …).
- role→permission map plus `admin => ['*']` (handled by the `Gate::before` bypass in `AppServiceProvider`).

This is application-owned configuration consumed by `AppServiceProvider::boot()` (one `Gate::define` per permission) and `app/Models/Concerns/HasSchoolRoles.php`.

### 11.2 `config/school.php` — school details

- `address`, `phone`, `email` ← `SCHOOL_ADDRESS`, `SCHOOL_PHONE`, `SCHOOL_EMAIL`.
- `logo` ← `SCHOOL_LOGO` (a path inside `/public`; empty shows a placeholder).
- `hall_ticket_instructions`: a static array of five strings used on hall tickets.

These values are **not set** in the current `.env`, so address/phone/email/logo render empty unless added.

### 11.3 `config/services.php`

Declares credential slots for `postmark`, `resend`, `ses`, and `slack` notifications. No application code references these arrays (no outbound mail/notification integration was found).

---

## 12. Third-party packages & integrations

### PHP (`composer.json`)

| Package | Constraint | Role | Used in code? |
|---|---|---|---|
| `php` | `^8.3` | Runtime | Local runtime is PHP 8.4.x |
| `laravel/framework` | `^13.17` | Framework | Yes |
| `barryvdh/laravel-dompdf` | `^3.1` | HTML→PDF | **Installed, but no view/controller calls the PDF facade**; "PDF" pages are browser-print HTML |
| `laravel/tinker` | `^3.0` | REPL | Dev |
| `laravel/boost` (dev) | `^2.10` | AI tooling / MCP | `boost.json`, `.mcp.json` |
| `laravel/breeze` (dev) | `^2.4` | Auth scaffolding | Auth controllers/views/layouts |
| `laravel/pail` (dev) | `^1.2.5` | Log tailing | Tooling |
| `laravel/pao` (dev) | `^1.0.6` | Dev tooling | Tooling |
| `laravel/pint` (dev) | `^1.27` | Code style | Tooling (no `pint.json` present) |
| `phpunit/phpunit` (dev) | `^12.5.12` | Tests | `phpunit.xml` |
| `fakerphp/faker`, `mockery/mockery`, `nunomaduro/collision` (dev) | — | Test support | Tests |

No API client, payment gateway, SMS gateway, or notification (Twilio, Nexmo, Firebase, Stripe, etc.) package is present. No `composer` platform config beyond `ext`-less defaults.

### JavaScript (`package.json`)

| Package | Constraint | Role |
|---|---|---|
| `alpinejs` | `^3.4` | Client interactivity (started in `resources/js/app.js`) |
| `tailwindcss` | `^3.1` | Active CSS framework (v3) |
| `@tailwindcss/forms` | `^0.5` | Form reset plugin |
| `@tailwindcss/vite` | `^4.0` | **Installed but unused** (would imply Tailwind v4) |
| `postcss`, `autoprefixer` | ^8 / ^10 | PostCSS pipeline |
| `vite` | `^8.0` | Bundler |
| `laravel-vite-plugin` | `^3.1` | Laravel<->Vite glue |
| `concurrently` | `^10` | Running dev processes |
| `@laravel/multiplex` | `^0.4` (optional) | Optional dependency |

Scripts: `npm run dev` (Vite dev server) and `npm run build` (production build). `composer run dev` maps to `php artisan dev` (the framework's dev-process runner, confirmed available via `php artisan list`).

### External runtime assets (per page render)

- `fonts.bunny.net` — Figtree webfont.
- `cdn.jsdelivr.net` — Bootstrap Icons 1.11.3.
- These are loaded directly from CDNs by the layouts/public pages (no local asset copies).

### AI / tooling integration

- `.mcp.json` registers an MCP server `laravel-boost` (`php artisan boost:mcp`).
- `boost.json` enables `cloud`, `guidelines`, `mcp`; disables `nightwatch`, `sail`; lists skills: `infer-conventions`, `deploying-to-cloud`, `laravel-best-practices`, `testing-best-practices`, `tailwindcss-development`.
- `.claude/` contains those skills; `.freebuff/project-id` exists (purpose not determined from the repo).
- `AGENTS.md` and `CLAUDE.md` carry Laravel Boost guidelines for agents (the substitution phase in `AGENTS.md` is still present, i.e. Boost guidelines live in `CLAUDE.md`).

### Cloud / deployment integration

- `boost.json` sets `cloud: true`, and the `deploying-to-cloud` skill is present, but **no Cloud/IaC configuration, Dockerfile, CI workflow, or Procfile exists** in the repository (see `TESTING_AND_DEPLOYMENT.md`).

---

## 13. Configuration gaps and items for further inspection

- **`SCHOOL_*` variables are unset** (not even in `.env.example`), so `config('school.*')` returns empty strings for address/phone/email/logo.
- **DB topology mismatch**: `.env` (MySQL) vs `.env.example`/`phpunit.xml` (SQLite); combined with MySQL-only SQL in code, behavior differs by environment.
- **dompdf is installed but unused** — decide whether it is intended for future PDF output or should be treated as dead weight; currently "PDF" links rely on browser print.
- **`@tailwindcss/vite` v4 installed alongside Tailwind v3** — one of the two is vestigial; the active pipeline is v3 + PostCSS.
- **Configured-but-idle infrastructure**: `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, `BROADCAST_CONNECTION=log`, and `MAIL_MAILER=log` are wired, but no jobs, broadcasts, or real mail are produced/consumed by application code identified so far.
- **`config/services.php` credentials unused** — no code path reads postmark/resend/ses/slack.
- **No `.env.example` entries for `AUTH_*`, `SESSION_SECURE_COOKIE`, `SESSION_SAME_SITE`, `LOG_SLACK_*`, `MAIL_URL`, `MAIL_EHLO_DOMAIN`, `DB_CHARSET`/`DB_COLLATION`** even though they are read by config — acceptable defaults exist, but they are undocumented in the template.
- **`APP_URL` must match the served origin** for the `public` disk URL (`APP_URL/storage`) and for asset URLs to resolve correctly.
- **`timezone` hardcoded to UTC** — confirm whether school-local time is expected anywhere (academic-year logic uses its own helper; see `app/Helpers/SystemHelper.php`).
