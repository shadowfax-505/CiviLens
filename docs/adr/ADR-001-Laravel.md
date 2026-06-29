# ADR-001: Use Laravel for CivicLens V1

## Status

Accepted

## Context

CivicLens needs a productive, secure web framework with strong database, authentication, queue, and testing support.

## Decision

Use Laravel as the primary application framework for v1.

## Consequences

Laravel gives CivicLens fast development, mature ecosystem support, and a clean path for authentication, policies, queues, testing, and APIs.

## V2 Impact

Laravel remains suitable for v2 as long as advanced workloads are isolated behind queues and services.

