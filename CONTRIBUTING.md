# Contributing to CivicLens

Contributions should preserve the project handbook and version strategy.

## Workflow

1. Read `docs/MASTER_INDEX.md`.
2. Create a focused branch.
3. Update tests and documentation with every feature.
4. Record architecture changes in `docs/adr/`.

## Local Quality Gate

Run these checks before opening a pull request:

- `composer validate --strict`
- `composer quality`
- `npm run build`
- `npm run test:e2e` when browser binaries are installed and UI behavior changed.

## Definition of Done

- Code is implemented.
- Tests pass.
- Documentation is updated.
- Security implications are reviewed.
- Static analysis and formatting pass.
- V2 impact is noted when relevant.
