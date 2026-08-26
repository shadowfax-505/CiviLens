# ADR-022: A word-processor document is rendered to be read, and the page it shows is ours

**Status:** Accepted
**Date:** 2026-08-26

## Context

Thirteen IMED artifacts were discovered, fetched, checksummed, stored — and never read. They are
Word documents, and every stage after acquisition works by looking at pages:

| stage | what it does with a page |
| --- | --- |
| routing | measures the text layer |
| selective OCR | rasterises and recognises |
| second read | re-reads the region a value was taken from |
| table detection | finds a grid in the page image |
| abstention | measures ink coverage |
| evidence | shows the reader the page with the characters ringed |

A PDF has pages. A `.docx` is a description of content plus formatting intent; it has no pages
until software lays it out. So those thirteen reached the end of acquisition and stopped, with no
failure recorded, because nothing had failed — there was simply nothing for the next stage to look
at. `civiclens:sources-report` counted them as acquired and the corpus counted them as documents.

## Decision

**Render them, at one place, and say what that costs.**

The conversion happens in `ArtifactWorkspace::materialize()` — the single method every consumer
asks for a path on disk. A document converted there flows through the whole pipeline as though it
had arrived as a PDF: no schema change, no second artifact row, no consumer that has to know. The
rendering is cached against the artifact's checksum, because materialising happens once per page
per pass across five passes.

`NativeExtractionService` was dispatching on the media type recorded at acquisition, which would
have looked the converted file up under the format it arrived in and refused it. It asks
`effectiveMediaType()` instead. That is the general hazard with converting behind an interface: any
caller that *branches* on type, rather than merely consuming the result, has to be moved with it.

### The part that is a claim about evidence, not about plumbing

The public promise of this project is that a reader does not have to trust it: every certified
figure is shown on the page it was read from, and if the crop is wrong the reader can see that it
is wrong. For a PDF that promise is exact. The page image is the publisher's own artefact
rasterised, so "go and look" means looking at what the publisher published.

For a converted document it is not exact. The words and figures are the publisher's; the line
breaks, pagination and spacing are **produced by our copy of LibreOffice**, and another renderer —
or the publisher's own Word — may lay the same file out differently. The reader is looking at our
typesetting of their content.

That is still evidence, and it is still worth having: a figure ringed in our rendering is a figure
that is genuinely in their file. But it is a weaker thing than the promise made everywhere else on
the site, and a page that quietly showed both as identical would be overstating eight percent of
its documents. So the evidence page marks these documents, names the reason, and points at the
original file as the thing to cite.

The flag is derived from the media type already copied into the publication snapshot rather than
read live, so a citation resolved years from now keeps the answer that was true when it was
published — the same rule ADR-021 sets for every other published value.

## Consequences

- Thirteen documents become readable. On first reading they produced 51 pages and 25 candidates:
  they are administrative forms — travel-approval paperwork and the like — not audit findings, so
  the gain is corpus coverage rather than certified figures.
- LibreOffice becomes an optional dependency. Without it those documents stay unread and the
  sources report says so, which is the state the project was already in.
- One class of evidence on the public site is now weaker than the rest and is labelled as such.
  Nothing else on the site carries a caveat of this kind, and nothing else should acquire one
  silently.
- Conversion is not reading. A form we can now lay out is not a figure we can certify, and the
  documents remain uncertified until a reviewer judges values from them.

## Alternatives rejected

**Convert at acquisition and store the PDF as the artifact.** The stored bytes would then not be
the bytes fetched, and the checksum would not be the publisher's file. Provenance is the asset;
spending it to avoid a conversion step is the wrong trade.

**Convert in each consumer.** Six places to change, six places to forget, and the evidence renderer
is the one most likely to be forgotten — which is the one where the omission would mislead a
reader rather than merely fail.

**Leave them unread.** Defensible, and it was the status quo. But the reason they were unread was
an accident of file format, not a decision about what belongs in the corpus, and an accidental
exclusion that nobody wrote down is exactly the kind of silent narrowing this project keeps
finding in itself.
