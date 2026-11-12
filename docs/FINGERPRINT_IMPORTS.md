# Fingerprint machine uploads and temporary tests

Open Attendance & upload. The upload panel accepts up to 16 CSV/TXT/DAT files per batch (10 MB each), so files from all eight company machines can be selected together. Assign the correct source machine to each file and click Import files & evaluate. All files are validated before a transaction writes any punches. An invalid employee ID, timestamp, code or layout identifies the filename and line and rejects the entire batch.

## Machine setup

Administrators can expand Machine setup & employee ID matching and register each actual machine/location. Machine names identify sources; they are not extracted by guessing filenames. Configure the layout and the actual IN and OUT codes based on a real export/manual. The synthetic test uses IN=0, OUT=1; these are examples, not verified settings for the company's hardware.

Two explicit layouts are supported:

- Delimited text with the exact header `enroll_id,timestamp,state`. Commas, tabs and semicolons are supported; timestamps use `YYYY-MM-DD HH:mm:ss`. Without a registered machine, states must be literal IN and OUT. A registered machine may define other explicit state codes.
- Headerless six-field device text: `ID YYYY-MM-DD HH:mm:ss VERIFY STATE WORKCODE RESERVED`. Fields may be separated by tabs or spaces. Verification/workcode/reserved are ignored; STATE is mapped using the machine's configured IN/OUT codes.

Unknown states (including unconfigured break/duty codes), different layouts, unknown IDs and ambiguous formats are rejected. No person is auto-created or matched by name. A representative raw file from each distinct actual machine format and confirmation of its state codes are still needed before claiming hardware compatibility. UTF-8 exports are supported; UTF-16 files are rejected with an encoding message. Dates are interpreted in Asia/Colombo; overnight shifts remain outside the same-day schedule rules.

When a machine uses the central enrollment ID, select the shared-ID checkbox. Otherwise add a machine-specific ID → employee mapping. The same employee can have several IDs across machines; the same ID on two machines can belong to different people. Leading zeros are preserved. A mapping takes precedence over shared lookup. Reassigning an existing machine ID to another employee is rejected to protect historical identity.

## Combining punches

Every punch is resolved to the central employee before merging. First IN and last OUT on the same date are selected across all machines, then the full affected months are evaluated under the monthly allowance policy. A missing IN or OUT remains for review; direction is not invented. Files may arrive together or at different times and in any order. Later files can complete previously missing punch pairs.

The global uniqueness key is employee + timestamp + state. Exact duplicates across files/machines are skipped in attendance, but all file/machine source associations are retained in `attendance_import_punches`. An exact file re-upload is deduplicated per machine. Identical file contents from two different machines retain both sources, including when their local IDs resolve to different employees. Daily history shows contributing machine names, and recent file history shows source and imported/duplicate counts. Existing single-file standardized imports remain supported by the API.

No direct network connection to a fingerprint machine or biometric templates are used. This workflow reads attendance export files uploaded by an authorized user.

## Temporary test data

The local demo exposes a Temporary test session panel to administrators. Create temporary test data, then either import all eight sample files with the test action or download them individually and upload them through the normal file queue using their matching test machines. The session creates two clearly marked employees, one temporary division, eight test machines and sixteen per-machine ID mappings.

The eight synthetic files contain 20 source punches: 15 unique employee/time/state punches and five duplicates, producing eight daily records. For Other Staff, November 2–6, 2026 evaluates to two covered late arrivals, two short leaves and one half-day. PL examples include on-time attendance, covered lateness and a missing OUT. Existing holiday configuration still applies.

Use Delete temporary test data after testing. Cleanup uses a server-owned manifest of exact created IDs; it removes the session's employees, mappings, machines, imports, source links, daily evaluations, punches, leave accounts/applications and ledger entries. It preserves existing employees and refuses cleanup if non-test employees have been assigned into the temporary division/machines. A cleanup audit summary remains; downloaded PDFs/files on the user's computer cannot be deleted by the server. Reusable test code and fixture generation remain available for future tests. This facility is restricted to the local demo SQLite database (and isolated in-memory tests), not the imported employee_info database or production.

## Local Windows startup

Run `powershell -NoProfile -ExecutionPolicy Bypass -File tools/start-local.ps1` from backend. The launcher creates a writable project-local PHP upload directory, keeps PHP notices out of JSON responses, logs errors locally, and allows sixteen 10 MB files in one request. It changes only this server process, not the system PHP configuration. The previous XAMPP temporary-directory setting caused startup notices to be prepended to otherwise successful upload JSON.

## Verification

Backend tests exercise multi-machine pairing, per-machine identity collisions, explicit numeric codes, leading zeros, identical files from different machines, batch rollback on an invalid file, re-upload idempotency, role restrictions and cleanup preservation. The live browser test selects all eight actual temporary files in the normal upload input, assigns their machines, verifies the 15/5 import result and daily evaluation, downloads a valid divisional PDF, deletes the test session through its confirmation dialog, and compares the remaining employee/division/machine/import counts to their baseline. Temporary data is cleaned up even if a test fails.
