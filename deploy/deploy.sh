#!/usr/bin/env bash
#
# Release a new version of the shop.
#
# Deploys are release directories with a `current` symlink, not an in-place
# `git pull`: the flip is atomic, and the previous release stays on disk so
# rollback.sh is a symlink move rather than a rebuild. The source project was
# deployed by FTP over the live directory, which meant every deploy had a
# window where half the files were new.
#
#   deploy.sh              # deploy origin/main
#   deploy.sh v1.2.0       # deploy any git ref
#
set -Eeuo pipefail

APP_DIR=${APP_DIR:-/var/www/shaniena}
REPO=${REPO:-git@github.com:wafazz/shaniena-v3.git}
REF=${1:-origin/main}
KEEP=${KEEP:-5}                       # releases to retain
PHP_FPM=${PHP_FPM:-php8.4-fpm}
RELEASE="$APP_DIR/releases/$(date -u +%Y%m%dT%H%M%SZ)"

log() { printf '\n\033[1;34m==>\033[0m %s\n' "$*"; }
fail() { printf '\n\033[1;31mDEPLOY FAILED:\033[0m %s\n' "$*" >&2; exit 1; }

[ -f "$APP_DIR/shared/.env" ] || fail "no $APP_DIR/shared/.env — see docs/deployment.md, first deploy is not automated"

log "Fetching $REF"
if [ ! -d "$APP_DIR/repo" ]; then
    git clone --mirror "$REPO" "$APP_DIR/repo"
fi
git --git-dir="$APP_DIR/repo" remote update --prune
SHA=$(git --git-dir="$APP_DIR/repo" rev-parse "$REF")

log "Building release $(basename "$RELEASE") at ${SHA:0:8}"
mkdir -p "$RELEASE"
git --git-dir="$APP_DIR/repo" archive "$SHA" | tar -x -C "$RELEASE"
echo "$SHA" > "$RELEASE/RELEASE_SHA"

# Shared state lives outside the release so it survives the flip.
ln -sfn "$APP_DIR/shared/.env" "$RELEASE/.env"
rm -rf "$RELEASE/storage"
ln -sfn "$APP_DIR/shared/storage" "$RELEASE/storage"

cd "$RELEASE"

log "Installing PHP dependencies"
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-progress

log "Building assets (client + SSR)"
# Dev dependencies are needed here, unlike composer above: vite and
# laravel-vite-plugin are devDependencies and vite.config.js imports both,
# so --omit=dev leaves `npm run build` with nothing to run.
npm ci --no-audit --fund=false
npm run build

log "Running migrations"
# Migrations run against the live database *before* the flip, so they must be
# backward compatible with the release still serving traffic: add columns, do
# not drop them. Drops go out in the deploy after the code that stopped using
# them (see docs/cutover.md).
php artisan migrate --force

log "Caching config, routes, views and events"
php artisan optimize
php artisan storage:link

PREVIOUS=$(readlink "$APP_DIR/current" 2>/dev/null || true)

log "Pointing current at the new release"
ln -sfn "$RELEASE" "$APP_DIR/current"

# php-fpm resolves the symlink once and caches it; without this it keeps
# serving the old release's files from opcache.
sudo systemctl reload "$PHP_FPM"
sudo supervisorctl restart shaniena-worker: shaniena-ssr:

log "Preflight"
# The SSR node process needs a moment to bind its port after a restart.
for attempt in 1 2 3 4 5; do
    if php artisan shaniena:preflight; then
        PASSED=1
        break
    fi
    sleep 3
done

if [ "${PASSED:-0}" != "1" ]; then
    if [ -n "$PREVIOUS" ]; then
        log "Preflight failed — rolling back to $(basename "$PREVIOUS")"
        "$APP_DIR/current/deploy/rollback.sh" || true
    fi
    fail "preflight did not pass; the failed release is kept at $RELEASE"
fi

log "Pruning old releases (keeping $KEEP)"
# Never prune whatever `current` points at: after a rollback that is not the
# newest directory, and deleting it would take the site down.
LIVE=$(basename "$(readlink "$APP_DIR/current")")
cd "$APP_DIR/releases"
ls -1dt */ | sed 's:/$::' | grep -vx "$LIVE" | tail -n "+$KEEP" | xargs -r rm -rf

log "Deployed ${SHA:0:8} — $(basename "$RELEASE")"
