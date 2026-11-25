# PROJECT_STRUCTURE

> Evidence basis: full recursive listing of the repository (excluding `vendor/`, `node_modules/`,
> `public/build/`). Paths are relative to the application root.

## 1. Top level

```
school-app/
├── AGENTS.md                 # Boost/agent guidelines (Laravel setup instructions)
├── CLAUDE.md                 # Project conventions note (mentions PHP 8.4)
├── .mcp.json                 # Laravel Boost MCP server config (php artisan boost:mcp)
├── boost.json                # Boost config: agents, skills, cloud, guidelines, mcp
├── composer.json / composer.lock
├── package.json / package-lock.json
├── vite.config.js / tailwind.config.js / postcss.config.js
├── phpunit.xml
├── .env / .env.example / .gitignore / .editorconfig
├── .freebuff/project-id      # external tooling marker
├── app/                      # application code (PHP)
├── bootstrap/                # framework bootstrap (app.php, providers.php)
├── config/                   # Laravel config files
├── database/                 # migrations, seeders, factories, database.sqlite
├── docs/                     # THIS documentation (newly created)
├── public/                   # web root (index.php, build/, storage symlink, hot)
├── resources/                # Blade views, CSS, JS
├── routes/                   # web.php, auth.php, console.php
├── storage/                  # logs, framework cache, app uploads
├── tests/                    # Pest/PHPUnit tests
└── .claude/skills/           # Claude Code skills shipped with the repo
```

Notable absence: there is **no `routes/api.php`** and **no `app/Policies/`, `app/Jobs/`,
`app/Events/`, `app/Listeners/`, `app/Notifications/`, `app/Mail/`, `app/Console/Commands/`,
`app/Http/Requests` (except `Auth/` and `ProfileUpdateRequest.php`), `app/Http/Resources/`,
`app/Http/Middleware/Authenticate.php`, or `lang/` directory.**

## 2. `app/` detailed structure

```
app/
├── Helpers/
│   └── SystemHelper.php                 # current_academic_year() (Bikram Sambat), autoloaded
├── Http/
│   ├── Controllers/
│   │   ├── Controller.php               # abstract base (empty)
│   │   ├── DashboardController.php      # role-aware dashboard
│   │   ├── ProfileController.php        # Breeze profile edit/update/delete
│   │   ├── TeamController.php           # public /team
│   │   ├── Auth/                        # 9 Breeze auth controllers
│   │   └── Admin/                       # 36 admin controllers (see CONTROLLERS_AND_SERVICES.md)
│   ├── Middleware/
│   │   ├── EnsureUserIsActive.php       # 'active' alias
│   │   └── EnsureUserIsStaff.php        # 'staff' alias
│   └── Requests/
│       ├── Auth/LoginRequest.php
│       └── ProfileUpdateRequest.php
├── Models/
│   ├── Concerns/HasSchoolRoles.php      # role/permission trait
│   ├── User.php
│   ├── UserRole.php
│   └── ... (42 more models; see MODELS_AND_DATABASE.md)
├── Providers/
│   └── AppServiceProvider.php           # Gates, Login listener
├── Services/
│   ├── GradeScale.php                   # letter-grade bands
│   └── ResultService.php                # per-subject & per-class result computation
├── Support/
│   ├── AcademicYear.php                 # year options / periods
│   ├── Money.php                        # payment modes + amount in words
│   └── Photo.php                        # store/delete images on public disk
└── View/Components/
    ├── AppLayout.php                    # -> layouts.app
    └── GuestLayout.php                  # -> layouts.guest
```

### `Admin/` controllers (36 files)

`About, Account, Activity, Admission, Asset, Attendance, Complaint, Conduct, Discipline, Document,
Exam, ExamSetup, Fee, FeeStructure, Hallticketcontroller (class HallTicketController), Helpdesk,
Inventory, LessonPlan, Library, MarksEntry, Notice, Parent, Payroll, Ptm, Result, SchoolClass,
SchoolEvent, Setting, Staff, Student, Subject, Substitute, Teacher, TeachingLog, Timetable, User`.

## 3. `config/` files

Standard Laravel config plus two **project-specific** files:

- `config/permissions.php` — role labels, permission catalogue, and role→permission map (RBAC source).
- `config/school.php` — school address/phone/email/logo (`SCHOOL_*` env vars) and hall-ticket instructions.

Other configs (`app, auth, cache, database, filesystems, logging, mail, queue, services, session`)
are effectively framework defaults. They are catalogued in `CONFIGURATION_AND_INTEGRATIONS.md`.

## 4. `database/` structure

```
database/
├── database.sqlite                      # local SQLite file (used by tests/.env.example)
├── factories/
│   └── UserFactory.php                  # only factory in the project
├── migrations/                          # 47 migrations (see MODELS_AND_DATABASE.md)
└── seeders/
    ├── DatabaseSeeder.php               # classes + 3 users + (broken) 1 student
    ├── FeeHeadSeeder.php                # NOT called by DatabaseSeeder
    └── SalaryComponentSeeder.php        # NOT called by DatabaseSeeder
```

## 5. `resources/` structure

```
resources/
├── css/app.css                          # Tailwind entry (v3 directives)
├── js/app.js                            # Alpine.js bootstrap
└── views/                               # 91 Blade files
    ├── welcome.blade.php                # public home (custom)
    ├── dashboard.blade.php              # authenticated dashboard (custom)
    ├── admin/                           # ~50 module views
    │   ├── dashboard.blade.php          # ORPHANED (not returned by any controller)
    │   ├── about.blade.php, settings/index.blade.php
    │   └── accounts/ activities/ admissions/ assets/ attendance/ calendar/ classes/
    │       complaints/ conduct/ discipline/ documents/ exams/ fee-structure/ fees/
    │       hall-tickets/ helpdesk/ inventory/ lesson-plans/ library/ marks/ notices/
    │       parents/ payroll/ ptm/ results/ students/ subjects/ teachers/ teaching-logs/
    │       timetable/ users/
    ├── auth/                            # 6 Breeze auth views
    ├── components/                      # Breeze components + components/ui/* (badge, empty-state,
    │                                    #   flash, page-header, stat-card)
    ├── frontend/                        # notices.blade.php, team.blade.php (public)
    ├── layouts/                         # admin.blade.php, app.blade.php, guest.blade.php
    ├── profile/                         # edit + partials
    ├── student/dashboard.blade.php      # ORPHANED (empty)
    ├── teacher/dashboard.blade.php      # ORPHANED (empty)
    └── substitutes/index.blade.php      # NOTE: outside admin/ (controller returns 'substitutes.index')
```

## 6. `routes/` structure

- `routes/web.php` — home/notices/team public routes; authenticated group wrapping all
  `admin/*`, dashboard and profile routes; ends with `require auth.php`.
- `routes/auth.php` — Breeze auth routes (login/register/logout/password reset/email verify).
- `routes/console.php` — only the default `inspire` command registration.

## 7. `tests/` structure

```
tests/
├── TestCase.php                         # base (CreatesApplication)
├── Feature/
│   ├── ExampleTest.php
│   ├── ProfileTest.php
│   ├── UiSmokeTest.php
│   └── Auth/ (AuthenticationTest, EmailVerificationTest, PasswordConfirmationTest,
│              PasswordResetTest, PasswordUpdateTest, RegistrationTest)
└── Unit/
    └── Helpers/SystemHelperTest.php      # only unit test
```

Details in `TESTING_AND_DEPLOYMENT.md`.

## 8. `public/` and `storage/`

- `public/index.php` (front controller), `public/.htaccess`, `public/robots.txt`,
  `public/favicon.ico`, `public/hot` (Vite dev marker), `public/build/` (compiled assets),
  `public/storage` (symlink present → `storage/app/public`).
- `storage/app` (uploads: `public/` and private `local/`), `storage/framework` (cache/sessions/views),
  `storage/logs`.

## 9. Agent / tooling files

- `.claude/skills/` contains shipped skills: `deploying-to-cloud`, `infer-conventions`,
  `laravel-best-practices`, `tailwindcss-development`, `testing-best-practices`.
- `boost.json` enables Laravel Boost with `claude_code` agent and the same five skills.
- `.freebuff/project-id` is a marker for external tooling.
- There is **no `.ai/rules/` directory** present.
