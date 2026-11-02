# Imported database schema

Inspected directly on 2026-11-02 using SHOW CREATE TABLE against local `employee_info` (MariaDB 10.4.32). [Schema-only SQL reference](../database/imported-schema.sql) contains no employee rows and must not be executed against the existing database. There was no original SQL dump in the repository.

All four tables use **MyISAM**, have auto-increment signed integer `id` primary keys, and have no declared foreign keys. Numeric display widths such as int(3) are not numeric range constraints.

## employee_data

| Column | Actual definition |
|---|---|
| id | int(20), not null, auto_increment, primary key |
| name | varchar(255), nullable |
| nic | varchar(12), nullable, unique index |
| address | varchar(255), nullable |
| contact_no | varchar(39), nullable |
| email | varchar(35), nullable |
| dob | date, nullable |
| emp_no | varchar(12), nullable |
| designation | int(3), nullable |
| unit | varchar(4), nullable |
| division | int(5), nullable |
| gender | varchar(6), nullable |
| qr_code | varchar(18), nullable |
| date_issued / date_expiry | date, nullable |
| application_form / image / signature | varchar(225), nullable |
| is_printed / is_active | int(1), nullable |
| inserted_on | timestamp, not null, default current_timestamp() |
| updated_on | datetime, nullable, on update current_timestamp() |

Charset utf8 (MariaDB utf8mb3), collation utf8_general_ci. The Employee model disables Laravel timestamps and leaves imported timestamp behavior to the database. It explicitly maps `designation -> designation.id`, `division -> division.id`, and string `unit -> unit.id`.

No employee category or fingerprint enrollment column exists. QR code is not treated as fingerprint ID. Zero nonnumeric nonempty unit values were found in the inspected data; the column type remains varchar(4), and requests preserve that limit. Existing inconsistent assignments are not silently repaired.

Archive uses existing is_active=0; inspection found 644 active and 136 inactive rows. This interprets inactive records as archived in GALMS and preserves every record. NULL status is displayed as unspecified. Restore is not implemented. No imported rows were changed by development verification.

## Masters

| Table | Columns | Charset |
|---|---|---|
| division | id int(11) PK auto_increment; division varchar(255) nullable; status int(11) NOT NULL DEFAULT 1 (1 active, 0 inactive) | latin1 / latin1_swedish_ci |
| unit | id int(11) PK auto_increment; unit_name varchar(255) nullable | latin1 / latin1_swedish_ci |
| designation | id int(11) PK auto_increment; designation varchar(255) nullable | latin1 / latin1_swedish_ci |

All master models disable timestamps. No parent-division relationship is invented for unit. Existing latin1 columns may not store Sinhala/Tamil names; charset conversion needs a separate reviewed migration and data/encoding verification. No conversion was performed.

## Application schema

New reviewed migration candidates cover users (standard Laravel fields), sessions, password_reset_tokens, cache/cache_locks, jobs/job_batches/failed_jobs and a users.role column. Sessions/cache/queue default to filesystem/sync locally, but standard migrations remain available. Sanctum is used only for SPA sessions, so no personal access token table is needed. There is no public registration.

No migrations recreate or alter imported tables. New attendance/leave tables and fingerprint/category extensions are intentionally pending specification and review. Before adding constraints, check orphaned IDs, type widths, signedness, collations, normalization and external writers. MyISAM cannot roll back changes; see the table-lock concurrency strategy in Architecture.

## Development verification effects

Read-only checks confirmed counts of 780 employees, 6 divisions, 18 units and 64 designations. Laravel's migration preview created an empty `migrations` tracker in employee_info; none of the application migration bodies ran there. A separate uniquely named `galms_test_*` database contains synthetic fixtures and copies of table definitions for integration verification. It is retained for review and is not used by the normal application.
