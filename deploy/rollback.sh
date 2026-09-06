#!/usr/bin/env bash
#
# Put the previous release back.
#
# Nothing is rebuilt: the release directory is still on disk with its vendor/
# and public/build/ intact, so this is a symlink move, an fpm reload and a
# worker restart. Anything the failed release wrote to the *database* is not
# undone — see the migration rule in docs/cutover.md, which is what keeps a
# rollback safe.
#
set -Eeuo pipefail

APP_DIR=${APP_DIR:-/var/www/shaniena}
PHP_FPM=${PHP_FPM:-php8.4-fpm}

log() { printf '\n\033[1;34m==>\033[0m %s\n' "$*"; }
fail() { printf '\n\033[1;31mROLLBACK FAILED:\033[0m %s\n' "$*" >&2; exit 1; }

CURRENT=$(readlink "$APP_DIR/current") || fail "no current release to roll back from"
TARGET=${1:-}

if [ -z "$TARGET" ]; then
    # The most recent release that is not the one running now.
    TARGET=$(ls -1dt "$APP_DIR"/releases/*/ | grep -v "^$CURRENT/\?$" | head -1) \
        || fail "no earlier release on disk"
fi

TARGET=${TARGET%/}
[ -d "$TARGET" ] || fail "$TARGET is not a release directory"

log "Rolling back $(basename "$CURRENT") → $(basename "$TARGET")"
ln -sfn "$TARGET" "$APP_DIR/current"
sudo systemctl reload "$PHP_FPM"
sudo supervisorctl restart shaniena-worker: shaniena-ssr:

cd "$APP_DIR/current"
for attempt in 1 2 3 4 5; do
    if php artisan shaniena:preflight; then
        log "Rolled back to $(basename "$TARGET")"
        exit 0
    fi
    sleep 3
done

fail "rolled back to $(basename "$TARGET") but preflight still fails — this is an outage, see docs/cutover.md"
