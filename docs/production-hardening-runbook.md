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

### GitHub Actions release flow

- A push to `main` runs `CI`; it does not deploy to production.
- To publish, open **Actions → Deploy Live**, select `main`, and set `deploy_live` to `true`.
- The workflow deploys only the exact `main` commit with a successful `CI` run and checks that `main` has not moved before publishing.
- Configure the repository Actions variables `SSH_HOST` and `SSH_USER`; `SSH_PORT` is optional and defaults to `22`.
- Configure the repository Actions secret `SSH_PRIVATE_KEY` with a private key whose public key is authorized for the deployment account on the server.
- Install the repository's `scripts/toriloup-manual-deploy.sh` as `/usr/local/sbin/toriloup-manual-deploy`, owned by root with mode `0755`.
- Add the narrow sudoers entry `owneradmin ALL=(root) NOPASSWD: /usr/local/sbin/toriloup-manual-deploy` using a root-owned `0440` file in `/etc/sudoers.d`, then validate it with `visudo -c`.
- The helper only accepts calls from `owneradmin`, deploys only `/var/www/toriloup/data/www/platform` as `toriloup:toriloup`, verifies the release archive SHA-256 and commit format, and runs the existing deploy script as the app owner. It is separate from the Zulors Shop deploy helper.
- The workflow uploads a checksummed release archive to `/tmp`, then the root helper stages it in a private `/var/tmp/toriloup-deploy` directory before running the hardened deploy script below.

For the one-time server setup, after placing `scripts/toriloup-manual-deploy.sh` at `/tmp/toriloup-manual-deploy.sh`, run:

```bash
sudo install -o root -g root -m 0755 /tmp/toriloup-manual-deploy.sh /usr/local/sbin/toriloup-manual-deploy
printf '%s\n' 'owneradmin ALL=(root) NOPASSWD: /usr/local/sbin/toriloup-manual-deploy' | sudo tee /etc/sudoers.d/toriloup-deploy >/dev/null
sudo chmod 0440 /etc/sudoers.d/toriloup-deploy
sudo visudo -c
```

The helper runs `APP_DIR=/var/www/toriloup/data/www/platform SOURCE_DIR=<staged-release> SKIP_GIT_SYNC=1 MAINTENANCE_MODE=0 bash <staged-release>/scripts/deploy-live.sh` as the `toriloup` app owner. The deploy script will:
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
