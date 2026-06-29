# 25 Contributing Guide

## Setup Expectations

Developers should run the app locally, run tests before PRs, and update documentation with implementation changes.

## Required Checks

- `composer validate --strict`
- `composer quality`
- `npm run build`
- `npm run test:e2e` for UI-impacting changes after Playwright browsers are installed.

## Pull Request Expectations

- Clear scope.
- Tests included.
- Docs included.
- Security impact noted.
- Static analysis, formatting, and maintainability checks pass.
- V2 impact noted when relevant.
