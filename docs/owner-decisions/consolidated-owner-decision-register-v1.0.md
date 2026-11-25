# Pragyan Pathashala
## Consolidated Owner Decision Register — Revision 1.0

**Register date:** 5 November 2026  
**Status:** Owner review and planning baseline  
**Implementation authorization:** NOT GRANTED

### 1. Purpose

This register records the owner's decisions about the school management system, public website, security, privacy, workflows and first-release scope.

It must guide future requirements, technical recommendations, implementation planning and release acceptance.

An approved owner decision does not automatically authorize development, database modifications, migrations, deployment or production changes.

### 2. Decision register

| ID | Subject | Owner decision | Status |
|---|---|---|---|
| D01 | Account closure and historical retention | Only the Super Administrator and specifically authorized administrators may close/deactivate accounts. Historical records follow separate retention rules by record category. | Decided |
| D02 | Confidential attachments and public staff portraits | Confidential attachments are restricted to the Super Administrator, specifically authorized administrators and relevant staff assigned to that case. Public staff portraits require explicit administrator selection and approval from the Media Library. Private profile photos never become public automatically. | Decided |
| D03 | Backup and recovery | Responsibility is shared: the hosting/provider manages backup infrastructure; school administration verifies coverage and recovery readiness. Technical team recommends realistic RTO and retention periods after reviewing capability, cost, operational needs and retention requirements. No numerical targets approved. | Partially decided |
| D04 | Admission-number policy | Numbering scheme, uniqueness, collision handling and related rules require owner approval. | Pending |
| D05 | Financial records and refunds | Authorized administrators may delete draft/unissued financial records. Issued/recorded financial records require audited adjustments or reversals preserving history. Refunds must record the actual method used. | Partially decided |
| D06 | Admission completion and matching | Admission completion should lead to appropriate student, class and guardian record creation/linking. Duplicate or ambiguous matches require controlled handling. Detailed rules remain to be approved. | Pending |
| D07 | Parent/student account onboarding | Registration requests require school verification and approval. Safe self-service recovery is allowed; sensitive contact changes, relationship disputes and ownership disputes require administrator verification. Initial credentials use a one-time, time-limited activation link/code sent only to verified admission-recorded contact details. Users create their own passwords. Password changes and administrator resets revoke all other active sessions. | Decided |
| D08 | Email and SMS notifications | Both channels are required. The school configures email, SMS or both for each notification type. Failed delivery triggers alternative-channel fallback; failure of both alerts authorized staff. Attempts and failures are audited. Notification priorities determine delivery and escalation rules. Exact event mappings remain pending. | Partially decided |
| D09 | Academic years and historical marksheets | Academic years have independently configurable start and end dates. Published results can be corrected only by authorized personnel through an audited process preserving the original version and recording who, when and why. Historical marksheets must not change when current student details change. | Decided |
| D10 | Payroll scope | First complete release includes basic staff salary records only. Full payroll processing is deferred. Officially recorded salary records preserve their original version and require audited corrections recording who, when and why. | Decided |
| D11 | Role and permission management | The Super Administrator centrally configures permissions for each role, including assigned modules and actions, and may grant specific additional authority to individual staff members. Detailed permission matrix remains to be prepared and approved. | Decided; matrix pending |
| D12 | Timetable and document downloads | Opening the timetable page must not create default periods. Administrators explicitly initiate configuration. Document download attempts and successful downloads are tracked separately; only actually served files count as successful downloads. | Decided |
| D13 | Public website publishing | The Super Administrator centrally delegates publishing permissions, potentially by category or department. The school management system is authoritative for official school records; the public website displays approved public information. Editorial content ownership rules remain to be finalized. | Partially decided |
| D14 | Performance and capacity | Design for expected school growth, with reasonable response times, capacity planning and performance testing before release. Avoid premature enterprise complexity. Exact targets and capacity thresholds require technical recommendations and owner approval. | Decided; targets pending |
| D15 | Unconfirmed and future features | Transport, hostel management, homework submission, public publications and advanced website functions are deferred until explicitly confirmed. They are not automatically included or permanently cancelled. | Decided |
| D16 | Library book covers | One shared cover image per book title/edition, reused by all physical copies. | Decided |
| D17 | Public staff portraits | Private account/profile photographs never become public automatically. Administrators explicitly select and approve an existing Media Library image for public display. | Decided |
| D18 | Timetable flexibility | Flexible period times, breaks and class/section schedules are supported only when specifically authorized by the Super Administrator. | Decided |
| D19 | Public events and announcements | Only staff authorized by the Super Administrator for the relevant category or department may publish public activities, events and announcements. | Decided |
| D20 | Examination results access | Results and marksheets require authenticated login. Parents access only verified linked children's records; students access only their own authorized records. | Decided |
| D21 | Student photographs | Administrative student photographs remain private and must not automatically appear on the public website. Public use requires separate, specific approval through an authorized process. | Decided |

### 3. Approved first-complete-release scope

The following seven areas remain in the approved first-complete-release scope:

1. Administration and planning.
2. Teacher records and examinations.
3. Parent portal and notifications.
4. Public website.
5. Library and inventory.
6. Attendance and timetable.
7. Fees and accounting.

**Implementation emphasis:** Marksheet → Public Website → Library.

Other required modules must be improved progressively, not silently removed from the first complete-release scope.

### 4. Project-wide principles

- Centralized administrator-controlled permissions.
- Verified parent–student relationships.
- No portal access based solely on a student ID.
- Support for parents with multiple children.
- School-provided student email addresses.
- Automatic creation/linking of appropriate student, classroom and guardian records after approved admission completion.
- Email and SMS business notification capabilities.
- Secure online availability.
- Backup and recovery readiness.
- Responsive web delivery; native application development is not approved.
- Hybrid public-media architecture with private school records separated from public media.
- Original historical academic and financial records must remain auditable.
- Public website content must use approved information.
- Student and staff private photographs must not become public implicitly.

### 5. Outstanding owner decisions and clarifications

The following matters must be addressed before final requirements approval:

| Priority | Item | Required next action |
|---|---|---|
| High | D03 | Review technical backup, recovery, RTO and retention recommendations. |
| High | D04 | Decide admission-number format, uniqueness and collision rules. |
| High | D05 | Clarify remaining financial-record and refund procedures. |
| High | D06 | Define admission completion, duplicate detection and ambiguous-match approval. |
| High | D08 | Approve exact notification event-to-channel mappings. |
| High | D11 | Review and approve the detailed role–module–action matrix. |
| Medium | D01/D02 | Approve record-category retention periods and attachment retention details. |
| Medium | D13 | Define editorial ownership and content-category responsibilities. |
| Medium | D14 | Approve response-time, capacity and performance thresholds. |

### 6. Audit findings requiring technical follow-up

The comprehensive system and security audits identified 31 findings:

- A01–A22: 22 system and architecture findings.
- N01–N09: 9 additional security findings.
- Severity classification: 14 High, 13 Medium, 4 Low.
- No Critical finding was established in the static audit.

These findings require evidence-based technical assessment and remediation proposals. Static findings must not be represented as confirmed production exploitation or runtime failures without corresponding evidence.

The technical team must retain the audit qualifications, snapshot limitations and unresolved runtime-verification requirements.

### 7. D2-A.2 media migration status

**OPEN — ROLLBACK NOT READY — NO EXECUTION AUTHORIZED.**

- Historical scratch test result: 64 passed, 5 unresolved foreign-key error-code assertions.
- Rollback wrapper passed syntax-only lint, but runtime readiness is not established.
- No rollback has been authorized.
- No D2-B work or hero-image backfill is authorized.
- Preserve both migration files, test helper, reports, hash baseline and synthetic image files.
- No cleanup or destructive database operation is permitted without separate approval.

### 8. Governance and authorization rules

All future work must distinguish:

1. **Owner decision:** What the school has approved as a requirement or policy.
2. **Technical recommendation:** What the technical team proposes, including risks, dependencies, cost and alternatives.
3. **Implementation authorization:** Explicit permission from the owner to perform a defined task.
4. **Verification evidence:** Demonstration that the implemented work satisfies the approved requirement.

A technical recommendation is not an owner decision. An owner decision is not implementation authorization.

No production database operation, migration rollback, privilege change, deployment or destructive cleanup is authorized by this register.

### 9. Next milestone

Prepare a traceable requirements and technical-action register that maps:

- D01–D21 owner decisions.
- A01–A22 and N01–N09 audit findings.
- Outstanding technical questions.
- Acceptance criteria and verification evidence.
- Dependencies and proposed implementation order.

Only after owner review should a detailed implementation roadmap and separately authorized work packages be prepared.

**End of Revision 1.0 — Working owner decision baseline.**
