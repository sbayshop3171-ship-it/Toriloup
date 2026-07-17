#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/toriloup/data/www/platform}"
GIT_URL="${GIT_URL:-https://github.com/sbayshop3171-ship-it/Toriloup.git}"
GIT_REMOTE="${GIT_REMOTE:-origin}"
GIT_BRANCH="${GIT_BRANCH:-main}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
NPM_BIN="${NPM_BIN:-npm}"

cd "$APP_DIR"

mkdir -p storage/logs
exec 9>"$APP_DIR/storage/logs/deploy-live.lock"
flock -n 9 || exit 0

if [ ! -d .git ]; then
    git init
fi

if ! git remote get-url "$GIT_REMOTE" >/dev/null 2>&1; then
    git remote add "$GIT_REMOTE" "$GIT_URL"
fi

git config --global --add safe.directory "$APP_DIR" >/dev/null 2>&1 || true
git fetch --quiet "$GIT_REMOTE" "$GIT_BRANCH"

current_commit="$(git rev-parse HEAD 2>/dev/null || true)"
target_commit="$(git rev-parse "$GIT_REMOTE/$GIT_BRANCH")"

if [ "${FORCE_DEPLOY:-0}" != "1" ] && [ "$current_commit" = "$target_commit" ]; then
    exit 0
fi

git reset --hard "$GIT_REMOTE/$GIT_BRANCH"
unlink public/hot >/dev/null 2>&1 || true

"$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-progress
"$NPM_BIN" ci --include=dev --no-audit --no-fund
"$NPM_BIN" run build

"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan storage:link >/dev/null 2>&1 || true
mkdir -p storage/app/public storage/logs bootstrap/cache storage/framework/cache storage/framework/sessions storage/framework/views
chmod -R ug+rw storage bootstrap/cache

"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache

printf '%s %s\n' "$(date -Is)" "$target_commit" >> storage/logs/deploy-live.state
