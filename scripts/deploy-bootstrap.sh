#!/usr/bin/env bash
set -euo pipefail

REPO=/var/www/oodan-backend

cd "$REPO"
git fetch --prune origin
git reset --hard origin/master

exec bash "$REPO/scripts/deploy.sh"
