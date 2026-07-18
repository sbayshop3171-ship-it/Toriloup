# Production Hardening Runbook

This runbook is the Week 7 operational baseline for Toriloup.

## Required environment

- `APP_URL`
- `SAAS_MARKETING_HOST`
- `SAAS_OWNER_HOST`
- `SAAS_MERCHANT_HOST`
- `VITE_API_KEY`
- `OPS_BACKUP_PATH`
- `OPS_BACKUP_MAX_AGE_HOURS`
- `OPS_HEALTHCHECK_URL`
- `OPS_STOREFRONT_HOST`
- `QUEUE_CONNECTION=database` or `QUEUE_CONNECTION=redis`

Do not use `QUEUE_CONNECTION=sync` in production. The deploy health gate treats sync queues as unsafe because uploads, notifications, mail, media conversions, and retries should not block web traffic.

## Deploy flow

1. Upload the current application source to a staged release directory, not directly into the live app directory.
2. Run `APP_DIR=/var/www/toriloup/data/www/platform SOURCE_DIR=<staged-release> SKIP_GIT_SYNC=1 MAINTENANCE_MODE=0 bash <staged-release>/scripts/deploy-live.sh`.
3. The deploy script will:
   - clear any stale Laravel maintenance lock left by an interrupted deploy
   - create a database backup
   - install PHP and Node dependencies in the staged release
   - build frontend assets in the staged release
   - run migrations after the staged build succeeds
   - publish the staged release to the live directory only after the build and health gate pass
   - refresh live caches and storage links
   - restart queues
   - run `ops:deploy-health`
   - force Laravel maintenance mode off as a final safety step
   - run `scripts/smoke-production.sh`

Default deploys intentionally do not call `php artisan down`. If a fully blocking maintenance deploy is ever required, set `MAINTENANCE_MODE=1` explicitly for that one operation.

## Smoke checks

Run:

```bash
php artisan ops:deploy-health
php artisan ops:smoke --strict
php artisan ops:backup-audit --max-age-hours="${OPS_BACKUP_MAX_AGE_HOURS:-36}"
bash scripts/smoke-production.sh
```

## Queue worker baseline

Preferred process manager: Supervisor or systemd.

Supervisor example:

```ini
[program:toriloup-queue]
command=php /var/www/toriloup/data/www/platform/artisan queue:work --tries=3 --timeout=120
directory=/var/www/toriloup/data/www/platform
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
stdout_logfile=/var/www/toriloup/data/www/platform/storage/logs/queue-worker.log
stderr_logfile=/var/www/toriloup/data/www/platform/storage/logs/queue-worker-error.log
```

Shared hosting fallback when Supervisor is not available:

```cron
* * * * * cd /var/www/toriloup/data/www/platform && php artisan queue:work --stop-when-empty --tries=3 --timeout=120 >> storage/logs/queue-worker.log 2>&1
```

This fallback is not as strong as Supervisor, but it keeps database-backed jobs moving until a managed worker is installed.

## Scheduler baseline

Cron:

```cron
* * * * * cd /var/www/toriloup/data/www/platform && php artisan schedule:run >> /dev/null 2>&1
```

The application schedules `ops:backup-audit` daily at `03:15`.

## Rollback flow

Run:

```bash
bash scripts/rollback-live.sh
```

Optional explicit commit:

```bash
bash scripts/rollback-live.sh <commit-sha>
```

Rollback behavior:

- resolves the previous deployed commit from `storage/logs/deploy-live.state` if no commit is provided
- keeps the app live by default and clears stale maintenance locks
- resets code to the target commit
- rebuilds dependencies and caches
- restarts queues
- runs smoke checks before finishing

Set `MAINTENANCE_MODE=1` only for an intentional blocking rollback window.

## Recovery notes

- `rollback-live.sh` rolls back code and cached runtime state only
- database schema rollback is still a deliberate manual step if a migration is not backward compatible
- each deploy records timestamp, commit, and backup artifact in `storage/logs/deploy-live.state`

Backup note:

- MySQL backups use the application DB credentials and `mysqldump --no-tablespaces`, so the app user does not need the `PROCESS` privilege.
