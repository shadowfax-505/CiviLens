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
- `/dashboard` is protected by Laravel auth middleware.
- `/login` exists as a placeholder route for the full auth UI.

## Acceptance Criteria

- App boots locally.
- Users can register and log in.
- Admin role exists.
- Tests cover auth basics.
- Docs updated.

## Remaining

- Implement full registration and login flow.
- Add real auth views and request validation.
- Configure MySQL/Redis/Meilisearch services for local development.
