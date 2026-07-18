#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR="${APP_DIR:-/var/www/toriloup/data/www/platform}"
GIT_URL="${GIT_URL:-https://github.com/sbayshop3171-ship-it/Toriloup.git}"
GIT_REMOTE="${GIT_REMOTE:-origin}"
GIT_BRANCH="${GIT_BRANCH:-main}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
NPM_BIN="${NPM_BIN:-npm}"
RSYNC_BIN="${RSYNC_BIN:-rsync}"
SKIP_GIT_SYNC="${SKIP_GIT_SYNC:-0}"
FORCE_DEPLOY="${FORCE_DEPLOY:-0}"
BACKUP_BEFORE_DEPLOY="${BACKUP_BEFORE_DEPLOY:-1}"
RUN_SMOKE="${RUN_SMOKE:-1}"
MAINTENANCE_MODE="${MAINTENANCE_MODE:-0}"
DEPLOY_COMMIT_SHA="${DEPLOY_COMMIT_SHA:-}"
SOURCE_DIR="${SOURCE_DIR:-}"
LOCK_FILE_RELATIVE="${LOCK_FILE_RELATIVE:-storage/logs/deploy-live.lock}"
STATE_FILE_RELATIVE="${STATE_FILE_RELATIVE:-storage/logs/deploy-live.state}"

cd "$APP_DIR"

mkdir -p storage/logs storage/framework
exec 9>"$APP_DIR/$LOCK_FILE_RELATIVE"
flock -n 9 || exit 0

maintenance_enabled=0
backup_artifact="none"

force_disable_maintenance() {
    (cd "$APP_DIR" && "$PHP_BIN" artisan up >/dev/null 2>&1) || true
    rm -f "$APP_DIR/storage/framework/down"
}

cleanup() {
    if [ "$maintenance_enabled" = "1" ]; then
        force_disable_maintenance
    fi
}

prepare_source_dir() {
    if [ -z "$SOURCE_DIR" ]; then
        return
    fi

    if [ ! -d "$SOURCE_DIR" ]; then
        echo "SOURCE_DIR does not exist: $SOURCE_DIR" >&2
        exit 1
    fi

    if [ "$SKIP_GIT_SYNC" != "1" ]; then
        echo "SOURCE_DIR deploy requires SKIP_GIT_SYNC=1." >&2
        exit 1
    fi

    cp "$APP_DIR/.env" "$SOURCE_DIR/.env"
    mkdir -p \
        "$SOURCE_DIR/storage/app/public" \
        "$SOURCE_DIR/storage/logs" \
        "$SOURCE_DIR/storage/framework/cache" \
        "$SOURCE_DIR/storage/framework/sessions" \
        "$SOURCE_DIR/storage/framework/views" \
        "$SOURCE_DIR/bootstrap/cache"
    chmod -R ug+rw "$SOURCE_DIR/storage" "$SOURCE_DIR/bootstrap/cache"
    cd "$SOURCE_DIR"
}

publish_source_dir() {
    if [ -z "$SOURCE_DIR" ]; then
        return
    fi

    "$RSYNC_BIN" -a --delete --delay-updates \
        --exclude='.env' \
        --exclude='.git' \
        --exclude='.github' \
        --exclude='.DS_Store' \
        --exclude='node_modules' \
        --exclude='public/hot' \
        --exclude='storage' \
        "$SOURCE_DIR"/ "$APP_DIR"/

    cd "$APP_DIR"
    force_disable_maintenance

    "$PHP_BIN" artisan optimize:clear
    "$PHP_BIN" artisan storage:link >/dev/null 2>&1 || true
    "$PHP_BIN" artisan config:cache
    "$PHP_BIN" artisan route:cache
    "$PHP_BIN" artisan view:cache
    "$PHP_BIN" artisan queue:restart >/dev/null 2>&1 || true
    "$PHP_BIN" artisan schedule:list >/dev/null
    "$PHP_BIN" artisan ops:deploy-health
}

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

php_env_value() {
    local key="$1" value="" app_dir

    app_dir="$(pwd)"

    if [ -f vendor/autoload.php ]; then
        value="$("$PHP_BIN" -r '
            $key = $argv[1] ?? "";
            $appDir = $argv[2] ?? getcwd();

            require $appDir . "/vendor/autoload.php";

            if (class_exists("\\Dotenv\\Dotenv")) {
                \Dotenv\Dotenv::createImmutable($appDir)->safeLoad();
            }

            $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

            if (is_array($value) || is_object($value)) {
                $value = "";
            }

            echo (string) ($value ?? "");
        ' "$key" "$app_dir" 2>/dev/null || true)"
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

    from_file="$(php_env_value "$key")"
    if [ -n "$from_file" ]; then
        printf '%s' "$from_file"
        return
    fi

    from_file="$(env_file_value "$key")"
    printf '%s' "${from_file:-$fallback}"
}

create_database_backup() {
    local backup_root db_connection timestamp artifact database_path dump_bin
    local db_host db_port db_database db_username db_password

    backup_root="$(env_value OPS_BACKUP_PATH "$APP_DIR/storage/app/backups")"
    mkdir -p "$backup_root"
    timestamp="$(date +%Y%m%d-%H%M%S)"
    db_connection="$(env_value DB_CONNECTION mysql)"
    db_connection="${db_connection:-mysql}"

    case "$db_connection" in
        sqlite)
            database_path="$(env_value DB_DATABASE "$APP_DIR/database/database.sqlite")"
            database_path="${database_path:-$APP_DIR/database/database.sqlite}"
            if [[ "$database_path" != /* ]]; then
                database_path="$APP_DIR/$database_path"
            fi
            artifact="$backup_root/${timestamp}-database.sqlite"
            cp "$database_path" "$artifact"
            ;;
        pgsql)
            dump_bin="${PG_DUMP_BIN:-pg_dump}"
            db_host="$(env_value DB_HOST 127.0.0.1)"
            db_port="$(env_value DB_PORT 5432)"
            db_database="$(env_value DB_DATABASE laravel)"
            db_username="$(env_value DB_USERNAME postgres)"
            db_password="$(env_value DB_PASSWORD '')"
            artifact="$backup_root/${timestamp}-database.sql.gz"
            if ! PGPASSWORD="$db_password" "$dump_bin" \
                --host="$db_host" \
                --port="$db_port" \
                --username="$db_username" \
                --dbname="$db_database" \
                | gzip -9 > "$artifact"; then
                rm -f "$artifact"
                return 1
            fi
            ;;
        *)
            dump_bin="${MYSQLDUMP_BIN:-mysqldump}"
            db_host="$(env_value DB_HOST 127.0.0.1)"
            db_port="$(env_value DB_PORT 3306)"
            db_database="$(env_value DB_DATABASE laravel)"
            db_username="$(env_value DB_USERNAME root)"
            db_password="$(env_value DB_PASSWORD '')"
            artifact="$backup_root/${timestamp}-database.sql.gz"
            if ! MYSQL_PWD="$db_password" "$dump_bin" \
                --host="$db_host" \
                --port="$db_port" \
                --user="$db_username" \
                --single-transaction \
                --quick \
                --skip-lock-tables \
                --no-tablespaces \
                "$db_database" \
                | gzip -9 > "$artifact"; then
                rm -f "$artifact"
                return 1
            fi
            ;;
    esac

    if [ ! -s "$artifact" ]; then
        rm -f "$artifact"
        return 1
    fi

    printf '%s' "$artifact"
}

if [ ! -f .env ]; then
    echo "Production .env is required before deploy." >&2
    exit 1
fi

force_disable_maintenance
prepare_source_dir

target_commit="${DEPLOY_COMMIT_SHA:-}"

if [ "$SKIP_GIT_SYNC" != "1" ]; then
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

    if [ "$FORCE_DEPLOY" != "1" ] && [ "$current_commit" = "$target_commit" ]; then
        exit 0
    fi

    git reset --hard "$GIT_REMOTE/$GIT_BRANCH"
fi

if [ -z "$target_commit" ]; then
    target_commit="manual-$(date +%s)"
fi

unlink public/hot >/dev/null 2>&1 || true
mkdir -p storage/app/public storage/logs bootstrap/cache storage/framework/cache storage/framework/sessions storage/framework/views
chmod -R ug+rw storage bootstrap/cache

if [ "$BACKUP_BEFORE_DEPLOY" = "1" ]; then
    if ! backup_artifact="$(create_database_backup)"; then
        echo "Database backup failed; deploy aborted before maintenance mode." >&2
        exit 1
    fi
fi

"$PHP_BIN" artisan optimize:clear

if [ "$MAINTENANCE_MODE" = "1" ]; then
    "$PHP_BIN" artisan down --retry=60
    maintenance_enabled=1
fi

trap cleanup EXIT
trap 'cleanup; exit 130' HUP INT TERM

"$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-progress
"$NPM_BIN" ci --include=dev --no-audit --no-fund
"$NPM_BIN" run build

"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan storage:link >/dev/null 2>&1 || true
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan queue:restart >/dev/null 2>&1 || true
"$PHP_BIN" artisan schedule:list >/dev/null
"$PHP_BIN" artisan ops:deploy-health

publish_source_dir
force_disable_maintenance
maintenance_enabled=0

if [ "$RUN_SMOKE" = "1" ]; then
    bash "$APP_DIR/scripts/smoke-production.sh"
fi

printf '%s %s %s\n' "$(date -Is)" "$target_commit" "$backup_artifact" >> "$APP_DIR/$STATE_FILE_RELATIVE"
