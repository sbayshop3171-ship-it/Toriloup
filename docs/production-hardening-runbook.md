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

## Deploy flow

1. Upload the current application build to the server.
2. Run `bash scripts/deploy-live.sh`.
3. The deploy script will:
   - create a database backup
   - enter maintenance mode
   - install PHP and Node dependencies
   - build frontend assets
   - run migrations
   - refresh caches and storage links
   - restart queues
   - run `ops:deploy-health`
   - bring the app back up
   - run `scripts/smoke-production.sh`

## Smoke checks

Run:

```bash
php artisan ops:deploy-health
php artisan ops:smoke --strict
php artisan ops:backup-audit --max-age-hours="${OPS_BACKUP_MAX_AGE_HOURS:-36}"
bash scripts/smoke-production.sh
```

## Queue worker baseline

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
- puts the app in maintenance mode
- resets code to the target commit
- rebuilds dependencies and caches
- restarts queues
- runs smoke checks before finishing

## Recovery notes

- `rollback-live.sh` rolls back code and cached runtime state only
- database schema rollback is still a deliberate manual step if a migration is not backward compatible
- each deploy records timestamp, commit, and backup artifact in `storage/logs/deploy-live.state`
