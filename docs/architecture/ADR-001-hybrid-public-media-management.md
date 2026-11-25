# ADR-001 — Hybrid Public Media Management

**Status:** Accepted as product direction (owner decision, Stage D1, 2026-11-01). D2-A foundation code and unapplied migrations now exist; no UI/live integration. The historical decision rationale is preserved; actual schema/workflow is in §9.

---

## 1. Context

The public website (M-04 owner direction) needs a photographic and content capability across 19 page areas (see `docs/PUBLIC_WEBSITE_CONTENT_SPEC.md`). The U1.1 audit (recorded in `docs/IMPLEMENTATION_PLAN.md` and `docs/TESTING_AND_DEPLOYMENT.md`) established the current reality of image handling:

- Only two photo surfaces exist today: homepage hero images (JSON array `website_hero_images` in the `settings` table, managed via `settings.manage`) and staff portraits (`staff_members.photo`, managed via `users.manage`).
- `/gallery` and `/news` are hard-coded empty placeholder pages; no gallery, news, or publication models exist.
- `school_events` has no image column; `SchoolActivity` has no public flag and no public page.
- `students.photo` is read by hall tickets but has no upload form.
- The Website Content UI advertises up to 8 MB per image, but the local PHP configuration accepts only `upload_max_filesize = 2M`, so larger files are rejected by PHP before Laravel validation (see Section 6 and the testing doc for current-vs-target values).

Three candidate architectures were considered:

### Option A — Module-specific uploads
Each module independently stores and manages its own photographs.

**Rejected.** Upload, validation, replacement and orphan-cleanup logic would be duplicated; cross-page reuse would be inconvenient; image metadata and management behavior could become inconsistent; every new module would need its own image-management implementation. Re-uploading reused photographs also duplicates storage and alt-text effort and provides no shared usage tracking or reference-safe deletion.

### Option B — Central Media Library for all media
One central repository would manage all school-system media and files, including public website photography and potentially private student, staff and administrative material.

**Rejected.** Public editorial photography and private records have fundamentally different access requirements. A single all-media repository risks exposing private records through public-media workflows unless extensive separation is added; it over-couples unrelated modules and complicates privacy and ownership boundaries. Existing private documents use authorization-checked endpoints on a private disk (NFR-01/02); page publishing must not imply access to confidential HR or student records. This option was not a public-photography-only library.

### Option C — Hybrid Media Management — **SELECTED**

**D1.1 terminology correction (2026-11-01):** the D1 record mislabeled the all-files library as A and per-page uploads as B. The original U1.1 comparison was A = Module-specific uploads, B = Central Media Library, C = Hybrid Media Management. This corrects the alternatives' labels and rationale; the owner's Option C selection and decision history are unchanged.

Two clearly separated domains:

1. **Central public website Media Library** — reusable photography for public pages only, with usage tracking, contextual per-use metadata, draft visibility, and reference-safe deletion.
2. **Separate private records, exactly as today** — student portraits/photos, private user account photographs, confidential staff information, payroll and HR documents, complaints/helpdesk attachments, internal school documents, and admission documents stay in their existing models, disks, and permission paths. The Media Library must never become their shared home. No U1.1-reported changes to any private-record model, and no new public exposure of any private-record field, are approved by this decision.

## 2. Selected architecture (Option C)

### 2.1 Conceptual entities (proposals for D2 design; no migrations created in D1)

| Entity (proposed) | Responsibility |
|---|---|
| `media_assets` | The single uploaded file record: storage path, random storage filename, dimensions, file size, verified MIME type, original/global caption and alt text, category/tags, publication status (draft/published), soft-deletion state. |
| `media_attachments` | One *use* of an asset somewhere: subject type/id (a page section, gallery photo slot, news article, event, publication), contextual caption, contextual alt text, display order, optional crop/focal data (deferred — open decision D1-O6). |
| `website_pages` | Independently addressable public pages (Primary, Secondary, Science, Computer Education, Robotics, Library, Sports, Campus & Facilities, Admissions, etc.) with URL slug, navigation placement, publication status. |
| `website_page_sections` | Editable content blocks on a page (title, body, layout hints, section ordering), each optionally attaching media. |
| `gallery_albums` | Named albums with cover-image selection, description, publication status. |
| `news_articles` | Article body, featured image, optional article gallery, publish state, category. |
| Page/event-specific relationships | e.g. event↔album and publication downloads, designed per feature rather than forced into a single shape. |

**Design intent:** structured records with real relationships are preferred over "throw everything in one JSON field" — the `/settings/website` hero JSON pattern is precisely what D2 will migrate *away* from (Section 4). JSON fields may be used where the data is genuinely unstructured (e.g. page-settings payload), not as a shortcut for anything requiring integrity, ordering, or referencing.

### 2.2 How one asset is reused

An upload creates exactly one `media_assets` row. Every public placement creates a `media_attachments` row referencing it. The same laboratory photograph can therefore be the Science page cover (one attachment) and a photo inside a Gallery album (another attachment) — one file, two uses, two independent captions/alt texts/orders.

### 2.3 Contextual metadata rule

- **Asset-level metadata** (one value per upload): dimensions, size, MIME, category/tags, default alt text.
- **Attachment-level metadata** (one value per use): display caption, alt text for the specific context, ordering within the page/album, crop data when approved.

D1 recommendation: contextual alt text should fall back to the asset default when a use leaves it empty, but contextual values always win. Exact empty-value behavior is settled at D2 design time.

### 2.4 Public / private separation

- The Media Library contains **only** content intended for public presentation. Private imagery (students, private account photos, staff-confidential files, HR/payroll docs) never becomes a media asset and never derives from one.
- Draft/unpublished assets are not served through any public route.
- Existing permission boundaries are preserved unchanged: hero images remain behind `settings.view/manage`; staff portraits behind `users.manage` and the `StaffMember` website-visibility rule (Section 2.5); hall-ticket photos behind `students.photo` reads (Section 2.6).
- A future image-derivative/optimization pipeline must obey the same rule: derivatives exist only for public assets and are served publicly; no derivative pipeline touches private records.

### 2.5 Teachers and leadership (staff portraits)

Stays as-is unless the owner chooses otherwise (open decision D1-O2). Portraits remain associated with `StaffMember` records; the team/leadership pages are driven by the existing website-visibility controls (`show_on_website` scope, `team_group`, `display_order`) and staff-profile publication permissions — not by the Media Library. Private user account photographs (`users.photo`) are never exposed by public pages. If the owner later approves media selection from the Library for staff profiles, that becomes a separate owner-approved design change.

### 2.6 Student photographs

- `students.photo` is a **private student-record photograph**; its existing hall-ticket read is preserved (hall tickets are issued through staff-facing, permission-checked workflow).
- It must **not** be exposed through the Media Library, public pages, or public routes.
- The missing upload form is a separate known requirement gap; any future upload belongs to a private student-record enhancement (open decision D1-O10) and must follow student-data authorization and privacy rules.

## 3. Permissions model influence (for D3; nothing implemented in D1)

Delegated permissions must decouple media from page publishing. The direction: capabilities such as *media view / upload & manage*, *page edit*, *page publish*, *gallery manage*, *news manage*, *event manage* are separately grantable (see `docs/PUBLIC_WEBSITE_CONTENT_SPEC.md` §6). "Media access" grants media administration, not the right to publish every page; "page edit" does not imply "publish"; private-record modules are unaffected. Definitions, role assignments, and the D1.5 authorization-hardening prerequisite are recorded in the implementation plan, not here.

## 4. Existing hero photographs → Media Library backfill (mandatory D2 dependency)

Current state: homepage hero images live as a JSON array (`website_hero_images`) in the `settings` table with per-entry ordering and alt-text information. The Media Library foundation stage (D2) must include an explicit backfill step; "integrate hero images" as an undocumented aside is not acceptable. The backfill must:

1. Read existing hero JSON entries.
2. Create corresponding central asset records (`media_assets`).
3. Create homepage hero attachment/reference records.
4. Preserve existing storage paths where safe and valid.
5. Preserve order, alt text, and any other supported per-entry information.
6. Avoid duplicate asset records when the same existing file appears more than once in the JSON.
7. Verify every existing hero reference resolves before the backfill is considered successful.
8. Preserve current homepage output and hero behavior throughout the migration (the hero render test from U1.1 is the regression reference).
9. Carry a rollback/recovery plan.
10. Never delete original hero files during an incomplete or failed backfill.

Execution is scheduled in D2 and **not performed in D1**; the `settings` table is untouched.

## 5. Asset lifecycle rules (for D2/D3 design)

- **Upload:** single and multiple images; type/MIME validation plus safe decoding; random/generated storage filenames; clear field-specific user-visible validation errors when the request is rejected (including the PHP-level limit case — see record in `docs/TESTING_AND_DEPLOYMENT.md`).
- **Failure behavior:** replacement writes must preserve the old file until the new one persists; uploads must not orphan files when a request fails partway.
- **Deletion:** an asset is deletable only when no attachment references it; referenced assets are blocked from deletion (or archived) with a clear reference list in the UI.
- **Replacement:** swaps the file behind the asset, keeping attachment references intact; prior derivatives invalidated.
- **Archival/cleanup:** permanent deletion vs archive of unreferenced media is an owner decision (D1-O5).
- **Optimization/derivatives:** responsive variants and compression are required capabilities; activation timing follows the asset lifecycle (open decision D1-O6 for crop/focal controls).

## 6. Upload limits (current vs target, not implemented)

Recorded exactly per the U1.1 audit; full version in `docs/TESTING_AND_DEPLOYMENT.md`:

| Setting | Current (U1.1-observed local) | Proposed product target |
|---|---|---|
| Per-image upload size | `upload_max_filesize = 2M` (rejected before Laravel validation) | **8 MB per image** |
| Aggregate request | `post_max_size = 8M` | ~**40 MB** POST, incl. multipart overhead |
| Images per upload request | UI advertises up to 8 MB/image; multiple uploads accepted | Up to **4 images** per request where supported |

Runtime limits, Laravel validation, and UI messaging must be brought into agreement in D2 (with D2 config changes); nothing was changed in D1. The Windows dev-server `TEMP`/`TMP` `ServeCommand` issue and its `--no-reload` workaround are local-development observations only and are not production answers.

## 7. Consequences

- **Positive:** one upload reused across pages; de-duplicated storage; usage tracking; per-use captions; deletion protection; clean separation from private records; a path to migrate the hero JSON without losing behavior.
- **Cost/trade-off:** two mental models (library assets vs attachments must be explained in admin UI copy); contextual metadata adds a join on every rendered use; deletion safety adds reference checks; the hero JSON must be backfilled and its regression risk managed (Section 4).
- **Deferred:** publication workflow shape (D1-O3/D1-O7), scheduled publishing (D1-O4), crop/focal controls (D1-O6), archival vs permanent deletion (D1-O5), book covers vs the Library (D1-O1), staff portraits selection (D1-O2), `SchoolActivity` public extension (D1-O8) — all recorded as proposals pending owner decisions; see `docs/PUBLIC_WEBSITE_CONTENT_SPEC.md` §7 and `docs/IMPLEMENTATION_PLAN.md` §F.

## 8. References

- `docs/PUBLIC_WEBSITE_CONTENT_SPEC.md` — 19-page content matrix, common capabilities, permissions concepts.
- `docs/PRD.md` §F.11 — FR-091–FR-098 (proposed requirements recording this decision), Section M open-decision proposals D1-O1…O10.
- `docs/IMPLEMENTATION_PLAN.md` — Stage D-series roadmap (D1 → D1.5 → D2 → D3 → D4 → D5 → D6).
- `docs/TESTING_AND_DEPLOYMENT.md` — baseline and upload-limit environment findings.
- `docs/AUTHENTICATION_AND_AUTHORIZATION.md` §5 — authorization observations including D1 classifications.
- `routes/web.php` (`/settings/website` image routes, `WebsiteSettingsController`) — current hero storage and permission surface.
- `app/Models/StaffMember.php`, `app/Models/Student.php`, `app/Http/Controllers/Admin/HallTicketController.php` — existing private-record photo surfaces.


## 9. D2-A implemented schema checkpoint (2026-11-01; owner review required before D2-B)

D2-A creates foundation code and migration files only. **Neither migration has been applied to the school MySQL database.** No UI/navigation, CMS, hero renderer/editor switch, or backfill apply operation is implemented. Historical D1 design proposals above remain evidence; this section supersedes them for actual D2-A behavior. Owner decisions O3/O5/O6/O7 are approved: **Draft → Review → Publish; archive by default with originals/metadata retained; basic attachment focal points and later preview, no crop editor; different uploader/reviewer/publisher, with existing admin wildcard override for self-approval only.** No `media.publish` permission.

### 9.1 Exact tables and migration order

1. `database/migrations/2026_11_01_000001_create_media_assets_table.php`
2. `database/migrations/2026_11_01_000002_create_media_attachments_table.php`

Both are additive. Types below use Laravel schema names (SQLite maps unsigned/decimal/timestamp to its native equivalents). `id()` is an unsigned big-integer auto-increment primary key on MySQL, integer primary key on SQLite. No unrelated table/column is changed.

**`media_assets`**

| Column | Type/default/nullability | Integrity/index |
|---|---|---|
| `id` | `id()` | Primary key |
| `disk` | string(40), default `media_originals`, required | Service only accepts this original disk |
| `original_path` | string(160), required | Unique |
| `public_disk` | string(40), default `media_derivatives`, required | Service only accepts this derivative disk |
| `public_path` | string(160), nullable | Unique; one JPEG derivative per asset in D2-A |
| `original_filename` | string(255), required | Display/provenance only; never a storage path |
| `storage_filename` | string(64), required | Unique, generated UUID plus verified MIME extension |
| `mime_type` | string(40), required | Verified JPEG/PNG/WebP/GIF |
| `file_size` | unsignedBigInteger, required | Bytes |
| `width`, `height` | unsignedInteger each, required | Decoded dimensions |
| `checksum` | char(64), required | Non-unique index; SHA-256 of original bytes |
| `title` | string(255), nullable | Validated metadata |
| `description` | text, nullable | Validated metadata |
| `default_alt_text` | string(255), nullable | Per-use override lives on attachment |
| `category` | string(60), nullable | Indexed; controlled service list, no database enum |
| `uploaded_by` | nullable foreignId → users.id | `ON DELETE SET NULL`, default update behavior |
| `status` | string(20), default `draft`, required | Indexed; service validates draft/review/published transitions |
| `submitted_at` | nullable timestamp | Review submission |
| `reviewed_by` | nullable foreignId → users.id | `ON DELETE SET NULL` |
| `reviewed_at` | nullable timestamp | Approval recorded while status remains Review |
| `published_by` | nullable foreignId → users.id | `ON DELETE SET NULL` |
| `published_at` | nullable timestamp | Publication recorded |
| `archived_at` | nullable timestamp | Indexed; separate from workflow state |
| `created_at`, `updated_at` | nullable timestamps | Laravel timestamps |

**`media_attachments`**

| Column | Type/default/nullability | Integrity/index |
|---|---|---|
| `id` | `id()` | Primary key |
| `media_asset_id` | required foreignId → media_assets.id | `ON DELETE RESTRICT`; no cascading original deletion |
| `attachable_type` | string(60), required | Stable allowlisted alias |
| `attachable_id` | unsignedBigInteger, required | Logical owner ID, service validates existence/type |
| `placement` | string(60), required | Validated alpha/dash role, e.g. hero/cover |
| `display_order` | unsignedInteger, default 0 | Zero-based slot |
| `caption` | text, nullable | Per-use caption |
| `alt_text` | string(255), nullable | Per-use alt; null means future renderer should use asset default |
| `focal_x`, `focal_y` | decimal(5,2), default 50 each | Service validates normalized 0–100 inclusive; per use |
| `created_at`, `updated_at` | nullable timestamps | Laravel timestamps |

Indexes: `media_assets` primary; unique original_path, public_path, storage_filename; ordinary checksum, category, status, archived_at indexes. All three user FK columns and media_attachments.media_asset_id have explicit portable indexes (verified in SQLite as well as defined for MySQL). `media_attachments` primary and asset FK; named owner lookup `media_attachment_owner(attachable_type, attachable_id)`; named unique `media_attachment_slot(attachable_type, attachable_id, placement, display_order)`. Multiple uses of one asset are permitted, but one owner slot cannot contain two assets. Use detach/reattach transactionally for a future replacement operation. Null public paths are permitted for unpublished assets.

Initial allowed category values (nullable = uncategorized): `homepage`, `academics`, `science`, `computer-education`, `robotics`, `library`, `leadership`, `teams`, `clubs`, `sports`, `campus`, `news`, `events`, `gallery`, `admissions`, `publications`. `MediaLibrary::CATEGORIES` and `Rule::in` validate the list; future filtering uses the indexed category column. There is no category UI/table/enum.

Relationships: MediaAsset hasMany MediaAttachment, belongsTo uploader/reviewer/publisher User; MediaAttachment belongsTo MediaAsset and morphTo owner. User deletion removes attribution links, **not media or files**. There is no `deleted_at`: archive is explicit `archived_at`; model deletion is rejected, no permanent-deletion service/route exists. Rollback order is attachments then assets; rollback drops their data but never removes original/derivative files. It must not be run on school data without owner approval and backup/reconciliation. Only the new migrations' down/up behavior was exercised in SQLite tests.

### 9.2 Complete D2-A morph map and owner rules

Centrally registered by `AppServiceProvider` using `Relation::morphMap` (not global `enforceMorphMap`, so unrelated models are unaffected):

| Stable alias | Model | Allowed owner / publication rule |
|---|---|---|
| `homepage-hero` | `App\Models\Setting` | Only the row whose key is `website_hero_images`; conservatively treated as live/published |
| `public-event` | `App\Models\SchoolEvent` | Existing event; live when `is_public` is true |

No other aliases are admitted by attachment service validation. In particular User, Student, StaffMember, SchoolActivity, documents and arbitrary PHP class names are rejected. New Science/Gallery/page models do not exist yet; their explicit owner adapters/aliases belong to their approved later stages. Existing owner models are not changed. Reverse attachment lookup is via alias/id; unknown/deleted owner references fail closed and block unpublish/archive until reconciled. Polymorphic owner IDs cannot carry database FKs across tables; existence and publication checks are service responsibilities. Future owner publication/deletion flows must use the media service and verify selected assets are active/published before publishing content. **No event CMS integration or changes to current event behavior occur in D2-A.**

### 9.3 Storage, validation and serving

Two additive local disks; neither has a public URL, storage symlink or temporary-URL serving enabled:

- `media_originals`: root `storage/app/private/media`; relative paths `originals/<uuid>.<verified extension>`; visibility private, serve false, throw true.
- `media_derivatives`: root `storage/app/media`; relative paths `published/<uuid>.jpg`; visibility private at filesystem level, serve false, throw true. This separate approved-output area is publicly served **only** via `GET /media/{id}` after checking published state, non-archived state and strict disk/path allowlists. It is intentionally not under `public/storage`: a static URL must never bypass later unpublish/archive. Published responses use no-store and nosniff; drafts/review/archived return 404.

`GET /admin/media/{id}/original` requires auth/verified/active/staff and `media.view`, rechecked in controller; response private/no-store. ID-based URLs never accept filesystem paths. Existing student/staff/HR/document handling is untouched. No API exposes arbitrary private disks. Generated-path validation rejects traversal; legacy planner accepts only local `public:hero/<safe filename>.<image extension>` and verifies resolved files stay inside the hero directory (including symlink escapes).

Upload service validates real images, verified MIME and successful GD decode, **8,192 KiB maximum**, max **24,000,000 decoded pixels** (processing-memory guard), generated names and SHA-256. Allowed metadata excludes client workflow/disk/path/reviewer values. Publication rechecks original checksum, produces an aspect-preserving JPEG bounded to 1920×1080 (no upscaling, white alpha background), retaining originals. This is one foundational derivative, not a full responsive variant pipeline or crop editor. No new dependency is installed. Image processing uses memory only, so no temporary output directory is needed. Upload/publication own their DB transaction; nesting these operations in an outer transaction is rejected to avoid file/outer-rollback inconsistency. Failed record creation or first derivative processing/storage cleans new output and rolls back DB state; I/O failure recovery must reconcile any physical cleanup failure rather than deleting school files blindly. UUID paths avoid user-controlled overwrite.

No batch HTTP upload endpoint or size promise is introduced in D2-A. Future D2-B upload requests must enforce an array of 1–4 images, each ≤8 MB, and negotiate an approximately 40 MB aggregate POST cap with runtime configuration. The previously reported PHP 2M/8M limits are unchanged and must not be advertised as supporting 8 MB until reconciled. `php artisan serve --no-reload` remains a Windows local-development workaround only; no PHP/vendor edits.

### 9.4 Actual services and workflow

`MediaLibrary` requires explicit actor + `media.manage` for upload, metadata, submit, approve, publish, draft, archive, restore, attach and detach. `media.view` governs interface status/original inspection; manage does not implicitly grant view. Existing config gate loop registers both keys. No staff grants or persisted memberships are changed: only existing admin wildcard currently grants them. Test-only grants exercise ordinary-manager separation without production assignments.

1. Upload → **Draft**, private, no derivative, uploader attributed.
2. Active Draft → **Review** via submit; approval cleared, submission timestamp set. Metadata editing is limited to active Draft, preventing changes after approval without renewed review.
3. An authorized **different** user approves: reviewer ID/time recorded; remains Review.
4. Approved Review → **Published** by an authorized different user; uploader cannot publish even if someone else approved. Both actions recheck separation. Only `hasRole('admin')` **and** configured admin `*` permits self-approval/publication, matching the existing admin Gate bypass. An ordinary role with `media.manage` or even a test wildcard does not qualify. No new `media.publish`.
5. Return to Draft checks live references, clears approval/publication metadata; derivative may remain stored but is inaccessible publicly. A later publish requires fresh review.
6. Archive checks live references, sets archived_at and preserves original/metadata. Restore requires manage, clears archived_at and returns to private Draft with fresh review required. No permanent deletion.
7. New attachments require an active Published asset; independent caption/alt/order/focal values are validated per use. Archive and unpublish inspect attachments' **current** owner publication flags and also checksum-match the **existing homepage JSON files even without attachments**. Broken/unknown legacy references fail closed. A clear usage list explains blocking references. Admin override never bypasses this integrity guard.

Asset-row locks serialize foundation service mutations/attach/detach under transactional databases. SQLite tests cannot establish MySQL row-lock/concurrency behavior; production-engine verification remains a later approval-gated step. Future page publication/replacement must take the same lock/integrity path; direct raw SQL/model writes are not a supported public mutation API. No schema checks/enums enforce status/category/focal ranges: portable service validation is authoritative; SQLite does not enforce unsigned integers/decimal precision like MySQL.

### 9.5 Hero backfill architecture (dry-run only)

Actual source is the settings JSON list `[{"path":"hero/…jpg","alt":"…"}, …]`; array position is display order, disk is existing `public`. `HeroImage` and `WebsiteSettingsController` remain unchanged, as do `Photo::url` and the public hero renderer.

`HeroBackfillPlan::inspect` and `media:hero-backfill` read JSON/files, validate safe paths, inspect readable images, checksum-deduplicate bytes into asset candidates, preserve individual placement order/alt, report invalid/missing/corrupt entries and return deterministic output with a source-JSON checksum. No media schema is needed for the plan, no DB/filesystem writes occur. Default is dry-run; `--apply` explicitly refuses execution in D2-A. The command was exercised only against isolated test settings/storage, never the configured MySQL database.

**D2-C apply/recovery contract (not implemented):** require approved schema and explicit actor/provenance; revalidate source checksum and every file, copy bytes to private generated paths while preserving originals/source JSON; match existing asset checksums plus provenance (do not automatically collapse unrelated uploads); map homepage-hero owner and unique slot keys; run idempotently in transactions with a per-run manifest, copy checksums, copied-path ownership and reconciliation report. Resume only after reconciling partial copies/rows; never delete existing hero files. Decide review/provenance handling for currently-live heroes explicitly rather than silently auto-approving them. Verify every attachment, preserve order/alt and unchanged homepage output, then separately approve renderer switch and rollback. Renderer remains JSON-based throughout D2-A. This stage prepares the read-only planner, not a tested apply implementation.

### 9.6 Routes, readiness and review boundary

- `/admin/media`: auth/verified/active/staff + media.view; returns a JSON readiness message, **no UI**.
- `/admin/media/{id}/original`: same access rules, private original inspection.
- `/media/{id}`: published derivative only, no original endpoint.

Every endpoint checks **both tables and all required columns before any media query**. Missing/incomplete schema returns a fixed friendly JSON **503**, even with debug enabled; no exception stack, SQL or filesystem paths. Service writes also check readiness before storage/DB writes. No implicit model binding queries media first. Unauthorized/guest requests still encounter permission/auth middleware first. There are no mutation routes and no sidebar/dashboard/navigation links.

**Review checkpoint:** approve this schema, migration order, morph map, storage and workflow before D2-B. Approval of code/migration files is not authorization to apply them to MySQL. D2-B UI, D2-C apply/live integration, D2-D final verification and all D3+ remain unstarted. Outstanding: future CMS owner adapters and publication coordination, responsive variants, batch UI/runtime limit reconciliation, migration execution approval, D2-C provenance/recovery design, and previously open O1/O2/O4/O8/O9/O10. No owner confirmation is needed again for O3/O5/O6/O7.

### 9.7 D2-A exact file manifest and verification

Modified existing files:

- `app/Providers/AppServiceProvider.php`
- `config/filesystems.php`
- `config/permissions.php`
- `docs/AUTHENTICATION_AND_AUTHORIZATION.md`
- `docs/DOCUMENTATION_INDEX.md`
- `docs/IMPLEMENTATION_PLAN.md`
- `docs/PRD.md`
- `docs/TESTING_AND_DEPLOYMENT.md`
- `docs/architecture/ADR-001-hybrid-public-media-management.md`
- `routes/web.php`

Added files:

- `app/Console/Commands/PlanHeroMediaBackfill.php`
- `app/Http/Controllers/Admin/MediaFoundationController.php`
- `app/Models/MediaAsset.php`
- `app/Models/MediaAttachment.php`
- `app/Services/Media/HeroBackfillPlan.php`
- `app/Services/Media/MediaFiles.php`
- `app/Services/Media/MediaLibrary.php`
- `app/Services/Media/MediaSchema.php`
- `app/Services/Media/MediaUsage.php`
- `database/migrations/2026_11_01_000001_create_media_assets_table.php`
- `database/migrations/2026_11_01_000002_create_media_attachments_table.php`
- `tests/Feature/MediaFoundationTest.php`

Focused suite: **34 tests / 234 assertions passing**. Full isolated SQLite suite: **339 tests / 1,795 assertions passing**, vs D1.5 **305 / 1,561**, delta **+34 / +234**. Zero failures/errors/skips; no old test modified or removed. PHP syntax passed for all 16 new/changed PHP files; targeted Pint passed. New tests initially encountered an unrelated legacy SQLite down-migration defect during teardown; teardown now discards only its isolated memory connection, while explicit new migration down/up tests remain. No legacy migration was changed.

Initial snapshot: 456 existing tracked/untracked files, no staged changes. Only the ten listed existing files were modified; the other **446 remained byte-for-byte unchanged**. Twelve new source/test/migration files were added. All six existing files under school storage matched the pre-full-suite snapshot afterward. No deletion, staging, commit, stash, reset, clean, revert, worktree, dependency installation, vendor/PHP/.env change, MySQL execution or live backfill occurred. No production staff role assignment changed. No SQLite file database/server/browser was used; HTTP checks were automated Laravel feature requests using memory SQLite and fake disks. D2-A is complete for owner schema review; D2-B/C/D and D3+ are unstarted.


## 10. D2-A.1 service correction (2026-11-01)

This supersedes §9's broad focal/placement validation description without changing its schema, morph map or historical evidence. **No migration file, index, FK, CHECK, DECIMAL definition, model, controller or permission changed.** Scratch MySQL testing (D2-A.2) and D2-B/C/D remain unstarted.

### Focal creation versus update

`MediaLibrary::attach` now shares strict attachment metadata validation with the new `updateAttachment` service method. `array_key_exists` distinguishes absent values from explicit null. Creation defaults only omitted axes to `50.00`. Update omits absent axes from the write entirely: X-only preserves Y, Y-only preserves X, and caption-only/empty updates preserve both. Null, blank and whitespace-only coordinates are rejected independently for X and Y before a persistence transaction writes anything.

Accepted values are integer 0–100; finite floats whose two-decimal representation round-trips to the identical float; or exact plain decimal strings matching `\A(?:0|[1-9][0-9]?|100)(?:\.[0-9]{1,2})?\z` and bounded to ≤100 (thus `100.01` is rejected). String decimals are padded to two places without rounding; float formatting is accepted only after strict round-trip equality, so an adjacent over-precision float is not silently rounded. Reject exponent strings, surrounding whitespace, plus/minus signs, excess decimal places, leading-zero strings, booleans, arrays/objects, NAN and infinity. Valid literal floats such as 0.29 and 0.57 retain their intended two-decimal values. Explicit zero never triggers the creation default.

`updateAttachment` requires `media.manage`, locks the current asset/attachment, permits context metadata only, and does not reparent or replace the asset. The asset must remain active/published. Invalid metadata or duplicate slots leave the stored attachment unchanged. These methods are the authoritative mutation boundary; no controller workaround or model mutator was introduced.

### Canonical placements and duplicate slots

Trim surrounding ASCII whitespace, map ASCII uppercase to lowercase, then validate the exact logical pattern `^[a-z0-9]+(-[a-z0-9]+)*$` (strict `\A`/`\z` anchors in PHP), maximum 60 characters. No Unicode transliteration or arbitrary punctuation removal. Reject blanks, non-strings, non-ASCII text, underscores, spaces within the slug, leading/trailing hyphens and repeated hyphens. Validation stops at the first placement failure, so objects do not reach a string-length operation. Store canonical strings only, including on placement updates.

Creation and update both check `(attachable_type, attachable_id, canonical placement, integer display_order)` before persistence. Owner type remains an exact allowlisted morph alias; owner ID is validated and resolved to the actual model key. Within the transaction, asset/attachment locks precede an owner-row lock; a locking duplicate lookup uses the current normalized slot and excludes the current attachment on update. This covers conflicts across different assets belonging to the same owner. It raises a field-specific ValidationException before INSERT/UPDATE; the unchanged database unique constraint remains the final safeguard. Distinct placements, orders and owners remain permitted. SQLite tests prove normalization and early duplicate rejection, not MySQL lock/concurrency behavior.

### Existing caller audit

| Existing source | Actual placement value | Result |
|---|---|---|
| `HeroBackfillPlan::inspect` | `hero` | Already canonical; unchanged |
| Existing media test attachment helper/defaults | `cover` | Already canonical; unchanged |
| Existing homepage attachment fixture | `hero` | Already canonical; unchanged |
| ADR examples | `hero`, `cover` | Already canonical; unchanged |
| `MediaUsage` | Displays stored placement in usage messages; no placement creator/lookup | No caller change needed |
| Media controllers/models | No attachment metadata mutation endpoint or placement writer outside service | No caller change needed |

Morph aliases are separate: `homepage-hero` → Setting and `public-event` → SchoolEvent. They were not renamed or normalized. No pre-existing invalid placement caller was found. No persisted media values were inspected: MySQL access was prohibited, and no other application database was used. New mixed/uppercase/whitespace test inputs are intentional normalization cases, not legacy values to migrate.

The previous duplicate test now expects service validation and additionally verifies the stored count. A separate direct-model test retains verification of the physical database unique constraint. The other original 33 media tests remain intact. Added regression coverage exercises direct service calls, both axes and create/update cases, precision/type edge cases, partial updates, independent contexts, normalization, no-partial-write/file preservation, cross-asset slot duplicates before INSERT, update conflicts, authorization and immutable owner/asset identity.

**D2-B deferred requirement:** use the existing non-unique SHA-256 index to warn managers about likely duplicate uploads, offering reuse versus an intentional separate upload. Do not block deliberate duplicates or attaching an existing asset in another context. No warning UI or duplicate-upload logic was implemented here; hero candidate deduplication is unchanged.

Verification: **focused MediaFoundationTest: 46 tests / 751 assertions passing; full suite: 351 tests / 2,312 assertions passing, versus baseline 339 / 1,795 (+12 tests / +517 assertions), zero failures/errors/skips**. No tests removed. Existing full-suite workflow, archive/reference safety, storage/public delivery, hero JSON/rendering and D1.5 authorization coverage remains passing. The encoding failure during test editing was recovered to the exact starting file hash before adding coverage; no original tests were lost. No school MySQL connection, scratch database, live migration, live storage write or live hero backfill occurred. SQLite memory schema setup/reversal and dry-run backfill regression checks ran only inside the existing automated test harness with fake storage. PHP syntax and targeted formatting passed. Stop for owner review; D2-A.2 and D2-B/C/D have not started.

*End of ADR-001.*
