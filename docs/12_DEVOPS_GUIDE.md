# 12 DevOps Guide

## CI/CD

V1 runs GitHub Actions on push and pull request. The workflow validates Composer metadata, installs PHP and Node dependencies with caching, prepares the Laravel test environment, runs Pest, Pint, PHPStan/Larastan, Rector dry-run, complexity metrics, the frontend build, Playwright browser tests, and Laravel cache checks.

## Static Analysis

- `composer analyse` runs PHPStan/Larastan at level 8.
- The active PHPStan scope covers controllers, requests, policies, providers, services, support classes, configuration, and routes.
- Strict/deprecation rule packages are installed for future expansion, but the active profile stays focused on reliable Laravel application checks.
- Suppressions should be avoided; if one becomes necessary, document the reason near the configuration change.

## Automated Refactoring

- `composer refactor:dry` runs Rector in dry-run mode.
- The current Rector profile is intentionally conservative and limited to safe dead-code detection.
- Do not apply broad automated rewrites to business workflows without a dedicated refactoring sprint.

## Browser Testing

- `npm run test:e2e` runs Playwright.
- CI installs Chromium before execution.
- Screenshots, videos, and traces are retained only when useful for failures.

## Operations

- Back up MySQL.
- Monitor queue failures.
- Track search indexing failures.
- Record release notes.
- Restart workers after deployments.
- Keep `.env.example` aligned with queue, storage, mail, and external-service configuration.

## V2 Expansion Notes

Add blue/green deployment notes, centralized logs, and infrastructure-as-code.
