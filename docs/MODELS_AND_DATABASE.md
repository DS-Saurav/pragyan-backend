# MODELS_AND_DATABASE

> Evidence basis: all 47 migrations in `database/migrations/`, all 44 model classes in
> `app/Models/`, `database/seeders/`, `database/factories/UserFactory.php`, and
> `app/Models/Concerns/HasSchoolRoles.php`. Column types are as declared in the migrations.

## 1. Conventions

- `id()` = `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` primary key.
- `timestamps()` = nullable `created_at`/`updated_at`.
- `foreignId(X)->constrained(T)` = `X BIGINT UNSIGNED NOT NULL` + FK to `T.id` (with the stated
  `onDelete`); an index is created automatically.
- `string(n)` = `VARCHAR(n)`; `boolean` = `TINYINT(1)`; `decimal(a,b)` = `DECIMAL(a,b)`.
- `current_academic_year()` is evaluated **at migration time**, so `academic_year` defaults are baked
  literal values (e.g. `"2083"`), not dynamically re-evaluated on insert.

## 2. Table reference

### 2.1 Framework tables

`users`: `id`, `name` (NOT NULL), `email` (NOT NULL, UNIQUE), `email_verified_at`, `password`,
`role` **ENUM('admin','teacher','student','parent') DEFAULT 'student'** (legacy), `phone`, `address`,
`remember_token`, `gender` VARCHAR(10), `status` VARCHAR(12) DEFAULT 'active', `last_login_at`,
`photo`, timestamps.

`password_reset_tokens`: `email` PK, `token`, `created_at`.
`sessions`: `id` PK, `user_id` (indexed, **no FK**), `ip_address`, `user_agent`, `payload` LONGTEXT,
`last_activity` (indexed).
`cache`: `key` PK, `value` MEDIUMTEXT, `expiration`. `cache_locks`: `key` PK, `owner`, `expiration`.
`jobs`: `id`, `queue` (indexed), `payload`, `attempts`, `reserved_at`, `available_at`, `created_at`.
`job_batches`: `id` PK, `name`, `total_jobs`, `pending_jobs`, `failed_jobs`, `failed_job_ids`,
`options`, `cancelled_at`, `created_at`, `finished_at`.
`failed_jobs`: `id`, `uuid` UNIQUE, `connection`, `queue`, `payload`, `exception`, `failed_at`;
index `(connection, queue, failed_at)`.

### 2.2 Core academics

**`school_classes`** — `id`, `name` (NOT NULL), `academic_year` (NOT NULL, baked default), `section`,
timestamps.

**`students`** — `id`, `adm_number` (NOT NULL, UNIQUE), `first_name`, `last_name`, `gender`,
`dob` DATE, `nationality`, `languages_spoken`, `curriculum` DEFAULT 'IB PYP', `eal` DEFAULT 'Native',
`class_applied` (NOT NULL), `academic_year` (NOT NULL, baked default), `roll_no` INT, `status`
DEFAULT 'active', `house` DEFAULT 'Red', `admission_date`, `blood_group` VARCHAR(5),
`emergency_contact`, `hobbies`, `transport_route`, `previous_school`, `has_allergies` BOOLEAN
DEFAULT false, `allergy_details`, `address` TEXT, `school_class_id` → `school_classes` **SET NULL**,
`photo`, timestamps.

**`admissions`** — `id`, `reg_number` (NOT NULL, UNIQUE), `first_name`, `last_name`, `gender`, `dob`,
`class_applied`, `academic_year`, `address`, `parent_name`, `relation` DEFAULT 'Father', `mobile`,
`email`, `status` DEFAULT 'registered', `rejection_reason`, timestamps.

**`parents`** — `id`, `name`, `relation` DEFAULT 'Father', `mobile`, `email`, `occupation`, `status`
DEFAULT 'active', `address`, timestamps.

**`parent_student`** (pivot) — `id`, `parent_id` → `parents` **CASCADE**, `student_id` → `students`
**CASCADE**, timestamps. **No unique on (parent_id, student_id).**

**`subjects`** — `id`, `name`, `code` (UNIQUE), `max_marks` INT DEFAULT 100, `passing_marks` INT
DEFAULT 33, `assigned_classes` JSON, timestamps.

**`teachers`** — `id`, `first_name`, `last_name`, `email` (NOT NULL, UNIQUE), `phone`, `department`
DEFAULT 'General', `status` DEFAULT 'active', `specialization`, `class_teacher_of`, `username`
(UNIQUE), `assignments` JSON, `user_id` → `users` **SET NULL** (UNIQUE), timestamps.

### 2.3 Attendance, timetable, planning

**`attendances`** — `id`, `school_class_id` → `school_classes` CASCADE, `student_id` → `students`
CASCADE, `date` DATE, `status` **ENUM('present','absent','late','half_day','leave')** DEFAULT
'present', timestamps. **Unique `(student_id, date)`.**

**`timetables`** — `id`, `school_class_id` CASCADE, `day`, `period`, `time_slot`, `subject_id` →
`subjects` SET NULL, `teacher_id` → `teachers` SET NULL, timestamps. **Unique
`(school_class_id, day, period)`.**

**`period_slots`** — `id`, `period_code`, `name`, `start_time` TIME, `end_time` TIME, `is_break`
BOOLEAN DEFAULT false, `sort_order` INT DEFAULT 0, timestamps.

**`lesson_plans`** — `id`, `teacher_id` CASCADE, `school_class_id` CASCADE, `subject_id` CASCADE,
`lesson_date`, `period` TINYINT, `duration_minutes`, `unit`, `topic` (NOT NULL), `objectives`,
`teaching_method`, `resources`, `homework`, `assessment`, `status` DEFAULT 'planned', `reflection`,
timestamps. Indexes `(lesson_date, status)`, `(teacher_id, lesson_date)`.

**`teaching_logs`** — `id`, `teacher_id` CASCADE, `school_class_id` CASCADE, `subject_id` CASCADE,
`lesson_plan_id` → `lesson_plans` SET NULL, `log_date`, `period`, `topic_covered` (NOT NULL),
`coverage` DEFAULT 'completed', `homework_given`, `students_present`, `students_absent`, `remarks`,
timestamps. Indexes `(log_date, school_class_id)`, `(teacher_id, log_date)`.

**`substitute_allocations`** — `id`, `allocation_date`, `day_of_week`, `absent_teacher_id` →
**`users`** CASCADE, `substitute_teacher_id` → **`users`** CASCADE, `periods_covered` TEXT (JSON/CSV),
`notes`, `status` **ENUM('allocated','completed','cancelled')** DEFAULT 'allocated', timestamps.

**`ptm_meetings`** — `id`, `title`, `school_class_id` SET NULL, `meeting_date`, `time_slot`, `venue`
DEFAULT 'Classroom', `agenda`, `status` **ENUM('scheduled','completed','cancelled')** DEFAULT
'scheduled', timestamps.

### 2.4 Exams

**`exams`** — `id`, `name`, `type` DEFAULT 'unit_test', `academic_year`, `start_date`, `end_date`,
`status` DEFAULT 'scheduled', `published_at`, timestamps. Index `(academic_year, status)`.

**`exam_subjects`** — `id`, `exam_id` CASCADE, `school_class_id` CASCADE, `subject_id` CASCADE,
`exam_date`, `start_time` TIME, `end_time` TIME, `theory_full` DECIMAL(6,2) DEFAULT 100,
`theory_pass` DEFAULT 35, `practical_full` DEFAULT 0, `practical_pass` DEFAULT 0, `marks_status`
DEFAULT 'pending', timestamps. **Unique `(exam_id, school_class_id, subject_id)`.**

**`exam_marks`** — `id`, `exam_subject_id` CASCADE, `student_id` CASCADE, `theory_marks` DECIMAL(6,2),
`practical_marks` DECIMAL(6,2), `is_absent` BOOLEAN DEFAULT false, timestamps. **Unique
`(exam_subject_id, student_id)`.**

### 2.5 Fees & accounts

**`fee_heads`** — `id`, `name` (UNIQUE), `frequency` DEFAULT 'monthly', `is_active` BOOLEAN DEFAULT
true, timestamps.

**`fee_structures`** — `id`, `academic_year`, `school_class_id` CASCADE, `fee_head_id` CASCADE,
`amount` DECIMAL(10,2), timestamps. **Unique `(academic_year, school_class_id, fee_head_id)`.**

**`fee_receipts`** — `id`, `receipt_no` (UNIQUE), `student_id` → `students` **RESTRICT**,
`academic_year`, `receipt_date`, `subtotal`/`discount`/`fine`/`total_due`/`paid_amount`/`balance`
DECIMAL(10,2) DEFAULT 0, `status` DEFAULT 'unpaid', `remarks`, `collected_by` → `users` SET NULL,
`refunded_at`, `refund_reason`, `deleted_at` (soft deletes), timestamps. Indexes `(status,
receipt_date)`, `(student_id, status)`, `academic_year`.

**`fee_receipt_items`** — `id`, `fee_receipt_id` CASCADE, `fee_head_id` SET NULL, `description`,
`period`, `amount`, timestamps.

**`fee_payments`** — `id`, `fee_receipt_id` CASCADE, `amount`, `mode`, `reference_no`, `paid_on`,
`received_by` → `users` SET NULL, timestamps. Index `paid_on`.

**`account_entries`** — `id`, `entry_date`, `type` (income|expense), `category`, `description`,
`amount` DECIMAL(12,2), `mode` DEFAULT 'cash', `reference_no`, `recorded_by` → `users` SET NULL,
`deleted_at` (soft deletes), timestamps. Index `(entry_date, type)`.

### 2.6 Payroll

**`staff_members`** — `id`, `teacher_id` → `teachers` SET NULL (UNIQUE), `name`, `designation`,
`type` DEFAULT 'teaching', `phone`, `joined_on`, `basic_salary` DECIMAL(10,2) DEFAULT 0, `bank_name`,
`bank_account`, `pan_no`, `status` DEFAULT 'active', `user_id` → `users` SET NULL (UNIQUE), `email`,
`gender`, `qualification`, `bio`, `photo`, `show_on_website` BOOLEAN DEFAULT false, `team_group`
DEFAULT 'teaching', `display_order` SMALLINT DEFAULT 0, timestamps. Indexes `(status, type)`,
`(show_on_website, team_group, display_order)`.

**`salary_components`** — `id`, `name` (UNIQUE), `type` (earning|deduction), `calc` DEFAULT 'fixed',
`default_value` DECIMAL(10,2) DEFAULT 0, `is_active` BOOLEAN DEFAULT true, timestamps.

**`staff_salary_components`** (pivot) — `id`, `staff_member_id` CASCADE, `salary_component_id`
CASCADE, `value` DECIMAL(10,2), timestamps. **Unique `(staff_member_id, salary_component_id)`.**

**`salary_slips`** — `id`, `slip_no` (UNIQUE), `staff_member_id` → `staff_members` **RESTRICT**,
`month` VARCHAR(7), `staff_name`, `designation`, `basic`, `working_days` DEFAULT 26, `unpaid_days`
DECIMAL(4,1) DEFAULT 0, `gross`, `total_deductions` DEFAULT 0, `net_pay`, `status` DEFAULT
'generated', `paid_on`, `payment_mode`, `reference_no`, `remarks`, `created_by` → `users` SET NULL,
`paid_by` → `users` SET NULL, `deleted_at` (soft deletes), timestamps. Indexes `(month, status)`,
`paid_on`.

**`salary_slip_lines`** — `id`, `salary_slip_id` CASCADE, `type`, `name`, `amount`, `is_system`
BOOLEAN DEFAULT false, timestamps.

### 2.7 Library, assets, inventory

**`books`** — `id`, `acc_no` (UNIQUE), `title`, `author`, `category`, `shelf`, `total_copies` DEFAULT 1,
`available_copies` DEFAULT 1, timestamps.

**`book_issues`** — `id`, `book_id` CASCADE, `member_type` (free text), `member_name` (free text, no
FK), `issue_date`, `due_date`, `returned_date`, `fine` INT DEFAULT 0, `status` DEFAULT 'issued',
timestamps.

**`assets`** — `id`, `asset_tag` (UNIQUE), `name`, `category`, `location`, `purchase_date`, `cost`
DECIMAL(12,2) DEFAULT 0, `quantity` DEFAULT 1, `available_quantity` DEFAULT 1,
`borrowed_by_student_id` → `students` SET NULL, `maintenance_logs_count` DEFAULT 0,
`maintenance_cost` DECIMAL(10,2) DEFAULT 0, `status` **ENUM('in_use','maintenance','retired','issued')**
DEFAULT 'in_use', `issue_date`, `due_date`, timestamps.

**`asset_loans`** — `id`, `asset_id` CASCADE, `student_id` CASCADE, `issue_date`, `due_date`,
`status` **ENUM('issued','returned','overdue')** DEFAULT 'issued', timestamps.

**`inventory_items`** — `id`, `name`, `category`, `quantity` INT DEFAULT 0, `reorder_level` INT
DEFAULT 5, `unit` DEFAULT 'pieces', timestamps.

**`inventory_movements`** — `id`, `inventory_item_id` CASCADE, `type` **ENUM('in','out','adjust')**
(no default), `quantity` (no default), `balance_after` (no default), `recipient_type`,
`recipient_name`, `supplier`, `bill_number`, `note`, `user_id` → `users` CASCADE (no default),
timestamps.

### 2.8 Communication, content, conduct

**`notices`** — `id`, `title`, `content`, `audience` DEFAULT 'all', `posted_by` (free text, no FK),
`date`, `status` **ENUM('published','draft')** DEFAULT 'published', timestamps.

**`school_events`** — `id`, `title`, `date`, `type` **ENUM('holiday','event','exam')** DEFAULT
'event', `description`, timestamps.

**`school_activities`** — `id`, `activity_date`, `title`, `type` (free text), `in_charge_id` →
`users` CASCADE, `status` **ENUM('scheduled','completed')** DEFAULT 'scheduled', timestamps.

**`discipline_incidents`** — `id`, `student_id` CASCADE, `reported_by` → `teachers` SET NULL,
`incident_date`, `title`, `description`, `location`, `witnesses`, `category` (no default),
`severity` DEFAULT 'medium', `status` DEFAULT 'open', `action_type`, `action_taken`,
`parent_notified` BOOLEAN DEFAULT false, `parent_notified_on`, `notified_via`, `follow_up_date`,
`resolution`, `closed_at`, timestamps. Indexes `incident_date`, `(status, incident_date)`.

**`conduct_registers`** — `id`, `type` **ENUM('merit','demerit')** DEFAULT 'merit', `student_id`
CASCADE, `recorded_by` → `users` CASCADE, `entry_date`, `points` INT DEFAULT 0, `note` TEXT
**NOT NULL (no default)**, timestamps.

**`user_roles`** — `id`, `user_id` CASCADE, `role` VARCHAR(40) (free string), timestamps. **Unique
`(user_id, role)`.** The migration also inserts an `admin` row for every user existing at migration
time (empty on a fresh DB).

**`settings`** — `id`, `key` (UNIQUE), `value` TEXT, timestamps.

### 2.9 Helpdesk, complaints, documents

**`helpdesk_tickets`** — `id`, `ticket_no` (UNIQUE), `subject`, `description`, `category` DEFAULT
'other', `priority` DEFAULT 'medium', `status` DEFAULT 'open', `raised_by` → `users` CASCADE,
`assigned_to` → `users` SET NULL, `attachment_path`, `last_activity_at`, `resolved_at`, `closed_at`,
`deleted_at` (soft deletes), timestamps. Index `(status, priority)`.
*Conditional migration: on an existing DB, columns are only added if missing, so fresh vs upgraded
schemas can diverge.*

**`helpdesk_replies`** — `id`, `helpdesk_ticket_id` CASCADE, `user_id` SET NULL, `type` DEFAULT
'reply', `body` TEXT, `is_internal` BOOLEAN DEFAULT false, `attachment_path`, timestamps.

**`school_complaints`** — `id`, `complaint_no` (UNIQUE), `subject`, `description` (NOT NULL),
`against` DEFAULT 'other', `against_detail`, `severity` DEFAULT 'medium', `status` DEFAULT 'open',
`incident_date`, `filed_by` → `users` CASCADE, `is_anonymous` BOOLEAN DEFAULT false, `assigned_to` →
`users` SET NULL, `is_escalated` BOOLEAN DEFAULT false, `escalated_at`, `resolution_note`,
`attachment_path`, `last_activity_at`, `resolved_at`, `deleted_at` (soft deletes), timestamps.
Index `(status, severity)`.

**`school_complaint_updates`** — `id`, `complaint_id` CASCADE, `user_id` SET NULL, `type` DEFAULT
'comment', `body` TEXT, `is_internal` BOOLEAN DEFAULT false, `attachment_path`, timestamps.

**`school_documents`** — `id`, `title`, `description`, `category` DEFAULT 'general', `audience`
DEFAULT 'all', `file_path`, `file_name`, `file_size`, `mime`, `external_url` VARCHAR(500), `version`
SMALLINT DEFAULT 1, `effective_date`, `expires_on`, `is_published` BOOLEAN DEFAULT true,
`download_count` INT DEFAULT 0, `last_downloaded_at`, `uploaded_by` → `users` SET NULL, `deleted_at`
(soft deletes), timestamps. Indexes `(audience, is_published)`, `expires_on`.

## 3. Model reference

### 3.1 Core

| Model | Table | Key config | Relationships | Notes |
|---|---|---|---|---|
| `User` | `users` | `#[Fillable(['name','email','password','phone','gender','status','photo'])]`, `#[Hidden([...])]`, `casts()` | `staff()` 1:1 StaffMember; `teacher()` 1:1 Teacher; `userRoles()` 1:N (trait) | `HasFactory`, `HasSchoolRoles`, `Notifiable`; `avatarUrl()`, `initials()` |
| `UserRole` | `user_roles` | `fillable: user_id, role` | none | bare model |
| `Student` | `students` | fillable list; no casts | `schoolClass()` belongsTo; `attendances()` hasMany | `booted()` syncs `school_class_id` from `class_applied`; `getFullNameAttribute()` |
| `Teacher` | `teachers` | `assignments => array` | **none** | `getFullNameAttribute()` |
| `ParentModel` | **`parents`** | fillable list | `students()` belongsToMany via `parent_student` | class name is `ParentModel` |
| `SchoolClass` | `school_classes` | `fillable: name, section` | **none** | `findByLabel()` static |
| `Subject` | `subjects` | `assigned_classes => array` | none | no other methods |
| `Admission` | `admissions` | fillable list | none | flat denormalized record |
| `Attendance` | `attendances` | consts + `date => date` | `student()`, `schoolClass()` | |
| `Timetable` | `timetables` | fillable list | `schoolClass()`, `subject()`, `teacher()` | |
| `PeriodSlot` | `period_slots` | `is_break => boolean` | none | |
| `Exam` | `exams` | consts; dates/datetime casts | `examSubjects()` hasMany | |
| `ExamSubject` | `exam_subjects` | float/date/time casts | `exam()`, `schoolClass()`, `subject()`, `marks()` | `getFullMarksAttribute()` |
| `ExamMark` | `exam_marks` | **empty class** (`$guarded=['*']`) | none | effectively not mass-assignable |
| `LessonPlan` | `lesson_plans` | `STATUSES`, `lesson_date => date` | `teacher()`, `schoolClass()`, `subject()` | |
| `TeachingLog` | `teaching_logs` | `COVERAGES`, `log_date => date` | `teacher()`, `schoolClass()`, `subject()`, `lessonPlan()` | |
| `SubstituteAllocation` | `substitute_allocations` | `allocation_date => date` | `absentTeacher()`/`substituteTeacher()` → **User** | relation names say "Teacher" but point to `users` |
| `PtmMeeting` | `ptm_meetings` | fillable list | `schoolClass()` | |
| `SchoolEvent` | `school_events` | fillable list | none | |
| `SchoolActivity` | `school_activities` | `activity_date => date` | `inCharge()` belongsTo User | |
| `Notice` | `notices` | fillable list | none | `posted_by` free text |

### 3.2 Finance, payroll, support

| Model | Table | Key config | Relationships | Notes |
|---|---|---|---|---|
| `FeeHead` | `fee_heads` | `FREQUENCIES`, `is_active => bool` | `structures()` hasMany | |
| `FeeStructure` | `fee_structures` | `amount => float` | `head()`, `schoolClass()` | |
| `FeeReceipt` | `fee_receipts` | `SoftDeletes`, `MODES`, `STATUSES` | `student()`, `items()`, `payments()`, `collector()` | `nextNumber()`, `refreshTotals()`, `alreadyBilled()`, `amountInWords()` |
| `FeeReceiptItem` | `fee_receipt_items` | `amount => float` | `receipt()`, `head()` | |
| `FeePayment` | `fee_payments` | `amount => float`, `paid_on => date` | `receipt()`, `receiver()` | |
| `AccountEntry` | `account_entries` | `SoftDeletes`, category consts | `recorder()` | |
| `Setting` | `settings` | `fillable: key, value` | none | static `get()`/`set()` |
| `StaffMember` | `staff_members` | `TYPES`, `GROUPS`, casts | `teacher()`, `user()`, `components()` belongsToMany, `slips()` | `scopeWebsite()`, `photoUrl()`, `initials()`, `defaultLines()` |
| `SalaryComponent` | `salary_components` | `TYPES`, `CALCS` | none | |
| `SalarySlip` | `salary_slips` | `SoftDeletes`, `STATUSES` | `staff()`, `lines()`, `creator()`, `payer()` | `monthLabel()`, `nextNumber()`, `compute()` |
| `SalarySlipLine` | `salary_slip_lines` | `amount => float`, `is_system => bool` | `slip()` | |
| `HelpdeskTicket` | `helpdesk_tickets` | `SoftDeletes`, `guarded=[]`, consts, `SLA_HOURS` | `raiser()`, `assignee()`, `replies()` | `isActive()`, `isOverdue()`, `scopeOverdue()`, `nextNumber()` |
| `HelpdeskReply` | `helpdesk_replies` | `guarded=[]` | `ticket()`, `user()` | |
| `Complaint` | **`school_complaints`** | `SoftDeletes`, `guarded=[]`, consts, `SLA_HOURS` | `filer()`, `assignee()`, `updates()` | `isActive()`, `isOverdue()`, `scopeOverdue()`, `hidesFilerFrom()`, `filerName()`, `nextNumber()` |
| `ComplaintUpdate` | **`school_complaint_updates`** | `guarded=[]` | `complaint()`, `user()` | |
| `SchoolDocument` | `school_documents` | `SoftDeletes`, `guarded=[]`, consts | `uploader()` | scopes `active/expired/expiring/forRoles`; `statusKey()`, `isPreviewable()`, `sizeLabel()`, `downloadName()`, `shownDate()` |
| `DisciplineIncident` | `discipline_incidents` | consts, casts | `student()`, `reporter()` → Teacher | |
| `ConductRegister` | `conduct_registers` | `entry_date => date` | `student()`, `recorder()` → User | |
| `Book` | `books` | fillable list | `issues()` hasMany | |
| `BookIssue` | `book_issues` | fillable list | `book()` | member stored as free text |
| `Asset` | `assets` | fillable list | `loans()` hasMany **where status=issued** | |
| `AssetLoan` | `asset_loans` | fillable list | `asset()`, `student()` | **file is `Assetloan.php`** |
| `InventoryItem` | `inventory_items` | fillable list | `movements()` hasMany latest | |
| `InventoryMovement` | `inventory_movements` | fillable list | `item()`, `user()` | |

### 3.3 `HasSchoolRoles` trait (attached to `User`)

| Member | Behaviour |
|---|---|
| `userRoles()` | `hasMany(UserRole)` |
| `roleNames()` | cached `pluck('role')` |
| `hasRole(string ...$roles)` | `array_intersect($roles, roleNames())` truthy |
| `hasPermission(string $permission)` | true if any role's `config('permissions.roles.{role}')` contains `*` or the permission |

## 4. Relationship map

```
User ─1:1─ StaffMember ─M:1─ Teacher
  │           ├─M:N─ SalaryComponent (pivot staff_salary_components.value)
  │           └─1:N─ SalarySlip ─1:N─ SalarySlipLine
  ├─1:1─ Teacher
  ├─1:N─ UserRole
  ├─1:N─ FeeReceipt (collected_by) ─1:N─ FeeReceiptItem ─M:1─ FeeHead ─1:N─ FeeStructure ─M:1─ SchoolClass
  │           ├─1:N─ FeePayment (received_by)
  │           └─M:1─ Student ─M:1─ SchoolClass
  │                   ├─1:N─ Attendance
  │                   ├─1:N─ ConductRegister (recorded_by)
  │                   ├─1:N─ DisciplineIncident (reported_by → Teacher)
  │                   ├─1:N─ AssetLoan ─M:1─ Asset
  │                   └─M:N─ ParentModel (pivot parent_student)
  └─ Person FKs from Complaint.filer/assignee, HelpdeskTicket.raiser/assignee,
     ComplaintUpdate/HelpdeskReply.user, AccountEntry.recorder, SchoolDocument.uploader,
     SchoolActivity.inCharge, FeePayment.receiver, SalarySlip.creator/payer,
     InventoryMovement.user, SubstituteAllocation.absent/substituteTeacher (both User)

SchoolClass ─1:N─ {Timetable, Attendance, LessonPlan, TeachingLog, ExamSubject,
                   FeeStructure, PtmMeeting, Student}
Teacher ─1:N─ {Timetable, LessonPlan, TeachingLog, DisciplineIncident}
Subject ─1:N─ {Timetable, LessonPlan, TeachingLog, ExamSubject}
Exam ─1:N─ ExamSubject ─1:N─ ExamMark
HelpdeskTicket ─1:N─ HelpdeskReply
Complaint ─1:N─ ComplaintUpdate   (school_complaints / school_complaint_updates)
InventoryItem ─1:N─ InventoryMovement
Book ─1:N─ BookIssue
```

## 5. Seeders and factories

- `database/factories/UserFactory.php` is the **only factory** (default Breeze user).
- `DatabaseSeeder`:
  - creates 23 `school_classes` (EL1–EL3 A/B, Class 1 A/B/C, Class 2–6 A/B, Class 7–10 A);
  - creates 3 `users` (admin/teacher/student emails) — but `role` and `address` are **not fillable**
    and are silently discarded, so all three get `users.role = 'student'` (DB default);
  - attempts to create one `Student` with **non-existent columns** (`user_id`, `roll_number`,
    `guardian_name`, `guardian_phone`) and omits NOT NULL columns (`adm_number`, `first_name`,
    `last_name`, `dob`, `class_applied`) → **`db:seed` will fail**, leaving partial data;
  - **never creates `user_roles` rows**, so seeded accounts have no roles/permissions.
- `FeeHeadSeeder` and `SalaryComponentSeeder` exist but are **not called** by `DatabaseSeeder`.

## 6. Notable issues (schema/model drift)

1. **Broken seeder** — `Student::create()` uses non-existent columns; `User::create()` drops
   `role`/`address`; no `user_roles` rows are created.
2. **Dual role systems** — `users.role` enum vs `user_roles` table. `HelpdeskController@staff()`
   still reads the legacy column.
3. **Baked academic-year defaults** — `school_classes.academic_year` and `students.academic_year`
   store the migration-time year; both `add_academic_year_*` migrations have empty `down()`.
4. **`ResultService` column drift** — `ADMISSION = 'admission_no'` but the column is `adm_number`.
5. **`AssetController`** reads `roll_number` (non-existent; actual `roll_no`).
6. **No `students.user_id` link** — student/parent *login* accounts are not relationally linked to
   student *profiles* (the schema has no FK), despite `DatabaseSeeder` assuming one.
7. **`Assetloan.php` class/file case mismatch** (PSR-4 breaks on Linux).
8. **`Hallticketcontroller.php` class/file case mismatch** (no model, but same portability class).
9. **Not-mass-assignable `ExamMark`** (`$guarded=['*']`, empty class) yet controllers use
   `updateOrCreate` with attributes — Laravel's `fill()` would silently drop them. (Verify at
   runtime; this is a latent bug.)
10. **`$guarded = []`** on `Complaint`, `ComplaintUpdate`, `HelpdeskTicket`, `HelpdeskReply`,
    `SchoolDocument` — fully open mass assignment.
11. **Pivot `parent_student`** lacks a unique constraint (duplicate links possible).
12. **`sessions.user_id`** has no FK (unlike other `user_id` columns).
13. **Enum vs VARCHAR inconsistency** for the same domain concepts (status/type) across tables.
14. **Duplicate/conditional migrations** (`exam_subjects` time columns, `students.photo`,
    `conduct_registers` columns, conditional `helpdesk_tickets` upgrade path) can yield different
    schemas between fresh and upgraded databases.
15. **Denormalized/free-text references** — `notices.posted_by`, `book_issues.member_name`,
    `substitution` naming, `teachers.assignments` JSON — no FK integrity.
