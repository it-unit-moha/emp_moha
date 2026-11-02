# Architecture

## Repository layout

```text
emp_moha/
|-- backend/                  Laravel API (Composer only)
|   |-- app/
|   |   |-- Enums/
|   |   |-- Http/             Controllers, Requests, Resources
|   |   |-- Models/
|   |   |-- Policies/
|   |   |-- Providers/
|   |   `-- Services/
|   |-- bootstrap/
|   |-- config/
|   |-- database/             Factories, migrations, seeders
|   |-- public/               PHP entry point
|   |-- resources/views/      Framework view directory
|   |-- routes/
|   |-- storage/              Required writable runtime directories
|   `-- tests/Feature/
|-- frontend/                 React application (npm)
|   `-- src/
|       |-- components/       Shared layout and UI primitives
|       |-- features/
|       |   |-- attendance/
|       |   |-- auth/
|       |   |-- dashboard/
|       |   |-- employees/
|       |   |-- leaves/
|       |   |-- master-data/
|       |   |-- reports/
|       |   |-- settings/
|       |   `-- users/
|       `-- lib/              API client and shared response types
|-- database/                 Imported schema references
`-- docs/                     Project and team documentation
```

Generated `frontend/dist`, `frontend/node_modules`, `backend/vendor` and runtime caches are ignored by Git. TypeScript build metadata is written under `frontend/node_modules/.cache/typescript`. The backend does not contain a second Vite/npm application. Module pages belong to their respective feature folders; create asset or public folders when real assets require them.

## Repository boundaries

- `frontend/src/components`: accessible application shell and shared UI primitives.
- `frontend/src/features`: authentication, dashboard, employees, master data and explicit module readiness pages.
- `frontend/src/lib/api.ts`: credentialed Axios client, response types and error normalization.
- `backend/app/Http/Controllers`: HTTP orchestration; Form Requests validate inputs; Resources whitelist employee/master fields.
- `backend/app/Models`: imported-table mappings and relationships, plus application users.
- `backend/app/Services`: employee archive/write logic, protected master deletion, imported-table concurrency, draft leave and schedule rules.
- `backend/app/Policies` and `Enums/Role.php`: authorization boundaries.
- `backend/config/galms.php`: schedule and draft policy configuration.
- `database/`: schema-only reference, never an import or migration source for the live database.

## Authentication

Same-site Sanctum SPA cookie sessions. Axios requests CSRF initialization before login, sends credentials and XSRF headers, and never persists auth tokens. Laravel regenerates the session on login and invalidates it on logout. Passwords are hashed through the model cast. Login is limited to five attempts per minute per email/IP. No registration endpoint or default seed account exists. Unauthorized responses clear frontend private query caches. Logout clears cached data. Every API operation requires authentication except login; all writes have server-side authorization.

Roles: Super Admin and Admin can create/edit/archive employees and create/edit/delete unreferenced master records; HR Officer can create/edit/archive employees and create/edit master records; Authorized Viewer reads only. Account CRUD is not yet implemented. An administrative gate exists for its future workflow; hiding UI controls is never the authorization mechanism.

## Imported tables

Explicit names, integer auto-increment primary keys, disabled Laravel timestamps, verified assignment relationships. MySQL owns imported `inserted_on`/`updated_on`; employee writes whitelist supported fields. Archive changes only `is_active` to zero. Imported attachment/signature/image/QR metadata is not exposed by the employee API.

MyISAM cannot provide rollback transactions or foreign keys. Short database table locks serialize employee assignment validation/writes and master reference checks/deletion. The application user needs LOCK TABLES. Assignment existence is rechecked inside the lock to close the form-validation/delete race. All four imported tables are locked briefly; keep these critical sections small. External systems can still write invalid relationships after locks release, so coordinate their writes. A future InnoDB conversion requires separate data/type/constraint review and explicit approval. It is not included here.

Master deletion checks all employee references, including archived records. No automatic foreign keys or imported-table conversions are attempted. New attendance imports must use dedicated InnoDB tables and transactional storage; no parser is implemented without a real device sample.

## API and frontend data

Versioned REST endpoints, Laravel pagination metadata, validation errors (422), authorization failures (401/403), unavailable import (409) and sanitized database failures (503). TanStack Query owns server state; React Hook Form/Zod validate form input while Laravel remains authoritative. Empty or unavailable attendance values are `null`, never invented zero counts.

The shell supports mobile navigation, a skip link, native focus-trapped modal dialogs and keyboard forms. Recharts has an accompanying accessible data table. UI uses system fonts so the administration portal does not depend on external font requests.

## Extensions requiring specification

Employee category and fingerprint enrollment need reviewed new schema; device imports need verified columns/timezone, identity mapping, duplicate keys, quarantine/error records, import history and transactions. Leave needs eligibility, service category, holiday calendar, working-day semantics, prorating, approvals, balance accrual and audit rules. Automatic deductions always throw until these are approved. Reports and user management need dedicated authorized/audited APIs. Do not move business calculations into React.
