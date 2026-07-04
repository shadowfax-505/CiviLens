# 22 Release Management

## Versioning

- `0.x` for pre-production development.
- `1.x` for stable v1 releases.
- `2.x` for v2 public intelligence platform releases.

## Release Checklist

- Tests pass.
- Changelog updated.
- Migrations reviewed.
- Documentation updated.
- Rollback plan documented.

## Release Candidate 1

RC1 version: `v1.0.0-RC1`.

Release artifacts:

- [RC1 Checklist](releases/RC1_CHECKLIST.md)
- [RC1 Release Notes](releases/RC1_RELEASE_NOTES.md)

RC1 remains a validation release. Production go/no-go depends on the quality gates, browser verification, deployment smoke checks, backup/restore readiness, and any unresolved critical blockers.
