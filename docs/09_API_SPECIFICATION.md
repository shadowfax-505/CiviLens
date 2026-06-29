# 09 API Specification

## Conventions

- JSON responses.
- Plural resource names.
- Laravel validation error format.
- Sanctum bearer tokens for private APIs.
- Public read-only APIs may be introduced in v2.

## Core Endpoints

- `POST /api/auth/register`
- `POST /api/auth/login`
- `POST /api/auth/logout`
- `GET /api/users`
- `GET /api/agencies`
- `GET /api/projects`
- `POST /api/projects`
- `GET /api/projects/{id}`
- `PUT /api/projects/{id}`
- `DELETE /api/projects/{id}`
- `GET /api/search`
- `GET /api/analytics`

## Current Implementation Status

The Laravel app currently implements web identity routes for:

- `GET /register`
- `POST /register`
- `GET /login`
- `POST /login`
- `POST /logout`
- `GET /forgot-password`
- `POST /forgot-password`
- `GET /reset-password/{token}`
- `POST /reset-password`
- `GET /verify-email`
- `GET /verify-email/{id}/{hash}`
- `POST /email/verification-notification`
- `GET /confirm-password`
- `POST /confirm-password`
- `GET /profile`
- `PUT /profile`
- `POST /profile/avatar`
- `PUT /profile/password`
- `PUT /profile/notifications`
- `GET /admin/users`
- `PATCH /admin/users/{user}/status`
- `PATCH /admin/users/{user}/lock`
- `PUT /admin/users/{user}/roles`
- `PUT /admin/users/{user}/password`

JSON API authentication endpoints are still planned and should be implemented with Sanctum when package installation is available.

## V2 Expansion Notes

Create an OpenAPI document and split public API contracts from private admin APIs.
