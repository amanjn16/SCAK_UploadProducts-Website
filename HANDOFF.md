# SCAK One Handoff

## Purpose

SCAK One is the unified successor for `www.scak.in`, the one-time Sale workflow, the Admin web interface, the signed Sale-first Android Admin app, and supplier stock feedback.

The existing Main and Sale projects remain unchanged. This repository was created from the Main Laravel repository and has the Sale Android source under `admin-android/`.

## Confirmed product rules

- Every product is either `regular` or `sale`.
- Sale products are also part of the regular customer catalogue; there is no `both` type.
- Name, brand, rate, availability, and a cover image are the common business requirements.
- Brand price, remarks, tags, supplier, supplier city, and category can be enabled and made optional/mandatory by product type.
- Disabling a configured field hides it but does not delete saved values.
- Products, reports, selection, PDFs, and sharing remain one UI. Sale is a filter, not a separate module.

## Implemented foundation

- Unified product fields and Regular/Sale filtering.
- Configurable product-field settings and per-type validation.
- Super-admin Product Fields screen controls Regular/Sale enablement, mandatory status, customer visibility, and PDF/sharing eligibility; disabling a field preserves data and clears its mandatory flag.
- Reusable supplier stock items with retained bundle photos.
- Daily supplier sessions prefilled with yesterday's item names, photos, and quantities.
- Explicit `Same`, `Change`, `Zero`, and `Not Found` confirmation before submission.
- New stock item plus bundle-photo capture in the Admin website.
- Sale-first mobile compatibility API backed by the shared Laravel product database.
- Sale Android source retained with clipboard/share intent, gallery, photo management, PDF, price-list, and stock-report code.
- Android application renamed to SCAK Admin while retaining `in.scak.sale` and the ignored local Sale signing files for upgrade compatibility.
- Android product creation asks Regular or Sale; Sale items are also part of the regular catalogue.
- Native Android Stock Feedback reuses yesterday's items/photos, supports daily confirmation and bundle-photo replacement.
- Automated two-day stock pilot verifies photo reuse and quantity carry-forward.
- Web admin authentication uses approved phone number, a six-digit PIN, and a time-based authenticator code. First login enrolls the authenticator with a manual setup key; admin OTP routes are disabled.
- Product images retain exact SHA-256 source fingerprints. A daily scheduled scan backfills historical images, and super admins can review exact-image duplicate groups, merge into a chosen product, or recoverably archive a duplicate.

## Current boundary

This is an implementation workspace, not a production deployment. No live database, website, DNS, or installed phone app has been changed.

Before production migration, inventory both live databases and media stores, back them up, match duplicates, assign product types, populate brands for historical products, and verify the Sale-to-main reconciliation counts.

## Verification

Use the workstation PHP configuration:

```powershell
$env:APP_KEY='a valid local Laravel key'
php -c 'D:\Codex\SCAK Websites\SCAK Main Website\SCAK_UploadProducts\Platform\php-dev.ini' vendor\phpunit\phpunit\phpunit
```

Build the signed Android app:

```powershell
Set-Location 'D:\Codex\SCAK Websites\SCAK One\admin-android'
cmd /c "gradle.bat --no-daemon -Dorg.gradle.jvmargs=-Xmx512m assembleRelease"
```

The ignored `admin-android/keystore.properties` and `admin-android/release-keystore.jks` must never be committed or exposed.
