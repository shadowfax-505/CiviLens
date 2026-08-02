# ADR-013: Heterogeneous Public Sources With Explicit Provenance

## Status

Accepted

## Context

Procurement, budgets, audit reports, and agency publications are distributed across government publishers and non-governmental organizations such as watchdogs, research institutions, and transparency groups. Treating all publishers as interchangeable would hide important context.

## Decision

V2 uses an allowlisted source registry that supports government, nonprofit, research, watchdog, and other public-interest publishers. Acquisition prefers APIs or feeds, then sitemaps and direct downloads, static HTML, and isolated browser rendering only for approved JavaScript-only sources. Each acquisition records source class, publisher, original URL, retrieval facts, license or rights decision, checksum, dates, language, geography, and supersession state.

Official records and non-governmental analysis remain visibly distinct. Cross-source corroboration is a reviewer-visible relationship, not an automatic truth score. Public output contains only metadata, a short approved source-attributed summary, and a link to the publisher's original source unless separate rights permit republication.

Crawlers honor documented access constraints, bounded rates, content limits, host/path allowlists, redirect validation, SSRF controls, and removal/correction workflows. CivicLens retains checksum-protected snapshots privately for reproducible extraction, change detection, review, and audit; it never exposes those snapshots publicly without an explicit rights decision. Public-interest purpose does not remove provenance, security, privacy, or publisher-rights obligations.

## Consequences

Source onboarding requires policy review and monitoring, but CivicLens gains broader coverage without obscuring who made each claim or where it originated.

## V2 Impact

The recently indexed timeline can mix source classes with explicit filters and badges while keeping evidence lineage intact.
