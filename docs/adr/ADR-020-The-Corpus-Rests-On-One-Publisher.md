# ADR-020: Document extraction rests on one publisher, and why

**Status:** Accepted
**Date:** 2026-08-14

## Context

Group-conditional calibration exists so that a low-resource subset cannot hide behind a healthy
average. That argument is worth little if every group belongs to the same publisher, so the intent was
to get a second publisher's documents extracting.

Investigating what the three registered endpoints actually produce:

| endpoint | resources discovered | documents acquired |
| --- | --- | --- |
| Civil Audit Directorate archive | 11 | **10** |
| BPPA e-GP tender listing | 3,219 | 0 |
| CAG audit document storage | 1 | 0 |

## What each is

**e-GP produces records, not files, by design.** The tender detail servlet answers POST only, so a
document fetch against a listing row returns an empty body. Dispatching one anyway failed seventy jobs
and made seventy pointless requests to the publisher. Those rows are captured as **observations** —
3,226 of them — and the resource type is marked record-only so nothing tries to fetch them. This is
correct behaviour and not a gap to be closed.

**CAG has no index to read.** Its category pages return placeholder text and the storage path has no
directory listing; a trial run discovered exactly one resource, the directory URL itself. Producing
documents from it would mean guessing filenames, which is not discovery and is not covered by the
recorded authorisation.

**The Civil Audit Directorate archive is exhausted.** Every link on its audit-report page has been
acquired. There are ten documents because the publisher published ten.

## Decision

Document extraction is a single-publisher corpus, and every artifact says so rather than implying
breadth that does not exist:

- `civiclens:paper-report` reports `documents_by_publisher` and `tender_observations` as separate
  figures, so a second publisher's records are never counted as a second publisher's documents.
- The dataset card states the position for each source, including what CivicLens holds nothing of.
- The method card lists single-publisher dependence under known limits.

The CAG endpoint stays registered, active and empty. The authorisation is real and the route is right;
what is missing is an index. Deleting it would erase a recorded governance decision to hide an
inconvenient zero.

## Consequences

**A second document source is the highest-value thing a person can add**, and it needs a human
decision this system will not make for itself: a source with a readable index, whose terms and
robots policy have been read, with an authorisation recorded against it. The machinery — allowlist,
DNS pinning, rate limits, quarantine, extraction, review, calibration — is publisher-agnostic and
already works.

**Until then, breadth claims are about scripts, not publishers.** The three calibration groups are
one publisher's Bengali, English and mixed pages. That is a real distinction and a much narrower one
than "Bangladesh public sources", and a write-up that blurs the two would be overclaiming.

**Corpus growth within the existing publisher is exhausted**, so more labels — not more crawling —
are what tighten the bound from here.
