# Campus Notes: security review project

This application is intentionally vulnerable educational material. Run it only on an isolated machine you control, with synthetic data and a disposable local database. Never expose it to the internet, deploy it, or connect it to shared or real accounts. The companion laboratory handout defines the assignment.

## Local setup

1. Install a supported PHP 8 release with `pdo_mysql` and a local MySQL server. Confirm `pdo_mysql` appears in `php -m`.
2. As a local MySQL administrator, import `schema.sql` into a fresh disposable server/database. It creates `notes_lab` and its tables; use a fresh database to avoid conflicts. Review the SQL before running it.
3. Create a dedicated local MySQL account with SELECT, INSERT, UPDATE, and DELETE privileges only on `notes_lab`; do not use a production account. Set `LAB_DB_USER` and `LAB_DB_PASSWORD` for your shell. In PowerShell use `$env:LAB_DB_USER = 'your_local_user'` and `$env:LAB_DB_PASSWORD = 'your_local_password'`. Do not commit credentials.
4. From this folder, run `php -S 127.0.0.1:8000 router.php` (the router keeps `src/`, `templates/`, `tests/` and `schema.sql` private). Visit `http://127.0.0.1:8000`. The PHP built-in server is for local development only. Stop it with Ctrl+C after use.
5. The schema includes two synthetic accounts and notes:
   - `alex@example.test` / `LabOnly-Alex-123`
   - `sam@example.test` / `LabOnly-Sam-456`

   These public, throwaway credentials are for the isolated starter application only; never reuse them elsewhere. Use separate browser profiles for separate sessions. **Repaired version:** passwords now use `password_hash()` / `password_verify()` (bcrypt). `schema.sql` already seeds both accounts with bcrypt hashes of the same throwaway passwords, so re-import `schema.sql` into a fresh database. Old MD5 rows will not log in.

The local HTTP setup is for the exercise only. A production deployment requires HTTPS, secure cookies, protected secrets, proper error handling, and additional security controls. Students should document their own fixes and limitations.

The starter includes `api.php`: GET `/api.php?user_id=1` lists notes for an owner, and POST `/api.php` accepts a JSON object with `id`, `title`, and `body` to update a note. These endpoints intentionally lack important protections; use only synthetic data in the isolated lab. Review their behavior and repair them as part of the assignment.

## Repaired version: file layout

```
index.php            web controller (form actions -> template)
api.php              JSON API (signed-in user's own notes only)
router.php           router for PHP's built-in server
src/bootstrap.php    safe error handling, secure session, security headers
src/db.php           PDO connection from environment variables
src/security.php     e() escaping, CSRF token, origin check
src/auth.php         register / login / logout
src/notes.php        parameterized, owner-scoped note queries + validation
templates/page.php   HTML template (all output escaped)
tests/run_tests.sh   automated checks (uses a throwaway SQLite copy)
```

## Using the API

1. Sign in through the web page (cookie session).
2. `GET /api.php` returns `{"notes": [...], "csrf": "<token>"}` for your own notes only.
3. `POST /api.php` with headers `Content-Type: application/json` and `X-CSRF-Token: <token>`, body `{"id": 1, "title": "...", "body": "..."}`.

No cross-origin access is allowed (no CORS headers).

## Running the tests

`bash tests/run_tests.sh` (needs PHP with `pdo_sqlite` and Python 3). It starts a temporary server on port 8001 with a disposable SQLite database and prints PASS/FAIL for each check.
