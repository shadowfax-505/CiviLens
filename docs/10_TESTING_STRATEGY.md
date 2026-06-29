# 10 Testing Strategy

## Test Types

- Unit tests for domain rules.
- Feature tests for HTTP endpoints.
- Authorization tests for role-sensitive actions.
- Database tests for relationships and constraints.
- Browser tests for critical UI flows with Playwright.

## Current Test Commands

- `composer test` runs the Laravel/Pest suite.
- `vendor/bin/pint --test` verifies code style.
- `composer analyse` runs PHPStan/Larastan at level 8 against the application layer.
- `composer refactor:dry` runs the safe Rector dry-run profile.
- `composer metrics` runs the local complexity report.
- `composer quality` runs the primary PHP quality gate.
- `npm run build` verifies frontend assets.
- `npm run test:e2e` runs Playwright browser coverage for login, registration, dashboard, admin CRUD/search/filter/pagination, authorization, and responsive smoke tests.

## Coverage Targets

V1 should cover authentication, project CRUD, budget/procurement flows, search, and admin-only operations.

## Browser Testing Notes

Playwright is configured in `playwright.config.ts` with Chromium desktop and mobile projects, screenshots on failure, retained failure videos, and first-retry traces. The browser suite uses a dedicated SQLite database at `database/browser.sqlite` and runs `migrate:fresh --seed` during global setup.

Browser binaries are intentionally not committed. Run `npx playwright install chromium` on developer machines or in CI before `npm run test:e2e`.

## V2 Expansion Notes

Add performance tests, API contract tests, and model evaluation tests for intelligence features.
