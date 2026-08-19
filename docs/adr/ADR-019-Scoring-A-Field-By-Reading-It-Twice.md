# ADR-019: Scoring a field by reading it twice

**Status:** Accepted
**Date:** 2026-08-14

## Context

Split conformal risk control holds for *any* nonconformity score. That is its strength and its trap:
validity says nothing about whether the score is worth thresholding.

The score in use was the page's OCR confidence, copied onto every field extracted from that page.
Measured over 265 adjudicated fields:

| | |
| --- | --- |
| AUC — probability a wrong field scores above a correct one | **0.5106** |
| Labelled pages where the score varied within the page | **0 of 124** |

A coin flip. The bound was sound and the selection it certified was arbitrary, which showed up
directly in calibration: the `en` group's threshold sat at 0.99 and accepted everything, `mixed`
accepted 10.5%, and neither number reflected risk.

## Decision

Score a field by **reading it a second time and asking whether the two readings agree**.

The review screen already locates each value's own characters on the page. That box is cropped from
the page raster, upscaled, and re-read with Tesseract in single-line mode. The score combines:

```
0.6 * normalised edit distance between the two readings (canonicalised)
0.3 * (1 - second-read confidence / 100)
0.1 * (the figure's commas do not group thousands)
```

Weights are fixed by role rather than tuned on labels: agreement dominates, a barely legible
agreement counts for less than a clear one, and `AmountGrouping` breaks ties because a misplaced
separator differs by one character and is a hundredfold error.

Digits are canonicalised before comparison. Legacy-font pages store Bengali numerals as the Latin
bytes that render them, so `216.9` and `২১৬.৯` are one reading written twice; scoring that as
disagreement would fire on every native page.

**A value that cannot be located scores `null`, not 1.0.** Marking it maximally nonconforming said
"as bad as the worst misreading" about 57 fields that were read correctly and merely could not be
found again, and cost the score its discrimination — 0.7531 measured with them, 0.8954 without. A
null score defers by rule and is excluded from calibration; since such a field is never accepted, it
cannot contribute to `P(accepted AND wrong)`, so the joint bound over the fields the score does apply
to is untouched.

## What was measured

| | before | after |
| --- | --- | --- |
| AUC over adjudicated fields | 0.5106 | **0.8954** |
| — `dgcivil-bangladesh\|bn` | — | 0.9554 |
| — `dgcivil-bangladesh\|mixed` | — | 0.8280 |
| Coverage (fields the score applies to) | 265 of 265 | 205 of 265 |
| Mean score, correct fields | — | 0.080 |
| Mean score, wrong fields | — | 0.493 |

The practical consequence, at equal guarantee, on the same calibration set:

| α | page confidence accepts | second read accepts |
| --- | --- | --- |
| 0.05 | 100% | 100% |
| 0.03 | 60% | **98%** |
| 0.02 | 46% | **90%** |

At α = 0.05 the corpus error rate is already inside the bound, so no threshold has to exclude
anything and the two scores look alike. The difference is what the score buys at a tighter level.

## Consequences

**A second read is a second opinion, not ground truth.** It is the same engine family, so a
systematically misread glyph can be misread identically twice and score as agreement. This bounds
what the score can detect and belongs in any write-up.

**Roughly a quarter of fields carry no score.** They defer. Improving location coverage — split
tokens, native pages, values inside merged cells — is the direct route to certifying more, and is
worth more than tuning the score's weights.

**A failed second read is rare enough to leave alone.** Two of 205 returned nothing; one was a
genuine error and one a false alarm. The measurement said not to special-case it.

**Scores must be recomputed when extraction changes**, and calibration recomputed after that.
`civiclens:score-fields` is queued per page — one raster serves every field on it.

## The check that did not fire

`CalibrationReport`'s leakage detector flags a score that separates outcomes perfectly, since that
usually means the score was derived from the gold value. It reports four incorrect fields scoring
below the worst correct one, so it does not fire — the separation is good and imperfect, which is
what an independent signal looks like. Had it fired, the honest response would have been to explain
why a re-read of pixels cannot see a label, never to silence the check.

## Addendum, 2026-08-19: the unlocatable share was mostly a bug

"Roughly a quarter of fields carry no score" was recorded above as a property of
the corpus. It was largely a defect in the locator.

These documents write a sum as `৩,৯৮,৭৪৮/-`. The amount pattern stops at the last
digit, so the extracted value never carried the terminator while the recognized
word always did, and the locator required an exact match. Since an unlocatable
value scores `null` and defers by rule, the effect was not cosmetic: the system
was withholding the sums of money and certifying the short decimals that happen
to be printed without a terminator — paragraph numbers off contents pages.

Tolerating a trailing currency terminator, and only a trailing one:

| | before | after |
| --- | --- | --- |
| Unlocatable, whole corpus | 1,703 | **1,012** |
| Coverage over adjudicated fields | 205 of 265 | **247 of 265** |
| AUC over adjudicated fields | 0.8954 | 0.8818 |
| — `dgcivil-bangladesh\|bn` | 0.9554 | 0.9443 |
| — `dgcivil-bangladesh\|mixed` | 0.8280 | 0.8333 |
| Accepted, whole corpus | 5,563 | **6,547** |
| — carrying thousands separators | 1,215 | **1,704** |

The AUC fell by 0.014 while coverage rose by 16 points. That is the expected
direction: the values that were invisible are harder than the ones that were
not, so ranking over more of them is slightly weaker ranking. The earlier 0.8954
was measured over a subset selected, unintentionally, for being easy to find.

Calibration sizes rose with coverage, and all three groups now certify at
α = 0.05 **conditionally** rather than through the ADR-018 ladder:

| group | before | after |
| --- | --- | --- |
| `dgcivil-bangladesh\|bn` | n = 85, α = 0.05 | n = 121, α = 0.05 |
| `dgcivil-bangladesh\|en` | n = 17, α = 0.056 relaxed | n = 29, α = 0.05 |
| `dgcivil-bangladesh\|mixed` | n = 9, α = 0.10 relaxed | n = 19, α = 0.05 |

**What this says about the method.** The bound was correct throughout. Both the
old numbers and the new ones are sound statements about `P(accepted AND wrong)`;
what changed is which population they are about, and the old population was worse
than it looked because it excluded the figures a reader would care about most.
A guarantee over a silently biased subset is the failure mode this addendum
exists to record: nothing in the calibration could have detected it, because
deferring is always allowed and never breaches the bound.

**Still outstanding.** 1,012 values remain unlocatable, which is the direct route
to certifying more and is worth more than tuning the score's weights.

A second claim made here on the same day — that a fifth of accepted values were
contents-page section numbers — was withdrawn. It was inferred from the shape of
the value and not checked against the page; in context most values of that shape
are budget figures in crore or percentages. ADR-021 records the correction and
the measurement that replaced it.

## Addendum, 2026-08-19: what the second read is cropping

The score is only as good as the region it re-reads, and twice on the same day
the region was wrong in a way no measurement of the *score* could reveal.

**Values printed with a currency terminator could not be located at all** — the
first addendum above.

**Values taken from a table cell were cropped by the cell.** A cell box is the
box of the cell: ruling lines, padding, and where the grid is imperfect, part of
the neighbouring column. Re-reading all of it returned `৪৭৪.২৫ | ৪৬,৮৩০` for a
value of `৪৬,৮৩০.৮৬`, a pipe where a rule line was, or nothing. Cropping the
value's own words *inside* the cell's bounds instead:

| | before | after |
| --- | --- | --- |
| Cell-derived, unlocatable | 0% | 0% |
| Cell-derived, accepted | 64.2% | **89.0%** |
| Text-derived, accepted | 77.5% | 77.5% |

Cell-derived values went from certifying *worse* than text-derived ones to
certifying substantially better, which is what they should always have done:
their location is known exactly rather than searched for.

**The pattern worth recording.** In both cases the score behaved correctly on
the pixels it was given, the bound held throughout, and the calibration could
not have flagged either — deferring never breaches `P(accepted AND wrong)`, so a
systematically mis-cropped population simply abstains and the guarantee stays
true over a quietly narrower set. AUC over the adjudicated fields is unchanged
at 0.8818 across both fixes. **What moved was coverage, and coverage is where
this method can go wrong without saying so.**
