# Public Website Content Specification (Draft)

**Status:** Draft — Stage D1 documentation output; specification only, no implementation. Requirements below are **proposed, not agreed** and are not ratification of anything.

**Companion documents:** architecture decision in `docs/architecture/ADR-001-hybrid-public-media-management.md`; requirements summary rows FR-091–FR-098 in `docs/PRD.md` §F.11; roadmap in `docs/IMPLEMENTATION_PLAN.md`; permissions catalog in `config/permissions.php`.

**Owner direction already recorded:** a dynamic school marketing/communications platform with backend-managed images, rotating hero, academic and extracurricular information, news, future student blogging/magazine, categorized galleries, contact/team information, and delegable publishing/editing authority (M-04, 2026-10-26). Delegated, administrator-controlled permissions by feature/module (M-05).

---

## 1. Scope of this document

This specification defines content-management requirements for **19 public page areas** plus the shared capabilities they rely on. It deliberately uses scope control:

- **Sections 2–3** define common page, media, publishing, and permission capabilities **once**.
- **Section 5** (the matrix) records only what differs per page — purpose, unique editable content, photography/album needs, and unique acceptance criteria. Rows may deviate from the common defaults by exception only.
- **Section 6** records the verified current route/navigation state and the required route-split/redirect work.
- **Section 7** records open owner decisions (D1-O1…O10) as proposals.

Nothing here is implemented; the selected architecture is recorded, not built.

## 2. Verified current building blocks (as inspected D1)

These facts anchor every matrix row's "current status" claims. They were verified by route/view/model inspection during D1, not from memory of prior reports:

| Building block | Verified state |
|---|---|
| Public pages exist for | home `/`, about `/about`, team `/team`, admissions `/admissions`, academics `/academics`, news `/news`, gallery `/gallery`, contact `/contact`, calendar `/calendar`, notices `/notices` — all through the shared `layouts.public` layout. |
| Homepage hero | JSON array `website_hero_images` in the `settings` table; managed (view/upload/reorder/remove) via `/settings/website` limited to `settings.view` / `settings.manage`. **No centralized library exists.** |
| Staff portraits & visibility | `staff_members` carries `photo`, `qualification`, `bio`, `show_on_website`, `team_group`, `display_order` (migration `2026_10_19_140405`). Managed under `users.manage`; public team page reads via a `scopeWebsite`-like filter (`show_on_website` + active status). |
| Student photos | `students.photo` exists and is read by hall-ticket workflows; **no upload form** exists in the inspected student workflow. Private record — never exposed publicly. |
| Events | `SchoolEvent.is_public` is the sole publication control; the public `/calendar` route filters `where('is_public', true)`. No image column, no album relation. |
| Activities | `SchoolActivity` is an internal ECA module (`activities.view/manage`, inside the authenticated staff area). **No public flag, no public page.** |
| News / Gallery pages | Both are **hard-coded placeholder pages** (`frontend.news`, `frontend.gallery`) — no models, no controllers. Gallery image requirement sits in FR-084 (preserved; unimplemented). |
| Uploads | Only two photo surfaces exist (hero via `settings.manage`, staff portraits via `users.manage`); UI advertises 8 MB/image but local PHP accepts only 2 MB per file (`upload_max_filesize = 2M`, `post_max_size = 8M`) — see `TESTING_AND_DEPLOYMENT.md` for current-vs-target. |
| Publications | No publication model, no public route. |

## 3. Common capabilities (defined once; matrix rows inherit them)

### 3.1 Common page/CMS capabilities

Every page area in Section 5, unless its row says otherwise, is expected to be:

1. **Independently addressable** — a real URL reachable by direct link, bookmark, and navigation, served through the shared public layout.
2. **Independently managed** — its page record, sections, and content are editable in the admin without touching sibling pages, and are *draft-able* (scheduled editing, save without publishing).
3. **Published or draft** — a draft page renders only its pre-existing public version (or 404 if first version), per the common publishing rules in 3.4.
4. **Reusable-media capable** — any image it displays comes from the central Media Library and carries contextual per-use metadata (3.2). No re-upload to place the same image twice on the site.
5. **Up-to-date navigation** — appears/disappears from the public nav according to its lifecycle state (nav membership itself reviewed at CMS work, per 3.4).
6. **Consistently branded** — shared public layout `layouts.public`, official **blue/yellow/white** palette; no forest-green or other palette drift.
7. **Audit-logged** — page edits and publishes are attributable to an acting user (framework requirement; mechanism at D3 design).

### 3.2 Common media capabilities (from ADR-001 Option C)

1. **Central Media Library** (`media_assets` concept): single and multiple image upload, previews and selection, search/filter, categories and descriptive metadata, captions plus default alt text, dimensions/size/verified MIME, cover selection where relevant, reordering within a page or album, draft/unpublished assets never publicly visible.
2. **Attachment-level metadata** (`media_attachments` concept): every *use* of an asset carries its own contextual caption, alt text, ordering, and (when approved, D1-O6) crop/focal data. Contextual values win over asset defaults.
3. **Usage tracking**: each asset lists every known use; **referenced assets cannot be deleted** — accidental deletion is blocked with a visible reference list. Replacement swaps the file behind the asset and keeps references working.
4. **Draft assets** hidden from public routes; published assets safe to serve.
5. **No private-record bleed**: student portraits/personal data, private user-account photos, confidential staff info, payroll/HR docs, complaints attachments, internal school documents, and admission documents stay in their existing models/permission paths and never become Media Library assets.
6. **Library access ≠ page publish access** (permissions, 3.3).
7. **Upload reliability**: type/MIME validation + safe decoding; random storage filenames; old file retained until replacement persists; no orphan files on partial failure; clear field-specific validation errors; runtime limits consistent with the 8 MB-per-image target (current `upload_max_filesize = 2M` is an open environment deficit, to be fixed in D2 — no change made in D1).

### 3.3 Common permissions concepts (proposals; realized in D3 — nothing implemented in D1)

The catalog currently has no dedicated website/media/news permissions. Existing surfaces use `settings.manage`, `users.manage`, `calendar.manage` and `admissions.manage`; Notices currently lacks a dedicated permission. The following **capability names are proposals**, for D2/D3 design after D1.5 hardening; `notices.manage` is proposed for D1.5. Existing keys are identified below, not recreated:

| Proposed capability | Meaning |
|---|---|
| `website.view` | See the website-content admin area (page list, library browse) without edit rights. |
| `website.manage` | Draft-edit page and section content of pages assigned to the user (auto-excludes private-record modules). |
| `website.publish` | Conditional proposal only if D1-O7 requires separate publishing authority; D1-O3 determines the workflow. |
| `media.view` | Browse the Media Library. |
| `media.manage` | Upload, categorize, caption, reorder, archive, delete (only unreferenced) assets. Does **not** grant page publishing. |
| `gallery.manage` | Manage albums and photographs (independent of pages). |
| `notices.manage` | Proposed D1.5 permission for existing Notice create/delete operations; role assignments require inspection. |
| `news.manage` | Future News CMS editorial articles; distinct from operational Notices. |
| `events.manage` | Manage public calendar events and their photography (distinct from the existing `calendar.manage` for the internal calendar module — mapping to be decided in D3). |
| `admissions.manage` | Existing application-review permission. Do not broaden it to website editors; public admissions page text uses `website.manage` (and conditional `website.publish`). Any reuse needs an explicit D3 scope/role decision. |
| `publications.manage` | Manage public publications/downloads. |

**Naming basis (D1.1 source inspection):** use `resource.action`, matching `settings.manage`, `payroll.manage` and `results.publish`. `HasSchoolRoles::hasPermission` checks exact keys (or admin `*`); it has no hierarchical sub-permission inheritance. If D1-O7 requires separate publishing, compatible proposals are `website.publish`, `news.publish`, `gallery.publish`, `events.publish` and `publications.publish`; they are not approved or implemented. A manage grant must not implicitly authorize publishing under that design.

Rules the D3 design must honor (M-05 direction): administrators grant/revoke by module and feature; editors see and manage only assigned content (e.g. a news editor cannot reach payroll; a gallery editor cannot reach student records); **media access does not imply publish rights on any page**; page edit ≠ publish; private records (students/staff/HR/admissions) keep their existing isolation regardless of website permissions.

### 3.4 Common publishing & visibility rules

1. Content has a **publish state**: draft or published (a scheduled state, D1-O4, is optional). Public routes show published only; drafts are invisible publicly.
2. Separate editing/publishing authorization is a proposal pending D1-O7; compatible manage/publish keys are described in §3.3. One person may hold both if approved.
3. Existing patterns to reuse where decisions allow: `SchoolEvent.is_public` for event publication; hero/settings publication via the existing `settings.website` routes until migrated.
4. Publication history/timestamps retained for audit; last-published output never breaks silently — replacement follows the asset-replacement rules in 3.2.3.

## 4. Route split / redirect requirement (the `/academics` problem)

**Verified current state (D1):** one route serves every academics-family nav item. Current public routes (actual, from `routes/web.php` and `resources/views/components/public/nav.blade.php`):

| Route name | URL | Serves |
|---|---|---|
| `home` | `/` | Homepage |
| `public.about` | `/about` | "History & Vision" AND (mislabeled) "Facilities" nav items |
| `public.team` | `/team` | "Principal's Message" AND "Team" nav items |
| `public.academics` | `/academics` | **Primary, Secondary, Curriculum, Results, and School-Life "Activities"** — five distinct destinations, one page |
| `public.admissions` | `/admissions` | Admissions landing, "Process", "Fees", "FAQ" (documented Phase-1 placeholder destinations) |
| `public.news` | `/news` | News (placeholder) |
| `public.gallery` | `/gallery` | Gallery (placeholder) |
| `public.contact` | `/contact` | Contact |
| `public.calendar` | `/calendar` | Public calendar (`SchoolEvent.is_public`) |
| `public.notices` | `/notices` | Public notices |

**Required beyond Phase 1 (all pending D3/D4 work; nothing implemented now):**

1. **Independent pages:** Primary (`/academics/primary` proposed), Secondary (`/academics/secondary` proposed), each independently manageable and independently addressable.
2. **Curriculum:** a separate destination if retained in navigation (**owner decision tied to D1-O3/D1-O8 outcomes**; may be `/ Academics` sub-page or a section).
3. **Results:** dedicated public page **or** reuse of the existing result-access workflow — open owner decision (D1-O9).
4. **Navigation and internal-link update:** every nav item points to its real destination; previously duplicated nav labels for About/Team (Facilities, Principal's Message) may also need their own targets or merged treatment (recorded here as a related, smaller discrepancy).
5. **Redirects / legacy compat:** the old `/academics` URL must continue to resolve — via direct route (if it becomes the Curriculum/overview page) or a 301 redirect to the new Primary/Secondary overview — so existing bookmarks never break.
6. **Canonical URLs & SEO continuity:** each split page declares one canonical URL; implemented via the new page routing in D3/D4; no URL thrash after launch.
7. **No broken links during transition:** redirect table and nav parser tested against each other before the old page is retired.

## 5. The 19-page matrix

Columns: **Purpose · Unique editable content · Media/album needs · Permission(s) · Existing route & current status · Unique DCs**. Everything else defaults to §3. "Status" = verified D1 status, not a promise.

> Abbreviations — DC: distinguishing acceptance criterion, only listed where the page has behavior unique enough to merit one. PD: page requires an owner decision before final content can be specified.

### 5.1 Primary Education

- **Purpose:** Primary program overview for prospective parents.
- **Unique content:** program strands/subjects, class structure for lower grades, age/eligibility, teaching philosophy.
- **Media:** cover + inline classroom photographs from the Library; cover select + per-use captions per §3.2.
- **Perms:** `website.manage` / conditional `website.publish`.
- **Current status:** `/academics` nav item "Primary" resolves to the shared academics page (§4) — no dedicated page.
- **DCs:** independently addressable URL; old nav path keeps working during the route split (§4.5).

### 5.2 Secondary Education

- **Purpose:** Secondary program overview.
- **Unique content:** program strands, elective options, exam board(s) relevant to secondary, lab-linked subjects.
- **Media:** cover + classroom/lab photos, per §3.2.
- **Perms:** same as §5.1.
- **Current status:** `/academics` item "Secondary" — no dedicated page.
- **DCs:** independently addressable URL; when the split lands, the old `/academics` Secondary nav must no longer land on a merged page (it should visibly route to Secondary content, not silently degrade).

### 5.3 Science and Laboratories

- **Purpose:** Showcase lab capacity and practical learning.
- **Unique content:** lab inventory/instrument lists, safety notes, science-club/portfolio highlights.
- **Media:** laboratory photographs as reusable assets; same photo may also sit inside a Gallery album (contextual captions differ).
- **Perms:** `website.manage` / conditional `website.publish`.
- **Current status:** no page; no dedicated content record.
- **DCs:** a laboratory photograph used here **and** in a Gallery album carries a different caption in each place without duplication of the file (illustrates §3.2.2/§3.2.3).

### 5.4 Computer Education

- **Purpose:** Computer learning, ICT curriculum, computing facilities.
- **Unique content:** equipment/software list, computing curriculum, ICT achievements.
- **Media:** photographs of computer labs/activities.
- **Perms:** `website.manage` / conditional `website.publish`.
- **Current status:** none.
- **DCs:** — (no unique behavior beyond common capability).

### 5.5 Robotics

- **Purpose:** Robotics/STEM program showcase.
- **Unique content:** robotics activities, competitions, equipment.
- **Media:** robotics and STEM activity photographs, per §3.2.
- **Perms:** `website.manage` / conditional `website.publish`.
- **Current status:** none. Photography approach agrees with M-04 direction for extracurricular photography.
- **DCs:** — (none beyond common capability).

### 5.6 Library

- **Purpose:** Public-facing introduction to the school library (collections, ethos, hours) — **distinct from the internal `admin.library` administration module** (`can:library.manage`).
- **Unique content:** collection highlights, borrowing info for visitors, reading-culture activities.
- **Media:** library space and reading-activity photographs.
- **Perms:** `website.manage` / conditional `website.publish`.
- **Current status:** no public page at all (the admin library module is unrelated and unaffected).
- **DCs:** book **cover images** integration with the Media Library or kept separate — open decision D1-O1.
- **PD:** related to D1-O1.

### 5.7 Principal and Leadership

- **Purpose:** Principal's message and leadership profiles.
- **Unique content:** Principal's statement, leadership bios. Portraits stay `StaffMember`-associated (§2/ADR-001 §2.5), surfaced by existing website-visibility.
- **Current status:** nav "Principal's Message" points at `public.team` (merged with Team) — mislabeled and merged.
- **Media:** staff portraits (private-record path, not the Library), per owner decision D1-O2.
- **Perms:** `website.manage` / conditional `website.publish` for the page text; staff portraits remain under `users.manage`.
- **DCs:** the public team/leadership rendering must respect `show_on_website` filtering and never leak private `users.photo` account images.

### 5.8 Teachers and Staff

- **Purpose:** Team listing with profiles/photographs.
- **Unique content:** none beyond existing team page behavior + profile structure.
- **Current status:** exists as `/team` (FR-077, layout verified); shares its destination with "Principal's Message" (§5.7 discrepancy); needs to split.
- **Media:** existing staff portraits only (D1-O2).
- **Perms:** page content under website-page permissions; portraits under `users.manage`.
- **DCs:** portraits remain staff-record-bound; the StaffMember editor is NOT treated as a general photo gallery (ADR-001 §2.5).

### 5.9 Teams and Departments

- **Purpose:** Structured department/house/committee profiles with photos.
- **Unique content:** multiple department profiles, structured roster display.
- **Media:** per-department photos; grouping by department.
- **Perms:** `website.manage` / conditional `website.publish` (+ optionally group-scoped assignment).
- **Current status:** none; existing `staff_members.team_group` provides a basis.
- **DCs:** displayed team membership derives from existing `team_group` values so it does not fork from staff-record truth.

### 5.10 Clubs and Extracurricular Activities

- **Purpose:** Public presentation of individual clubs/ECA groups.
- **Unique content:** per-club descriptions, in-charges, meeting summaries/photo essays.
- **Media:** club activity photos.
- **Perms:** `website.manage` / conditional `website.publish`.
- **Current status:** none; existing internal `SchoolActivity` model is private (`activities.view/manage`) with no public flag or public page.
- **PD:** owner must decide (§7 D1-O8) whether to extend `SchoolActivity` with explicit publication controls or introduce a separate public clubs/activities model. **Internal activity records must not be exposed by default** — that is a hard rule under either choice.
- **DCs:** internal/internal-only club records must remain invisible even when a public clubs page exists; publication is explicit per record.

### 5.11 Sports

- **Purpose:** Sports program, facilities, and achievements.
- **Unique content:** sport program details, achievements timeline, sports-day highlights.
- **Media:** photographs by sport/event.
- **Perms:** `website.manage` / conditional `website.publish`.
- **Current status:** none.
- **DCs:** — none beyond common capability.

### 5.12 Campus and Facilities

- **Purpose:** Independently managed facility information (e.g. classrooms, labs, transport, playground) with photographs.
- **Unique content:** per-facility records (independent facility entries, not one long page body) — a facility can appear/stand alone without editing the whole page.
- **Media:** facility photos; cover per facility.
- **Perms:** `website.manage` / conditional `website.publish`.
- **Current status:** none as a standalone page; the About page's nav item "Facilities" currently points at `/about` — mislabeled and merged (§5.7/§4).
- **DCs:** facilities are independently manageable records, so adding/editing one does not require editing an About-page blob; nav item "Facilities" points to the new page (not `/about`).

### 5.13 Homepage

- **Purpose:** Approved structure preserved: Hero → Statistics → School Introduction/Highlights → Principal's Welcome → Notices (`frontend.notices` cross-lists published notices) → Upcoming Events (`SchoolEvent.is_public`) → Admissions/Contact CTA.
- **Unique content:** hero rotation + per-hero caption/CTA, statistics, highlight cards, welcome text, section ordering.
- **Media:** images **selected from the central Media Library** rather than uploaded ad hoc; the hero's existing JSON storage is migrated (ADR-001 §4 backfill, D2); **the existing `settings.website` hero routes remain the fallback path until the backfill lands**.
- **Perms:** page-level under Website-Content permissions; hero editing stays `settings.manage`-controlled until its integration milestone.
- **Current status:** hero exists via `/settings/website` (`settings.view/manage` routes; verified).
- **DCs:** homepage hero output unchanged right after the D2 backfill (hero render test from U1.1 is the regression reference); order and alt-text values from the JSON carry across; CTA `route('public.admissions')`/`route('public.calendar')` destination remains reachable.
- **Branding rule:** official **blue/yellow/white** branding; no forest-green.

### 5.14 About Us

- **Purpose:** History/vision/mission/premises content with CTA to Contact.
- **Unique content:** organization-mission narrative; the current (verified FR-081) content structure retained.
- **Facilities split:** after §5.12 exists, the merged nav destination is removed and About focuses on history/mission (nav de-duplicated).
- **Perms:** `website.manage` / conditional `website.publish`.
- **Current status:** exists (`/about`, verified FR-081) but is currently the destination of the mislabeled "Facilities" nav item.
- **DCs:** after the Facilities split, `/about` no longer carries facility content; nav and content updated together.

### 5.15 News

- **Purpose:** News articles (M-04 "news" surface; FR-083 placeholder).
- **Unique content:** article records: title, teaser, body, category, publication date, featured image, optional article gallery, publish state.
- **Media:** featured image from the Library; article galleries as attachments.
- **Perms:** `news.manage` (+ conditional `news.publish`, pending D1-O7).
- **Current status:** `/news` is a hard-coded placeholder page (`frontend.news`) with no model/controller.
- **DCs:** article detail pages are addressable (`/news/{slug}` proposed) and render their body, per FR-083's AC (visible date, teaser).

**Notices remain distinct:** the existing Notice module handles operational announcements: important school notices, examinations, fees, holidays and attendance. It currently lacks dedicated create/delete authorization; D1.5 proposes `notices.manage`. News covers achievements, competitions/event coverage, academic activities, updates and articles, with featured images and optional galleries, under future `news.manage`. Public display does not merge these responsibilities or permissions.

### 5.16 Events and Calendar

- **Purpose:** Public events/monthly calendar built on the existing `SchoolEvent` flow.
- **Unique content:** event records via existing admin calendar; `is_public` remains the publication control; event photography linking where decided.
- **Perms:** `events.manage` concept (mapping with existing `calendar.manage` decided at D3); `SchoolEvent.is_public` reused rather than replaced (**do not assume the existing event model needs replacing**).
- **Current status:** works (`/calendar`, FR-086); events have **no image column or album relation** today.
- **DCs:** when event photography is added (D5), event images are Media Library assets attached to the event record — not ad-hoc uploads into the calendar module; non-public events never render publicly (FR-086 AC).

### 5.17 Gallery

- **Purpose:** School photo galleries organized by albums (FR-084; unimplemented today).
- **Unique behavior:** album records with **cover-image selection**, **multiple photographs per album**, **per-photo captions**, **display ordering**; albums may reference events (e.g. Schools Day) without duplicating the event record; category/filtering; directory browsing preserved (`/gallery` URL stays).
- **Perms:** `gallery.manage` (+ conditional `gallery.publish`, pending D1-O7).
- **Current status:** `/gallery` is a hard-coded placeholder (`frontend.gallery`); no models. **FR-084 preserved and retained for Gallery** — do not renumber and do not create a duplicate gallery feature ID.
- **DCs:** an album with zero photos still renders (friendly message, FR-084 AC); per-photo caption/order of an album photo **does not affect** the same photo's caption/order elsewhere.

### 5.18 Admissions

- **Purpose:** Editable admissions information page plus inquiry/contact CTA (**not** the application-review module).
- **Unique content:** process steps list (editable), fee/approach information per the owner's admissions policy (may still cost PD), FAQ (if Phase-2 landing pages are approved; name/copy from Phase-1 report), inquiry details.
- **Current status:** landing page exists (`/admissions`, FR-085); Process/Fees/FAQ nav items are documented Phase-1 placeholders pointing to the same landing page (nav.blade.php comment confirms this is explicit).
- **Perms:** proposed `website.manage` / conditional `website.publish` for public page text. Existing `admissions.manage` continues to control application review; neither responsibility implies the other.
- **DCs:** editing the public admissions copy never touches admission applications; the two permission domains are provably disjoint in tests.

### 5.19 Publications

- **Purpose:** School publications and downloadable resources (magazines/year/planners/newsletters).
- **Unique content:** publication records: title, category, year/edition, description, downloadable file, cover image, publish state.
- **Media:** cover images from the Library; downloadable files are **not** Media Library assets (they are publication files, stored per publication in their own storage path with whichever public/authorization mix is decided).
- **Perms:** `publications.manage` (+ conditional `publications.publish`, pending D1-O7).
- **Current status:** none (no model, no public route).
- **DCs:** downloadable files listed publicly are expected to be genuinely public-serving files; files intended for staff/student-only use go through the private documents module (`documents.view`) instead, preserving NFR-02 boundaries.

## 6. Content-management architecture responsibilities (summary)

Detailed entity relationships live in ADR-001 §2. Responsibility split summarized:

1. **Media asset management** — one asset per physical upload; global default metadata.
2. **Website page management** — page records, slugs, navigation lifecycle; publish state per page.
3. **Page sections/content blocks** — ordered editable blocks per page, each optionally attaching media.
4. **Media-to-page relationships** — the attachment link carrying per-use caption/alt/order (+ crop when approved).
5. **Gallery albums & photographs** — dedicated album/photo structure (not a generic content blob).
6. **News articles & featured images** — article records with optional galleries.
7. **Event records & event photography** — the existing `SchoolEvent` remains the primary record; photography is attached, not embedded in a redesign.
8. **Academic/activity page content** — pages like Primary/Secondary/Science own narrative sections.
9. **Publishing workflow** — state machine proposals (§3.4) refined by D1-O3/D1-O4/D1-O7 decisions.
10. **Permissions & audit history** — §3.3 capability model; audit of page edits and publishes (recorded per §3.1.7).

**Anti-pattern rule (binding on D2/D3 design):** page/album/asset data is stored as **structured records** when relationships, ordering, or referential integrity matter — a shared JSON dump is **not** acceptable merely for convenience.

## 7. Open owner decisions (proposals; nothing approved)

Recorded identically in `docs/PRD.md` §M (the PRD notes remain the canonical prose). Here, each carries a proposal — clearly labeled, not approved:

| # | Decision | D1 proposal (non-binding) |
|---|---|---|
| D1-O1 | Library book cover images: integrate with the public Media Library, or keep them book-specific? | Keep book-specific for first release; add Library integration later only if cover reuse is actually needed. |
| D1-O2 | Public staff profile: may it select from the Media Library, or stay exclusively bound to `StaffMember` portraits? | Stay bound to `StaffMember` (matches ADR-001 §2.5 and existing `users.manage` ownership); no auto-exposure of private account photos. |
| D1-O3 | Publishing workflow: draft→review→publish, or direct publishing by authorized editors? | Direct publishing by appropriately authorized users for first release (conditional publish keys in §3.3; D1-O7 still open); add review step later if owner volume warrants. |
| D1-O4 | Scheduled publishing required for first release? | No; plain draft/published only. |
| D1-O5 | Unreferenced media: archived or permanently deleted? | Archive (soft-delete) with a visible re-approve action; physical deletion only via a separate owner-run cleanup step. |
| D1-O6 | Crop/focal-point controls in first release? | No; defer to a later stage. |
| D1-O7 | May editors publish their own content, or does publishing require a separate publisher? | Editors and publishers are distinct capabilities; small team may hold both; assignments made in D3. |
| D1-O8 | Extend `SchoolActivity` with publication controls, or introduce a separate public clubs/activities model? | Separate public model for clubs/ECA; internal `SchoolActivity` remains private (cleanest privacy separation). |
| D1-O9 | Results: standalone public page or reuse the existing result-access workflow? | Reuse the existing result-access workflow; optionally a small public landing page describing result access when scheduled content lands. |
| D1-O10 | Student portrait upload now, or as a separate private student-record enhancement? | Separate enhancement; not bundled into public website stages (student data authorization rules apply). |

## 8. Dependencies of this specification

- Testing baseline (289 tests / 1,426 assertions, U1.1-reported) in `TESTING_AND_DEPLOYMENT.md` — any D-series work must mechanically preserve it.
- Hero backfill is a D2 precondition for publication of the Homepage row's DCs.
- D1.5 (authorization hardening) **precedes** D3 delegated permissions per the implementation plan.
- Route split execution is scoped to D3/D4; D1 delivers only the requirement (§4) and revision-ready redirect map.

*End of document — Stage D1.*
