# SCAK Platform Backend

Laravel 12 backend and customer website for the SCAK revamp.

## What is included

- Customer OTP login, catalog, filters, product detail pages, local bucket flow, and order request submission
- Admin API for OTP login, product management, image upload, PDF generation, and order request handling
- Normalized catalog schema for suppliers, cities, categories, fabrics, sizes, and features
- WordPress import command for products, approved admins, verified customers, and optional analytics archive export

## Project structure

- `routes/web.php`: customer website routes and JSON endpoints used by the Blade storefront
- `routes/api.php`: Sanctum-protected admin API used by the Android app
- `app/Services/WordPressImportService.php`: WooCommerce and OTP migration helpers
- `app/Services/OtpService.php`: WhatsApp OTP integration wrapper with database-backed challenges
- `resources/views/storefront`: customer-facing Blade pages

## Local setup

1. Copy `.env.example` to `.env`
2. Set `DB_*`, `WP_DB_*`, `OTP_*`, storage, and FCM values
3. Create the SQLite file if using the default local setup: `database/database.sqlite`
4. Install dependencies:

```powershell
php -c ..\php-dev.ini ..\composer.phar install
```

5. Generate the key and run migrations:

```powershell
php -c ..\php-dev.ini artisan key:generate --force
php -c ..\php-dev.ini artisan migrate --force
```

6. Create the public storage symlink:

```powershell
php -c ..\php-dev.ini artisan storage:link
```

7. Start the app:

```powershell
php -c ..\php-dev.ini artisan serve
```

## Useful commands

```powershell
php -c ..\php-dev.ini vendor\bin\phpunit
php -c ..\php-dev.ini artisan route:list
php -c ..\php-dev.ini artisan scak:import-wordpress --archive-analytics
```

## Notes

- The local development setup in this workspace uses SQLite for convenience.
- Production is intended for MySQL 8, Redis, and an S3-compatible object store.
- Customer website auth is session-based; Android admin auth uses Sanctum tokens.
