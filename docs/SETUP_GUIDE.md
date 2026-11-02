# Local setup

## Prerequisites

PHP 8.2+, Composer 2, Node.js 22.12+ (Node 24 used locally), npm, and MySQL/MariaDB. Required PHP extensions include PDO MySQL, mbstring, OpenSSL, tokenizer, XML, ctype, fileinfo, curl and zip. PDO SQLite is required for isolated default tests. XAMPP PHP and MariaDB are available on the inspected machine. Composer is a PHP archive at `C:/xampp/php/composer`; invoke it through PHP if `composer` fails on Windows.

## Backend installation

From the repository root:

```powershell
cd backend
php C:/xampp/php/composer install
# On another machine, use: composer install
# Copy only if .env does not already exist:
Copy-Item .env.example .env
php artisan key:generate
```

Set the local `.env` credentials without committing them:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=employee_info
DB_USERNAME=your_local_username
DB_PASSWORD=your_local_password
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173
SANCTUM_STATEFUL_DOMAINS=localhost:5173
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
```

The local ignored `.env` was configured for the connection that successfully read the imported database. `.env.example` contains no database password. `APP_DEBUG=false` prevents exception disclosure. Do not regenerate an existing application key on a shared or deployed system.

## Review new migrations before applying

Inspect `backend/database/migrations/`: standard Laravel users/password reset/session, cache and queue tables, plus a user-role column. None alters the imported tables. The role defaults to Authorized Viewer. No foreign keys to imported tables are added. The standard `sessions.user_id` is an indexed unsigned bigint, without a foreign-key constraint.

Back up the database using your approved local procedure. Ensure these new table names do not conflict with existing infrastructure. After team review, apply additive application migrations and create an administrator:

```powershell
php artisan migrate
php artisan galms:create-admin
```

The administrator command prompts for a name, email and hidden password of at least 12 characters. It rejects duplicate email addresses and never ships a default account. Role administration through the portal is unfinished. Do not run destructive migration resets or import the schema reference into the existing database.

`php artisan migrate --pretend` is not strictly read-only on an uninitialized database: Laravel can create its migration tracking table first. Use the checked-in migration files for initial review.

```powershell
php artisan galms:check-database
php artisan route:list --path=api
php artisan serve --host=localhost --port=8000
```

The database-check command reads counts, relationships and an employee resource without printing personal data.

The backend is API-only and requires no npm installation or Vite build. Laravel storage, bootstrap cache and view directories are retained for framework operation.

## Frontend installation

In a second terminal from the repository root:

```powershell
cd frontend
npm.cmd ci
# Optional; the example value is also the development default:
Copy-Item .env.example .env
npm.cmd run dev
```

`VITE_API_URL=http://localhost:8000` is the backend origin, without `/api/v1`. Open `http://localhost:5173`. Vite uses port 5173 with strict-port checking. Do not mix `127.0.0.1` with `localhost` in browser URLs. Axios initializes `/sanctum/csrf-cookie` and sends credentials and XSRF headers. No bearer token is required.

## Tests and builds

```powershell
# backend/ (working directory matters)
php artisan test
php vendor/bin/pint --test
# frontend/
npm.cmd run build
```

Default database tests forcibly use in-memory SQLite and guard against any other connection. They create synthetic fixtures, never import real employee data. To additionally verify MyISAM behavior on MariaDB/MySQL:

```powershell
# backend/; requires local credentials with CREATE DATABASE and LOCK TABLES
$env:GALMS_VERIFY_MYSQL='1'
php artisan test --filter=MySqlIntegrationTest
Remove-Item Env:GALMS_VERIFY_MYSQL
```

This reads only the four source table definitions, creates a uniquely named `galms_test_*` database, applies application migrations there and tests synthetic CRUD. It retains that database and prints its name for review. It never cleans up by dropping databases and never writes imported employee records. Do not grant the deployed application CREATE DATABASE privileges for this test.

## Troubleshooting

- API unavailable: confirm MariaDB and Laravel are running and inspect the local Laravel log.
- 401: sign in again. 403: the role lacks permission. 419: refresh and retry; confirm matching localhost origins and CSRF cookies.
- Login database error before initial setup: application migrations and administrator provisioning are still required.
- MyISAM writes fail: the local database user needs `LOCK TABLES` for the short assignment/delete critical sections. Do not remove these checks to bypass permissions.
- Production: deploy the built frontend and Laravel under the same site, configure allowed origins/stateful domains, HTTPS and secure session cookies, rotate local credentials, disable debug, configure backups and obtain a security review before handling production data.
