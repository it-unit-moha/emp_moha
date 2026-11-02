# API v1

Backend origin: `http://localhost:8000`. Prefix: `/api/v1`. Send `Accept: application/json`. All endpoints except login require a Sanctum-authenticated session. For first-party SPA authentication follow the [official Sanctum documentation](https://laravel.com/docs/12.x/sanctum#spa-authentication).

## Authentication sequence

1. GET `/sanctum/csrf-cookie` with credentials to establish CSRF/session cookies.
2. POST `/api/v1/login` with `{ "email": "your-email", "password": "your-password" }` and URL-decoded XSRF-TOKEN cookie in `X-XSRF-TOKEN`.
3. GET `/api/v1/user` to inspect the signed-in user (password is never serialized).
4. POST `/api/v1/logout` with credentials and CSRF header; returns 204.

Login returns `{ "data": { "id": ..., "name": ..., "email": ..., "role": ... } }`. Invalid login returns 422; rate limiting returns 429. Registration is unavailable. Never place session cookies or real passwords in committed scripts.

## Employee endpoints

| Method | Path | Behavior |
|---|---|---|
| GET | `/employees` | Paginated list |
| POST | `/employees` | Create, 201 |
| GET | `/employees/{id}` | Details, 200 |
| PUT/PATCH | `/employees/{id}` | Validated update, 200; name required |
| DELETE | `/employees/{id}` | Safe archive via is_active=0, 200 |

List query: `search`, `page`, `per_page` (1–100), `sort` (`id`, `name`, `emp_no`), `direction` (`asc`, `desc`), `division`, `unit`, `designation`, `status` (`all`, `active`, `archived`). Default name ascending, 15 per page, all statuses. Counts include archived records unless filtered.

Employee write fields: `name` (required, 255); `nic` (nullable, unique, 12); `emp_no` (12); `email` (valid email, 35); `contact_no` (39); `address` (255); `dob` (Y-m-d, not future); `gender` (6, preserving source representation); `division` and `designation` (existing integer IDs); `unit` (existing numeric ID serialized as a string, max 4 characters). Empty optional values may be null. `is_active` is service-controlled and cannot be mass-assigned through a request. Category/fingerprint and imported document fields are not accepted write fields.

Details additionally expose `division_name`, `unit_name` and `designation_name`. Each response is `{ "data": ... }`; lists include standard Laravel `links` and `meta` (current_page, last_page, per_page, total).

## Master data

The same routes apply to `/divisions`, `/units`, `/designations`:

| Method | Path | Behavior |
|---|---|---|
| GET | `/{module}` | List; search, page, per_page, direction |
| POST | `/{module}` | Create `{ "name": "Official name" }`, 201 |
| GET | `/{module}/{id}` | Details |
| PUT | `/{module}/{id}` | Update name |
| DELETE | `/{module}/{id}` | Delete only when unreferenced by any employee |

Resources normalize the differing imported name columns into `{ "id": ..., "name": ..., "status": ... }`. Status is meaningful for divisions; other masters return null. Names are required, maximum 255. Referenced deletes return 422 with `errors.record`. HR cannot delete master records; viewers cannot write.

## Dashboard and foundations

- GET `/dashboard`: `data.total_employees`, `total_divisions`, `total_units`, `employees_by_division`; present/absent/late/on_leave are null until implemented; recent_attendance is empty.
- GET `/attendance`: empty data and readiness metadata with configured schedules.
- POST `/attendance/import`: authorized upload validation, maximum 10 MiB, CSV/TXT/DAT extension and content validation. Valid uploads return 409 explaining that parsing/storage is disabled; no file is persisted. Invalid requests return 422.
- GET `/leaves`: empty data, readiness message and draft policy configuration. No application/approval/deduction endpoints yet.
- GET `/up`: Laravel health check (process boot, not database readiness).

## Errors

422 uses `{ "message": "...", "errors": { "field": ["..."] } }`. 401 is unauthenticated, 403 unauthorized, 404 missing route/record, 419 expired/missing CSRF, 429 throttled. Database query failures return sanitized 503 messages; debug is disabled by default. No raw SQL/credentials are returned to clients. Consult local server logs for diagnosis.

Use the browser's signed-in Network panel or a local API client with cookie support to exercise these endpoints. Run `php artisan route:list --path=api` for the installed route inventory. For safe synthetic endpoint checks use `php artisan test`; do not create test employees in employee_info.
