# Public Transparency & Citizen Engagement Module

## Responsibility

The Public module exposes approved civic information to guests and citizens while keeping administrative source records private by default.

## Implemented Scope

- About-first public landing page, project explorer, agency profiles, procurement summaries, document library, and public search.
- Public procurement award notices for approved awards with public disclosure status.
- Public project detail pages composed through public-safe services, including map display when project coordinates exist.
- Public portfolio map markers are read-only, capped, viewport-validated, and explicitly allowlisted; unpublished, inactive, archived, and incompletely located projects are excluded.
- Public document downloads through Laravel application routes only.
- Role-aware shared navigation/search handoff plus staff/admin change-request entry points for projects, agencies, contractors, procurement, and documents.
- Authenticated citizen report submission with queued acknowledgement emails, UUID-based status tracking, and a detailed citizen report dashboard.
- Private citizen-report attachments streamed only through an authorized application route for the submitter or report moderators.
- Staff/admin moderation queue with expanded report context, status changes, archive/restore actions, and append-only activities.

## Tables

- `citizen_report_categories`
- `citizen_report_statuses`
- `citizen_reports`
- `citizen_report_activities`

## Security

Public records are default-deny. Projects and tenders must be public, active, and not archived. Procurement award notices require approved awards with public disclosure status. Bid amounts, evaluation scores, committee comments, internal documents, and non-public award history are not published. Documents must have public visibility and are streamed through application routes without exposing storage paths. Citizen reports require authentication to submit and are not published as public source facts. Citizen-report attachments are stored on a private disk and require report-view authorization to download; acknowledgement emails contain only the report reference and authenticated tracking link.

## V2 Notes

Public APIs, open-data exports, maps, semantic search, OCR publication, and AI public summaries remain deferred.
