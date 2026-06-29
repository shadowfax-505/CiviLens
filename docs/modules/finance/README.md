# Finance Module

## Responsibility

The Finance module owns the Budget Engine and is the single source of truth for project financial information.

## Implemented Features

- Budget dashboard with total budget, allocated budget, spent budget, remaining budget, utilization percentage, revision count, and financial health.
- Budget CRUD, archive, and restore.
- Budget detail view with financial cards, revision history, transaction history, and timeline-style records.
- Revision management with previous allocation, new allocation, difference, reason, approval date, and approver.
- Transaction management for configurable transaction types including allocation, adjustment, expenditure, refund, and transfer.
- Immutable financial transactions: transaction delete requests return 405 and model deletion is blocked.
- Search/filter/sort/pagination by project, agency, fiscal year, funding source, budget type, status, amount range, date range, and global search.
- Policies and validation for every financial operation.
- Factories, seed data, feature tests, unit tests, and calculation tests.

## Tables

- `budget_categories`
- `budget_types`
- `budget_statuses`
- `budget_transaction_types`
- `budgets`
- `budget_revisions`
- `budget_transactions`

## Financial Calculations

- Remaining balance = current allocation - reserved amount - committed amount - actual expenditure.
- Utilization percentage = actual expenditure / current allocation.
- Revisions update current allocation while preserving previous allocation history.
- Expenditure transactions increase actual expenditure.
- Allocation and refund transactions increase current allocation.
- Adjustment and transfer transactions decrease current allocation.

## V2 Notes

Add chart rendering, analytics caching, procurement-linked encumbrances, document-backed approvals, and public finance APIs after the financial schema is stable.
