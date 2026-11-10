# Operational workflow implementation

Updated 10 November 2026. This supersedes the missing-feature findings in the earlier [SRS audit](SRS_REQUIREMENTS_AUDIT.md) for the functionality listed below. It does not certify statutory compliance or claim that every unresolved policy rule is implemented.

## Correct operating sequence

1. Open **Workflow & setup**. Configure divisions, units and designations.
2. Create or complete each **Employee register** profile: file/service number, service grade, staff category (`pl` or `other`), unique device enrollment ID and a verified vacation allowance of 24/28 days. Unassigned categories/enrollment IDs block attendance import for those employees.
3. Configure **Public holidays**, then initialize the leave year. Casual opening is 21 days; vacation opening uses the explicitly assigned allowance. Existing accounts are never overwritten. Maternity, paternity and lapsed opening credits require a recorded administrator adjustment after eligibility review.
4. In **Attendance & upload**, upload validated punches and review daily results and import history.
5. In **Leave & balances**, submit requests, approve/reject eligible requests, inspect balances and ledger history, and record documented accrual/lapse/opening adjustments. An administrator can cancel an approval; the original debit is preserved and a compensating credit is posted.
6. Review **Settings** and explicitly enable the SRS late-penalty rule only after the operating rules are verified. Enabling reconciles existing imported summaries; disabling reverses automatic penalties through compensating entries. Neither operation deletes history.
7. Generate **PDF reports** and inspect **Audit history**. Admin accounts can create accounts, assign roles and disable access through **User management**. Accounts are disabled rather than deleted so historical actor IDs remain traceable.

The sidebar now follows Overview → Setup → Operations → Administration, replacing the old index-based grouping. The dashboard uses imported attendance results rather than a permanent placeholder. Absence is not inferred from an incomplete upload or unknown roster.

## Delivered functionality

| Area | Current implementation |
|---|---|
| Schema | Additive employee profiles, holidays, import batches, raw punches, daily summaries, leave accounts/applications/ledger, audit records, settings and private report snapshots. Fresh databases get an InnoDB-capable employee/master register, including foreign keys when installed entirely from scratch. Existing imported tables are left intact. |
| Employees | Service grade, unique enrollment ID, PL/Other category, optional verified vacation entitlement; API validation, editor/details and profile-aware attendance recalculation. Creation initializes missing annual accounts. |
| Import | Explicit CSV header/record validation; enrollment matching to active categorized employees; all-or-nothing validation; unique file hashes and punch keys; duplicate counts; batch history; immediate daily recalculation. |
| Daily calculation | PL 08:00–16:45; Other 08:30–16:15. Gross late minutes are rounded up from elapsed seconds. Required OUT is category end plus lateness on the same date. First IN/last OUT are used; missing/reversed punches are marked for review. Weekend/holiday punches are excluded from uncovered-late penalties. |
| Late penalties | Opt-in setting, off by default. Each three uncovered late days per employee/month produces a 0.5-day casual debit. Account/profile locking, target-versus-posted reconciliation and append-only correction credits/debits prevent duplicate deductions. Negative resulting balances remain visible for administrative review. |
| Short leave | Morning/afternoon types, Other Staff only, one working date, 1–90 minutes, max two pending/approved requests per employee/month. A third request is rejected and the form prepares a half-day casual request for the user to review and submit explicitly. Exact clock windows remain an approval check pending clarification. |
| Leave | Persisted applications, overlap checks, pending/approved/rejected/cancelled statuses, admin approval, sufficient-balance checks and ledger entries. Casual half-days supported. Domestic requests exclude configured holidays/weekends; foreign vacation counts calendar days. Maternity/paternity requests are capped at 84/3 chargeable days respectively, subject to verified credits. |
| Holiday corrections | Create/remove public or mercantile dates and recalculate attendance/penalties. Dates covered by approved leave cannot be changed silently: cancel the approval, correct the calendar and resubmit/reapprove. Pending leave is recounted against the current calendar at approval. |
| Reporting | DomPDF downloads for monthly attendance (each calendar date, IN/OUT, lateness, required departure, status, approved short leave/daily leave allocations, monthly penalty entries), department-grouped leave utilization and late warnings. Utilization uses ledger-posting dates and Asia/Colombo month boundaries. |
| Scheduling | `galms:late-warning` generates a private PDF snapshot. Laravel schedule defines the 25th, 09:00 Asia/Colombo, with overlap protection. Authenticated snapshot downloads are available. |
| Access and audit | Existing session/CSRF/role mechanisms retained. Admin-only holiday/credit/policy/account/audit/review operations; write roles import/request leave; viewers cannot mutate. Disabled users cannot log in or call protected APIs. Self-demotion/self-disabling is blocked. Audit records never store passwords. |

## Attendance file contract

Use comma-separated CSV content, including when the filename ends in `.txt` or `.dat`:

```csv
enroll_id,timestamp,state
DEMO001,2026-11-02 08:45:00,IN
DEMO001,2026-11-02 16:20:00,OUT
```

Timestamps are local Asia/Colombo wall-clock times in `YYYY-MM-DD HH:MM:SS`; states are the literal strings `IN` and `OUT`. Leading zeroes in enrollment IDs are preserved. Unknown IDs, inactive/unassigned employees, malformed timestamps, unsupported states and malformed rows reject the whole file with row feedback (up to 20 errors). Limits: 10 MB / 50,000 punches. Duplicate punches within or across files are skipped; an identical file is not reimported.

This is a published interchange contract, **not an assertion that an unspecified fingerprint machine exports this exact format**. Native numeric punch codes, separators, device IDs and overnight shifts need real device samples and an explicit adapter. Do not rename an arbitrary vendor file and assume it will parse.

The synthetic example is [demo-attendance.csv](../frontend/public/samples/demo-attendance.csv), also served locally at `/samples/demo-attendance.csv`. It uses only demo enrollment IDs. `tools/setup-demo.php` now adds those mappings only to synthetic `DEMO-*` employees and preserves existing profiles/passwords/accounts. Browser verification imported this file and approved one synthetic half-day request, so the demo includes real test workflow records.

## Install and run

Use the existing Laravel 12/React 18 setup. Laravel 12 was retained under the previously recorded approval; this task did not downgrade it to Laravel 11.

```powershell
cd backend
php composer.phar install
php artisan migrate
php artisan serve --host=localhost --port=8000
# From frontend/ in another terminal:
npm.cmd ci
npm.cmd run dev
```

Use the installed Composer executable or your own Composer archive path; `composer.phar` above is an example. The current machine required `php -d extension=zip <composer-path> install` to enable archive extraction for that command. No system PHP configuration was edited. DomPDF and its dependencies are locked in `composer.lock`.

The local app remains on **isolated SQLite demo.sqlite**. The additive migrations were applied there only. The imported `employee_info` database and the external SQL dump were not modified. A real MySQL 8.0 `galms_db` deployment still needs valid credentials and deployment verification; those credentials were unavailable. Fresh-install migrations were tested with SQLite; current MySQL execution/concurrency remains unverified.

Existing MyISAM tables still use short legacy locks for register writes; they cannot provide cross-table transactional rollback. Newly created InnoDB registers use transactions. Preserve/back up and migrate imported records into the fresh relational deployment through a reviewed import procedure before production use. Do not run `migrate:fresh` or destructive resets against imported data. The register-creation migration is intentionally forward-only on rollback.

For scheduled reports, the host must invoke Laravel's scheduler. For a local development session:

```powershell
cd backend
php artisan schedule:work
```

Production hosting should invoke `php artisan schedule:run` every minute using its task service. The task definition and PDF generation were tested, but no operating-system scheduled task/service was installed. `php artisan galms:late-warning --month=2026-11` can generate a snapshot manually; the demo contains one such manually generated snapshot.

## Verification

- Backend suite: 21 tests discovered, 195 assertions, no failures; one opt-in MySQL integration test skipped; two PHP 8.5 deprecations remain. The tests cover the original CRUD/auth foundation plus new enrollment uniqueness, opening accounts, import atomicity/deduplication, missing punches, covering correction, append-only penalty reconciliation, short-leave limits, half-day approval, domestic/foreign counting, holidays, all three PDF responses, policy switching, snapshots, account controls, leave cancellation and a fresh installation.
- Frontend TypeScript/Vite production build succeeds. Existing Zod annotation and bundle-size warnings remain; changed frontend files are formatted.
- Browser verification: every new operational route opens; synthetic attendance upload produces daily summaries; a half-day casual application is approved; a PDF downloads; new pages fit mobile widths. Account creation/disabling, accessible enrollment controls and new routes at 320px were also verified with no browser runtime errors.
- No claim of legal/statutory certification or production acceptance is made by these tests.

## Still needs policy/device/deployment input

1. Real device export samples and punch-code definitions. The strict CSV interchange is functional; a vendor-specific adapter cannot be verified without them.
2. Numeric grace period, transport/operational-duty exceptions, exact short-leave time windows, overnight handling and any roster-dependent deficit rules. Gross SRS late calculations are implemented; missing/leave-conflicting punches stay under review. Those review flags do not silently authorize a deduction.
3. Verified vacation accrual, carry-forward/lapse rules and maternity/paternity eligibility and documentation. Administrators can record audited adjustments/credits now; no unspecified automatic accrual/lapse algorithm is invented.
4. Production MySQL credentials, imported-data migration, multi-user/concurrency verification, host scheduler activation and business/legal acceptance against the applicable circulars.

These are explicit remaining acceptance items, not features that the refreshed UI should be assumed to have completed automatically.
