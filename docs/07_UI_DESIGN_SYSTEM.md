# 07 UI Design System

## UX Goals

CivicLens should feel trustworthy, calm, and data-rich. Interfaces should prioritize clarity, auditability, and accessibility over flashy visuals.

## V1 Pages

- Home and public project search.
- Login and dashboard.
- Projects index and detail.
- Agency detail.
- Budget and procurement tables.
- Document library.
- Admin analytics.

## Components

- Filter sidebar.
- Search input.
- Data table.
- Status badge.
- Audit timeline.
- Document card.

## V2 Expansion Notes

V2 uses shared tokens and accessible components within four distinct shells:

- Public: Earth-to-Dhaka narrative, district exploration, recently indexed sources, and evidence search.
- Citizen: saved districts, major-change notifications, report submission, and follow-up.
- Staff: assigned work, guided source-data proposals, document processing, and review queues.
- Administrator: access control, source governance, publication, rules, auditing, and operations.

The globe uses normal document scrolling with a sticky scene, never a nested scroll trap. Every shell requires responsive, keyboard, reduced-motion, and dark-mode verification. Presentation may hide irrelevant actions, but authorization remains enforced by policies and middleware.

## V2 Implemented Foundations

- Semantic surface, text, border, focus, motion, and role-accent tokens are centralized in `resources/css/app.css`.
- The shared Blade layout resolves one of four explicit shells: public, citizen, staff, or administrator. Public pages use evidence-discovery navigation; authenticated workspaces use a permission-filtered role rail.
- Administrator purple, staff blue, citizen green, and public teal accents communicate context without becoming an authorization mechanism.
- Buttons provide brief press feedback, focus remains visibly ringed, and all non-essential transitions are disabled when reduced motion is requested.
- The public landing page continues from the nine-stage Earth journey into district exploration and a filterable evidence timeline without nested scroll capture.
- The editable reference library and four shell mockups are maintained in [CivicLens v2 Experience System](https://www.figma.com/design/bblCjgjnIZyqhuhdlpzEMi).
