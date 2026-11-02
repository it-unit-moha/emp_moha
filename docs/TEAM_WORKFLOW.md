# Team workflow

Foundation changes remain on development, uncommitted. Do not push or commit without the owner's approval. No nested Git repository exists.

After reviewing and committing the foundation together, recommended developer branches are `developer-1`, `developer-2` and `developer-3`, created manually from an updated development branch. Suggested ownership:

| Developer | Initial ownership |
|---|---|
| developer-1 | Employee records and master data |
| developer-2 | Device format investigation and attendance |
| developer-3 | Approved leave workflows and reporting |

Use pull requests into development. Agree API request/response contracts in docs before changing shared interfaces. Keep controllers small and services focused. Share components from frontend/src/components and split growing feature folders rather than accumulating one global file. Coordinate edits to routes, shared types, layout, package manifests and business configuration. Commit lockfiles with dependency changes and use composer install / npm ci for reproduction.

Each PR should describe behavior, validation and schema impact. Run frontend build and backend tests. Imported tables must never be reset, truncated, recreated or used for destructive tests. Use the isolated SQLite suite or opt-in MySQL verification. New migrations require review; coordinate unique timestamps and never rewrite a migration already applied by a teammate. No automatic penalties or guessed device parsers.

Ignore .env files, credentials, logs, dependencies and generated builds. Share .env.example changes with safe placeholders. Never include employee personal data, database exports, session cookies, screenshots of private records or access tokens in commits. Schema-only references may be shared after review.
