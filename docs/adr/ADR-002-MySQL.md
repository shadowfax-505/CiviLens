# ADR-002: Use MySQL as Primary Database

## Status

Accepted

## Context

The project must demonstrate strong relational database design and advanced filtering.

## Decision

Use MySQL as the primary relational database.

## Consequences

MySQL supports normalized schema design, foreign keys, indexes, transactions, and practical deployment.

## V2 Impact

V2 can add read replicas, analytics stores, or vector search without replacing MySQL as source of truth.

