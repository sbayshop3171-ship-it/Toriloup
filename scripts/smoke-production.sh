#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR="${APP_DIR:-/var/www/toriloup/data/www/platform}"
PHP_BIN="${PHP_BIN:-php}"
CURL_BIN="${CURL_BIN:-curl}"
TIMEOUT_SECONDS="${TIMEOUT_SECONDS:-15}"
SKIP_BACKUP_AUDIT="${SKIP_BACKUP_AUDIT:-0}"

cd "$APP_DIR"

health_url="${OPS_HEALTHCHECK_URL:-${APP_URL:-}/up}"
api_key_header="${VITE_API_KEY:-${APP_KEY:-}}"
owner_host="${SAAS_OWNER_HOST:-}"
merchant_host="${SAAS_MERCHANT_HOST:-}"
storefront_host="${OPS_STOREFRONT_HOST:-}"

request_json() {
    local url="$1"

    "$CURL_BIN" --fail --silent --show-error --max-time "$TIMEOUT_SECONDS" \
        -H "Accept: application/json" \
        -H "x-localization: en" \
        ${api_key_header:+-H "x-api-key: ${api_key_header}"} \
        "$url" >/dev/null
}

"$PHP_BIN" artisan ops:deploy-health
"$PHP_BIN" artisan ops:smoke --strict

if [ "$SKIP_BACKUP_AUDIT" != "1" ]; then
    "$PHP_BIN" artisan ops:backup-audit --max-age-hours="${OPS_BACKUP_MAX_AGE_HOURS:-36}"
fi

if [ -n "$health_url" ]; then
    "$CURL_BIN" --fail --silent --show-error --max-time "$TIMEOUT_SECONDS" "$health_url" >/dev/null
fi

if [ -n "$owner_host" ]; then
    request_json "https://${owner_host}/api/platform/up"
fi

if [ -n "$merchant_host" ]; then
    request_json "https://${merchant_host}/api/merchant/up"
fi

if [ -n "$storefront_host" ]; then
    request_json "https://${storefront_host}/api/storefront/up"
fi
