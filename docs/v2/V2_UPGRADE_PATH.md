# V2 Upgrade Path

## Goal

Make CivicLens v1 easy to evolve into a public civic intelligence platform without rewriting core domains.

## Upgrade Rules

- Preserve v1 table names unless an ADR approves a breaking change.
- Add new capabilities through additive migrations where possible.
- Keep AI outputs separate from source records.
- Version public APIs.
- Introduce async workers for OCR, analytics, and indexing.

## V2 Candidate Milestones

1. Public read-only API.
2. Map-based project explorer.
3. OCR document extraction.
4. Semantic document search.
5. Explainable risk indicators.
6. Data quality dashboard.
7. Production observability.

