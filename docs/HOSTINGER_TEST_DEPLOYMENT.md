# Hostinger Test Deployment

This guide is for staging the Laravel app on `https://test.scak.in` before moving to `https://www.scak.in`.

## Recommended repo to deploy

Deploy only the Laravel backend folder:

- local folder: `D:\Codex\SCAK_UploadProducts\Platform\backend`
- GitHub repo: create a dedicated repo for this folder, for example `scak-platform-backend`

The Android app should stay in a separate repo or remain local for now.

## Hostinger setup

1. Create `test.scak.in` as a subdomain / website in hPanel and point it to an empty folder.
2. In Git Deployment, connect that site to the GitHub repo for this Laravel backend.
3. Keep the repository install path at the site root for `test.scak.in`.
4. Make sure SSH access is enabled for the hosting plan.

## Server requirements

- PHP 8.3 or newer
- MySQL database for staging
- Composer support through Hostinger Git deployment / SSH

## Environment values for staging

Use values like these in `.env` on Hostinger:

```dotenv
APP_NAME="SCAK Platform"
APP_ENV=staging
APP_DEBUG=true
APP_URL=https://test.scak.in

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=YOUR_TEST_DB
DB_USERNAME=YOUR_TEST_USER
DB_PASSWORD=YOUR_TEST_PASSWORD

CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database

FILESYSTEM_DISK=products
PRODUCT_FILESYSTEM_DRIVER=local

OTP_TEST_MODE=true
OTP_ENDPOINT=
```

## First-time SSH commands

Run these on the Hostinger site after the first deployment:

```bash
cd ~/domains/test.scak.in
cp .env.example .env
php artisan key:generate --force
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
```

If `storage:link` is not allowed on the plan, create the equivalent link or folder mapping manually so `/storage` points to `storage/app/public`.

## Why the root files exist

This repo includes a root `index.php` and root `.htaccess` so Hostinger can serve the app directly from the Git deployment folder without needing the Apache document root to be changed to `public/`.

## Cutover later

When staging is approved:

1. Change `APP_URL` to `https://www.scak.in`
2. Point the production domain to the same deployment flow
3. Change the Android app base URL from `https://test.scak.in/` to the production URL
