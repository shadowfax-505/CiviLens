# Public Transparency & Citizen Engagement Module

## Responsibility

The Public module exposes approved civic information to guests and citizens while keeping administrative source records private by default.

## Implemented Scope

- Public portal home, project explorer, agency profiles, procurement summaries, document library, and public search.
- Public procurement award notices for approved awards with public disclosure status.
- Public project detail pages composed through public-safe services.
- Public document downloads through Laravel application routes only.
- Authenticated citizen report submission, UUID-based status tracking, and citizen report dashboard.
- Staff/admin moderation queue with status changes, archive/restore actions, and append-only activities.

## Tables

- `citizen_report_categories`
- `citizen_report_statuses`
- `citizen_reports`
- `citizen_report_activities`

## Security

Public records are default-deny. Projects and tenders must be public, active, and not archived. Procurement award notices require approved awards with public disclosure status. Bid amounts, evaluation scores, committee comments, internal documents, and non-public award history are not published. Documents must have public visibility and are streamed through application routes without exposing storage paths. Citizen reports require authentication to submit and are not published as public source facts.

## V2 Notes

Public APIs, open-data exports, maps, semantic search, OCR publication, and AI public summaries remain deferred.
