#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR="${APP_DIR:-/var/www/toriloup/data/www/platform}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
NPM_BIN="${NPM_BIN:-npm}"
TARGET_COMMIT="${1:-${TARGET_COMMIT:-}}"
RUN_SMOKE="${RUN_SMOKE:-1}"
BACKUP_BEFORE_ROLLBACK="${BACKUP_BEFORE_ROLLBACK:-1}"
STATE_FILE_RELATIVE="${STATE_FILE_RELATIVE:-storage/logs/deploy-live.state}"

cd "$APP_DIR"

if [ ! -d .git ]; then
    echo "Rollback requires a git checkout on the server." >&2
    exit 1
fi

if [ ! -f .env ]; then
    echo "Production .env is required before rollback." >&2
    exit 1
fi

if [ -z "$TARGET_COMMIT" ]; then
    if [ ! -f "$STATE_FILE_RELATIVE" ] || [ "$(wc -l < "$STATE_FILE_RELATIVE")" -lt 2 ]; then
        echo "No previous deployment commit recorded in $STATE_FILE_RELATIVE." >&2
        exit 1
    fi

    TARGET_COMMIT="$(tail -n 2 "$STATE_FILE_RELATIVE" | head -n 1 | awk '{print $2}')"
fi

if [ -z "$TARGET_COMMIT" ]; then
    echo "Unable to resolve rollback target commit." >&2
    exit 1
fi

if [ "$BACKUP_BEFORE_ROLLBACK" = "1" ]; then
    APP_DIR="$APP_DIR" BACKUP_BEFORE_DEPLOY=1 RUN_SMOKE=0 MAINTENANCE_MODE=0 FORCE_DEPLOY=0 SKIP_GIT_SYNC=1 DEPLOY_COMMIT_SHA="rollback-prep-$(date +%s)" \
        bash "$APP_DIR/scripts/deploy-live.sh" >/dev/null
fi

"$PHP_BIN" artisan down --retry=60
trap '"$PHP_BIN" artisan up >/dev/null 2>&1 || true' EXIT

git fetch --quiet --all --tags
git reset --hard "$TARGET_COMMIT"

"$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-progress
"$NPM_BIN" ci --include=dev --no-audit --no-fund
"$NPM_BIN" run build

"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan queue:restart >/dev/null 2>&1 || true
"$PHP_BIN" artisan up >/dev/null 2>&1 || true
trap - EXIT

if [ "$RUN_SMOKE" = "1" ]; then
    bash "$APP_DIR/scripts/smoke-production.sh"
fi

printf '%s %s %s\n' "$(date -Is)" "$TARGET_COMMIT" "rollback" >> "$APP_DIR/$STATE_FILE_RELATIVE"
