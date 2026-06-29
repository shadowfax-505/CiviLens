# ADR-004: Use Laravel Sanctum for API Authentication

## Status

Accepted

## Context

CivicLens needs token-based API authentication for private APIs.

## Decision

Use Laravel Sanctum.

## Consequences

Sanctum fits Laravel apps and supports API tokens without unnecessary OAuth complexity.

## V2 Impact

Public APIs can remain unauthenticated or rate-limited separately while private APIs use Sanctum.

