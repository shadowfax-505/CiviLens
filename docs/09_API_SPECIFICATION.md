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

The Laravel app currently has web routes for `/login` and protected `/dashboard`. JSON API authentication endpoints are still planned and should be implemented with Sanctum when the API layer begins.

## V2 Expansion Notes

Create an OpenAPI document and split public API contracts from private admin APIs.
