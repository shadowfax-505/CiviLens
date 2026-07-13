# Enterprise Document Management Module

## Change Proposal Governance

Only administrators may upload, modify, archive, or restore documents. Staff may read and download permitted documents, then submit a structured Change Request with optional supporting evidence for administrator review.

## Purpose

The Document module is the enterprise document management system for CivicLens. It stores document metadata, secure storage references, immutable version history, permissions, activities, and processing metadata for every civic record uploaded to the platform.

Documents are source records. File versions are never overwritten, internal storage paths are never exposed directly, and future OCR, semantic search, AI summarization, virus scanning, thumbnails, and indexing run through queued processing hooks.

## Tables

- `document_types`
- `document_categories`
- `document_statuses`
- `document_visibilities`
- `document_permission_types`
- `document_tags`
- `documents`
- `document_versions`
- `documentables`
- `document_tag`
- `document_permissions`
- `document_activities`
- `document_ocr_metadata`
- `document_ai_metadata`

## Relationships

Documents reference configurable type, category, status, visibility, owner, uploader, creator, and updater records. Tags are many-to-many through `document_tag`.

Document attachments use the `documentables` polymorphic table so one document can be related to projects, budgets, tenders, contracts, contractors, organizations, agencies, and geographic records without duplicating module-specific attachment tables. This preserves module boundaries while keeping the document library searchable from a single query layer.

Document versions belong to a parent document and preserve storage disk, path, checksum, file size, uploader, reason, and version number. Previous versions are retained when a file is replaced.

## Storage

All uploads use Laravel Storage through `DocumentStorageService`. The active disk is controlled by `DOCUMENT_STORAGE_DISK` or `FILESYSTEM_DISK`, and upload size is controlled by `DOCUMENT_MAX_UPLOAD_KB`.

The storage abstraction is compatible with local disks, Amazon S3, Cloudflare R2, Azure Blob, Google Cloud Storage, and future storage providers supported by Laravel.

## Admin UI

Implemented web routes:

- `GET /admin/documents`
- `GET /admin/documents/archived`
- `GET /admin/documents/create`
- `POST /admin/documents`
- `GET /admin/documents/{document}`
- `GET /admin/documents/{document}/edit`
- `PUT /admin/documents/{document}`
- `GET /admin/documents/{document}/download`
- `GET /admin/documents/{document}/preview`
- `POST /admin/documents/{document}/versions`
- `PATCH /admin/documents/{document}/archive`
- `PATCH /admin/documents/{document}/restore`
- `POST /admin/documents/bulk`

The document library supports dashboard summary cards, upload trends, document type statistics, recent uploads, largest files, missing metadata checks, pending OCR counts, global search, filters, sorting, pagination, archive/restore, bulk archive, bulk tagging, download, preview, version history, and timeline views.

## Security

`DocumentPolicy` protects every route. Administrators and users with `documents.manage` can manage records. Publicly visible documents may be viewed by authenticated users, while modification, archive, restore, download, and bulk operations require policy approval.

Upload requests validate MIME/extension allowlists, file size limits, required metadata, relationship type allowlists, and tag existence. Storage paths are retained as private metadata and downloads stream through authorized Laravel routes.

Sprint 11 adds public document listing and download routes for documents with public visibility only. Public downloads still stream through application routes and never reveal storage paths.

## Events And Queues

Domain events include `DocumentUploaded`, `DocumentUpdated`, `DocumentArchived`, `DocumentVersionCreated`, and `DocumentMetadataUpdated`.

Prepared queue jobs include `GenerateDocumentThumbnail`, `RunDocumentOcr`, `ExtractDocumentMetadata`, `ScanDocumentForViruses`, `IndexDocumentForSearch`, and `ProcessDocumentAiMetadata`.

## Testing

Pest coverage includes seeding, CRUD, validation, authorization, secure upload rejection, storage persistence, version replacement, immutable version/activity guards, search/filter/sort/pagination, archive/restore, bulk operations, events, and queue dispatch.

Playwright browser coverage includes authenticated upload, detail view, download, archive, restore, search, and responsive admin workflow smoke coverage.

## V2 Notes

OCR, semantic search, embeddings, AI summaries, entity extraction, classifications, external sharing, virus scanning implementation, thumbnails, and preview generation remain queued/future-facing capabilities. AI-generated metadata must remain separate from document source facts unless an ADR explicitly promotes it into production workflow state.
