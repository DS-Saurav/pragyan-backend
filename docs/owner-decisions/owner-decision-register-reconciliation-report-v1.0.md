# Pragyan Pathashala — Owner Decision Register Reconciliation Report — Revision 1.0

**Review date:** 5 November 2026 (Asia/Katmandu)

**Authority:** The complete Consolidated Owner Decision Register — Revision 1.0 supplied by the owner in this conversation. Its policy statements are the new baseline; older pending proposals do not override them. The register date is supplied by the owner, not an independently reconstructed approval timestamp for each decision.

**Scope:** Establish two documentation files and reconcile existing documentation only. No application implementation, database operation, test execution, roadmap change or release approval.

## 1. Register creation and evidence baseline

Created `D:/school-app/docs/owner-decisions/consolidated-owner-decision-register-v1.0.md` from the supplied register, preserving its wording, all nine sections, all 21 decision rows and implementation restrictions. Neither destination existed before this task. The register was not rewritten as Revision 1.1, and no corrections were silently applied to it.

Supplied decision-status totals: 13 `Decided`; 4 `Partially decided` (D03/D05/D08/D13); 2 `Pending` (D04/D06); 1 `Decided; matrix pending` (D11); 1 `Decided; targets pending` (D14). These total 21. Settled principles and pending details are separated below without changing those original labels.

The only other new file is this report. No PRD, ADR, application, migration or existing audit artifact was edited.

### Evidence inspected

Repository paths and relevant sections/lines:

- `D:/school-app/docs/PRD.md`: non-functional requirements, lines 308–324; roadmap lines 399–416; M-02–M-10, lines 427–435; bounded media decisions, lines 438–451.
- `D:/school-app/docs/architecture/ADR-001-hybrid-public-media-management.md`: selected architecture/private boundary, §§1–2; staff portraits §2.5, line 74; historical open proposals §7, line 128; approved media decisions §9, line 143; checkpoint line 256; historical D2-A.1 state §10, lines 295/328.
- `D:/school-app/docs/PUBLIC_WEBSITE_CONTENT_SPEC.md`: draft/proposal authority, line 3; results navigation line 112; covers line 176; portraits lines 184/193; publications §5.19, line 288; open proposal table §7, lines 320–329.
- `D:/school-app/docs/IMPLEMENTATION_PLAN.md`: D-series scope/approval boundaries, lines 75–90; unresolved matters §F, lines 243–255; historical verification/search record §§F–G.
- `D:/school-app/docs/IMPLEMENTATION_STATUS.md`: payroll row line 89; proposed future confirmations, lines 167–176, particularly write-on-read line 174.
- `D:/school-app/docs/AUTHENTICATION_AND_AUTHORIZATION.md`: authentication table and post-login description, lines 25–39; role/gate descriptions §§2–3.
- `D:/school-app/docs/TESTING_AND_DEPLOYMENT.md`: historical media verification boundaries, lines 181/193.
- `D:/school-app/docs/DOCUMENTATION_INDEX.md`: existing document inventory.
- `D:/school-app/storage/framework/verify/d2a2-20261104/report.json`: read only saved `summary`, `state` and test count; no database identity, grants, private records or credentials were retrieved or printed. Summary is 64 passed / 5 failed, 69 recorded tests, state `STOPPED_AT_OWNER_REVIEW_CHECKPOINT`.
- The newly created owner register, including its decision table and restrictions.

Chat artifacts reviewed (not pre-existing repository report files):

- Original comprehensive static audit, writing block `78432`, dated 4 November 2026.
- Original feature matrix, writing block `69218`, and its subsequent reconciliation.
- Audit validation/reconciliation response containing the amended A01–A22 register.
- Comprehensive Security and Release-Readiness Audit dated 5 November 2026, including its severity register.
- Security Findings Validation and Owner Decision Preparation response, including current qualifications and follow-up IDs T01–T16.
- Earlier Owner Decision Register, writing block `90517`, dated 5 November 2026, and the subsequent updated pending table. These were proposals awaiting choices at that time; the supplied owner register now resolves or narrows those questions.

No standalone local copies of those comprehensive audit/validation reports or the complete feature matrix were identified in the inspected docs inventory/search. Their complete chat content, rather than guessed meanings from IDs, was used. General project documentation and historical verification reports are not substitutes for those specific artifacts.

### Snapshot limitations

Initial branch: `master`. Initial HEAD: `bc947127a3115b59db29880adfebf2ec6fdbe7b6`. Initial Git status: 276 modified/untracked entries. These include 63 tracked modifications and 213 untracked files from the established working tree; preservation is verified by the captured status list and hashes, not merely these totals.

A pre-write in-memory baseline captured Git status and SHA-256 values for 479 existing files: tracked/untracked non-ignored files plus files under `storage/framework/verify`, excluding `.env` files. This includes existing D2-A.2 helper, saved reports, baseline and synthetic verification files. It is a task-preservation baseline, not a reconstruction of the original audit snapshot. No baseline file was created. Ignored files outside that verification tree and filesystem metadata/ACLs were not exhaustively snapshotted.

Original audit/report hashes and a complete original source snapshot are unavailable. No specific audit-snapshot mismatch is asserted. Historical test definitions/results do not establish current runtime behavior.

## 2. Decisions confirmed and traceability

The supplied policy is authoritative even where PRD text has not caught up. The following records identify what is settled and what remains; they do not reopen approved policy.

| ID | Confirmed owner direction / prior trace | Unresolved details; interpretation of supplied status | Related findings |
|---|---|---|---|
| D01 | Account closure/deactivation restricted to Super Administrator and specifically authorized administrators; categories govern retention. Extends M-05. | Access/closure policy decided; category retention periods and administrator-continuity exceptions still need definition. | A03 |
| D02 | Confidential case access is bounded; public portraits require explicit administrator selection/approval from Media Library; private photos never auto-publish. Extends M-05 and resolves D1-O2 with D17. | Access/portrait principles decided; retention and detailed assigned-case access matrix pending. Do not offer implicit public account-photo use as an option. | A04, N02 |
| D03 | Provider handles infrastructure, school checks coverage/recovery; technical team recommends targets. Extends M-06. | Partially decided; no provider configuration or numerical targets established. RPO, RTO, frequency, retention, restore checks and monitoring responsibility need evidence/recommendations. | A22; A06 operational responsibility only |
| D04 | Detailed admission-number policy awaits owner approval. M-03/FR-011 require duplicate avoidance. | Pending scheme/year/reset/collision handling; pending detail does not authorize duplicate identifiers. | A02, A12 |
| D05 | Draft/unissued deletion allowed to authorized administrators; issued records require audited adjustments/reversals; actual refund method recorded. | Partially decided: precise state definitions, approval limits, corrections, refunds and audit-retention procedure pending. | A05, N03 |
| D06 | Automatic admission→student/classroom/guardian creation or linking and parent reuse are already settled in M-03/FR-010–011 and register §4. | `Pending` applies to completion criteria, matching and exceptions. It must not be read as making automation optional. Recommend a later status clarification, not a silent edit. | A07, A12 |
| D07 | Registration requires school verification/approval; activation is one-time/time-limited to verified admission contacts; users choose passwords; specified session revocation and administrator-verified sensitive changes. Extends M-10. | Policy decided. Clarify password change versus recovery versus administrator reset, preservation of current session, revocation of all other sessions, activation expiry/retry and contact exceptions. Recovery session semantics are not explicitly stated by the supplied revocation sentence. | A08, N01, N06, N07, N09; N05 identity dependency |
| D08 | Both channels, configurable per event; alternative fallback, dual-failure alert and audited attempts/failures. Extends M-07. | Partially decided: priorities, event mappings, recipient verification, fallback timing, retry limits, providers/cost and escalation detail pending. | A09 |
| D09 | Configurable year boundaries; authorized, audited corrections preserve original results; marksheets isolated from later student-detail changes. Extends M-03 and record reliability direction. | Policy decided; calendar conventions, correction authority and publication acceptance evidence still needed. Technical serialization is not an owner policy choice. | A10, N04 |
| D10 | Basic salary records only for first complete release; full payroll deferred; official salary history preserved with audited corrections. | Decided scope restriction. Define what basic records contain and what makes a record official; existing full payroll code is not authorization to accept it for release or remove it. | A19 |
| D11 | Super Administrator controls role/module/action matrix and individual additional grants. Extends M-05. | Decided principles, pending matrix. Do not keep the entire permission model open or describe the matrix as approved. Define mapping to existing admin role separately. | A11, A18 |
| D12 | Timetable reads do not initialize records; administrators explicitly configure; attempts and served-file success are distinct metrics. | Policy decided. Define server-recorded successful response; it cannot prove full recipient download/opening. | A17 |
| D13 | Super Administrator delegates public publishing; SMS authoritative for official records; only approved public information displayed. Extends M-04/M-05. | Partially decided editorial ownership, category/department assignments and information-review responsibility. Media approvals remain separately settled. | A20; A18 context |
| D14 | School growth, reasonable response, capacity planning/performance testing and restrained complexity. | Decided principles; numerical targets and workload thresholds pending technical recommendations and owner approval. | A21 |
| D15 | Listed additional features deferred until confirmation, not cancelled. | Decided deferral. Exact boundary of `advanced website functions` needs clarification; seven required areas remain intact. | Matrix optional/future rows; N05 only where future acceptance depends on scope |
| D16 | Shared cover per book title/edition across copies. | Grouping decided. Storage disk, Media Library integration, image rights and title/edition model are not settled by grouping. D1-O1 is only partially answered. | Library cover matrix row |
| D17 | Administrator selects/approves an existing Media Library image for public staff portrait. | Decided; duplicates portrait principle in D02 without losing either ID. This supersedes the staff-only portrait-source proposal, not private/public separation. | N02; D1-O2 |
| D18 | Flexible timetable only when specifically authorized by Super Administrator. | Authorization principle decided. Whether authority concerns enabling/configuration rights or each individual schedule change remains to be clarified. Does not resolve old media scheduling D1-O4. | Timetable feature; A17 setup dependency |
| D19 | Category/department-authorized staff publish public activities/events/announcements. | Publishing authority decided. Public activity data model remains a technical recommendation; D1-O8 model question is not answered solely by publisher policy. | Public content features; A18 permission context |
| D20 | Results/marksheets authenticated; parents verified linked children, students own authorized records. Reinforces M-05/M-10. | Access decided. Public results-information navigation/landing-page choice D1-O9 remains distinct; no public student-results access implied. | A08, A10 context |
| D21 | Administrative student photos private; public use requires separate specific approval through authorized process. | Privacy decided. Administrative approval is not evidence of any separately required consent/privacy process. Upload timing (D1-O10) and public-use procedure remain unresolved. | Student-photo matrix row; private-media boundary |

All seven M-02 areas are preserved: administration/planning; teacher records/examinations; parent portal/notifications; public website; library/inventory; attendance/timetable; fees/accounting. Marksheet → Public Website → Library emphasis remains. M-09's feature completion → security/bugs/inconsistencies → data-loss/reliability → verification sequence remains recorded. Severity does not change that sequence or authorize work.

## 3. Confirmed documentation conflicts — 6 distinct issues

Counting rule: each C-ID is one grouped documentation conflict/superseded proposal, even if several passages repeat it. Proposed historical scope is distinguished from previously approved policy; this is not a count of six revoked approvals or production defects.

| ID | Existing evidence | Reconciliation against new baseline |
|---|---|---|
| C01 | ADR-001 §2.5 line 74; PRD D1-O2 line 441; public spec lines 184/193/321 restrict or propose staff-record portrait sourcing and leave library selection open. | D02/D17 select the Media Library explicitly. Staff-only source proposal is superseded. Staff association/publication controls can remain; implicit private-photo exposure remains prohibited. No ADR silently edited. |
| C02 | PRD FR-046–048 lines 212–214 describe components, monthly payroll generation and payable slips as draft requirement candidates; earlier chat D10 offered acceptance or deferral. | New D10 restricts first complete release to basic salary records and defers full payroll processing. Those earlier candidate requirements cannot be treated as approved first-release commitments. Existing implementation status is a separate fact. |
| C03 | PRD P-14 line 415 and implementation plan D5 scope include Publications; public spec §5.19 line 288 proposes public publications. | D15 defers public publications and other named advanced additions until explicitly confirmed. These proposed stages need future scope reconciliation, not automatic execution. No blanket cancellation of required website scope. |
| C04 | PRD M-10 line 435 and implementation plan §F line 247 leave open registration versus admin-created/invited accounts; prior chat D07 was pending. | D07 now requires school verification/approval and activation to verified admission contacts. Old alternatives cannot be presented as still wholly unanswered. Whether a public request form exists remains an operational/UI detail consistent with approval. |
| C05 | Implementation status future actions line 174 asks whether timetable creation-on-read and document counter behavior are intentional; prior D12 offered alternatives. | D12 now prohibits timetable initialization on opening and requires separate attempt/success tracking. That intent question is resolved; measurement semantics remain to define. |
| C06 | Testing/deployment line 193 and ADR-001 lines 295/328 retain historical `D2-A.2 unstarted` text. | Saved scratch report records 69 tests with 64 passed / 5 failed and an owner-review stop. Current baseline is OPEN — ROLLBACK NOT READY — NO EXECUTION AUTHORIZED. Preserve historical paragraphs but do not rely on them as current status. |

Other new policies absent or incomplete in older documentation are counted as gaps below, rather than inflated into additional contradictions. No pending choice was newly represented as approved by this review. Earlier pending tables remain historical proposals and are superseded only to the extent explicitly decided in the supplied register.

## 4. Missing/incomplete documentation — 8 grouped items

These are documentation/traceability gaps, not eight newly audited vulnerabilities. A gap can relate to a C-ID; counts are not additive counts of independent product features.

| ID | Gap and evidence location | Required future documentation action (not executed) |
|---|---|---|
| M01 | Comprehensive audit, matrix and validation artifacts remain chat-only; docs inventory has no standalone copies of the specified reports. `docs/DOCUMENTATION_INDEX.md` lists older project/roadmap documentation. | Identify authoritative archival versions, dates and snapshot limitations. Add index links in separately authorized documentation work. Do not fabricate historical report hashes. |
| M02 | Register §§6/9 list findings and next milestone but do not provide a complete decision→requirement→finding→acceptance/evidence mapping. Older PRD/ADR predate N01–N09 and D01–D21 choices. | Prepare traceable requirements/technical-action register for owner review, with technical-only findings retained rather than forced into owner policy. |
| M03 | Super Administrator, specifically authorized administrators, individual grants and category assignments are not mapped to existing roles in PRD M-05 or AUTH §§2–3. | Produce role–module–action and individual-grant matrix; recommend role mapping without inventing a new role hierarchy or equating every current admin to Super Administrator. |
| M04 | Retention remains open: register §5 D01/D02 and PRD NFR-13 line 324. | Category-specific account/history/attachment retention, closure exceptions and authorized public-photo/consent procedure. Access restrictions are already decided. |
| M05 | Backup responsibilities now exist but PRD M-06/NFR-12 and register D03 lack approved recovery targets/procedure. | Recommend RPO (time-based acceptable loss), RTO (acceptable recovery time), frequency/retention, restore-test frequency/evidence, failure monitoring and recovery sign-off. No numbers or provider backups presumed. |
| M06 | PRD M-10/AUTH table do not incorporate D07 activation, contact-change and per-flow session rules. AUTH line 27 says `MustVerifyEmail on User`; amended N01 qualifies effective middleware enforcement. | Separate approved account/activation policy from actual enforcement; define change/recovery/admin-reset session handling and evidence. Do not turn documentation into proof of implementation. |
| M07 | PRD M-07 still leaves delivery policy generally open; new D08 settles fallback/auditing/escalation principles but lacks event, priority, retry and recipient detail. | Event/channel matrix, priority definitions, recipient verification, fallback timing/limits and authorized escalation. Providers and costs remain recommendations. |
| M08 | PRD NFR-10/M-04/M-09 and register D09–D14 lack detailed acceptance/evidence for calendar/history corrections, basic salary records, content ownership and performance thresholds. | Define these acceptance details without reopening principles. Clarify successful served-file metric and timetable authorization granularity. |

## 5. Superseded proposals and questions still requiring owner review

No repeat approval is needed for decided principles. Suggested clarifications/status annotations below are recommendations only; the supplied file remains unchanged.

- D06: annotate in a future revision as automation already decided; completion/matching/exceptions pending. `Pending` must not negate register §4 or M-03.
- D11/D14: retain decided principles and visibly pending matrix/targets. A normalized `PARTIALLY DECIDED` label could express this in a later revision only if adopted; no status was changed here.
- D01/D02: retain decided closure/access/portrait policies while separately tracking retention details. D02/D17 must cross-reference each other.
- D03: identify realistic RPO/RTO, backup/restore frequency and retention recommendations and the person/team monitoring failures; no numerical targets approved.
- D04: numbering format/year/reset/legacy policy; technical uniqueness/collision safeguards must meet approved rules, not become optional.
- D05: define draft/unissued versus officially recorded; authorization limits, refund-method procedure and adjustment/reversal evidence. Original financial history preservation is settled.
- D07: distinguish normal password change, self-service recovery and administrator reset. Clarify current-session treatment and recovery revocation; do not infer those details from `all other active sessions` alone. Activation duration, retries, expired contact and dispute handling remain detailed questions.
- D08: approve exact event/channel and priority matrix, verified recipients, fallback timing, retry limits and authorized dual-failure alerts.
- D09/D10: define correction approvers, official-record boundaries and audit evidence; obtain school marksheet sample. Decide no new payroll scope by inference.
- D12: count server-recorded successful file responses separately from attempts. This is not proof the recipient fully downloaded or opened a document; handling interrupted/partial transfers needs an operational definition.
- D13/D19: settle editorial owners and category/department assignments. Public activity representation is a technical recommendation, not an unselected owner-approved model.
- D15: define `advanced website functions` and any deferred-feature boundary without silently shrinking seven required areas.
- D16: shared cover per title/edition is decided. The storage/Media Library integration part of D1-O1 remains unresolved.
- D17: explicit Media Library selection resolves D1-O2; private account photos remain separate. Technical adapters and migration requirements require proposals, not execution.
- D18: clarify whether specific authorization governs configuration rights, each schedule change, or both. Timetable flexibility does not approve scheduled media publishing (old D1-O4 remains open).
- D20: authenticated results access is decided. A public informational landing page/navigation destination remains open; it cannot expose personal results.
- D21: clarify public-use approval and any separately applicable consent/privacy process; no automatic public exposure. This does not decide private upload timing under D1-O10.

Old public-spec §7 proposals O3/O5/O6/O7 are already superseded for media by PRD M′ and ADR §9: Draft → Review → Publish; archive originals/metadata with no D2 permanent deletion; basic focal positioning/later preview and no full crop editor; different authorized reviewer with recorded administrator override. These remain approved. New D02/D17 do not silently alter them or replace Option C with an all-files public library.

## 6. Audit traceability and technical recommendations

Counts independently reconciled against the complete chat audit and amended validation: A01–A22 = 22; N01–N09 = 9; total 31. Severity register: 14 High, 13 Medium, 4 Low, no Critical established. These severities are not P1/P2/P3 priorities, execution order or evidence of production exploitation. All original finding IDs/qualifications are retained; no security audit was repeated here.

| Finding | New baseline relationship / outstanding technical evidence |
|---|---|
| A01 | Technical inventory-lock/concurrency follow-up; not a new owner policy decision. |
| A02 | D04 numbering plus technical identifier mismatch/schema verification. |
| A03 | D01 closure/retention; effective administrator continuity and cascade verification. |
| A04 | D02 access/retention; actual private file delivery remains unverified. |
| A05 | D05 state/correction policy; payment/refund/deletion concurrency verification. |
| A06 | Independent effective test-target identity/isolation before destructive testing; database name is insufficient. D03 only for operational responsibility. |
| A07 | D06 and M-03 settled automation; matching/completion acceptance pending. |
| A08 | D07/D20 and M-05/M-10 ownership; end-to-end verified linking and isolation absent/unverified. |
| A09 | D08 both channels/fallback/audit; actual delivery and failure evidence still required. |
| A10 | D09 historical immutability/year rules; rollover and regeneration verification. |
| A11 | D11 canonical permission/assignment matrix; legacy-role reconciliation technical. |
| A12 | D04/D06/D07 uniqueness/matching; actual constraint/concurrency evidence. |
| A13 | Technical spreadsheet export safety/client verification; no need for a new policy vote. |
| A14 | Technical migration-history/reversibility review; no migration execution authorized. |
| A15 | Technical case-sensitive packaging/autoload verification. |
| A16 | Technical image capabilities, dimension/memory/storage-failure verification; media approvals unchanged. |
| A17 | D12 intent settled; served-file metric and authorized timetable setup verification. |
| A18 | D11/D13/D19 effective route/method/object permission checks; approved matrix still pending. |
| A19 | D10 defers full processing, preserves basic official salary history; existing payroll risks remain documented, not silently removed. |
| A20 | D13 documentation authority; obsolete-template reachability unverified. |
| A21 | D14 school workload/threshold recommendations and measurement. |
| A22 | D03 recovery assurance; no inference external backups absent or present. |
| N01 | D07 intended account approval; technical verification-interface/middleware enforcement. |
| N02 | D02/D17 explicit public portraits, private account separation; no anonymous exposure assumed. |
| N03 | D05 ledger history/refund method; soft-deletion query effects and reporting verification. |
| N04 | D09 publication immutability; controlled concurrent save/lock/publish evidence. |
| N05 | D07 identity alignment/library acceptance; persistent borrower relationship is a technical design gap, not automatically resolved by D16. |
| N06 | D07 registration approval; response enumeration/abuse controls and upstream behavior unverified. |
| N07 | D07 per-flow session rules; technical revocation and session-driver evidence. |
| N08 | Technical attribute-context/browser verification; no demonstrated stored-XSS execution. |
| N09 | D07 activation/user-chosen passwords supersede generated-password handover option; actual lifecycle verification remains. |

These mappings provide reconciliation context, not the full next-milestone acceptance/action register. Additional technical recommendations still needed: dependency advisory evidence (not looked up externally here), deployment configuration/secret protection evidence, safe verification boundaries, financial/history invariants, public-media adapters, record/version models, representative capacity measurement and restore design. Recommendations must distinguish static evidence, isolated runtime testing and deployment/external evidence. Routine read-only technical reasoning does not require a new owner policy decision; execution authorization remains separate.

## 7. D2-A.2 and authorization wording

**D2-A.2 OPEN — ROLLBACK NOT READY — NO EXECUTION AUTHORIZED.**

Saved historical outcome: **64 passed / 5 failed; the five FK error-code assertions remain unresolved.** The supplied register's wording `5 unresolved foreign-key error-code assertions` is preserved verbatim, but must not obscure the report's recorded failed count. No assertions were converted into passes. No current database state was queried or inferred from historical counts.

The supplied register records that a rollback wrapper passed syntax-only lint. The exact linted wrapper version/source hash and corresponding lint artifact were not independently established in this task. Treat this as a supplied historical statement, not a newly verified lint result or runtime readiness. Do not associate `run.php` (the saved test helper) with a corrected rollback-wrapper lint claim without evidence. No lint or wrapper was executed here.

Preserve both migration files, saved helper/reports/baseline and synthetic verification files. No rollback, reapplication, D2-B, hero backfill, privilege change or cleanup is authorized.

Governance must separately distinguish owner policy/product decision, technical recommendation, explicit defined-task authorization, verification evidence and release approval. Register §8 lists four categories and omits a separate release-approval category; recommend adding that distinction in a future approved revision. Its prohibition on production changes is not authorization to run non-production destructive operations. This task authorizes only the two documentation files. No one-week commitment, sprint or deployment is established.

The next milestone remains preparation of a traceable requirements and technical-action register for owner review, not implementation. No detailed roadmap/work package is approved here, and no approved sequence is changed.

## 8. Evidence unavailable and review limitations

- No original complete audit/source snapshot hashes; no invented snapshot mismatch.
- No existing local authoritative copies/hashes of the listed chat-only audit, validation and decision proposals.
- No independent wrapper-version/hash-linked lint evidence identified in this review.
- No current provider backup/restore proof, deployment settings, runtime results or current advisory evidence; no HTTP/database inspection permitted or performed.
- No approved numerical recovery/performance targets or complete role/action/retention/event matrices.
- Super Administrator role mapping, additional school consent/privacy procedures and fine-grained operational definitions remain unresolved; not invented.

## 9. Preservation verification

Post-write verification is recorded below after comparing the captured pre-existing status list and 479-file hash baseline. It excludes the two intentionally new documentation files from the expected unchanged set. No unexpected changes will be cleaned or repaired automatically.

Verification result: all 479 pre-existing baseline files retained identical SHA-256 content hashes; none were missing. All 276 original Git-status entries remained unchanged (63 tracked modifications and 213 untracked files). Branch and HEAD were unchanged. The only added files/status entries were the two requested Markdown deliverables. No unexpected additions or changes were found. The register contains exactly D01–D21 once each in its decision table and the five supplied status labels/counts stated in §1. No staging or committing occurred.
