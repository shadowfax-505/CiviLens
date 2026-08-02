# ADR-014: Role-Specific Experience Shells on Shared Foundations

## Status

Accepted

## Context

The v1 layout exposes a dense shared navigation to users with very different goals. Citizens need geographic discovery and reporting, staff need guided evidence and proposal workflows, and administrators need governance, configuration, moderation, and operations.

## Decision

V2 keeps Blade, Tailwind, Alpine, shared tokens, accessibility primitives, and domain routes, but introduces separate public, citizen, staff, and administrator shells. Each shell has role-appropriate navigation, dashboard hierarchy, terminology, density, shortcuts, and empty states. Policies and permissions—not presentation—remain the authorization boundary.

Public and citizen screens prioritize **Explore my district**, recently indexed documents, saved districts, notifications, evidence search, and report follow-up. Staff screens prioritize assigned work, data-quality issues, change requests, document processing, and review queues. Administrator screens prioritize user access, source governance, rules, publication, system health, and audit history.

Responsive, keyboard, reduced-motion, and dark-mode behavior is required in every shell. Existing routes and forms migrate incrementally; the change does not require a SPA rewrite.

## Consequences

Some shared layout code becomes shell-level components, while visual tokens and lower-level controls remain reusable. Browser coverage must prove both role separation and authorization for every migrated workflow.

## V2 Impact

This is the next implementation slice after the Earth landing journey and precedes crawler or OCR activation.
