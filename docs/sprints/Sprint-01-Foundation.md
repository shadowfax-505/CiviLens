# Sprint 01: Foundation

## Objectives

- Scaffold Laravel application.
- Configure local environment.
- Add authentication.
- Decide roles and permissions implementation.

## Progress

- Laravel 13 scaffold is complete.
- Local `.env` boots the app as CivicLens.
- Role and permission tables, models, factories, seed data, and tests are complete.
- Registration, login, logout, forgot password, password reset, email verification, and password confirmation are implemented.
- Profile management, avatar upload, password changes, notification preferences, and account activity are implemented.
- Administrator user management with search, filtering, sorting, pagination, status controls, locks, role assignment, and password reset is implemented.
- `/dashboard` is protected by Laravel auth and active-account middleware.

## Acceptance Criteria

- App boots locally.
- Users can register and log in.
- Admin role exists.
- Tests cover auth basics.
- Docs updated.

## Remaining

- Implement full registration and login flow.
- Configure MySQL/Redis/Meilisearch services for local development.
- Install Sanctum and implement token API auth when package installation is available.
