# GALMS

**Home Affairs Division Attendance & Leave Management System**

Two independent applications in one repository: React 18 + TypeScript + Vite in `frontend/`, and Laravel 12 + Sanctum in `backend/`. Laravel 12 replaces the initially requested Laravel 11 with project-owner approval because Composer blocked Laravel 11 on security advisories. PHP 8.2 remains supported by the selected framework.

## Current operational workflow

See [Implementation status](docs/IMPLEMENTATION_STATUS.md) for the corrected setup-to-report sequence, implemented attendance/leave/PDF workflows, test evidence and remaining policy/device/deployment inputs. The original readiness descriptions below are historical and are superseded by that document.

## Start here

See [Setup Guide](docs/SETUP_GUIDE.md) for configuration, migration review and first administrator provisioning.

After installing dependencies, configuring `.env`, reviewing/applying the new application migrations and creating your administrator:

```powershell
# Terminal 1, from repository root
cd backend
powershell -NoProfile -ExecutionPolicy Bypass -File tools/start-local.ps1
# Alternative on other platforms: php artisan serve --host=localhost --port=8000
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

## Operational modules and remaining inputs

Employee enrollment/category/grade, strict attendance CSV ingestion, duplicate protection, daily late-covering calculations, holiday configuration, leave requests and approvals, audited balances, opt-in penalty reconciliation, PDF exports, scheduled-report definitions and account controls are implemented. Follow the [workflow guide](docs/IMPLEMENTATION_STATUS.md).

Native device adapters, unspecified grace/short-leave windows, automatic vacation accrual/lapse rules, statutory acceptance and production MySQL/scheduler deployment remain acceptance items. The current app is an isolated SQLite demo; imported employee data is preserved.

## Database safety

`employee_info` and its four imported MyISAM tables are preserved. No imported-table migration or data export was created. New application migrations are available for review and were exercised in isolated test databases; they are not applied to the local imported database. An empty Laravel `migrations` tracking table was created by `migrate --pretend`; see [verification](docs/VERIFICATION.md).

## Developer documentation

- [Architecture](docs/ARCHITECTURE.md)
- [API documentation](docs/API_DOCUMENTATION.md)
- [Database schema](docs/DATABASE_SCHEMA.md)
- [Team workflow](docs/TEAM_WORKFLOW.md)
- [Verification and limitations](docs/VERIFICATION.md)

All changes remain uncommitted on `development`. No branch was created or pushed.
