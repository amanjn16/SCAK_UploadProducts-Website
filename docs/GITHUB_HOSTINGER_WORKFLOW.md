# GitHub And Hostinger Workflow

Use this when you want a clean deploy repo for `https://test.scak.in` while keeping the full project in a separate master repo.

## Recommended structure

- Master repo: full workspace with Laravel, Android, old WordPress, docs, and scripts
- Deploy repo: Laravel backend only, exported from `Platform/backend`

## 1. Export the deploy repo

From this workspace, run:

```powershell
powershell -ExecutionPolicy Bypass -File "D:\Codex\SCAK_UploadProducts\scripts\export-backend-deploy-repo.ps1" -TargetPath "D:\Codex\scak-platform-backend"
```

That creates a clean copy of the Laravel app without local-only items like `.env` and `vendor`.

## 2. Create the GitHub repo

Create an empty private repo on GitHub, for example:

- `scak-platform-backend`

Do not add a README or `.gitignore` from GitHub because the exported folder already has what we need.

## 3. Push the deploy repo

Replace `YOUR_GITHUB_URL` with the actual repo URL:

```powershell
cd "D:\Codex\scak-platform-backend"
git init -b main
git add .
git commit -m "Initial staging deploy"
git remote add origin YOUR_GITHUB_URL
git push -u origin main
```

## 4. Connect `test.scak.in` in Hostinger

In Hostinger hPanel:

1. Create `test.scak.in` as a website / subdomain
2. Open that site in hPanel
3. Go to Git Deployment
4. Add the GitHub repository and branch `main`
5. Leave the install path empty so Hostinger deploys to the selected site's root folder
6. Trigger the first deploy
7. Enable auto deployment webhook if you want every GitHub push to redeploy automatically

Official Hostinger docs:

- Git deployment: https://support.hostinger.com/en/articles/1583302-how-to-deploy-a-git-repository-in-hostinger
- SSH access: https://support.hostinger.com/en/articles/1583245-how-to-connect-to-a-hosting-plan-via-ssh

## 5. First-time server setup over SSH

SSH into the Hostinger site and run:

```bash
cd ~/domains/test.scak.in
cp .env.example .env
php artisan key:generate --force
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
```

## 6. Staging `.env` values

At minimum set these:

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

OTP_TEST_MODE=true
```

## 7. Update staging after local changes

Whenever backend changes are ready:

```powershell
powershell -ExecutionPolicy Bypass -File "D:\Codex\SCAK_UploadProducts\scripts\export-backend-deploy-repo.ps1" -TargetPath "D:\Codex\scak-platform-backend"
cd "D:\Codex\scak-platform-backend"
git add .
git commit -m "Update staging deploy"
git push
```

If Hostinger auto deployment is enabled, the site should redeploy after the push.
