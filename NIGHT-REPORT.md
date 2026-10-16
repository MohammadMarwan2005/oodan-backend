# Night report — phases 1–5

**Status:** Phases 1–5 of `docs/PLAN-laravel.md` are implemented and committed on local `master`.
**Nothing was pushed.** No server, `scripts/deploy.sh` or GitHub setting was touched.
**Tests:** 67 tests, 624 assertions, all green (MySQL 8.4). Every `/api/v1` response in those tests is
validated against `openapi.yaml`.

Open questions and the defaults I picked are in **`QUESTIONS.md`** (36 items). Please skim it before
pushing.

## ⚠️ Before you push to `master` (which deploys)

1. **Install `intl` on the VPS.** Filament requires `ext-intl` (plus `zip`/`xml`). Without it,
   `composer install` in `scripts/deploy.sh` fails and the deploy stops halfway:
   `apt install php8.4-intl php8.4-zip php8.4-xml && systemctl reload php8.4-fpm`.
2. **Server `.env`:**
   - `JWT_SECRET=` → `openssl rand -base64 64` (required; login/refresh return 500 without it)
   - `GOOGLE_CLIENT_IDS=` → the Android OAuth client id (comma-separated for more)
   - `MAIL_*` (SMTP) and `MAIL_QUEUE_CONNECTION=sync` (until a queue worker exists)
   - `DB_CONNECTION=mysql` + credentials
3. **nginx:** raise `client_max_body_size` (e.g. `20m`) on the vhost, and PHP `post_max_size` to
   match. Backup pushes carry up to a 10 MiB payload.
4. After the first deploy: `php artisan app:make-admin you@example.com` (it asks for a password).
5. Existing data: the new migrations only *add* to the default `users` table (password becomes
   nullable), so they are safe if the initial migrations already ran on the server.

## What was built

| Commit | Phase |
|---|---|
| `5bc32fa` chore: install Laravel Boost | setup required by CLAUDE.md (separate, easy to revert) |
| `2aa237d` API skeleton, error shape, OpenAPI contract, contract test harness | 1 |
| `5338d3c` JWT auth, refresh tokens, email codes, Google login, /me | 2 |
| `6e24a94` sync push/pull, per-user seq, LWW merge rules | 3 |
| `b513237` backups sync and `GET /backups/{id}` | 4 |
| `bd6661b` Filament admin panel, widgets, users resource, `app:make-admin` | 5 |
| `dfbc094` fix: cap pulls at the committed `sync_seq` | 3 (bug found in self-review) |

**Phase 1:** `/api/v1` routing. Every exception under `/api` renders
`{error: {code, message, fields?}}`. `openapi.yaml` (OpenAPI 3.1) has every path and every schema
from the plan. The contract harness lives in `tests/TestCase.php` + `tests/Contract/OpenApiContract.php`:
it validates every `/api/v1` test response (status must be documented, body must match the schema)
with `opis/json-schema`. A test that hits an undocumented status fails.

**Phase 2:** HS256 JWT access tokens (15 min, `sub/iat/exp/device_id`) behind an `api` guard.
Opaque refresh tokens (64 bytes, SHA-256 stored, 60 days, one per device, rotated on use;
reusing a rotated token revokes that device). Register/login/Google (JWKS verified, `iss`/`aud`/
`exp`, links only on `email_verified`), logout, 6-digit email codes (15 min, 5 attempts) for
verification and password reset (reset revokes every refresh token), `GET/PATCH/DELETE /me`
(delete confirmed by password or Google `id_token`, full cascade). Rate limits: 5/min per IP+email
on auth endpoints, 60/min per user on sync.

**Phase 3:** `devices`, `categories`, `shots`, `user_settings`. A push runs in one transaction
holding the user row lock. A seq is allocated only when a row actually changes. Mergers in
`app/Services/Sync/Mergers` apply the plan's table, with ties keeping the server value. Invalid
items are rejected one by one with a code; batches are capped at 500. Pull merges all entities in
seq order with `next_since`/`has_more` pagination and updates the device's `last_pulled_seq`.

**Phase 4:** `backups`. Pushes insert (payload required), and `deleted_at` can be set once.
Deleting drops the payload. Pull returns metadata only; `GET /backups/{id}` returns the payload.

**Phase 5:** Filament v5 panel at `/admin`, `is_admin` only. Dashboard: users, verified,
active 7/30 days, white:black ratio, shots per day (30 days), signups per week (12 weeks). Users
resource is list + view only, with verified, joined, last seen and counts for devices, shots,
white and black. Actions: toggle admin and delete (same cascade as `DELETE /me`). A test seeds a
note, a category name and a backup payload, then asserts that none of them appears on any panel page.

## Bugs caught by the tests during the night

- A shot rejected by its category check had already allocated a seq, which left a gap. Fixed:
  the check now runs before allocation.
- Pull ran its four entity queries separately, so a push committing between them could make a
  client skip a row permanently. Fixed: pulls are capped at the committed `sync_seq` (has a test).

## How I ran it (your machine)

- Your system PHP is 8.3, but `composer.lock` needs 8.4 (as on the server). I used a standalone
  static PHP 8.4.23 in the session scratchpad and did **not** install anything system-wide or
  change your shell profile. To work locally, install PHP 8.4 with `intl` + `pdo_mysql`.
- That PHP build has no `pdo_sqlite`, so **`phpunit.xml` now targets MySQL** (`oodan_test`). I ran
  the suite against a throwaway container that is still running:
  `docker rm -f oodan-test-mysql` removes it. To rerun the tests:
  ```sh
  docker run -d --name oodan-test-mysql -p 127.0.0.1:33061:3306 --tmpfs /var/lib/mysql \
    -e MYSQL_ROOT_PASSWORD=secret -e MYSQL_DATABASE=oodan_test mysql:8.4
  DB_PORT=33061 DB_USERNAME=root DB_PASSWORD=secret php artisan test
  ```
- Your local `.env` is untouched (still SQLite). `.env.example` now shows MySQL and the new keys.

## Not done

- **Phase 6 (deploy)** and **phase 7 (client fixtures)**: out of scope for tonight.
- No scheduled pruning of revoked refresh tokens / used codes (QUESTIONS #18).
- Backup payloads are only shape-checked at the top level, so a malformed shot inside a payload
  is stored as is (QUESTIONS #29).
- The default Laravel `README.md` was not rewritten.
