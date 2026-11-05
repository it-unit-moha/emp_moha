# GALMS

**Home Affairs Division Attendance & Leave Management System**

Two independent applications in one repository: React 18 + TypeScript + Vite in `frontend/`, and Laravel 12 + Sanctum in `backend/`. Laravel 12 replaces the initially requested Laravel 11 with project-owner approval because Composer blocked Laravel 11 on security advisories. PHP 8.2 remains supported by the selected framework.

## Start here

See [Setup Guide](docs/SETUP_GUIDE.md) for configuration, migration review and first administrator provisioning.

After installing dependencies, configuring `.env`, reviewing/applying the new application migrations and creating your administrator:

```powershell
# Terminal 1, from repository root
cd backend
php artisan serve --host=localhost --port=8000
```

```powershell
# Terminal 2, from repository root
cd frontend
npm.cmd run dev
```

Open **http://localhost:5173**. Use `localhost` for both applications so Sanctum cookies share the same host. No default accounts, public registration or browser-stored tokens.

## Available functionality

- Session login/logout, current user, protected routes, four role levels and policy enforcement.
- Employee search, filters, sorting, pagination, create, details, edit and safe archive (`is_active=0`). Actual imported column mappings and assignment validation.
- Division, unit and designation lists, forms and API CRUD; referenced master records cannot be deleted, including references from archived employees.
- API-backed employee/division/unit counts and employee distribution chart. Attendance statistics remain unavailable rather than showing invented zeroes.
- Responsive navy/white administrative layout, keyboard-accessible forms/dialogs, toast feedback and explicit error/loading/empty states.

## Deliberately unfinished modules

Attendance ingestion, duplicate detection, import history, fingerprint enrollment, attendance calculations and missing-punch review await a real device export and reviewed new tables. Upload validation exists, but parsing/persistence returns 409 and saves nothing. Leave application/approval/balance/history/deduction workflows await stakeholder-approved rules. Configurable draft policies and schedule services are provided. Reports, account administration and editable settings have readiness pages, not completed CRUD.

Employee category/fingerprint fields do not exist in the imported schema and have not been invented. No automatic leave penalty is enabled.

## Database safety

`employee_info` and its four imported MyISAM tables are preserved. No imported-table migration or data export was created. New application migrations are available for review and were exercised in isolated test databases; they are not applied to the local imported database. An empty Laravel `migrations` tracking table was created by `migrate --pretend`; see [verification](docs/VERIFICATION.md).

## Developer documentation

- [Architecture](docs/ARCHITECTURE.md)
- [API documentation](docs/API_DOCUMENTATION.md)
- [Database schema](docs/DATABASE_SCHEMA.md)
- [Team workflow](docs/TEAM_WORKFLOW.md)
- [Verification and limitations](docs/VERIFICATION.md)

All changes remain uncommitted on `development`. No branch was created or pushed.
