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
- `npm run test:orbital` verifies renderer bootstrap fallback, the exact journey cadence, seamless asset generation, no-data detection, color matching, eight-pixel feathering, candidate validation, manifest provenance, and the review-only refresh workflow.
- `npm run test:e2e` runs Playwright browser coverage for login, registration, dashboard, admin CRUD/search/filter/pagination, authorization, health/version readiness, executive command center, intelligence dashboard accessibility, rule management, report generation screens, dark-mode rendering paths, keyboard access, responsive smoke tests, and the Civic Earth journey.

## Coverage Targets

V1 should cover authentication, project CRUD, budget/procurement flows, search, intelligence engine reproducibility, rule management audit history, production health/version checks, report exports, and admin-only operations.

## Browser Testing Notes

Playwright is configured in `playwright.config.ts` with Chromium desktop and mobile projects, screenshots on failure, retained failure videos, and first-retry traces. The browser suite uses a dedicated SQLite database at `database/browser.sqlite` and runs `migrate:fresh --seed` during global setup.

Browser binaries are intentionally not committed. Run `npx playwright install chromium` on developer machines or in CI before `npm run test:e2e`.

The browser harness starts the feature-enabled application on port 8010 and a rollout-disabled static-hero instance on port 8011, keeping the developer preview on port 8000 independent. Civic Earth coverage verifies the exact nine stages, wheel and native scrolling boundaries, keyboard Home/End/arrows, reduced motion, dark mode, mobile text placement, public-safe project marker payloads, and renderer-module/runtime/regional imagery failure fallbacks. Stages 1–5 are attached to the Playwright report so the release reviewer can confirm there are no dark swaths, seams, blank tiles, or imagery errors.

## V2 Expansion Notes

Add performance tests, API contract tests, and model evaluation tests for intelligence features.
