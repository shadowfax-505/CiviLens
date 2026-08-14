# ADR-017: Table structure from a second recognizer, text from the first

**Status:** Accepted
**Date:** 2026-08-14

## Context

A reviewer working the adjudication queue could not make sense of figures taken from tables. A row
such as

```
মোট প্রাপ্তি (৬+৭) ৫৭৩২০২] ৫৪৭২৫৩ | ৮৫৫৩৯৮
```

is three columns — almost certainly three fiscal years — flattened into one line. The characters are
read correctly and the value is still structurally meaningless: nobody can say which year it belongs
to or what line item it measures. An amount without its row and column cannot feed an indicator, a
comparison, or anything publishable.

Tesseract reports word geometry and reads this corpus at `ben+eng`. It has no notion of a table.

## Decision

Two recognizers, each doing only what it is good at.

**PaddleOCR PP-Structure supplies structure only** — where a table is and where its cells sit.
**Tesseract supplies the text**, as it already did. Cell text is assembled by taking the Tesseract
words whose box centres fall inside each cell box.

PP-Structure's own recognizer never produces text here. It has no established quality on Bengali,
which is most of this corpus, and adopting it for text would silently change how every page is read.
Its output is kept in the sidecar's JSON as `model_text` for inspection and is not persisted.

The sidecar lives in `tools/table-structure/` with a pinned `requirements.txt`, is invoked with
`Symfony\Component\Process\Process`, and its interpreter path is configuration. A machine without it
installed keeps working; those pages simply carry no table structure. CI does not install it and the
tests fake it.

The pass is queued, never synchronous: detection costs about 90 seconds a page and most pages in this
corpus have no table.

## What was measured

| | |
| --- | --- |
| Pages with a detectable table | 6 of 12 sampled |
| Runtime | ~90 s per page, CPU |
| Pages carrying review candidates | 223 |
| Candidate values appearing more than once on their own page | 81 of 300 sampled (27%) |

That last figure decided the design. Linking an *existing* candidate to a cell by matching its text is
ambiguous for roughly a quarter of items, so candidates are generated **from cell text** instead and
carry their row and column by construction. Older flat-text candidates keep working and carry none.

## Known limitations, measured on page 34 of the 2017-18 audit report

The model returned a three-row grid for a three-row table, and its rows were **not the table's rows**.
Its cell boxes covered the two data rows and one paragraph beneath the table; the heading row carrying
the fiscal years had no box at all. Its own structure HTML, meanwhile, described heading, amounts and
growth — one row out from its own boxes.

Consequences, both accepted for now and visible rather than hidden:

- **No column heading.** The fiscal year a figure belongs to is often not captured. The review screen
  shows the column position and does not invent a heading it does not have.
- **Prose can be swept into cells.** Cells of three to seven words appeared where the table had none.
  `ReviewCandidateGenerator` therefore takes values only from cells of at most two words; every real
  value cell on that page held exactly one.

The row and its label are shown to the reviewer as read, not as verified, and take no part in the
judgement, which remains only whether the characters match the page. A wrong label attached to a
correct figure would look like provenance, which is worse than having none.

A line-ruling approach — deriving the grid from the table's own drawn rules with morphology rather
than from a learned model — would capture the heading row and exclude unruled prose on pages like
this one. It is the obvious next thing to measure.

## Consequences

- A second Python runtime and roughly a gigabyte of models, optional at runtime and pinned.
- Structure is context, not certification. The conformal bound continues to certify only that the
  characters match the page; nothing about rows or columns enters it, and any write-up that mixes the
  two overclaims.
