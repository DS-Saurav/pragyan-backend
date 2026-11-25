# Blade & Public Website

This document describes the view layer of the application: layouts, Blade components, the design-system CSS, the public (unauthenticated) website, print/PDF-by-print pages, and the view conventions actually used in the code.

Scope note: This file is based only on the Blade files present in `resources/views/` and the frontend configuration files (`tailwind.config.js`, `postcss.config.js`, `vite.config.js`, `resources/css/app.css`, `resources/js/app.js`). Where a view exists but is not returned by any controller, this is called out explicitly. Nothing here is a recommendation or a plan — it reflects the current on-disk state.

---

## 1. Frontend build pipeline

| Item | Value / Path | Notes |
|---|---|---|
| Entry points | `resources/css/app.css`, `resources/js/app.js` | Registered in `vite.config.js` |
| Template directive | `@vite(['resources/css/app.css', 'resources/js/app.js'])` | Used by all layouts and the public pages |
| Tailwind | **v3** via PostCSS | `resources/css/app.css` uses `@tailwind base/components/utilities`; `tailwind.config.js` + `postcss.config.js` present |
| `@tailwindcss/forms` | Enabled as a Tailwind plugin | `tailwind.config.js` line 20 |
| `@tailwindcss/vite` (v4) | Installed in `package.json` but **not used** | No `@import "tailwindcss"` anywhere; the active pipeline is Tailwind v3 |
| JS | Alpine.js only | `resources/js/app.js` imports `alpinejs`, assigns `window.Alpine`, calls `Alpine.start()` |
| Tailwind content paths | framework pagination views, `storage/framework/views/*.php`, `resources/views/**/*.blade.php` | `tailwind.config.js` lines 6–10 |
| Font | Figtree via `fonts.bunny.net` | Linked inline in each layout |
| Icons | Bootstrap Icons 1.11.3 via jsDelivr CDN | `<link>` in each layout/public page |

Implication of the Tailwind version split: only classes reachable from the configured `content` globs are generated. Because the content glob includes all of `resources/views/**/*.blade.php`, dynamically composed class names (for example `'ui-badge ' . $tones[$tone]` in `components/ui/badge.blade.php`) are only safe where the concrete class string also appears verbatim somewhere in scanned views.

### Design system (`resources/css/app.css`)

The stylesheet defines two `@layer`s:

- `@layer base` — CSS variables (`--ui-ink`, `--ui-accent`, `--ui-accent-strong`, `--ui-ring`), body background/text, `::selection`, `:focus-visible` ring, and `[x-cloak] { display:none }`.
- `@layer components` — the `ui-*` utility class family used across admin views:
  - Page: `ui-page`, `ui-page-header`, `ui-page-title`, `ui-page-subtitle`, `ui-section-title`
  - Card: `ui-card`, `ui-card-body`, `ui-card-header`, `ui-card-footer`
  - Buttons: `ui-btn`, `ui-btn-primary`, `ui-btn-secondary`, `ui-btn-danger`, `ui-btn-ghost`, `ui-btn-sm`
  - Forms: `ui-label`, `ui-help`, `ui-input`, `ui-input-error`, `ui-error`
  - Alerts: `ui-alert`, `ui-alert-success/error/warning/info`
  - Badges: `ui-badge`, `ui-badge-neutral/success/warning/danger/info/brand`
  - Tables: `ui-table` (+ `thead`/`th`/`td`/`tbody tr` sub-selectors)
  - Empty/stat: `ui-empty`, `ui-empty-icon`, `ui-empty-title`, `ui-empty-text`, `ui-stat`, `ui-stat-label`, `ui-stat-value`, `ui-stat-icon`
  - Misc: `ui-link`, `ui-divider`, `ui-tabs`, `ui-tab`, `ui-tab-active`

Print support is global: `@media print { .no-print { display:none !important } body { background:#fff !important } }` (lines 237–245). A reduced-motion block disables animations (lines 247–255).

Brand colors are inline arbitrary values in several places (`bg-[#0b1a4a]`), not Tailwind theme tokens.

---

## 2. Layouts

There are three page layouts plus a Breeze component layout.

### 2.1 `resources/views/layouts/admin.blade.php` (390 lines) — primary authenticated layout

Used by 42 views via `@extends('layouts.admin')` (verified list below). It is the main inward-facing shell.

- **Head**: charset/viewport, CSRF meta, `theme-color #0b1a4a`, `@yield('title', 'Dashboard') | {app.name}`, Figtree + Bootstrap Icons, `@vite(...)`, an inline `<style>` block, and `@stack('head')`.
- **Sidebar (`<aside id="sidebar">`)**: dark gradient, brand block, avatar, role chips, scrollable nav, dark-mode toggle, logout form. The nav is defined inline as a PHP `$menu` array grouped into sections (Overview, Records, Library, Daily, Academic, Finance, Support, Administration, Account) and filtered at render time by:
  - `'staff' => true` items hidden unless `$isStaff` (`! $user->hasRole('student', 'parent')`);
  - items with `'permission'` hidden unless `$user->hasPermission(...)`;
  - routes resolved defensively with `Route::has($item['route']) ? route(...) : '#'`;
  - active state via `request()->routeIs($item['pattern'])`;
  - optional sidebar count badges via `$sidebarBadges ?? []`.
- **Header bar**: mobile menu button, `@yield('title')`, school name, academic-year pill (`current_academic_year()`), welcome text, refresh button.
- **Main**: `<x-ui.flash />` then `@yield('content')`.
- **Footer**: copyright, "Developed by Saurav Acharya", link to `admin.about`.
- **Inline JS (vanilla, lines 308–385)**: sidebar open/close with backdrop, `localStorage`-persisted collapse state, Escape-to-close, `matchMedia` handling, and a "quick dark mode" that toggles `html.dark` and applies an `invert(1) hue-rotate(180deg)` filter to `#mainContent` only (not a true themed dark mode). Also auto-scrolls the active nav item into view.
- **Stacks**: `@stack('head')`, `@stack('scripts')`.
- Sidebar and header are `print:hidden`.

Note: the header title is a second `@yield('title')`; views without `@section('title')` therefore show the literal default `Dashboard` in both the document title and the header.

**Views extending `layouts.admin` (42):** `dashboard.blade.php`, `substitutes/index.blade.php`, `profile/edit.blade.php`, and under `admin/`: `about`, `activities/index`, `admissions/index`, `assets/index`, `attendance/index`, `calendar/index`, `classes/index`, `complaints/index`, `complaints/show`, `conduct/index`, `discipline/index`, `documents/index`, `exams/index`, `exams/setup`, `fee-structure/index`, `fees/index`, `hall-tickets/index`, `helpdesk/index`, `helpdesk/show`, `inventory/index`, `lesson-plans/index`, `library/index`, `library/circulation`, `marks/index`, `notices/index`, `parents/index`, `payroll/index`, `payroll/staff`, `ptm/index`, `results/index`, `settings/index`, `students/index`, `students/edit`, `subjects/index`, `teachers/index`, `teaching-logs/index`, `timetable/index`, `users/index`.

### 2.2 `resources/views/layouts/guest.blade.php` (81 lines) — authentication shell

Two-column split: a marketing panel (hidden below `lg`) and a form panel with the `{{ $slot }}` inside a card. Links to `home`, `public.notices`, `public.team`. Used by the six Breeze auth pages through `<x-guest-layout>`:

`auth/login`, `auth/register`, `auth/forgot-password`, `auth/reset-password`, `auth/confirm-password`, `auth/verify-email`.

### 2.3 `resources/views/layouts/app.blade.php` (27 lines) — legacy Breeze `<x-app-layout>`

Minimal Breeze shell (optional `$header` slot, `{{ $slot }}`, gray-100 background). Its **only consumer** is `resources/views/admin/dashboard.blade.php` (§5), which no route returns. Otherwise effectively unused.

### 2.4 Breeze component layout

`<x-app-layout>` and `<x-guest-layout>` are resolved by Breeze's anonymous component convention: `resources/views/components/` contains `layouts` indirectly via the class/package — the concrete files are `layouts/app.blade.php` and `layouts/guest.blade.php` referenced by name. No dedicated `components/layouts/*.blade.php` files exist; Breeze resolves these names.

---

## 3. Blade components

### 3.1 Breeze components (`resources/views/components/`)

`application-logo`, `auth-session-status`, `danger-button`, `dropdown`, `dropdown-link`, `input-error`, `input-label`, `modal`, `nav-link`, `primary-button`, `responsive-nav-link`, `secondary-button`, `text-input`.

These are the stock Breeze set. Several have been superseded in the admin UI by the `ui-*` CSS classes and `x-ui.*` components, but remain resolvable and are still referenced by profile partials and some auth pages.

### 3.2 Custom UI components (`resources/views/components/ui/`)

| Component | Path | Props | Renders |
|---|---|---|---|
| `x-ui.badge` | `ui/badge.blade.php` | `tone` (`neutral`/`success`/`warning`/`danger`/`info`/`brand`, default `neutral`) | `ui-badge ui-badge-{tone}` span |
| `x-ui.empty-state` | `ui/empty-state.blade.php` | `icon` (default `bi-inbox`), `title`, `message`, `$actions` slot | `ui-empty` block |
| `x-ui.flash` | `ui/flash.blade.php` | — (reads session) | success/error/warning/status alerts + `$errors->any()` list |
| `x-ui.page-header` | `ui/page-header.blade.php` | `title`, `subtitle`, `$actions` slot | `ui-page-header` |
| `x-ui.stat-card` | `ui/stat-card.blade.php` | `label`, `value`, `icon` (default `bi-graph-up`), `tone` (default `emerald`) | `ui-stat` card |

`x-ui.flash` is invoked once per admin page from `layouts/admin.blade.php:291`; it centralizes session flash and validation-error display (see `VALIDATION_AND_ERROR_HANDLING.md`).

### 3.3 Partials

- `profile/partials/update-profile-information-form.blade.php`, `update-password-form.blade.php`, `delete-user-form.blade.php`
- `admin/payroll/_pay-fields.blade.php` (included by payroll views)
- `admin/hall-tickets/_ticket.blade.php` (repeatable ticket block)

---

## 4. Public website (unauthenticated)

The public site consists of **three standalone pages**. None of them `@extends` a layout and there is **no shared public layout**; the header/nav/footer markup is duplicated in each file.

| Page | View | Route (name) | Data source | Notes |
|---|---|---|---|---|
| Home / landing | `resources/views/welcome.blade.php` (221 lines) | likely `home` → `route('home')` referenced by layouts | Static copy | Full HTML doc; sticky header; notices/team nav; auth-aware CTA (Dashboard vs Log in/Register). Feature sections describe admissions/students/attendance/exams/finance/communication. |
| Notice Board | `resources/views/frontend/notices.blade.php` (110 lines) | `public.notices` | Controller-provided notices | Full HTML doc; header duplicates welcome's; lists published announcements. |
| Our Team | `resources/views/frontend/team.blade.php` (437 lines) | `public.team` | `$groups`, `$total`; ordered via `\App\Models\StaffMember::GROUPS` | **Does not use Vite/Tailwind.** It ships its own large inline `<style>` block and reads `StaffMember::GROUPS` directly in a `@php` block. Uses `@php $order = \App\Models\StaffMember::GROUPS; $total = collect($groups)->flatten(1)->count();`. |

Observations about the public site:

- Duplication: `welcome.blade.php` and `frontend/notices.blade.php` each define the same sticky header/brand/nav/CTA markup independently. `frontend/team.blade.php` has a third, CSS-inline presentation.
- Inconsistent presentation stack: two pages use Tailwind `ui-*` classes; `team.blade.php` is hand-written CSS with no Tailwind/Vite.
- The public pages expose links to `public.notices` and `public.team` and auth links `login`/`register`, all via `Route::has(...)` guards on `welcome.blade.php` (lines 47–52).
- Branding (`config('app.name')`, `#0b1a4a`, Figtree, Bootstrap Icons) is hardcoded per page rather than centralized.

Whether the public site is "complete" cannot be determined from views alone; the mapping of route → view and any controller data is documented in `ROUTES_AND_ENDPOINTS.md` and `BLADE_AND_PUBLIC_WEBSITE.md` §7.

---

## 5. Print / "PDF" pages

The application has **no server-side PDF rendering** in the view layer. `barryvdh/laravel-dompdf` is installed but no controller or view calls the `PDF` facade or `->download()`/`->stream()`. Instead, the following pages are full standalone HTML documents (own `<!DOCTYPE html>`) intended to be printed via the browser (`window.print()`), and named/described as PDF or print output:

| View | Purpose | Print handling |
|---|---|---|
| `admin/parents/pdf.blade.php` | Parents list export | Standalone doc; `@media print` |
| `admin/fees/receipt.blade.php` | Fee receipt | Standalone doc |
| `admin/payroll/slip.blade.php` | Salary slip | Standalone doc |
| `admin/results/marksheet.blade.php` | Student marksheet | Standalone doc |
| `admin/results/ledger.blade.php` | Results ledger | Standalone doc |
| `admin/accounts/book.blade.php` | Day/cash book | Standalone doc; toolbar Print button |
| `admin/discipline/notice.blade.php` | Discipline notice | Standalone doc |

Separately, many normal admin pages add `@media print` + `.no-print` regions inside the admin layout and provide "Print / Save as PDF" buttons calling `window.print()`, e.g. `admin/accounts/index`, `admin/complaints/index`, `admin/helpdesk/index`, `admin/users/index`, `admin/marks/index`, `admin/lesson-plans/index`, `admin/calendar/index`, `admin/hall-tickets/index`, `admin/assets/index`, `admin/notices/index`, `admin/ptm/index`.

The admin layout already hides the sidebar/header/footer under print via `print:hidden`; the print pages above additionally rely on `.no-print` from `app.css`.

---

## 6. Conventions actually used in views

- **Escaping**: standard Blade `{{ }}` interpolation; no `{!! !!}` observed in the files reviewed for this document.
- **Titles**: `@section('title', ...)` is present in only 14 views (`dashboard`, `substitutes/index`, `profile/edit`, `admin/about`, `admin/activities/index`, `admin/admissions/index`, `admin/assets/index`, `admin/attendance/index`, `admin/calendar/index`, `admin/hall-tickets/index`, `admin/conduct/index`, `admin/library/index`, `admin/settings/index`, `admin/library/circulation`). Most admin views omit it and therefore inherit the default `Dashboard` label in both the `<title>` and the in-page header.
- **Stacks**: `@stack('head')` and `@stack('scripts')` are provided by `layouts/admin`; views may push page-specific assets/markup there.
- **Modals**: implemented as inline `fixed inset-0 z-50 ... no-print` blocks (e.g. `users/index`, `accounts/index`, `complaints/index`, `helpdesk/index`, `lesson-plans/index`) rather than the Breeze `x-modal` component in most admin screens.
- **Alerts**: most admin views still render ad-hoc `session('success')`/`session('error')` blocks inline **in addition to** the centralized `x-ui.flash`; migration to the component is partial.
- **Styling**: mixes `ui-*` component classes, raw Tailwind utilities, and arbitrary brand colors; a few older views (e.g. `admin/marks/index`) still use gray-scale Breeze classes (`bg-gray-100`, `text-gray-500`).
- **Inline PHP**: several views embed `@php` blocks to build menus, maps, or fetch model constants (`layouts/admin`, `frontend/team`).

---

## 7. View inventory by area

Counts derived from `resources/views/**/*.blade.php` (91 files).

- **Layouts (3):** `layouts/admin`, `layouts/app`, `layouts/guest`.
- **Components (18):** 13 Breeze (`components/*`) + 5 custom (`components/ui/*`).
- **Auth (6):** login, register, forgot/reset/confirm-password, verify-email.
- **Profile (4):** `profile/edit` + 3 partials.
- **Public (3):** `welcome`, `frontend/notices`, `frontend/team`.
- **Admin (≈45):** index/setup/show/circulation/partial views per module (about, activities, accounts, admissions, assets, attendance, calendar, classes, complaints, conduct, discipline, documents, exams, fee-structure, fees, hall-tickets, helpdesk, inventory, lesson-plans, library, marks, notices, parents, payroll, ptm, results, settings, students, subjects, teachers, teaching-logs, timetable, users).
- **Top-level:** `dashboard.blade.php` (role-aware dashboard via `layouts.admin`).
- **Outside `admin/`:** `substitutes/index.blade.php` and `profile/edit.blade.php` extend `layouts.admin` but live outside the `admin/` folder.

---

## 8. Orphaned / unreachable views

These Blade files exist on disk but are **not returned by any controller** (verified against controller `view(...)` calls):

| View | Evidence | Notes |
|---|---|---|
| `resources/views/admin/dashboard.blade.php` | Uses `<x-app-layout>`; dashboard is rendered by the top-level `dashboard.blade.php` | Legacy Breeze admin dashboard; references `admin.classes.index`. |
| `resources/views/teacher/dashboard.blade.php` | File is **empty (0 bytes)** | No controller returns `teacher.dashboard`. |
| `resources/views/student/dashboard.blade.php` | File is **empty (0 bytes)** | No controller returns `student.dashboard`. |

The role-aware dashboard is instead implemented by `resources/views/dashboard.blade.php`, which uses `$isCommunity` to split the "community" (student/parent) experience from the staff experience (section begins at line 18). The three files above are leftovers from an earlier per-role dashboard approach.

---

## 9. External assets & dependencies at render time

- `fonts.bunny.net` (Figtree) — every layout and public page.
- `cdn.jsdelivr.net` Bootstrap Icons 1.11.3 — every layout and public page **except** `frontend/team.blade.php`.
- `@vite(...)` dev/build manifest — layouts, auth, public pages (`welcome`, `frontend/notices`), and `frontend/team` **not** included.

Because `frontend/team.blade.php` bypasses Vite and `app.css`, it does not receive the global print/reduced-motion rules or the `ui-*` components; it is fully self-contained.

---

## 10. Gaps and items needing further inspection

- **No shared public layout**: three public pages duplicate markup and diverge in styling approach. Whether this is intentional is unknown.
- **Missing `@section('title')`** across many admin views → all show `Dashboard` in browser tab and header.
- **Orphaned/empty views** (`admin/dashboard`, empty `teacher/dashboard`, empty `student/dashboard`) still present.
- **`frontend/team.blade.php`** performs model lookups (`\App\Models\StaffMember::GROUPS`) directly in the view; confirm the controller-provided `$groups` shape and that this page is linked only from public/auth surfaces.
- **dompdf unused**: the "PDF" pages are browser-print HTML; confirm intended behavior (see `CONFIGURATION_AND_INTEGRATIONS.md`).
- **Print CSS duplication**: several views re-declare their own `@media print`/`.no-print` rules in addition to the global ones in `app.css`.
- **Alpine vs inline vanilla JS**: `app.js` starts Alpine, but the admin layout's interactive behavior is hand-written vanilla JS; no Alpine directives were observed in the layouts reviewed. Confirm whether Alpine is relied upon anywhere.
- **Encoding**: a few source files displayed non-ASCII punctuation (em/en dashes) when read through the console; no functional issue identified, but worth confirming file encodings are consistently UTF-8 before any bulk edit.
