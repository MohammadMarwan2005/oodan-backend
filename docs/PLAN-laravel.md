# PLAN — عوداً عوداً backend (Laravel)

Backend for the «عوداً عوداً» heart app. Stores each user's heart on the server and syncs it across
offline-first clients (Android now, iOS/web later). This repo also owns the API contract
(`openapi.yaml`) that every client follows.

Work in vertical slices, Conventional Commits, one phase at a time. **Ask before guessing** anything
about the VPS, domains, or credentials.

---

## 0. Decisions (fixed)

| Topic | Decision |
|---|---|
| Framework | Laravel 13.37.0 (pin `laravel/framework` to `13.37.0`), PHP version per VPS (ask) |
| DB | MySQL |
| Auth | JWT access token (15 min) + opaque refresh token (60 days, rotated on every use, one per device) |
| Login | Email + password, Google (client sends Google ID token, server verifies) |
| Email verification | Mandatory before any sync endpoint works |
| Admin | Filament, admin users only, **counts and stats only — never notes or category names** |
| Contract | OpenAPI 3.1 in `openapi.yaml` at repo root, source of truth; responses are tested against it |
| Versioning | All routes under `/api/v1` |
| Sync | Offline-first, client-generated UUIDv7 ids, per-user server sequence for pulls, last-write-wins by device time |
| Encryption of notes | **Not now.** Keep `note` and category `name` as plain columns; encryption comes later |
| Account deletion | Immediate, cascades everything |
| Mail | SMTP from `.env` (the owner fills `MAIL_*` on the VPS) |

### Decisions I made — owner should confirm

- **Email verification and password reset use 6-digit codes**, not links. Mobile has no deep-link setup;
  codes work on every client. Codes expire after 15 minutes, max 5 attempts.
- **Export/import happen on the client**, not the server. The client already holds the full heart
  offline; the server only needs to sync the result. The export file schema still lives in the
  contract (`ExportFile`) so all clients read each other's files.
- **Backups are kept indefinitely** until the user deletes one.

---

## 1. Data model

All client-owned ids are **UUIDv7 generated on the device** (stored as `uuid` / `char(36)`).
All client timestamps are UTC with milliseconds (`datetime(3)`). `seq` = the user's sync sequence at
the moment the row last changed on the server.

### `users`
| column | type | notes |
|---|---|---|
| id | bigint PK | |
| name | string | |
| email | string unique | |
| password | string null | null for Google-only accounts |
| google_sub | string null unique | |
| email_verified_at | timestamp null | Google accounts are verified on creation |
| sync_seq | bigint unsigned, default 0 | per-user change counter |
| is_admin | bool, default false | Filament access |
| timestamps | | |

### `shots`
| column | type | notes |
|---|---|---|
| id | uuid PK | from the device |
| user_id | FK → users, cascade | |
| color | enum(`white`,`black`) | immutable |
| x, y | decimal(6,2) | viewBox 400×460; immutable |
| r | decimal(5,2) | 3–40; immutable |
| intensity | tinyint unsigned | 10–100; immutable |
| shot_at | datetime(3) | device time; display order |
| undone_at | datetime(3) null | reversible (undo/redo) |
| deleted_at | datetime(3) null | final-ish (clear, truncated redo, replace-import); restore can revive |
| state_changed_at | datetime(3) | LWW clock for `undone_at` + `deleted_at` |
| note | text null | ≤ 500 chars |
| category_id | uuid null FK → categories | must belong to user and match `color` |
| meta_changed_at | datetime(3) | LWW clock for `note` + `category_id` |
| device_id | uuid | origin device, diagnostics only |
| seq | bigint unsigned | index `(user_id, seq)` |
| timestamps | | server bookkeeping |

### `categories` (one table; `kind` separates white/black)
| column | type | notes |
|---|---|---|
| id | uuid PK | from the device |
| user_id | FK, cascade | |
| kind | enum(`white`,`black`) | immutable |
| name | string(60) | Arabic for now |
| color | char(7) null | `#RRGGBB` |
| icon | string(40) null | key from the clients' fixed icon set |
| preset_key | string(40) null | set for seeded defaults (e.g. `dhikr`), lets clients localize later |
| sort | int | |
| changed_at | datetime(3) | LWW clock for all mutable fields incl. `deleted_at` |
| deleted_at | datetime(3) null | soft delete; shots keep pointing to it |
| seq | bigint unsigned | index `(user_id, seq)` |

`en_name` is **not** added now; it comes with English support.

### `user_settings` (key–value, LWW per key)
`(user_id, key, value json, changed_at datetime(3), seq)`, PK `(user_id, key)`.
Keys: `dot_size` (3–40), `intensity` (10–100), `sound_enabled` (bool). Reject unknown keys.

### `backups`
| column | type | notes |
|---|---|---|
| id | uuid PK | from the device |
| user_id | FK, cascade | |
| reason | enum(`clear`,`import_replace`,`restore`) | |
| shots_count | int | |
| payload | longtext (JSON) | an `ExportFile` document |
| created_at | datetime(3) | device time |
| deleted_at | datetime(3) null | user deleted the backup |
| seq | bigint unsigned | |

### `devices`
`(id uuid PK, user_id FK cascade, platform enum(android, ios, web), name string null,
app_version string null, last_seen_at timestamp, last_pulled_seq bigint)`.

### `refresh_tokens`
`(id, user_id FK cascade, device_id uuid, token_hash char(64), expires_at, revoked_at null, created_at)`.
Store only the SHA-256 of the token.

### `auth_codes`
`(id, user_id FK cascade, purpose enum(verify_email, reset_password), code_hash, attempts tinyint, expires_at, used_at null)`.

---

## 2. Sync rules (the server's job)

### Sequence allocation
Every write that changes a syncable row runs in one transaction:

```php
DB::transaction(function () use ($user, $changes) {
    $user = User::whereKey($user->id)->lockForUpdate()->first();
    foreach ($changes as $change) {
        if (applied($change)) {          // only rows that actually changed
            $user->sync_seq++;
            $row->seq = $user->sync_seq;
            $row->save();
        }
    }
    $user->save();
});
```

The row lock on the user guarantees a user's `seq` values commit in order, so a pull never skips a gap.

### Merge rules for `POST /sync/push`

| Entity | New id | Existing id |
|---|---|---|
| shot | insert | immutable fields ignored; `(undone_at, deleted_at)` replaced if incoming `state_changed_at` is newer; `(note, category_id)` replaced if incoming `meta_changed_at` is newer |
| category | insert | all mutable fields replaced if incoming `changed_at` is newer |
| setting | insert | replaced if incoming `changed_at` is newer |
| backup | insert | only `deleted_at` can change (set once) |

- **Ties** (equal timestamps): the existing server value stays. Clients take the server's version on pull, so every device converges.
- **Idempotent:** pushing the same item twice changes nothing and allocates no new `seq`.
- **Validation:** `x ∈ [0,400]`, `y ∈ [0,460]`, `r ∈ [3,40]`, `intensity ∈ [10,100]`, `note ≤ 500`,
  `category.kind == shot.color`, the category belongs to the user, a `shot_at` more than 24h in the future
  is rejected. One invalid item rejects only that item; the response lists rejected ids with reasons.
- **Batch limit:** 500 items per push across all arrays.

### Pull
`GET /sync/pull?since={seq}&limit={≤500}` returns every shot, category, setting and backup **metadata**
(no `payload`) with `seq > since`, ordered by `seq`, plus `next_since` and `has_more`. Clients loop until
`has_more = false`. Updates `devices.last_pulled_seq` and `last_seen_at`.

---

## 3. API (`/api/v1`) — write it into `openapi.yaml` first

Every request from an app sends `X-Device-Id` (uuid), `X-Platform`, `X-App-Version`.
Errors use one shape: `{ "error": { "code": "string", "message": "string", "fields": { … }? } }`.

**Auth**

| Method | Path | Body → Response |
|---|---|---|
| POST | `/auth/register` | `{name, email, password}` → `TokenPair + User` (unverified; code emailed) |
| POST | `/auth/login` | `{email, password}` → `TokenPair + User` |
| POST | `/auth/google` | `{id_token}` → `TokenPair + User` (creates or links by verified email) |
| POST | `/auth/refresh` | `{refresh_token}` → new `TokenPair` (old one revoked) |
| POST | `/auth/logout` | → 204, revokes this device's refresh token |
| POST | `/auth/email/send-code` | → 204 (rate limited) |
| POST | `/auth/email/verify` | `{code}` → `User` |
| POST | `/auth/password/forgot` | `{email}` → 204 (always 204, no account enumeration) |
| POST | `/auth/password/reset` | `{email, code, password}` → 204, revokes all refresh tokens |

**Account**

| Method | Path | Notes |
|---|---|---|
| GET | `/me` | `User` incl. `email_verified` |
| PATCH | `/me` | `{name}` |
| DELETE | `/me` | `{password}` or `{id_token}` to confirm → 204, immediate cascade delete |

**Sync** (requires verified email, else `403 email_not_verified`)

| Method | Path | Notes |
|---|---|---|
| POST | `/sync/push` | `{shots[], categories[], settings[], backups[]}` → `{accepted: {shots[], categories[], settings[], backups[]}, rejected: [{id, code}], seq}` — *accepted* = processed (applied, or ignored as duplicate/older) |
| GET | `/sync/pull` | `?since&limit` → `{shots[], categories[], settings[], backups[], next_since, has_more}` |
| GET | `/backups/{id}` | full backup incl. `payload` |

**Contract schemas to define:** `User`, `TokenPair`, `Shot`, `Category`, `Setting`, `BackupMeta`,
`Backup`, `PushRequest`, `PushResponse`, `PullResponse`, `Error`, `ExportFile`.

`ExportFile` (client-side export/import, same as backup `payload`):

```json
{ "format": "oodan-export", "version": 1, "exported_at": "2026-10-16T20:00:00.000Z",
  "shots": [Shot…], "categories": [Category…], "settings": { "dot_size": 14, "intensity": 80, "sound_enabled": true } }
```

Wire timestamps are ISO-8601 UTC with milliseconds. Field names are `snake_case`.

---

## 4. Auth details

- JWT: verify Laravel 13 compatibility of `php-open-source-saver/jwt-auth` first; if it lags, use
  `firebase/php-jwt` with a small custom guard. Claims: `sub`, `iat`, `exp`, `device_id`. HS256 with a
  dedicated secret.
- Refresh tokens: 64 random bytes, base64url, store the hash, rotate on every refresh. Reusing a
  revoked token revokes every token of that user on that device (theft signal).
- Google: verify the ID token signature against Google's JWKS, `iss`, `exp`, and `aud` ∈
  `GOOGLE_CLIENT_IDS` (comma-separated env: Android, later iOS and web client ids). Link to an existing
  account only when Google says `email_verified = true`.
- Rate limits: login/register/code endpoints 5/min per IP+email; sync 60/min per user.
- Mail is queued. Ask the owner whether the VPS runs a queue worker (Supervisor/systemd); if not, use
  `sync` mail for now.

---

## 5. Filament (admin only)

Panel at `/admin`, gate: `is_admin`. Create the first admin with an artisan command
(`app:make-admin {email}`).

- **Dashboard widgets:** total users, verified users, active users (7/30 days via `devices.last_seen_at`),
  shots per day (30 days), overall white vs black ratio, signups per week.
- **Users resource:** list + view: email, verified, created, last seen, devices count, shots count,
  white/black counts. Actions: delete user (same cascade as `DELETE /me`), toggle admin.
- **Never** show `note`, category `name`, or backup `payload` anywhere in the panel.

---

## 6. Deployment

The owner already runs Laravel apps on a Hetzner VPS (Ubuntu 24.04, nginx, PHP-FPM, Certbot, MySQL),
deployed by GitHub Actions over SSH running `deploy.sh` on push to `master`. Follow the same pattern.
**Ask the owner** for: domain/subdomain, app path, PHP version, DB name/user, whether a queue worker
exists, and the SSH secret names. Do not touch other apps' nginx files or databases.

`deploy.sh`: `composer install --no-dev -o`, `php artisan migrate --force`, `filament:assets`,
`optimize`, permissions, PHP-FPM reload.

---

## 7. Tests (only what matters)

- **Sync merge rules:** insert, idempotent re-push, LWW for state/meta/category/setting, tie-break,
  validation rejections, `seq` monotonic with no gaps per user, pull pagination.
- **Auth flow:** register → code → verify → sync allowed; unverified → 403; refresh rotation and reuse
  detection; Google token verification (mock JWKS).
- **Contract:** every API test response is validated against `openapi.yaml`
  (e.g. `hotmeteor/spectator` or `league/openapi-psr7-validator`).
- **Fixtures for clients:** a test writes example responses to `contract/fixtures/*.json`; the Android
  repo decodes them in its own tests.

---

## 8. Phases

1. **Skeleton:** Laravel 13.37.0 app, MySQL config, `/api/v1` routing, error shape, `openapi.yaml` with all
   schemas and paths (no implementation yet), contract test harness.
2. **Auth:** users, JWT + refresh tokens, register/login/refresh/logout, email codes, password reset,
   Google login, `/me`, `DELETE /me`.
3. **Sync core:** migrations for shots, categories, settings, devices; seq allocation; push/pull; tests.
4. **Backups:** table, push/pull metadata, `GET /backups/{id}`.
5. **Filament:** panel, admin command, dashboard widgets, users resource.
6. **Deploy:** nginx vhost, Certbot, `.env` (owner fills `MAIL_*`, `GOOGLE_CLIENT_IDS`), GitHub Actions.
7. **Fixtures:** publish `contract/fixtures/` for the Android repo.
