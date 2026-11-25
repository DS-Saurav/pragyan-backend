# Public Website Launch Readiness

**Status:** Public website operating guide and readiness checklist. It does not assign a role or individual privilege to any person, configure hosting, schedule a server task, create a backup, or approve a production launch.

## 1. Editorial responsibility matrix — approval required

The school must approve the named people and their exact public-content privileges before using this as a production responsibility matrix. The table maps responsibilities to public editorial capabilities; it does not assign any user account.

| Proposed responsibility | May do after school approval | Must not do without a separate approved capability or second person |
|---|---|---|
| Public Content Author | Create and revise private drafts for the assigned area; submit them for review. Relevant existing capabilities are `news.author`, `gallery.author`, `notices.author`, `public_pages.author`, and `public_team.author`. | Approve, publish, schedule, withdraw, archive, or make a colleague's work public. |
| Public Content Reviewer | Check accuracy, family-safe wording, links, media placement, alt text, and consent evidence; approve or return a revision. Relevant existing capabilities end in `.review`. | Review their own revision unless they hold the existing `admin` role; publish it without a separate publishing capability; or edit private staff/employment records. |
| Public Content Publisher | Publish, schedule, cancel a schedule, withdraw, archive, restore, and set an optional feature only in their assigned area. Relevant existing capabilities end in `.publish`. | Treat a schedule as a substitute for review, bypass media consent, or change the school identity without the identity owner. |
| Media Reviewer / Publisher | Review media metadata and public-consent state; publish or withdraw only consent-safe Media Library assets. Relevant existing capabilities are `media.review` and `media.publish`. | Expose originals, private-record photos, or media with missing/withdrawn consent or missing required alternative text. |
| Public Identity / Visit owner | Maintain verified public contact, visit, and directions information through `public_identity.manage`. | Make unverified claims, update editorial content as an identity change, or publish staff information. |

The school has approved the existing `admin` role to approve its own public editorial drafts through the explicit review action. That action still records the reviewer and time; it does not skip the review state, media checks, or publication action. A named non-admin editor may receive only selected public editorial capabilities from an administrator. Keep an approval record (who authorized each person's areas and capabilities, and when) before making an individual grant. This does not settle the separate owner-policy question of who holds the wider Super Administrator office or authorize individual grants to internal school modules.

An active admin can open **Users → Public editorial access** on a staff account and grant exact author, review, or publish actions for Media, News, Gallery, Notices, About/Academics, and public Team. The grant history records the admin, target, capability, and grant or revocation. No one receives a grant merely because this screen exists. Do not use this screen for private calendar events, public admissions management, school settings, student records, finance, or the wider role matrix. A grant becomes ineffective when the recipient is suspended or no longer an eligible staff account; account status and role changes also revoke saved grants.

## 2. Editorial operating procedure

### Intake and drafting

1. Record the source of every proposed fact, date, link, and public photograph. Do not copy from internal student, HR, payroll, application, complaint, or document records.
2. Create a private revision in the appropriate editorial area. Write a descriptive title, summary, body, and alt text that makes sense in that exact placement; mark decorative media only when it truly conveys no information.
3. For every identifiable person in a public image, retain the school-approved consent evidence and any expiry or withdrawal instructions. Do not treat a previous social-media post as consent.
4. Preview the revision on a small screen and desktop. Check headings, meaningful link text, long names, long biographies, dates, and the visible image alternative text or decorative treatment.

### Review, publish, and schedule

1. An authorized reviewer checks the source, family-safe language, public audience, former-slug impact, consent state, media status, and alternative text. A non-admin reviewer must be a different person from the author and submitter. An existing admin may approve their own revision through the recorded approval action.
2. The authorized publisher publishes immediately or chooses a Kathmandu-time schedule. Confirm the displayed Nepal-time date and time with the requester before saving.
3. The production scheduler must invoke `php artisan schedule:run` at least once per minute. That scheduler runs the application command `public-content:release-due`; it is not activated merely by saving a scheduled revision.
4. After the due time, confirm the canonical public URL, navigation visibility, page metadata, and media display without relying on a visitor request to trigger publication.

### Urgent withdrawal and consent withdrawal

1. If a fact, link, or image must be removed, use the applicable **Withdraw** action immediately; do not wait for the next editorial cycle.
2. If consent is withdrawn, withdraw the Media Library asset/consent as the first action. Do not reuse cached browser screenshots, copied storage URLs, or original files to keep it public.
3. Withdraw or replace every affected public revision, record the reason and time, and check the public URL, index page, featured area, and sitemap after the change.
4. Use normal incident escalation for a suspected privacy breach. This guide is not a substitute for the school's safeguarding or legal procedure.

### Slug changes and recovery

1. Change a slug through the editorial workflow, never by creating a manual server redirect. Verify the old public slug redirects only to the current public item and the sitemap contains only the current canonical URL.
2. For an incomplete or unsafe replacement, keep the current live revision in place. Correct the replacement, send it through review again, then publish or schedule it.
3. For an accidental withdrawal or archive, restore it as a private draft, validate consent and facts again, obtain review, and republish. Do not use a database edit as a recovery shortcut.
4. Database or storage restore is a separate incident procedure: restore only into an isolated recovery environment first, validate access controls and media consent, then obtain explicit operational approval for any production recovery.

## 3. Production and deployment readiness checklist

This repository does **not** establish a hosting provider, canonical domain, TLS certificate, trusted-proxy configuration, backup service, scheduler/cron, queue worker, deployment pipeline, or production monitoring. Confirm each item with the chosen host before launch.

### Application and web delivery

- [ ] The school has approved the canonical public origin. Set `APP_URL` to that HTTPS origin; do not leave a local or provisional host as the public canonical URL.
- [ ] Set `APP_ENV=production` and `APP_DEBUG=false`; verify an unexpected error shows the safe 500 page rather than exception details.
- [ ] Force HTTPS at the edge/application as appropriate, set `SESSION_SECURE_COOKIE=true`, and verify redirects, asset URLs, canonical tags, social URLs, sitemap URLs, and cookie behavior on the real origin.
- [ ] Configure and test trusted-proxy behavior for the selected load balancer or reverse proxy. Do not assume proxy headers are trustworthy until the host's proxy ranges are known.
- [ ] Build and deploy the Vite assets (`npm run build`); confirm the production manifest is present and no local `public/hot` development marker is deployed.
- [ ] Verify writable application storage, public media derivative delivery, and that private originals are never web-served.

### Database, queues, cache, and scheduler

- [ ] Back up the production database before applying reviewed migrations. Use `php artisan migrate --force` only in the approved deployment process; never use `migrate:fresh` on a school database.
- [ ] Confirm the selected production database is reachable and all required tables exist. The application currently defaults cache, session, and queue storage to database-backed drivers, so their tables and operational capacity must be validated together.
- [ ] If a database queue is used, provision and supervise a queue worker with an explicit restart and failure-handling plan. No worker is configured by this repository.
- [ ] Install one host-owned cron or scheduler service that runs the application scheduler every minute, for example `* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1`. Replace `/path/to/app` with the host's actual release path and test it there; this guide does not configure the cron entry.
- [ ] Confirm `public-content:release-due` runs once, reports success, and does not overlap a still-running instance. Verify a controlled scheduled item in a non-production environment before relying on it for an announcement.
- [ ] Confirm cache invalidation after publish, withdrawal, consent withdrawal, and scheduled release. Do not introduce a long-lived public media cache unless immediate revocation is proven.

### Logging, errors, and privacy

- [ ] Set and test a production logging channel, retention policy, access controls, alert recipient, and error-reporting process. Do not place learner, applicant, consent, password, or payment data in logs.
- [ ] Verify safe HTML errors for 403, 404, 419, 429, and 500, plus JSON errors for API/JSON requests, with no exception messages, paths, trace, IDs, or secrets exposed.
- [ ] Confirm the response headers `X-Content-Type-Options`, `Referrer-Policy`, `X-Frame-Options`, and `Permissions-Policy` with the chosen proxy/CDN. Do not add an enforced generic CSP without a tested asset and nonce plan.
- [ ] The public shell currently loads Bunny Fonts and Bootstrap Icons from jsDelivr, and uses an inline brand-variable style. Those third parties can receive visitor network metadata and can affect availability. Before launch, decide whether to keep the tested external assets, bundle approved equivalents, or implement a tested narrow CSP; no analytics or new third-party service is introduced by this phase.

### Backup and restore readiness

- [ ] Name the backup owner, frequency, retention, encryption, storage location, and access reviewers for the database and both original/derivative media stores.
- [ ] Protect deployment secrets and `APP_KEY` separately from the database backup, with access logging and a recovery owner.
- [ ] Perform and record a restore test into an isolated environment: database, public derivative paths, private originals, access controls, consent-withdrawal state, and a small public-page smoke test.
- [ ] Define the decision-maker and communication path for recovery. A backup is not considered ready until a restore has been tested.

## 4. School inputs still needed before rollout

1. The approved production domain and hosting/proxy owner.
2. Named people for the responsibility matrix and explicit role/permission approval.
3. Verified public identity details, crest/logo source, contact/visit owner, and any approved public social links.
4. Editorial source-of-truth and consent-evidence process for every public story, gallery image, team portrait, and urgent correction.
5. Backup, restore, scheduler, queue-worker, log-retention, and incident-response ownership at the selected host.

