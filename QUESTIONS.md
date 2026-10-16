# Open questions — chosen defaults

Questions that came up while implementing phases 1–5 unattended. Each one has the default I went
with. Change any of them and I'll adjust.

## Environment / tooling

1. **Local PHP is 8.3, but `composer.lock` needs PHP ≥ 8.4.1** (Symfony 8.1). The server runs 8.4.
   - *Default:* I didn't touch the lock or your system PHP. I ran everything with a standalone
     static PHP 8.4.23 binary (static-php-cli "bulk" build) kept in the session scratchpad.
     To work locally you need PHP 8.4 with `intl`, `pdo_mysql`, `sodium` (e.g. `ppa:ondrej/php`).
2. **Tests run on MySQL, not SQLite.** That PHP build has no `pdo_sqlite`. MySQL behaviour also
   matters here (`datetime(3)`, `lockForUpdate`, enums).
   - *Default:* `phpunit.xml` now uses `DB_CONNECTION=mysql`, `DB_DATABASE=oodan_test`. Host, port,
     user and password come from your environment/`.env`. I ran the tests against a throwaway Docker
     container (`mysql:8.4` on `127.0.0.1:33061`, container name `oodan-test-mysql`, tmpfs data).
     Run `docker rm -f oodan-test-mysql` to remove it.
3. **Laravel Boost** — CLAUDE.md said to install it before any changes, so I did
   (`laravel/boost` dev dependency, regenerated `CLAUDE.md`, `.claude/skills`, `boost.json`). It is
   its own commit, so you can revert it on its own.
4. **New dependencies** (Boost's guidelines say to ask first; the plan needs them):
   `firebase/php-jwt` (JWT + Google JWKS), `opis/json-schema` + `symfony/yaml` (dev, contract
   tests), `filament/filament` (phase 5).

## Contract / API shape

5. **"TokenPair + User" response shape.**
   - *Default:* `{ "tokens": TokenPair, "user": User }` (`AuthSession`). Nesting keeps both schemas
     strict (`additionalProperties: false`). `/auth/refresh` returns a bare `TokenPair`.
6. **`User` fields** beyond the plan: `has_password` (Google-only accounts have none, so the client
   knows to confirm deletion with `id_token`) and `google_linked`.
7. **Rejected push items** carry `entity` (`shot|category|setting|backup`) plus optional `fields`,
   on top of the planned `{id, code}`. Settings are identified by `key`.
8. **Device headers.** `X-Device-Id` + `X-Platform` are *required* (`400 invalid_device_headers`)
   on register/login/google/refresh and on `/sync/*`. They are optional elsewhere.
   `X-App-Version` is always optional.
9. **Google login when the email exists but Google says `email_verified=false`** →
   `409 account_conflict` (no link, no new account).
10. **`DELETE /me` wrong confirmation** → `403 invalid_confirmation` (not 401, so clients don't
    treat it as an expired session).
11. **`/auth/email/send-code` when already verified** → `204`, nothing sent.
12. **Password rules:** min 8, max 255 characters, no other complexity rules.

## Auth (phase 2)

13. **JWT library:** `firebase/php-jwt` v7 with a small custom guard (`Auth::viaRequest('jwt')`,
    guard name `api`). I didn't use `php-open-source-saver/jwt-auth`: I couldn't confirm Laravel 13
    support offline, and the plan names this fallback. The same library verifies Google tokens.
14. **`JWT_SECRET`** must be set on the server (`openssl rand -base64 64`). The app throws if it is
    missing. It is *not* derived from `APP_KEY`.
15. **Queue worker for mail?** The plan says to ask. *Default:* the code mail is a queued mailable
    on `MAIL_QUEUE_CONNECTION`, which defaults to `sync` (sent during the request, no worker
    needed). Set it to `database` once a worker runs.
16. **Password reset also marks the email verified**, because the code proves the user owns the
    mailbox. A Google-only account can use reset to add a password.
17. **Rate limits:** register/login/google/forgot/reset/send-code/verify share one limiter,
    5/min per IP+email (email of the signed-in user for the code endpoints). `/auth/refresh` gets
    30/min per IP, which the plan doesn't specify.
18. **Old refresh tokens and used codes are never pruned.** Rows stay for reuse detection. A
    scheduled prune (e.g. tokens expired > 60 days) can come with deploy; no scheduler is
    assumed yet.
19. **Users table:** I added a new migration (password nullable, `google_sub`, `sync_seq`,
    `is_admin`) instead of editing the initial one, in case it has already run on the server.
