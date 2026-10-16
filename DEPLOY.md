# Deployment

## Flow

```
push to master
  └─> GitHub Actions (.github/workflows/deploy.yml)
        └─> SSH to the production server
              └─> forced command on the deploy key runs /var/www/oodan-backend/deploy.sh
                    ├─ git fetch --prune origin && git reset --hard origin/master
                    └─ exec bash scripts/deploy.sh   (build steps, version-controlled)
```

The SSH key on the server is pinned to a forced command, so whatever the workflow
sends as a command is ignored — the server always runs its own `/var/www/oodan-backend/deploy.sh`.

- `deploy.sh` at the repo root on the server is **untracked** (see `.gitignore`) so the
  `git reset --hard` never rewrites the script while it is running.
- `scripts/deploy-bootstrap.sh` is the version-controlled template for that untracked
  bootstrap copy. It does the git work, then hands off to `scripts/deploy.sh`.
- `scripts/deploy.sh` holds the actual build steps and is safe to change in git —
  it is only reached via `exec` after the reset has already completed.

## Required GitHub secrets

| Secret | Value |
| --- | --- |
| `SSH_HOST` | Production server hostname or IP |
| `SSH_USER` | SSH user the deploy key belongs to |
| `SSH_PRIVATE_KEY` | Private key whose public half is pinned to the forced command |

## One-time server setup

1. Clone the repo to `/var/www/oodan-backend`, create `.env` (`APP_ENV=production`,
   `APP_DEBUG=false`, DB credentials) and run `php artisan key:generate`.
2. Install the bootstrap script:

   ```bash
   sudo cp /var/www/oodan-backend/scripts/deploy-bootstrap.sh /var/www/oodan-backend/deploy.sh && sudo chmod 755 /var/www/oodan-backend/deploy.sh
   ```

3. Generate a deploy key pair and add the public half to the deploy user's
   `~/.ssh/authorized_keys`, pinned to the forced command:

   ```
   command="/var/www/oodan-backend/deploy.sh",no-port-forwarding,no-agent-forwarding,no-X11-forwarding,no-pty ssh-ed25519 AAAA... github-deploy
   ```

4. Add the private half and host/user as the GitHub secrets above.
5. Point the web server's document root at `/var/www/oodan-backend/public`.

## Server facts

Adjust `scripts/deploy.sh` if the server differs:

- Repo / app: `/var/www/oodan-backend`
- PHP: `/usr/bin/php8.4`
- Composer: `/usr/local/bin/composer`
- FPM service: `php8.4-fpm`
- Deploy runs as `root`

## Manual deploy

```bash
sudo /var/www/oodan-backend/deploy.sh
```
