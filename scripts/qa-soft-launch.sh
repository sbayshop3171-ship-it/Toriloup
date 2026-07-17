#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR="${APP_DIR:-$(pwd)}"
PHP_BIN="${PHP_BIN:-php}"
NPM_BIN="${NPM_BIN:-npm}"
SOFT_LAUNCH_MANIFEST="${SOFT_LAUNCH_MANIFEST:-}"

cd "$APP_DIR"

"$PHP_BIN" artisan ops:deploy-health
"$PHP_BIN" artisan ops:smoke
"$PHP_BIN" artisan ops:backup-audit --allow-missing --max-age-hours="${OPS_BACKUP_MAX_AGE_HOURS:-36}"
"$PHP_BIN" artisan ops:soft-launch-audit

if [ -n "$SOFT_LAUNCH_MANIFEST" ]; then
    "$PHP_BIN" artisan ops:soft-launch-onboard "$SOFT_LAUNCH_MANIFEST" --dry-run
fi

"$PHP_BIN" artisan test
"$NPM_BIN" run build

echo "Soft launch QA gate passed."
