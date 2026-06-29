# 11 Deployment Guide

## V1 Local Environment

Use Docker or Laravel Sail for PHP, MySQL, Redis, Meilisearch, and mail testing.

## Deployment Principles

- Environment variables configure services.
- Database migrations run during controlled releases.
- Queue workers are supervised.
- Storage is backed up.
- Uploaded files must use Laravel Storage disks rather than hardcoded provider paths.
- CI must pass Composer validation, tests, Pint, PHPStan/Larastan, Rector dry-run, frontend build, browser tests, and cache checks before deployment.

## Release Verification

Before promoting a release, run:

- `composer install --no-interaction --prefer-dist --optimize-autoloader`
- `composer quality`
- `npm ci`
- `npm run build`
- `npm run test:e2e`
- `php artisan config:cache`
- `php artisan route:cache`
- `php artisan view:cache`

Queue workers should be restarted after deployment so new event listeners and queued job classes are loaded.

## V2 Expansion Notes

Add separate workers for OCR, indexing, analytics, and public API workloads.
