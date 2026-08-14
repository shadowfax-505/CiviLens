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
