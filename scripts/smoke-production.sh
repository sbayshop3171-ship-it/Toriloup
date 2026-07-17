#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR="${APP_DIR:-/var/www/toriloup/data/www/platform}"
PHP_BIN="${PHP_BIN:-php}"
CURL_BIN="${CURL_BIN:-curl}"
TIMEOUT_SECONDS="${TIMEOUT_SECONDS:-15}"
SKIP_BACKUP_AUDIT="${SKIP_BACKUP_AUDIT:-0}"

cd "$APP_DIR"

env_file_value() {
    local key="$1" value

    value="$(grep -E "^${key}=" .env | tail -n 1 | cut -d= -f2- || true)"
    value="${value%$'\r'}"

    if [[ "$value" == \"*\" && "$value" == *\" ]]; then
        value="${value:1:${#value}-2}"
    elif [[ "$value" == \'*\' && "$value" == *\' ]]; then
        value="${value:1:${#value}-2}"
    fi

    printf '%s' "$value"
}

env_value() {
    local key="$1" fallback="${2:-}" current from_file

    current="${!key-}"

    if [ -n "$current" ]; then
        printf '%s' "$current"
        return
    fi

    from_file="$(env_file_value "$key")"
    printf '%s' "${from_file:-$fallback}"
}

app_url="$(env_value APP_URL '')"
health_url="$(env_value OPS_HEALTHCHECK_URL '')"

if [ -z "$health_url" ] && [ -n "$app_url" ]; then
    health_url="${app_url%/}/up"
fi

api_key_header="$(env_value VITE_API_KEY "$(env_value APP_KEY '')")"
owner_host="$(env_value SAAS_OWNER_HOST '')"
merchant_host="$(env_value SAAS_MERCHANT_HOST '')"
storefront_host="$(env_value OPS_STOREFRONT_HOST '')"

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
