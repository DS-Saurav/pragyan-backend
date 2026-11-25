# ROUTES_AND_ENDPOINTS

> Evidence basis: `php artisan route:list --except-vendor` (217 routes) and `routes/web.php`,
> `routes/auth.php`, `routes/console.php`. There is **no API**. All routes are web routes.

## 1. Route registration overview

- `routes/web.php` is registered via `bootstrap/app.php` (`withRouting(web: ...)`).
- `routes/auth.php` is **required at the bottom of `web.php`** (not via `withRouting(auth: ...)`).
- `routes/console.php` registers only the default `inspire` command.
- Health check: `GET /up` (registered by `withRouting(health: '/up')`).

### Middleware groups used in `web.php`

```
Public (no middleware)
  GET / , GET /notices , GET /team , + all routes/auth.php

auth + verified + active
  └── dashboard, profile.*, and everything under admin/*
        ├── [staff] group  -> students, classes, admissions, parents, subjects, teachers,
        │                     assets, inventory, library, timetable, attendance, notices, ptm
        ├── [can:*] groups -> calendar, lesson plans, teaching logs, exams, marks, results,
        │                     hall tickets, discipline, conduct, activities, fees, fee-structure,
        │                     payroll, users, accounts, helpdesk, complaints, documents, settings
        └── [staff]        -> substitutes
```

Legend for the tables below: middleware shown is **in addition to** the outer
`auth, verified, active` applied to the whole authenticated group.

## 2. Public routes

| Method | URI | Name | Handler | Notes |
|---|---|---|---|---|
| GET | `/` | `home` | Closure (`web.php:45`) | Lists 3 published notices (`audience` in all/public) into `welcome` |
| GET | `/notices` | `public.notices` | Closure (`web.php:56`) | All published public notices into `frontend.notices` |
| GET | `/team` | `public.team` | `TeamController@index` | `StaffMember::website()` grouped by `team_group` |

## 3. Auth routes (`routes/auth.php`)

| Method | URI | Name | Handler | Middleware |
|---|---|---|---|---|
| GET | `/register` | `register` | `RegisteredUserController@create` | `guest` |
| POST | `/register` | — | `RegisteredUserController@store` | `guest` |
| GET | `/login` | `login` | `AuthenticatedSessionController@create` | `guest` |
| POST | `/login` | — | `AuthenticatedSessionController@store` | `guest` |
| GET | `/forgot-password` | `password.request` | `PasswordResetLinkController@create` | `guest` |
| POST | `/forgot-password` | `password.email` | `PasswordResetLinkController@store` | `guest` |
| GET | `/reset-password/{token}` | `password.reset` | `NewPasswordController@create` | `guest` |
| POST | `/reset-password` | `password.store` | `NewPasswordController@store` | `guest` |
| GET | `/verify-email` | `verification.notice` | `EmailVerificationPromptController` | `auth` |
| GET | `/verify-email/{id}/{hash}` | `verification.verify` | `VerifyEmailController` | `auth`, `signed`, `throttle:6,1` |
| POST | `/email/verification-notification` | `verification.send` | `EmailVerificationNotificationController@store` | `auth`, `throttle:6,1` |
| GET | `/confirm-password` | `password.confirm` | `ConfirmablePasswordController@show` | `auth` |
| POST | `/confirm-password` | — | `ConfirmablePasswordController@store` | `auth` |
| PUT | `/password` | `password.update` | `PasswordController@update` | `auth` |
| POST | `/logout` | `logout` | `AuthenticatedSessionController@destroy` | `auth` |

## 4. Dashboard & profile

| Method | URI | Name | Handler |
|---|---|---|---|
| GET | `/dashboard` | `dashboard` | `DashboardController@index` |
| GET | `/profile` | `profile.edit` | `ProfileController@edit` |
| PATCH | `/profile` | `profile.update` | `ProfileController@update` |
| DELETE | `/profile` | `profile.destroy` | `ProfileController@destroy` |

## 5. Students `[staff]`

| Method | URI | Name | Handler |
|---|---|---|---|
| GET | `admin/students` | `admin.students.index` | `StudentController@index` |
| POST | `admin/students` | `admin.students.store` | `StudentController@store` |
| PUT/PATCH | `admin/students/{student}` | `admin.students.update` | `StudentController@update` |
| DELETE | `admin/students/{student}` | `admin.students.destroy` | `StudentController@destroy` |
| PATCH | `admin/students/{student}/status` | `admin.students.status` | `StudentController@updateStatus` |

Note: `StudentController@promote` exists in code but **has no route** (dead method).

## 6. Classes & admissions `[staff]`

| Method | URI | Name | Handler |
|---|---|---|---|
| GET | `admin/classes` | `admin.classes.index` | `SchoolClassController@index` |
| POST | `admin/classes` | `admin.classes.store` | `SchoolClassController@store` |
| DELETE | `admin/classes/{id}` | `admin.classes.destroy` | `SchoolClassController@destroy` |
| GET | `admin/admissions` | `admin.admissions.index` | `AdmissionController@index` |
| POST | `admin/admissions` | `admin.admissions.store` | `AdmissionController@store` |
| PATCH | `admin/admissions/{admission}/status` | `admin.admissions.status` | `AdmissionController@updateStatus` |
| DELETE | `admin/admissions/{admission}` | `admin.admissions.destroy` | `AdmissionController@destroy` |

## 7. Parents `[staff]`

| Method | URI | Name | Handler |
|---|---|---|---|
| GET | `admin/parents` | `admin.parents.index` | `ParentController@index` |
| POST | `admin/parents` | `admin.parents.store` | `ParentController@store` |
| GET | `admin/parents/export-csv` | `admin.parents.export.csv` | `ParentController@exportCsv` |
| GET | `admin/parents/export-pdf` | `admin.parents.export.pdf` | `ParentController@exportPdf` |
| PUT/PATCH | `admin/parents/{parent}` | `admin.parents.update` | `ParentController@update` |
| DELETE | `admin/parents/{parent}` | `admin.parents.destroy` | `ParentController@destroy` |
| POST | `admin/parents/{parent}/link-child` | `admin.parents.link` | `ParentController@linkChild` |
| DELETE | `admin/parents/{parent}/unlink-child/{student}` | `admin.parents.unlink` | `ParentController@unlinkChild` |

## 8. Subjects & teachers `[staff]`

| Method | URI | Name | Handler |
|---|---|---|---|
| GET | `admin/subjects` | `admin.subjects.index` | `SubjectController@index` |
| POST | `admin/subjects` | `admin.subjects.store` | `SubjectController@store` |
| GET | `admin/subjects/export-csv` | `admin.subjects.csv` | `SubjectController@exportCsv` |
| PUT/PATCH | `admin/subjects/{subject}` | `admin.subjects.update` | `SubjectController@update` |
| DELETE | `admin/subjects/{subject}` | `admin.subjects.destroy` | `SubjectController@destroy` |
| GET | `admin/teachers` | `admin.teachers.index` | `TeacherController@index` |
| POST | `admin/teachers` | `admin.teachers.store` | `TeacherController@store` |
| GET | `admin/teachers/export-csv` | `admin.teachers.csv` | `TeacherController@exportCsv` |
| PUT/PATCH | `admin/teachers/{teacher}` | `admin.teachers.update` | `TeacherController@update` |
| DELETE | `admin/teachers/{teacher}` | `admin.teachers.destroy` | `TeacherController@destroy` |
| POST | `admin/teachers/{teacher}/assign` | `admin.teachers.assign` | `TeacherController@assign` |
| DELETE | `admin/teachers/{teacher}/assignment/{assignmentId}` | `admin.teachers.remove-assignment` | `TeacherController@removeAssignment` |

## 9. Assets & inventory `[staff]`

| Method | URI | Name | Handler |
|---|---|---|---|
| GET | `admin/assets` | `admin.assets.index` | `AssetController@index` |
| POST | `admin/assets` | `admin.assets.store` | `AssetController@store` |
| GET | `admin/assets/export-csv` | `admin.assets.csv` | `AssetController@exportCsv` |
| DELETE | `admin/assets/{asset}` | `admin.assets.destroy` | `AssetController@destroy` |
| POST | `admin/assets/{asset}/issue` | `admin.assets.issue` | `AssetController@issueItem` |
| POST | `admin/assets/{asset}/return` | `admin.assets.return` | `AssetController@returnItem` |
| POST | `admin/assets/barcode-return` | `admin.assets.barcode-return` | `AssetController@barcodeReturn` |
| GET | `admin/inventory` | `admin.inventory.index` | `InventoryController@index` |
| POST | `admin/inventory` | `admin.inventory.store` | `InventoryController@store` |
| GET | `admin/inventory/{inventoryItem}` | `admin.inventory.show` | `InventoryController@show` (JSON) |
| PUT/PATCH | `admin/inventory/{inventoryItem}` | `admin.inventory.update` | `InventoryController@update` |
| DELETE | `admin/inventory/{inventoryItem}` | `admin.inventory.destroy` | `InventoryController@destroy` |
| POST | `admin/inventory/{inventoryItem}/dispense` | `admin.inventory.dispense` | `InventoryController@dispense` |
| POST | `admin/inventory/{inventoryItem}/restock` | `admin.inventory.restock` | `InventoryController@restock` |

## 10. Library `[staff] + can:library.manage`

| Method | URI | Name | Handler |
|---|---|---|---|
| GET | `admin/library/books` | `admin.library.books.index` | `LibraryController@index` |
| POST | `admin/library/books` | `admin.library.books.store` | `LibraryController@storeBook` |
| PUT | `admin/library/books/{book}` | `admin.library.books.update` | `LibraryController@updateBook` |
| DELETE | `admin/library/books/{book}` | `admin.library.books.destroy` | `LibraryController@destroyBook` |
| GET | `admin/library/circulation` | `admin.library.circulation.index` | `LibraryController@circulation` |
| POST | `admin/library/circulation/issue` | `admin.library.circulation.issue` | `LibraryController@issueBook` |
| POST | `admin/library/circulation/return/{issue}` | `admin.library.circulation.return` | `LibraryController@returnBook` |

## 11. Timetable, attendance, notices, PTM `[staff]`

| Method | URI | Name | Handler |
|---|---|---|---|
| GET | `admin/timetable` | `admin.timetable.index` | `TimetableController@index` |
| POST | `admin/timetable` | `admin.timetable.store` | `TimetableController@store` |
| POST | `admin/timetable/periods` | `admin.timetable.periods.store` | `TimetableController@storePeriodSlots` |
| DELETE | `admin/timetable/periods/{period}` | `admin.timetable.periods.destroy` | `TimetableController@destroyPeriodSlot` |
| GET | `admin/attendance` | `admin.attendance.index` | `AttendanceController@index` |
| POST | `admin/attendance` | `admin.attendance.store` | `AttendanceController@store` |
| POST | `admin/attendance/copy-yesterday` | `admin.attendance.copy-yesterday` | `AttendanceController@copyFromYesterday` |
| GET | `admin/notices` | `admin.notices.index` | `NoticeController@index` |
| POST | `admin/notices` | `admin.notices.store` | `NoticeController@store` |
| DELETE | `admin/notices/{notice}` | `admin.notices.destroy` | `NoticeController@destroy` |
| GET | `admin/ptm` | `admin.ptm.index` | `PtmController@index` |
| POST | `admin/ptm` | `admin.ptm.store` | `PtmController@store` |
| PUT | `admin/ptm/{ptm}` | `admin.ptm.update` | `PtmController@update` |
| DELETE | `admin/ptm/{ptm}` | `admin.ptm.destroy` | `PtmController@destroy` |

## 12. Calendar (view for all authenticated; manage gated)

| Method | URI | Name | Handler | Extra middleware |
|---|---|---|---|---|
| GET | `admin/calendar` | `admin.calendar.index` | `SchoolEventController@index` | — (outside `staff`) |
| POST | `admin/calendar` | `admin.calendar.store` | `SchoolEventController@store` | `can:calendar.manage` |
| PUT | `admin/calendar/{schoolEvent}` | `admin.calendar.update` | `SchoolEventController@update` | `can:calendar.manage` |
| DELETE | `admin/calendar/{schoolEvent}` | `admin.calendar.destroy` | `SchoolEventController@destroy` | `can:calendar.manage` |

## 13. Lesson plans `[can:lessonplans.*]`

| Method | URI | Name | Handler | Permission |
|---|---|---|---|---|
| GET | `admin/lesson-plans` | `admin.lesson-plans.index` | `LessonPlanController@index` | `lessonplans.view` |
| GET | `admin/lesson-plans/csv` | `admin.lesson-plans.csv` | `LessonPlanController@csv` | `lessonplans.view` |
| POST | `admin/lesson-plans` | `admin.lesson-plans.store` | `LessonPlanController@store` | `lessonplans.manage` |
| PUT | `admin/lesson-plans/{lessonPlan}` | `admin.lesson-plans.update` | `LessonPlanController@update` | `lessonplans.manage` |
| PATCH | `admin/lesson-plans/{lessonPlan}/status` | `admin.lesson-plans.status` | `LessonPlanController@updateStatus` | `lessonplans.manage` |
| DELETE | `admin/lesson-plans/{lessonPlan}` | `admin.lesson-plans.destroy` | `LessonPlanController@destroy` | `lessonplans.manage` |

## 14. Teaching logs `[can:teachinglogs.*]`

| Method | URI | Name | Handler | Permission |
|---|---|---|---|---|
| GET | `admin/teaching-logs` | `admin.teaching-logs.index` | `TeachingLogController@index` | `teachinglogs.view` |
| GET | `admin/teaching-logs/csv` | `admin.teaching-logs.csv` | `TeachingLogController@csv` | `teachinglogs.view` |
| POST | `admin/teaching-logs` | `admin.teaching-logs.store` | `TeachingLogController@store` | `teachinglogs.manage` |
| PUT | `admin/teaching-logs/{teachingLog}` | `admin.teaching-logs.update` | `TeachingLogController@update` | `teachinglogs.manage` |
| DELETE | `admin/teaching-logs/{teachingLog}` | `admin.teaching-logs.destroy` | `TeachingLogController@destroy` | `teachinglogs.manage` |

## 15. Exams, setup, marks, results, hall tickets

| Method | URI | Name | Handler | Permission |
|---|---|---|---|---|
| GET | `admin/exams` | `admin.exams.index` | `ExamController@index` | `exams.view` |
| POST | `admin/exams` | `admin.exams.store` | `ExamController@store` | `exams.manage` |
| PUT | `admin/exams/{exam}` | `admin.exams.update` | `ExamController@update` | `exams.manage` |
| DELETE | `admin/exams/{exam}` | `admin.exams.destroy` | `ExamController@destroy` | `exams.manage` |
| GET | `admin/exams/{exam}/setup` | `admin.exams.setup` | `ExamSetupController@show` | `exams.manage` |
| PUT | `admin/exams/{exam}/setup` | `admin.exams.setup.save` | `ExamSetupController@save` | `exams.manage` |
| POST | `admin/exams/{exam}/setup/load` | `admin.exams.setup.load` | `ExamSetupController@load` | `exams.manage` |
| POST | `admin/exams/{exam}/setup/add` | `admin.exams.setup.add` | `ExamSetupController@add` | `exams.manage` |
| POST | `admin/exams/{exam}/setup/copy` | `admin.exams.setup.copy` | `ExamSetupController@copy` | `exams.manage` |
| DELETE | `admin/exams/{exam}/setup/{examSubject}` | `admin.exams.setup.remove` | `ExamSetupController@remove` | `exams.manage` |
| POST | `admin/exams/{exam}/publish` | `admin.exams.publish` | `ExamController@publish` | `results.publish` |
| POST | `admin/exams/{exam}/unpublish` | `admin.exams.unpublish` | `ExamController@unpublish` | `results.publish` |
| GET | `admin/marks` | `admin.marks.index` | `MarksEntryController@index` | `marks.view` |
| PUT | `admin/marks/{examSubject}` | `admin.marks.save` | `MarksEntryController@save` | `marks.enter` |
| POST | `admin/marks/{examSubject}/unlock` | `admin.marks.unlock` | `MarksEntryController@unlock` | `marks.lock` |
| GET | `admin/results` | `admin.results.index` | `ResultController@index` | `results.view` |
| GET | `admin/results/{exam}/class/{schoolClass}/ledger` | `admin.results.ledger` | `ResultController@ledger` | `results.view` |
| GET | `admin/results/{exam}/class/{schoolClass}/marksheets` | `admin.results.marksheets` | `ResultController@marksheets` | `results.view` |
| GET | `admin/results/{exam}/student/{student}/marksheet` | `admin.results.marksheet` | `ResultController@marksheet` | `results.view` |
| GET | `admin/hall-tickets` | `admin.hall-tickets.index` | `HallTicketController@index` | `exams.manage` |

**Authorization gap:** `admin.marks.save` is gated by `marks.enter`, but the same action accepts
`action=lock` (locking marks) which conceptually requires `marks.lock`. A user with only
`marks.enter` can lock marks. (Unlocking correctly requires `marks.lock`.)

## 16. Discipline

| Method | URI | Name | Handler | Permission |
|---|---|---|---|---|
| GET | `admin/discipline` | `admin.discipline.index` | `DisciplineController@index` | `discipline.view` |
| GET | `admin/discipline/csv` | `admin.discipline.csv` | `DisciplineController@csv` | `discipline.view` |
| GET | `admin/discipline/{incident}/notice` | `admin.discipline.notice` | `DisciplineController@notice` | `discipline.view` |
| POST | `admin/discipline` | `admin.discipline.store` | `DisciplineController@store` | `discipline.report` |
| PUT | `admin/discipline/{incident}` | `admin.discipline.update` | `DisciplineController@update` | `discipline.manage` |
| PATCH | `admin/discipline/{incident}/close` | `admin.discipline.close` | `DisciplineController@close` | `discipline.manage` |
| PATCH | `admin/discipline/{incident}/notify` | `admin.discipline.notify` | `DisciplineController@notify` | `discipline.manage` |
| DELETE | `admin/discipline/{incident}` | `admin.discipline.destroy` | `DisciplineController@destroy` | `discipline.delete` |

## 17. Conduct & activities

| Method | URI | Name | Handler | Permission |
|---|---|---|---|---|
| GET | `admin/conduct` | `admin.conduct.index` | `ConductController@index` | `conduct.view` |
| POST | `admin/conduct` | `admin.conduct.store` | `ConductController@store` | `conduct.manage` |
| GET | `admin/conduct/{conduct}/edit` | `admin.conduct.edit` | `ConductController@edit` (JSON) | `conduct.manage` |
| PUT/PATCH | `admin/conduct/{conduct}` | `admin.conduct.update` | `ConductController@update` | `conduct.manage` |
| DELETE | `admin/conduct/{conduct}` | `admin.conduct.destroy` | `ConductController@destroy` | `conduct.manage` |
| GET | `admin/activities` | `admin.activities.index` | `ActivityController@index` | `activities.view` |
| POST | `admin/activities` | `admin.activities.store` | `ActivityController@store` | `activities.manage` |
| GET | `admin/activities/{activity}/edit` | `admin.activities.edit` | `ActivityController@edit` (JSON) | `activities.manage` |
| PUT/PATCH | `admin/activities/{activity}` | `admin.activities.update` | `ActivityController@update` | `activities.manage` |
| DELETE | `admin/activities/{activity}` | `admin.activities.destroy` | `ActivityController@destroy` | `activities.manage` |

## 18. Fees & fee structure

| Method | URI | Name | Handler | Permission |
|---|---|---|---|---|
| GET | `admin/fees` | `admin.fees.index` | `FeeController@index` | `fees.view` |
| GET | `admin/fees/csv` | `admin.fees.csv` | `FeeController@csv` | `fees.view` |
| GET | `admin/fees/{receipt}/print` | `admin.fees.print` | `FeeController@receipt` (HTML print) | `fees.view` |
| POST | `admin/fees` | `admin.fees.store` | `FeeController@store` | `fees.collect` |
| POST | `admin/fees/{receipt}/pay` | `admin.fees.pay` | `FeeController@pay` | `fees.collect` |
| POST | `admin/fees/{receipt}/refund` | `admin.fees.refund` | `FeeController@refund` | `fees.refund` |
| DELETE | `admin/fees/{receipt}` | `admin.fees.destroy` | `FeeController@destroy` | `fees.delete` |
| GET | `admin/fee-structure` | `admin.fee-structure.index` | `FeeStructureController@index` | `fees.view` |
| PUT | `admin/fee-structure` | `admin.fee-structure.save` | `FeeStructureController@save` | `fees.structure` |
| POST | `admin/fee-structure/copy` | `admin.fee-structure.copy` | `FeeStructureController@copy` | `fees.structure` |
| POST | `admin/fee-structure/heads` | `admin.fee-structure.heads.store` | `FeeStructureController@storeHead` | `fees.structure` |
| PUT | `admin/fee-structure/heads/{head}` | `admin.fee-structure.heads.update` | `FeeStructureController@updateHead` | `fees.structure` |
| DELETE | `admin/fee-structure/heads/{head}` | `admin.fee-structure.heads.destroy` | `FeeStructureController@destroyHead` | `fees.structure` |
| POST | `admin/fee-structure/generate` | `admin.fee-structure.generate` | `FeeStructureController@generate` | `fees.generate` |

## 19. Payroll

| Method | URI | Name | Handler | Permission |
|---|---|---|---|---|
| GET | `admin/payroll` | `admin.payroll.index` | `PayrollController@index` | `payroll.view` |
| GET | `admin/payroll/csv` | `admin.payroll.csv` | `PayrollController@csv` | `payroll.view` |
| GET | `admin/payroll/print` | `admin.payroll.print-month` | `PayrollController@printMonth` | `payroll.view` |
| GET | `admin/payroll/staff` | `admin.payroll.staff.index` | `StaffController@index` | `payroll.view` |
| GET | `admin/payroll/{slip}/print` | `admin.payroll.slip` | `PayrollController@slip` (HTML print) | `payroll.view` |
| POST | `admin/payroll` | `admin.payroll.store` | `PayrollController@store` | `payroll.manage` |
| POST | `admin/payroll/run` | `admin.payroll.run` | `PayrollController@run` | `payroll.manage` |
| POST | `admin/payroll/staff` | `admin.payroll.staff.store` | `StaffController@store` | `payroll.manage` |
| POST | `admin/payroll/staff/import-teachers` | `admin.payroll.staff.import` | `StaffController@importTeachers` | `payroll.manage` |
| PUT | `admin/payroll/staff/{staff}` | `admin.payroll.staff.update` | `StaffController@update` | `payroll.manage` |
| DELETE | `admin/payroll/staff/{staff}` | `admin.payroll.staff.destroy` | `StaffController@destroy` | `payroll.manage` |
| POST | `admin/payroll/components` | `admin.payroll.components.store` | `StaffController@storeComponent` | `payroll.manage` |
| PUT | `admin/payroll/components/{component}` | `admin.payroll.components.update` | `StaffController@updateComponent` | `payroll.manage` |
| DELETE | `admin/payroll/components/{component}` | `admin.payroll.components.destroy` | `StaffController@destroyComponent` | `payroll.manage` |
| PUT | `admin/payroll/{slip}` | `admin.payroll.update` | `PayrollController@update` | `payroll.manage` |
| DELETE | `admin/payroll/{slip}` | `admin.payroll.destroy` | `PayrollController@destroy` | `payroll.manage` |
| POST | `admin/payroll/pay-all` | `admin.payroll.pay-all` | `PayrollController@payAll` | `payroll.pay` |
| POST | `admin/payroll/{slip}/pay` | `admin.payroll.pay` | `PayrollController@pay` | `payroll.pay` |
| POST | `admin/payroll/{slip}/unpay` | `admin.payroll.unpay` | `PayrollController@unpay` | `payroll.pay` |

## 20. Users

| Method | URI | Name | Handler | Permission |
|---|---|---|---|---|
| GET | `admin/users` | `admin.users.index` | `UserController@index` | `users.view` |
| GET | `admin/users/csv` | `admin.users.csv` | `UserController@csv` | `users.view` |
| POST | `admin/users` | `admin.users.store` | `UserController@store` | `users.manage` |
| PUT | `admin/users/{user}` | `admin.users.update` | `UserController@update` | `users.manage` |
| POST | `admin/users/{user}/status` | `admin.users.status` | `UserController@toggleStatus` | `users.manage` |
| POST | `admin/users/{user}/reset-password` | `admin.users.reset-password` | `UserController@resetPassword` | `users.manage` |
| POST | `admin/users/{user}/website` | `admin.users.website` | `UserController@toggleWebsite` | `users.manage` |
| DELETE | `admin/users/{user}` | `admin.users.destroy` | `UserController@destroy` | `users.delete` |

## 21. Accounts

| Method | URI | Name | Handler | Permission |
|---|---|---|---|---|
| GET | `admin/accounts` | `admin.accounts.index` | `AccountController@index` | `accounts.view` |
| GET | `admin/accounts/csv` | `admin.accounts.csv` | `AccountController@csv` | `accounts.view` |
| GET | `admin/accounts/book` | `admin.accounts.book` | `AccountController@book` (HTML print) | `accounts.view` |
| POST | `admin/accounts` | `admin.accounts.store` | `AccountController@store` | `accounts.manage` |
| PUT | `admin/accounts/{entry}` | `admin.accounts.update` | `AccountController@update` | `accounts.manage` |
| DELETE | `admin/accounts/{entry}` | `admin.accounts.destroy` | `AccountController@destroy` | `accounts.manage` |

## 22. Helpdesk

| Method | URI | Name | Handler | Permission |
|---|---|---|---|---|
| GET | `admin/helpdesk` | `admin.helpdesk.index` | `HelpdeskController@index` | `helpdesk.view` |
| GET | `admin/helpdesk/csv` | `admin.helpdesk.csv` | `HelpdeskController@csv` | `helpdesk.view` |
| GET | `admin/helpdesk/{ticket}` | `admin.helpdesk.show` | `HelpdeskController@show` | `helpdesk.view` |
| POST | `admin/helpdesk/{ticket}/reply` | `admin.helpdesk.reply` | `HelpdeskController@reply` | `helpdesk.view` |
| POST | `admin/helpdesk` | `admin.helpdesk.store` | `HelpdeskController@store` | `helpdesk.create` |
| PATCH | `admin/helpdesk/{ticket}` | `admin.helpdesk.update` | `HelpdeskController@update` | `helpdesk.manage` |
| DELETE | `admin/helpdesk/{ticket}` | `admin.helpdesk.destroy` | `HelpdeskController@destroy` | `helpdesk.delete` |

## 23. Complaints

| Method | URI | Name | Handler | Permission |
|---|---|---|---|---|
| GET | `admin/complaints` | `admin.complaints.index` | `ComplaintController@index` | `complaints.view` |
| GET | `admin/complaints/csv` | `admin.complaints.csv` | `ComplaintController@csv` | `complaints.view` |
| GET | `admin/complaints/{complaint}` | `admin.complaints.show` | `ComplaintController@show` | `complaints.view` |
| POST | `admin/complaints/{complaint}/reply` | `admin.complaints.reply` | `ComplaintController@reply` | `complaints.view` |
| POST | `admin/complaints` | `admin.complaints.store` | `ComplaintController@store` | `complaints.create` |
| PATCH | `admin/complaints/{complaint}` | `admin.complaints.update` | `ComplaintController@update` | `complaints.manage` |
| POST | `admin/complaints/{complaint}/escalate` | `admin.complaints.escalate` | `ComplaintController@escalate` | `complaints.manage` |
| DELETE | `admin/complaints/{complaint}` | `admin.complaints.destroy` | `ComplaintController@destroy` | `complaints.delete` |

## 24. Documents

| Method | URI | Name | Handler | Permission |
|---|---|---|---|---|
| GET | `admin/documents` | `admin.documents.index` | `DocumentController@index` | `documents.view` |
| GET | `admin/documents/csv` | `admin.documents.csv` | `DocumentController@csv` | `documents.view` |
| GET | `admin/documents/{document}/download` | `admin.documents.download` | `DocumentController@download` | `documents.view` |
| POST | `admin/documents` | `admin.documents.store` | `DocumentController@store` | `documents.manage` |
| PUT | `admin/documents/{document}` | `admin.documents.update` | `DocumentController@update` | `documents.manage` |
| PATCH | `admin/documents/{document}/publish` | `admin.documents.publish` | `DocumentController@publish` | `documents.manage` |
| DELETE | `admin/documents/{document}` | `admin.documents.destroy` | `DocumentController@destroy` | `documents.delete` |

## 25. Substitutes `[staff]`

| Method | URI | Name | Handler |
|---|---|---|---|
| GET | `admin/substitutes` | `admin.substitutes.index` | `SubstituteController@index` |
| POST | `admin/substitutes` | `admin.substitutes.store` | `SubstituteController@store` |
| DELETE | `admin/substitutes/{substitute}` | `admin.substitutes.destroy` | `SubstituteController@destroy` |

## 26. Settings & about

| Method | URI | Name | Handler | Permission |
|---|---|---|---|---|
| GET | `admin/settings` | `admin.settings.index` | `SettingController@index` | `settings.view` |
| PUT | `admin/settings` | `admin.settings.update` | `SettingController@update` | `settings.manage` |
| GET | `admin/about` | `admin.about` | `AboutController@index` | — (any staff role; no `can:`) |

## 27. Route-level observations

- **Routes outside the `staff` group but inside `auth`** rely solely on `can:` permissions:
  calendar, lesson plans, teaching logs, exams/marks/results, discipline, conduct, activities,
  fees, payroll, users, accounts, helpdesk, complaints, documents, settings. This is intentional so
  that granted roles (e.g. a `librarian` or `teacher`) can reach them; a **role-less authenticated
  account has none of these permissions and will be denied by the gates**.
- `admin/about` has **no permission middleware**; any authenticated user in the `staff` group
  (i.e. not student/parent) can view it.
- `Route::resource` usages exclude `create/show/edit` in most modules; create/edit forms are modals
  inside the `index` view.
- A few displayed route names in `route:list` use a lowercase class segment
  (`Hallticketcontroller`) but the route file imports `HallTicketController`; the class resolves on
  Windows only (see `IMPLEMENTATION_STATUS.md` portability note).
- `/admin/substitutes` is defined with `->middleware('staff')` again even though it sits at the end
  of the authenticated group (defensive, harmless).
