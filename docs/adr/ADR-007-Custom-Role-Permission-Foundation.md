# ADR-007: Implement a Custom Role and Permission Foundation

## Status

Accepted

## Context

CivicLens v1 needs role-based access control early in Sprint 1. The platform needs stable domain language for `admin`, `staff`, and `citizen` access before project, procurement, and reporting modules are implemented.

## Decision

Implement first-party `roles`, `permissions`, `role_user`, and `role_permission` tables with Eloquent models and relationship helpers on `User`.

## Consequences

The project has a transparent and database-first access foundation without introducing an external package before requirements settle. If the authorization model grows complex, the project can later adopt a package or policy layer behind the same domain terms.

## V2 Impact

V2 can add richer permission scopes, public API access tiers, and organization-level roles without renaming v1 roles.

