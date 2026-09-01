# ADR-023: Abstention belongs to a value, not to a page

**Status:** Accepted
**Date:** 2026-09-01

## Context

1,386 pages of 11,063 were abstained: attempted by the recognizer, declined, and
stored with no text at all. Recovering one meant reading it again, so for
practical purposes their content did not exist. The operator asked why pictures
of physical pages were not being read.

Rendering a sample and looking at them found three different things wearing the
same label.

**Pages were being read sideways.** A landscape form or budget table printed on
a portrait page reached OCR at its stored orientation. In a 24-page sample of
readable-but-abstained pages, six needed a quarter turn and one a half. Rotating
and re-reading four:

| page | turn | mean confidence | characters |
| --- | --- | --- | --- |
| 9283 | 90° | 51.7 → 73.2 | 541 → 802 |
| 10038 | 90° | 49.9 → 71.5 | 668 → 1041 |
| 8321 | 270° | 46.4 → 74.1 | 1739 → 1984 |
| 10010 | 90° | 44.4 → 64.0 | 562 → 1119 |

**And none of them cleared the gate**, which is what made the second finding
unavoidable.

**The gate was page-level and the guarantee is per field.** A page was accepted
only if its *mean* per-word confidence reached 80. Page 8321, straightened:

| statistic | value |
| --- | --- |
| mean word confidence | 74.1 — abstains |
| median | 83.7 |
| 56 numeric tokens | **mean 83.5** |
| words at 80 or above | 175 of 303 |

Fifty-six figures read well, every one discarded, because a tail of dot leaders
and table-rule fragments dragged the arithmetic mean under the line.

ADR-019 already rejected page-level confidence **as a score**: it is identical
for every field on a page and ranks errors at AUC 0.511, a coin flip. It was
still the gate deciding whether a page was read at all — the same statistic,
rejected for judging a value, retained for judging every value at once.

**Some pages are genuinely empty.** 244 blank, 48 almost-blank, and section
dividers such as a single reverse-video word reading পরিশিষ্টসমূহ.

## Decision

**Straighten the page, keep what was read, and abstain per value.**

1. The enhanced pass detects orientation with Tesseract's `--psm 0` and turns
   the image with GD before recognising it. Only the enhanced pass, so a page
   the primary read fine pays nothing. The turn is recorded, because the word
   geometry is then in the rotated frame.
2. A page whose mean falls short is no longer discarded. Its text is kept on a
   distinct path, marked, excluded from candidates by default, never shown as
   certified. Pages with nothing legible still abstain outright.
3. On such a page, a value is offered to a reviewer only if **its own token
   confidence** clears the threshold.

### The threshold is fitted, not chosen

Picking a number here would repeat the mistake the 80 gate embodies. Measured
over 719 labelled values located in the word geometry:

| token confidence | n | wrong | error |
| --- | --- | --- | --- |
| 0–60 | 51 | 20 | 39.2% |
| 60–70 | 19 | 4 | 21.1% |
| 70–80 | 37 | 6 | 16.2% |
| 80–90 | 107 | 6 | 5.6% |
| 90–100 | 505 | 16 | 3.2% |

At 80 and above the error is **3.6%**, which is the corpus-wide reviewer error
rate. Below 80 it is **28%**. So 80 was never the wrong number — it was applied
to the page instead of to the value. Per-token confidence is strongly
predictive of the thing page-level confidence cannot see, which is the same
observation ADR-019 made from the other side.

## Consequences

**This widens the population the guarantee covers, and the old labels do not
cover the new part.** Values recovered from previously-abstained pages are a
different population: worse-printed, rotated, older. Certifying them against
thresholds fitted on pages that cleared the old gate assumes an exchangeability
that does not hold — and it would break the bound in the opposite direction from
every defect this project has found so far. Not a guarantee true of less than it
claims, but one claimed over more than it was fitted on.

So the per-token gate applies **only** to pages on the new path. Pages that
cleared the old gate keep the candidates they always had, and recovered values
enter as candidates whose groups must be labelled afresh before they certify.

**Abstention stops being a black hole.** The reason a page fell short travels
with its text, so the next attempt is a re-score rather than a re-OCR of the
corpus. That is what made the 1,386 expensive: nothing about them was kept.

**A crop must be drawn in the frame the page was read in.** Geometry recorded
after straightening is meaningless against an image rendered as stored, and a
box drawn there rings the wrong characters confidently. This is the DPI bug of
ADR-019's era in a second dimension, and the frame is now two numbers.

## What this does not fix

The rules work on the shape of a reading, not on its meaning. A page read
confidently and wrongly still certifies, and per-token confidence is a better
judge than the page mean without being a good one: at 80 and above, values are
still wrong 3.6% of the time. That residue is what the conformal bound is for,
and it is why the token gate decides what a *reviewer sees* rather than what is
*certified*.
