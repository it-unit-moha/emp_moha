# GALMS SRS implementation audit

> Historical pre-implementation snapshot. Substantial operational features have since been added; see [the current implementation status and workflow](IMPLEMENTATION_STATUS.md) for delivered functionality, verification and remaining acceptance items.

Reviewed: 10 November 2026 (Asia/Colombo).

Reference: the user-supplied “Software Requirements Specification (SRS), Home Affairs Division Attendance & Leave Management System (GALMS)” attachment, sections 1–7.

**Conclusion: the project does not yet satisfy the full SRS.** It has working authentication, employee/master-data management and a themed React interface. Attendance ingestion, daily calculations, persisted leave workflows, holiday handling and PDF reports remain incomplete or absent. A navigation item, policy card or configured allowance is not evidence that its operational workflow is implemented.

This review compares application code and current runtime state with the supplied document. It does not independently establish compliance with the Establishments Code or circulars, and it does not inspect or modify the external employee SQL dump or imported employee records. Only this audit document was added for this task.

## Status definitions

- **Implemented:** working code and a route/UI exist for the stated scope, supported by existing test coverage where available.
- **Partial:** some validation, configuration or presentation exists, but the complete SRS workflow does not.
- **Missing:** no operational implementation found in the application code, routes or application schema.
- **Deviation:** implementation differs from the technology or architecture specified by the supplied SRS.
- **Unverified:** evidence is insufficient to establish the requirement.

## Requirement matrix

| SRS | Requirement | Status | Evidence and gap |
|---|---|---|---|
| 1 | Operational attendance and leave system compliant with the stated E-Code/circulars | Unverified / incomplete | The core operational engines are absent. There is no complete requirement-to-rule mapping or compliance acceptance evidence. Matching a few configured numbers is insufficient. |
| 2 | Laravel 11.x backend | Deviation | `backend/composer.json` requires Laravel `^12.0`; the running framework is 12.70.2. README and older verification notes record an earlier approved change, but this supplied SRS still says Laravel 11. Reconcile the document with the approved architecture. |
| 2 | React 18 and TypeScript frontend | Implemented | `frontend/package.json`, `.tsx` feature pages and the TypeScript/Vite build. React dependency is `^18.3.1`. |
| 2 | REST API | Implemented for the delivered scope | Employee/master CRUD, dashboard and session-auth endpoints are registered in `backend/routes/api.php`. Attendance/leave endpoints are foundations rather than operational engines. |
| 2–3 | MySQL 8.0 database named `galms_db`, with self-contained relational storage | Deviation / partial | Current local application uses SQLite `demo.sqlite`. Imported-schema support targets the existing `employee_info` tables and documents MyISAM without foreign keys. MySQL connection support exists, but the specified complete MySQL 8.0 `galms_db` deployment/schema is not established. The SQLite demo was deliberately isolated for testing. |
| 2 | Attendance, leave-ledger and audit-log storage | Missing | Current migrations create Laravel infrastructure and user roles. No application attendance, leave-ledger, holiday or audit-log tables were found. The active database table listing confirms their absence. Framework log files are not a business audit trail. |
| 2, 7 | DomPDF or Snappy server-side PDF engine | Missing | Neither package is declared in `backend/composer.json`; no PDF controller, export route or PDF templates were found. |
| 2, 7 | Required scheduled tasks | Missing | `php artisan schedule:list` reports no scheduled tasks. Queue infrastructure tables do not implement the required report scheduling. |
| 3 | Add, view, update and archive employee records | Implemented for existing fields | `EmployeeController`, `EmployeeService`, employee API routes and `EmployeePage.tsx`; archive sets `is_active=0`. Existing tests exercise create/read/update/archive. |
| 3 | Full name, NIC, file/service number, email, department/unit and designation | Implemented with mapping caveat | `name`, `nic`, `emp_no`, `email`, `division`, `unit`, `designation` are exposed by `EmployeeRequest` and `EmployeeResource`. Confirm that `emp_no` is the intended official file/service number; there is no separate service-file-number field. |
| 3 | Service grade | Missing | No grade field in the employee model, validation, resource or form. Designation does not provide a separate service grade. |
| 3 | Unique fingerprint enrollment mapping (`enroll_id`) | Missing | No enrollment field, uniqueness constraint, employee mapping interface or device matching service. `qr_code` in the imported schema is not a fingerprint enrollment mapping. |
| 3 | Assign PL Staff versus Other Staff to each employee | Missing | Configuration names the categories, but employees have no persisted staff-category field or assignment control. |
| 3 | Initialize casual leave balances and vacation accumulation on employee creation | Missing | `EmployeeService::create()` validates assignments and inserts an active employee. It does not create a leave ledger or balance entries. |
| 4 | PL schedule 08:00–16:45 | Partial | Correct start/end strings exist in `backend/config/galms.php` and are displayed on the attendance page. They are not assigned to employees or used by a daily calculation engine. |
| 4 | Other Staff schedule 08:30–16:15 | Partial | Correct start/end strings exist, with the same missing employee assignment and evaluation workflow. |
| 4 | Working time, grace period and category-based eligibility | Missing | `AttendanceRulesService::schedule()` only returns a configured schedule. There is no working-time/grace evaluation. The SRS does not specify the numeric standard grace period. |
| 4, 6.1 | Other Staff morning/afternoon short-leave windows | Missing | No category-aware short-leave request workflow or window validation. The 10:00 / 14:45 references are not implemented as operational rules. |
| 5.1–5.2 | Administrator upload screen for TXT/DAT/CSV exports | Partial | The attendance page displays import readiness but has no working file-picker/upload wizard. The backend import endpoint exists and validates the submitted file before refusing persistence. |
| 5.2 | Upload file validation | Partial | `AttendanceImportRequest` validates allowed file types/extensions and a 10 MB limit with write-role authorization. It does not validate actual enrollment/timestamp/punch-state record structure. |
| 5.3 | Parse device exports and match enrollment IDs | Missing | `FoundationController::import()` returns HTTP 409 after validation. It explicitly saves no file or records. No device parser or mapping implementation exists. |
| 5.3 | Detect duplicate punches | Missing | No attendance punch identifiers, deduplication constraints or operational deduplication service. |
| 5.3 | Persist raw IN/OUT punches in `attendance_logs` | Missing | No `attendance_logs` migration/model/persistence flow. |
| 5.4 | Automatically calculate the uploaded date range immediately | Missing | No import-to-calculation pipeline or daily attendance summaries. Dashboard attendance values are deliberately `null`, not computed. |
| 6.1 | Morning and afternoon short-leave applications | Missing | Leave page presents policy values and a disabled-workflow message; there is no application endpoint or persisted request model. |
| 6.1 | 90-minute short-leave cap | Partial | `LeaveRulesService::validateShortLeave()` rejects values above 90, but is not wired to an operational application workflow. |
| 6.1 | Maximum two short leaves in each calendar month | Partial | Helper rejects a supplied count of two or more. It does not derive the employee/month count from saved requests because those records do not exist. |
| 6.1 | Block third request and redirect to half-day casual leave | Missing | No request flow, redirection/conversion, ledger entry or approval handling. A boolean helper returning false does not implement this requirement. |
| 6.2 | Calculate late minutes from actual IN and category start | Missing | No calculation method or persisted daily attendance result. |
| 6.2 | Same-day covering; required OUT = end time + late minutes | Missing | No required-OUT or same-day matching/evaluation logic. |
| 6.2 | Automatically mark “Late Covered” | Missing | No late-covering status calculation or storage. |
| 6.2 | Each three uncovered late days per month deducts 0.5 casual day | Missing / explicitly disabled | Configuration contains a threshold of three, but automatic deductions are false. `LeaveRulesService::applyLatePenalty()` throws a `DomainException`; there is no monthly counting or ledger deduction. |
| 6.3 | Casual 21, vacation 24/28, maternity 84 working days, paternity 3 days | Partial | Numbers exist in configuration and UI. Eligibility, requests, balance initialization, debits, accrual and history are not implemented. |
| 6.3 | Lapsed leave | Missing | No lapsed-leave rules, storage or workflow. |
| 6.3 | Admin-managed public and mercantile holiday calendar | Missing | No holiday table/model/API/admin editor. Settings is a readiness/account-information page, not a holiday calendar. |
| 6.3 | Exclude holidays from leave deductions and attendance deficits | Missing | No business calendar integration or deficit/deduction engine. |
| 6.3 | Domestic leave excludes weekends/holidays; foreign vacation includes them | Missing | No domestic/foreign request distinction or date-counting engine. |
| 7 | Monthly employee attendance sheet PDF | Missing | Reports page is readiness-only; no PDF generation or download endpoint. |
| 7 | Department-wise leave utilization PDF with opening/used/remaining balances | Missing | Neither ledger data nor the report generator exists. |
| 7 | Late penalty warning PDF on the 25th for officers with 3+ uncovered late instances | Missing | No uncovered-late aggregation, PDF implementation or scheduled task. |

## Working foundation outside the missing engines

Session authentication, CSRF-aware login/logout, password hashing, login throttling and role-based employee/master permissions exist. Employee search, pagination, sorting, assignment filters and safe archive work. Division, unit and designation CRUD includes checks that prevent deleting referenced records. The dashboard shows actual employee/master counts and employee distribution; attendance figures remain unavailable.

The shared emerald UI and GSAP transitions are presentation improvements. They do not supply the missing backend business workflows. User Management and editable Settings also remain readiness pages; their presence in navigation should not be counted as completed administrative CRUD.

## Verification performed for this audit

1. Inspected backend controllers, requests, models, services, policies, configuration, migrations, API/console routes and Composer dependencies; inspected React pages and related documentation.
2. Ran `php artisan route:list --path=api`: 17 registered route entries. Generic master-data routes cover divisions, units and designations. No operational holiday, leave-application, balance, report-download or enrollment routes were found.
3. Ran `php artisan schedule:list`: no scheduled tasks defined.
4. Booted Laravel and read connection metadata and table names only: framework 12.70.2, SQLite `demo.sqlite`; tables are `cache`, `cache_locks`, `designation`, `division`, `employee_data`, `failed_jobs`, `job_batches`, `jobs`, `migrations`, `password_reset_tokens`, `sessions`, `unit`, `users` (reported with SQLite's `main.` prefix).
5. Ran `php vendor/bin/phpunit --display-skipped`: 12 tests discovered, 90 assertions, no failures; one opt-in MySQL integration test skipped; two deprecations reported under PHP 8.5.12. The default tests use isolated in-memory SQLite. They verify the existing foundation and intentionally disabled imports/penalties; passing them does not establish SRS completeness. No MySQL test or production import was performed for this audit.
6. Frontend production build and workspace browser checks passed in the immediately preceding theme task. They were not repeated here because this task changes only documentation. They do not validate the absent engines.

## Recommended implementation order

1. Reconcile architecture and schema: document the approved Laravel version, select the intended MySQL 8.0 `galms_db` deployment, and plan preservation/mapping of imported employee data. Define transactional attendance/leave/audit tables and migrations.
2. Finish employee fields: service grade, unique enrollment ID and category; add validated mappings and initialize employee leave ledgers.
3. Implement manual attendance upload using actual device samples: structural validation, row feedback, enrollment matching, duplicate handling, atomic persistence and date-range evaluation.
4. Build the daily engine: category schedules, missing-punch handling, late minutes, same-day covering and reviewable monthly uncovered-late results.
5. Build holiday/calendar and leave workflows: short-leave types, persisted monthly limits, third-request handling, domestic/foreign date counting, eligibility, accrual/lapsed leave and ledger history. Apply automatic penalties only through the specified, validated and auditable workflow.
6. Add all three server-side PDF reports and the 25th-of-month task, with end-to-end acceptance tests spanning import through calculation, leave ledger and reports.

## Decisions still needed before rules can be accepted

- The SRS writes “8.45 hours” and “7.45 hours”; the listed clock windows span 8 hours 45 minutes and 7 hours 45 minutes (8.75 and 7.75 decimal hours). Define treatment of breaks and the intended representation.
- Specify the grace period, precise short-leave windows, eligible categories and monthly counting rules (for example, whether cancelled/rejected requests count).
- Specify vacation eligibility/accrual/lapse rules, the leave year, partial-day handling, insufficient casual balances and deduction reversals.
- Supply actual device exports and define device clock/time zone, punch-state meanings, multiple-device duplicates, missing punches and overnight/operational-duty handling.
- Establish the applicable circulars and a signed-off rule-to-source mapping. This audit cannot certify statutory compliance.
- Clarify the warning report's intended scope: uncovered late instances through the 25th, and how later-month or corrected records affect the final monthly penalty.

## Main source references

- [API routes](../backend/routes/api.php)
- [Foundation controller: explicit disabled operations](../backend/app/Http/Controllers/FoundationController.php)
- [Employee validation and available fields](../backend/app/Http/Requests/EmployeeRequest.php)
- [Employee create/update/archive service](../backend/app/Services/EmployeeService.php)
- [Configured schedules and draft leave values](../backend/config/galms.php)
- [Attendance schedule helper](../backend/app/Services/AttendanceRulesService.php)
- [Short-leave helper and disabled penalty method](../backend/app/Services/LeaveRulesService.php)
- [Attendance UI](../frontend/src/features/attendance/AttendancePage.tsx)
- [Leave UI](../frontend/src/features/leaves/LeavePage.tsx)
- [Report readiness UI](../frontend/src/features/reports/ReportsPage.tsx)
- [Feature tests](../backend/tests/Feature/GalmsTest.php)
- [Demo setup](../backend/tools/setup-demo.php)
- [Imported schema reference](../database/imported-schema.sql)

Older setup/verification documents describe the original MySQL configuration and earlier environment. For the current local connection and demo setup, use the runtime findings above and [DEMO.md](DEMO.md); do not treat historical environment notes as current deployment evidence.
