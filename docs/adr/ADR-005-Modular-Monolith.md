# ADR-005: Use a Modular Monolith

## Status

Accepted

## Context

CivicLens is broad but should remain achievable for v1.

## Decision

Use a modular monolith with clear domain modules instead of microservices.

## Consequences

Development stays simpler while module boundaries preserve future extraction options.

## V2 Impact

V2 can split OCR, search indexing, or analytics workers without changing the core app.

