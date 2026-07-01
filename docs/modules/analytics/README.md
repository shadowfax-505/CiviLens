# Business Intelligence & Analytics Module

## Purpose

The Analytics module is the CivicLens business intelligence layer. It turns normalized source-of-truth records into reusable metrics, executive dashboards, reports, snapshots, alerts, and chart-ready data.

Analytics does not own operational facts. Projects, budgets, procurement records, contractors, documents, search records, users, agencies, and geography remain authoritative in their own modules.

## Tables

- `analytics_snapshot_periods`
- `analytics_snapshots`
- `analytics_reports`
- `analytics_alert_rules`
- `analytics_alerts`
- `dashboard_states`
- `analytics_events`

Snapshots and alerts are immutable history records. Reports store generated dashboard payloads and export metadata. Dashboard states store user-specific filter presets. Analytics events audit dashboard access and analytics activity.

## Services

- `MetricRegistry` registers independent metrics by key and category.
- `MetricEngine` calculates and caches individual metrics.
- `AggregationEngine` centralizes source-record query filters.
- `DashboardService` composes dashboard payloads.
- `TrendAnalysisService` prepares trend and distribution data.
- `ForecastPreparationService` exposes future-model feature readiness without running AI.
- `ReportBuilder` generates report records and CSV output.
- `SnapshotService` generates immutable periodic snapshots.
- `InsightService` evaluates rule-based alerts.
- `ChartService` emits chart-library-neutral definitions.
- `EnterpriseProcurementAnalyticsService` calculates Sprint 12 procurement intelligence metrics including participation, award distribution, average bidders, single-bid tenders, repeat winners, duration, competitiveness, completion rate, and variation frequency.

## Dashboards

Implemented dashboard contexts:

- Executive
- Finance
- Procurement
- Contractors
- Agency
- Projects
- Search
- System Health

Dashboards support URL-persistent filters for date range, fiscal year, agency, division, district, project, budget, funding source, and procurement method where applicable.

## Authorization

`AnalyticsPolicy` protects dashboard, metrics, reports, snapshots, and alerts. Administrators may view and generate analytics. Users with `analytics.view` may view dashboards. Users with `analytics.manage` may queue snapshots and reports.

## Events And Queues

Events:

- `MetricCalculated`
- `DashboardViewed`
- `SnapshotGenerated`
- `ReportGenerated`
- `AlertTriggered`
- `InsightGenerated`
- `AnalyticsCacheRefreshed`

Jobs:

- `GenerateAnalyticsSnapshot`
- `GenerateAnalyticsReport`

## UI

Admin routes provide an executive dashboard, dashboard tabs, filters, KPI cards, chart-ready panels, rule-based alerts, report listing, and alert listing. Chart payloads are emitted through `data-chart-definition` attributes so Chart.js or another chart library can be attached later without changing analytics services.

## V2 Notes

Future sprints may add scheduled report delivery, PDF and spreadsheet rendering packages, materialized aggregate tables, GIS map widgets, predictive analytics, and AI-generated insights. AI outputs must remain explainable and separated from source facts unless an ADR promotes them.

Sprint 10 intelligence indicators can feed analytics summaries by aggregate counts and review status only. Analytics must not duplicate indicator evidence or mutate intelligence review state.

Sprint 12 procurement metrics feed the procurement dashboard and are designed for future intelligence indicators. They are calculated from operational source tables and do not duplicate tender, bid, award, contract, variation, or payment source facts.
