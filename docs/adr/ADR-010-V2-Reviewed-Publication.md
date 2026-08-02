# ADR-010: Reviewed, Versioned Public Publication

## Status

Accepted

## Context

V2 will ingest documents and may produce summaries or irregularity candidates that mention agencies, contractors, or people. Publishing machine-generated interpretation directly would create avoidable accuracy, fairness, and audit risks.

## Decision

CivicLens will publish from a separate, versioned public projection. Two reviewers make initial decisions blindly. Agreement is required; disagreement creates an independent blind tie-break assignment. Named-subject material receives a private 30-calendar-day response window before publication. Failed notice keeps the item private with a renewable deadline.

Published wording is neutral, evidence-bound, and approved by reviewers. A subject response is represented by a short reviewer-approved neutral summary plus links to public evidence supplied by the subject. Corrections remain visible as **under re-review** and produce a new version rather than overwriting history.

## Consequences

Publication is slower and requires staffing, assignment controls, SLA monitoring, and immutable review events. In return, source facts, automated candidates, reviewer judgments, subject responses, and public claims remain distinguishable and auditable.

## V2 Impact

The recently indexed timeline may show source metadata before an interpretive summary is approved, but no generated summary or irregularity claim can bypass this workflow.
