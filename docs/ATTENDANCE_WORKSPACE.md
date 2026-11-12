# Attendance workspace — 11 November 2026

Open Divisions to browse in the order supplied: Administration, Home Affairs, Finance, Planning, Development, Engineering, Regional Administration Reforms, Internal Audit, Investigation. Other existing divisions follow these nine. Division management remains available through Manage divisions.

Select a division, then an employee, to see their details and every calendar day in a selected month. Month, optional calendar date, and name/service-number/enrollment search are available above the directory. Archived employees retain their history. Missing records are shown explicitly rather than inferred as absence. Employee monthly PDFs, full division monthly attendance sheets, and monthly divisional summaries are available in the workspace. Reports also supports the division filter and divisional summary.

## Evaluation

The supplied image specifies PL Staff 08:00–16:45 and Other Staff 08:30–16:15. Exact positive late seconds are rounded up to minutes. Required OUT is the standard end plus those minutes; covering must occur on that same calendar day. No additional grace minutes were supplied.

The implemented interpretation of “late only twice per month” is that only the first two late arrivals may be cleared by covering. An uncovered late arrival, or any later late arrival even when covered, uses short leave. The first two eligible short leaves are allowed per employee/calendar month; further instances become half-day outcomes. An approved short-leave application also consumes that month's allowance, including when no punches exist on its date. Other Staff morning arrival must be by 10:00 and afternoon departure at or after 14:45. Instances outside these windows become half-days. A day with both arrival and departure exceptions is currently one evaluated daily instance; a separate policy is needed if these must count as two short leaves.

PL short-leave windows were blank in the supplied image, so their conversions are marked Policy review rather than assigned invented cutoffs. Missing/invalid punches and full-day approved leave conflicts require review. Remarks spell out LC — Late Come, SL — Short Leave, HD — Half Day, and the reason for conversion.

Uploads evaluate all affected employee months in chronological order inside the import transaction. Uploading an earlier date therefore updates later allowance usage. Repeated files/punches remain deduplicated. Re-evaluate month refreshes existing records after policy updates. Existing automatic-deduction settings still govern balance postings; evaluation itself always runs. When enabled, each half-day outcome posts 0.5 casual days, with compensating ledger entries on corrections or disabling deductions. The old three-uncovered-lates penalty is superseded.

## Local data

The additive evaluation migration has been applied to the isolated demo SQLite database only. The nine official division names are included in demo setup; existing synthetic employees keep their existing Demo division assignments. Empty official divisions correctly show no employees until employees are assigned using the register. No real employee records or biometric mappings were invented or reassigned. Imported employee_info MySQL tables remain unchanged.

The local PHP checkout was missing six locked PDF dependencies; these were restored using Composer install. In this environment the ZIP extension was enabled for that Composer invocation only.

## Verification

The backend suite passes in isolated in-memory SQLite. It covers monthly resets, the covered-third-late rule, short-leave boundaries, backdated imports, corrections, ledger reversals, division filtering/order, PDF generation, and short-leave approvals/cancellations on dates without punches. Approved short leaves reserve their month's slots even if their dates are later than uploaded exceptions. PHP 8.5 reports existing PDO constant deprecations. The opt-in MySQL integration check is not a production verification.

The frontend production build and TypeScript checks pass. A headless Chrome check authenticated against the running app, verified the ordered directory and 30 daily rows for November, and downloaded a divisional PDF with HTTP 200 and the correct PDF signature; no browser runtime exceptions occurred.
