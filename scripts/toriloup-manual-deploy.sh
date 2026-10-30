#!/usr/bin/env bash

set -Eeuo pipefail

readonly APP_DIR="/var/www/toriloup/data/www/platform"
readonly APP_OWNER="toriloup"
readonly APP_GROUP="toriloup"
readonly STAGING_ROOT="/var/tmp/toriloup-deploy"
readonly HELPER_PATH="/usr/local/sbin/toriloup-manual-deploy"

fail() {
    echo "$1" >&2
    exit 1
}

check_invocation() {
    [[ "$EUID" -eq 0 ]] || fail "This helper must run as root through sudo."
    [[ "${SUDO_USER:-}" == "owneradmin" ]] || fail "Only the configured deployment account may use this helper."
    [[ -d "$APP_DIR" && -f "$APP_DIR/artisan" && -f "$APP_DIR/.env" ]] || fail "Toriloup production app is not ready at the configured path."
    [[ ! -L "$APP_DIR" ]] || fail "The production app path must not be a symlink."
    [[ "$(stat -c '%U:%G' "$APP_DIR")" == "$APP_OWNER:$APP_GROUP" ]] || fail "Toriloup app ownership does not match the deployment configuration."
    id "$APP_OWNER" >/dev/null 2>&1 || fail "Configured app owner does not exist."
    getent group "$APP_GROUP" >/dev/null || fail "Configured app group does not exist."
    for command_name in flock getent gzip id install rsync runuser sha256sum stat tar; do
        command -v "$command_name" >/dev/null || fail "Required server command is unavailable: $command_name"
    done
}

if [[ "$#" -eq 1 && "$1" == "--check" ]]; then
    check_invocation
    runuser -u "$APP_OWNER" -- test -r "$APP_DIR/.env" || fail "The app owner cannot read the production environment file."
    runuser -u "$APP_OWNER" -- test -w "$APP_DIR/storage" || fail "The app owner cannot write production storage."
    runuser -u "$APP_OWNER" -- bash -c 'command -v php >/dev/null && command -v composer >/dev/null && command -v npm >/dev/null' || fail "The app owner needs PHP, Composer, and npm on PATH."
    install -d -o root -g root -m 0755 "$STAGING_ROOT"
    [[ ! -L "$STAGING_ROOT" && "$(stat -c '%U:%G' "$STAGING_ROOT")" == "root:root" ]] || fail "The private release staging root is not safely owned."
    echo "Toriloup deploy helper is ready for owneradmin."
    exit 0
fi

[[ "$#" -eq 3 ]] || fail "Usage: $HELPER_PATH <commit-sha> <release-archive> <sha256>"

DEPLOY_SHA="$1"
RELEASE_ARCHIVE="$2"
RELEASE_ARCHIVE_SHA256="$3"

[[ "$DEPLOY_SHA" =~ ^[0-9a-f]{40}$ ]] || fail "Invalid deployment commit SHA."
[[ "$RELEASE_ARCHIVE_SHA256" =~ ^[0-9a-f]{64}$ ]] || fail "Invalid release archive checksum."
[[ "$RELEASE_ARCHIVE" == "/tmp/toriloup-release-${DEPLOY_SHA}.tar.gz" ]] || fail "Invalid release archive path."

check_invocation
[[ -f "$RELEASE_ARCHIVE" && ! -L "$RELEASE_ARCHIVE" ]] || fail "Release archive is missing or is a symlink."
[[ "$(stat -c '%U' "$RELEASE_ARCHIVE")" == "owneradmin" ]] || fail "Release archive must be uploaded by owneradmin."

actual_checksum="$(sha256sum "$RELEASE_ARCHIVE" | cut -d' ' -f1)"
[[ "$actual_checksum" == "$RELEASE_ARCHIVE_SHA256" ]] || fail "Release archive checksum does not match."

mkdir -p /run/lock
lock_key="$(printf '%s' "$APP_DIR" | sha256sum | cut -c1-16)"
exec 9>"/run/lock/toriloup-deploy-${lock_key}.lock"
flock -n 9 || fail "A Toriloup deployment is already running."

TEMP_DIR="$(mktemp -d "/tmp/toriloup-release-${DEPLOY_SHA}.XXXXXX")"
SOURCE_DIR="$STAGING_ROOT/$DEPLOY_SHA"

cleanup() {
    rm -rf -- "$TEMP_DIR"
    if [[ -n "${SOURCE_DIR:-}" && "$SOURCE_DIR" == "$STAGING_ROOT/"* ]]; then
        rm -rf -- "$SOURCE_DIR"
    fi
    rm -f -- "$RELEASE_ARCHIVE"
}
trap cleanup EXIT

if tar -tzf "$RELEASE_ARCHIVE" | grep -E '(^/|(^|/)\.\.(/|$))' >/dev/null; then
    fail "Release archive contains an unsafe path."
fi

mkdir -p "$TEMP_DIR/source"
tar -xzf "$RELEASE_ARCHIVE" -C "$TEMP_DIR/source"
[[ -f "$TEMP_DIR/source/artisan" && -f "$TEMP_DIR/source/scripts/deploy-live.sh" ]] || fail "Release archive is missing the Laravel app or deploy script."

install -d -o root -g root -m 0755 "$STAGING_ROOT"
[[ ! -L "$STAGING_ROOT" && "$(stat -c '%U:%G' "$STAGING_ROOT")" == "root:root" ]] || fail "The private release staging root is not safely owned."
if [[ -e "$SOURCE_DIR" || -L "$SOURCE_DIR" ]]; then
    [[ ! -L "$SOURCE_DIR" ]] || fail "The staged release path must not be a symlink."
    rm -rf -- "$SOURCE_DIR"
fi
install -d -o "$APP_OWNER" -g "$APP_GROUP" -m 0700 "$SOURCE_DIR"
rsync -a --delete --chown="$APP_OWNER:$APP_GROUP" "$TEMP_DIR/source/" "$SOURCE_DIR/"

runuser -u "$APP_OWNER" -- env \
    APP_DIR="$APP_DIR" \
    SOURCE_DIR="$SOURCE_DIR" \
    SKIP_GIT_SYNC=1 \
    MAINTENANCE_MODE=0 \
    DEPLOY_COMMIT_SHA="$DEPLOY_SHA" \
    bash "$SOURCE_DIR/scripts/deploy-live.sh"

echo "Deployed ${DEPLOY_SHA} to the Toriloup production app."
