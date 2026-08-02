# V2 Upgrade Path

## Goal

Make CivicLens v1 easy to evolve into a public civic intelligence platform without rewriting core domains.

## Upgrade Rules

- Preserve v1 table names unless an ADR approves a breaking change.
- Add new capabilities through additive migrations where possible.
- Keep AI outputs separate from source records.
- Version public APIs.
- Introduce async workers for OCR, analytics, and indexing.
- Publish interpretations only through the versioned blind-review workflow.
- Keep official and non-governmental sources distinct through explicit provenance.
- Resolve browser location ephemerally and persist district preferences only.
- Keep compute provider-neutral and CPU-capable.

## V2 Candidate Milestones

1. Role-specific public, citizen, staff, and administrator experiences.
2. District-first public explorer and recently indexed timeline.
3. Governed source registry and acquisition ledger.
4. Bangla, English, and mixed-document extraction.
5. Blind review, subject response, and versioned publication.
6. Shadow-mode evidence-bound model evaluation.
7. Public API, dataset exports, quality dashboards, and production observability.
