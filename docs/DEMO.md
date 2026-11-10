# Local demo

From `backend/`, run `C:\xampp\php\php.exe tools/setup-demo.php` (or `php tools/setup-demo.php`). This requires local mode and PDO SQLite. It creates a separate `database/demo.sqlite`, applies application migrations there, adds synthetic master records and 12 employees (10 active, 2 archived), and creates a Super Admin account at `demo.admin@example.test`. The randomly generated password is printed only when the account is first created. Re-running preserves the account password and does not duplicate seed records.

The script switches the ignored local `.env` to this SQLite database and preserves the initial configuration as `.env.before-demo`. Restart the backend if it does not pick up the new configuration. Restore that saved configuration and run `php artisan config:clear` to return to the previous database settings. The setup never accesses the imported MySQL database. The demo database and saved environment are ignored by Git.

Open http://localhost:5173 with the Laravel server running at http://localhost:8000. Test login/logout, dashboard counts, employee create/edit/search/filter/archive, and division/unit/designation management. Referenced master records cannot be deleted until their employee assignments are removed, including archived employees.

Attendance processing, leave workflows, reports, account administration and editable settings remain unfinished. Demo data does not enable these modules. SQLite supports functional exploration but does not verify MySQL/MyISAM locking behavior.
