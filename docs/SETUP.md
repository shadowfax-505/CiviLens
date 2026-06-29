# Local Setup

This guide prepares CivicLens for Sprint 1 development.

## Prerequisites

- PHP compatible with the chosen Laravel version.
- Composer.
- Node.js and npm.
- MySQL.
- Redis.
- Meilisearch.
- Git.

Docker or Laravel Sail can provide these services if you do not want to install them directly.

## First Laravel Scaffold

When development begins, scaffold Laravel into this repository root.

```bash
composer create-project laravel/laravel .
```

If Composer refuses because the directory is not empty, use a temporary directory, then move generated Laravel application files into this repo without deleting `.ai/`, `docs/`, `prompts/`, or `.github/`.

## Environment

1. Copy `.env.example` to `.env`.
2. Set database credentials.
3. Set `MEILISEARCH_HOST` and `REDIS_HOST`.
4. Generate the app key after Laravel exists.

```bash
php artisan key:generate
```

## Verification

After Laravel is scaffolded:

```bash
php artisan about
php artisan test
npm install
npm run build
```

## V2 Boundary

Do not enable v2 features during v1 development unless an ADR promotes them into the active roadmap.

