# Analytics API

Sprint 09 implements authenticated admin analytics endpoints:

- `GET /admin/analytics` - render the dashboard UI with URL-persistent filters.
- `GET /admin/analytics/metrics` - return one registered metric as JSON.
- `POST /admin/analytics/snapshots` - queue an immutable dashboard snapshot.
- `GET /admin/analytics/reports` - list generated reports.
- `POST /admin/analytics/reports` - queue report generation.
- `GET /admin/analytics/reports/{report}/download` - download CSV output for generated report payloads.
- `GET /admin/analytics/alerts` - list rule-based analytics alerts.

Current formats are `csv`, `pdf`, and `xlsx` at the request-contract level. CSV output is implemented without extra packages. PDF and spreadsheet rendering remain future-compatible until dependencies are approved.

V1 metrics are aggregate and permission-aware. V2 can add public-data analytics and scheduled delivery.
