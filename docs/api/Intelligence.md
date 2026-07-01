# Intelligence API

Sprint 10 intelligence routes are authenticated admin web endpoints. They are permission-aware and require `intelligence.manage` or administrator access.

## Routes

- `GET /admin/intelligence`
- `GET /admin/intelligence/dashboard/summary`
- `GET /admin/intelligence/indicators`
- `GET /admin/intelligence/indicators/{indicator}`
- `PATCH /admin/intelligence/indicators/{indicator}/review`
- `POST /admin/intelligence/rules/{rule}/run`
- `GET /admin/intelligence/rules/{rule}/preview`
- `GET /admin/intelligence/processing-jobs`
- `POST /admin/intelligence/processing-jobs`

## Governance

Indicators are advisory and source-backed. They require human review before acceptance and must never be treated as automated legal conclusions.
