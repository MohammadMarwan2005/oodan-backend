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
