# ShopKing GitHub + Live Deploy Runbook

## 1) First-time local setup

```bash
git init -b main
git add .
git commit -m "Initial project backup"
```

## 2) Create GitHub repo and connect remote

Create an empty GitHub repository (do not add README/license from GitHub UI), then run:

```bash
git remote add origin https://github.com/<your-username>/<your-repo>.git
git push -u origin main
```

## 3) Daily workflow (safe)

```bash
git checkout main
git pull origin main

# make changes
git add .
git commit -m "Describe changes"
git push origin main
```

## 4) Live server first deploy (Laravel)

```bash
cd /var/www/shopking
git clone https://github.com/<your-username>/<your-repo>.git .
cp .env.example .env
composer install --no-dev --optimize-autoloader
npm install
npm run build
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Set permissions:

```bash
chown -R www-data:www-data /var/www/shopking
chmod -R 775 storage bootstrap/cache
```

## 5) Live server update deploy

```bash
cd /var/www/shopking
git fetch --prune origin main
git checkout -B main origin/main
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 6) GitHub Actions deploy secrets (required)

Repository `Settings` -> `Secrets and variables` -> `Actions`:

- `SSH_HOST`: live server IP or domain
- `SSH_USER`: SSH username
- `SSH_PORT`: usually `22`
- `SSH_PRIVATE_KEY`: private key for the above user
- `DEPLOY_PATH`: server path, example `/var/www/shopking`

If any one of these is missing, deploy workflow will fail before SSH.

## 7) Quick troubleshoot when push works but live is not updated

1. Open `GitHub -> Actions -> Deploy Live` and confirm the latest run is `success`.
2. If `Deploy via SSH` fails immediately (within a few seconds), usually secrets or SSH access is wrong.
3. SSH to server and verify repository commit:

```bash
cd /var/www/shopking
git rev-parse --short HEAD
git log -1 --oneline
```

4. Compare with GitHub latest commit on `main`. If different, deploy did not pull latest code.
5. Run manual deploy once on server to unblock production:

```bash
cd /var/www/shopking
git fetch --prune origin main
git checkout -B main origin/main
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 8) Important rules

- Never commit `.env`.
- Keep `APP_DEBUG=false` in production.
- Always backup database before risky changes.
- Run queue worker in production if notifications/jobs are used:

```bash
php artisan queue:work --tries=3
```

- Add cron for scheduler:

```bash
* * * * * cd /var/www/shopking && php artisan schedule:run >> /dev/null 2>&1
```
