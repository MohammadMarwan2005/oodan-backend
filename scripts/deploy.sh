#!/usr/bin/env bash
set -euo pipefail

APP=/var/www/oodan-backend
PHP=/usr/bin/php8.4
COMPOSER=/usr/local/bin/composer

log(){ echo "[$(date '+%F %T')] $*"; }

cd "$APP"

log "composer install (prod)…"
$PHP "$COMPOSER" install --no-interaction --prefer-dist --no-dev --optimize-autoloader

log "migrate…";    $PHP artisan migrate --force
log "assets…";     $PHP artisan storage:link || true
# Filament arrives in a later phase; skip its assets until it is installed.
if $PHP artisan list --raw | grep -q '^filament:assets'; then $PHP artisan filament:assets; fi
log "optimize…";   $PHP artisan optimize
log "queue restart…"; $PHP artisan queue:restart
log "perms…";      chown -R www-data:www-data "$APP"; chmod -R ug+rwX "$APP/storage" "$APP/bootstrap/cache"
log "reload fpm…"; systemctl reload php8.4-fpm

log "== Deploy OK =="
