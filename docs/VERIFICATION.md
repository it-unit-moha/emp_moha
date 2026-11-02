# Verification record ? 2026-11-02

## Environment

Clean initial repository on development with only README.md; no Laravel, React or SQL dump existed. PHP 8.2.12, Composer 2.10.3 (invoked as PHP archive), Node 24.3.0, npm 11.4.2 and XAMPP MariaDB 10.4.32 were inspected. Required PHP extensions, including PDO MySQL/SQLite, are present. MySQL CLI is available at C:/xampp/mysql/bin/mysql.exe but not initially on PATH.

Laravel 11 template downloaded successfully, but Composer blocked all matching framework releases due to security advisories. The owner approved using a supported version. Laravel 12.70.2 and Sanctum 4.3.3 were installed using the official Laravel application structure. No nested Git repository, commit, push or branch creation occurred.

## Executed checks

- Frontend dependency installation succeeded. React 18.3.1 is retained. Router, Zod and resolvers were updated to compatible patched versions; Recharts was upgraded to its maintained major. npm installation audit reported zero vulnerabilities after the upgrades.
- React TypeScript production build succeeded; dashboard/chart code is loaded separately. Dependency comments from Zod produce harmless Rollup annotation warnings, which are removed during bundling.
- Frontend source formatted with Prettier and backend with Laravel Pint.
- Composer dependency installation succeeded, lockfile is included in the uncommitted changes, composer.json validates strictly, and installed PHP requirements are compatible. Composer reported no security advisories during installation.
- Laravel boots and registers 17 API route entries, including dynamic constrained master routes covering divisions/units/designations.
- Default suite: 11 passing tests, 90 assertions; the opt-in MySQL test is skipped by default. Tests exercise login/logout/current user, role restrictions, password hashing, employee CRUD/archive/filter/sort, master CRUD/reference checks, validation, dashboard data, leave boundaries and rejected import persistence.
- Separate opt-in MariaDB integration run: one test, 26 assertions passed using exact copies of imported table definitions and synthetic data. It exercised employee create/read/update/archive, all three master create/update/delete operations, references blocking deletion and actual MyISAM locking. Retained test database: galms_test_20261102_090851_baa948.
- Read-only live database/model/resource verification succeeded: 780 employees, 6 divisions, 18 units, 64 designations; no imported records were written.
- HTTP: React HTML 200, Laravel /up 200, unauthenticated /api/v1/user 401, /sanctum/csrf-cookie 204, and login without CSRF 419.
- The browser preview tool failed to initialize with a kernel-assets path error. No screenshots, full browser interaction tests, mobile visual review or browser accessibility audit were completed.

## Database effects and pending setup

No imported table was replaced, deleted, recreated, truncated or altered. No employee_info records were created or changed. Laravel's migrate --pretend unexpectedly initialized an empty migrations tracking table before printing SQL; this side effect is preserved and disclosed. Application migration bodies were not run on employee_info. New application table migrations still require review/application and secure administrator provisioning before local sign-in can succeed.

The isolated MySQL test database remains for review, containing synthetic data and an ephemeral hashed test account. Normal backend .env still targets employee_info. Default SQLite tests are in memory and have a hard connection guard.

## Not implemented / not verified

This is a working development foundation with API-tested employee/master CRUD, not a production-complete attendance/leave system. Pending: real device parser, biometric mapping, import history/duplicate detection/transactions, daily attendance computation, leave persistence/eligibility/holiday calendar/approvals/balances/penalties, reporting exports, account-management CRUD and editable system configuration. No fake operational metrics or automatic deductions are enabled.

MyISAM, latin1 master-table encoding and existing unconstrained relationships require a separately reviewed database modernization plan before production. Multi-writer coordination remains necessary. Full end-to-end browser authentication/CRUD against employee_info was deliberately not performed; this would require approved application migrations and real administrator provisioning.
