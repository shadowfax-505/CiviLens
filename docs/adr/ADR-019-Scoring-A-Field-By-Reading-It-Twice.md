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

---

## Addendum, 2026-08-26: a score records which reader made it

A score is a claim that two readings of the same pixels agreed. What counts as
agreement is not fixed — it changed when the locator learned that documents
write a sum as `৩,৯৮,৭৪৮/-`, when cropping became cell-aware, and when a
disagreement about a sign began deferring by rule. Every score taken before a
change describes a reader that no longer exists.

Nothing in the schema could say so. A corpus half-scored by an old reader and
half by a new one looked exactly like a corpus scored once, and the way this was
actually found was by sampling: values marked `unlocatable` were handed to the
current locator, which found them. 2,611 IMED candidates and 2,540 of cbad's sat
deferred by a rule that no longer applied. On twenty pages re-scored as a check,
unlocatable fell from 54 to 39.

This is the failure mode this project keeps meeting, in a new place. The bound
was never breached — a stale score defers rather than over-accepts — and the
population the guarantee covered was quietly smaller than the corpus. Health
looked perfect throughout.

`extraction_fields.score_reader_version` now records `SecondReadScorer::READER_VERSION`
with every score. `civiclens:health` reports every version present and how many
scores are owed a re-read, and `civiclens:score-fields` visits unscored pages
first, then pages scored by an earlier reader, then the rest.

**Bump `READER_VERSION` whenever a change alters what a score means** — the
locator's matching rules, the crop, the nonconformity formula, the deferral
rules. Recalibration follows a re-score, as this ADR already requires: the
scores the thresholds were fitted to must be the scores the thresholds are
applied to.

The version is bumped by hand and that is a real weakness — nothing forces it.
Deriving it from the source files was rejected because a comment would then
invalidate the corpus, and in a codebase where the reasoning lives in comments
that would make the signal useless within a week.

---

## Addendum, 2026-08-27: a numeral written in two alphabets defers by rule

58 published figures were numbers spelled half in Bengali digits and half in
Latin ones. A document does not do that, so every one of them was a reading
artefact — and they were not harmless:

| certified | second read | what it was |
| --- | --- | --- |
| `1১৪০৭৭.৩৩` | `[১৪০৭৭.৩৩` | a table rule line, read as a leading digit by one pass and a bracket by the other. The figure certified at ten times the value on the page. |
| `8৫১৭.৪` | `৪৫১৭.৪` | the passes disagree about the first digit: Latin 8 against Bengali ৪, which is 4. |
| `৫8,8৪8` | `¢8,888/-` | neither reading is a number. |

The score could not see it. Edit distance calls `1১৪০৭৭.৩৩` against
`[১৪০৭৭.৩৩` a one-character difference in nine, which is a small number, and
the character in question is the most significant digit. This is the same
lesson as the sign: **a metric over characters cannot weigh what a character
means**, and the fix in both cases is a rule rather than a better score.

Deferred, never corrected. Which script was meant is a guess, and guessing here
would invent a figure — this project has already published money once by
repairing a value instead of declining it.

267 values now defer under `mixed-script-numeral`. The bound is untouched, by
the argument this ADR already makes for unlocatable values: a field that is
never accepted cannot contribute to P(accepted AND wrong).

The rule catches this case by its shape rather than by understanding it. A rule
line misread as a digit on a page that is wholly Bengali or wholly Latin
produces a numeral in one alphabet and still certifies unseen. Nothing here
finds that.

---

## Addendum, 2026-08-27: readings that disagree about the leading digit defer

Found by asking a narrower question than the last one: not "which values look
odd" but "where do the two readings differ, and does the difference matter".

164 accepted figures had readings that disagreed about the most significant
digit, and 24 more where one reading carried a leading digit the other did not.

| first read | second read | difference |
| --- | --- | --- |
| `১০০০০` | `৯০০০০` | ten thousand or ninety thousand |
| `400.00` | `800.00` | double |
| `39656.49` | `639656.49` | sixteenfold |
| `৩৩৫৫৪` | `২৩৩৫৫৪` | sevenfold |

Every one scored below its group's threshold. Edit distance calls the first
pair one character in five, which is a small number, and calls the third one in
nine.

**The magnitude is what a public-finance figure is for.** A reading that agrees
about every digit except which order of magnitude the number is has not agreed
about anything worth publishing.

This is the third rule of this shape, after the sign and the mixed alphabet,
and the lesson has been the same each time: **a metric over characters cannot
weigh what a character means.** The nonconformity score is a good ranking of
how confidently two passes agreed; it is not, and cannot be made into, a
judgement about which disagreements change the meaning of a number. Those need
rules, and the rules defer rather than resolve — which reading is right is
exactly what neither pass can tell us.

The differing character must be a digit in at least one reading. A second read
returning `“3500.00` for `3500.00` has caught a quotation mark in the crop:
that is a fact about the crop, not a dispute about the figure, and deferring on
it would abstain on values nothing is wrong with.

Together with the mixed-alphabet rule this withdrew 238 published figures. The
bound is untouched, by the argument this ADR already makes for unlocatable
values.

**What is still not caught.** All three rules work on the shape of a
disagreement, not on what the number means. Where both passes make the same
mistake they agree, the score is low, and the value certifies — which is the
blind spot this ADR opened with and has not closed. A rule line read as a digit
by both passes on a single-script page produces two identical readings of a
figure ten times too large, and nothing here finds it. Only a person looking at
the page does.

---

## Addendum, 2026-09-01: the statistic this ADR rejected was still the gate

This ADR replaced page-level OCR confidence as a *score*, because it is
identical for every field on a page and ranks errors at AUC 0.511. It did not
touch the other place the same number was used: whether a page was read at all.

A page was accepted only if its mean word confidence reached 80, and 1,386 pages
that fell short stored no text whatsoever. One of them, straightened, had a mean
of 74.1 and 56 numeric tokens averaging 83.5 — fifty-six figures read well and
discarded because dot leaders dragged the arithmetic mean under the line.

The measurement that settles it, over 719 labelled values located in the
geometry: tokens read at 80 or above are wrong 3.6% of the time, which is this
corpus's reviewer error rate; below 80 they are wrong 28%. **Per-token
confidence is predictive where the page mean is a coin flip** — the same
quantity, informative per value and uninformative averaged.

ADR-023 records the change. The lesson for this ADR is narrower and worth
keeping: rejecting a statistic for one job does not remove it from the others,
and the gate is a decision about a value's fate exactly as the score is.

---

## Addendum, 2026-09-02: a score can go stale because the page changed, not the reader

`score_reader_version` answers one question: was this score taken by the reader
we have now? It cannot answer the other one. A score is a claim about two
readings of the same pixels, and it goes stale just as surely when the *page*
changes under a reader that did not.

That is what happened. Court pages were extracted and scored before they had any
word geometry at all, so the locator had nothing to search and every value on
them deferred `unlocatable` — correctly, at the time. The native-geometry
backfill then gave 35,514 pages their word boxes. The reader was unchanged, the
version matched, health was clean, and **81,707 values on 19,749 pages sat
deferred by a rule that had stopped applying**. Sampled against the current
locator, about 70% of them locate.

This is the same shape as the stale-reader finding above and as nearly every
defect this project has found: the bound was never breached, because an
unlocatable value defers rather than over-accepts. The guarantee was simply true
of a much smaller population than the corpus, and nothing said so.

### Why the scores were invalidated by hand rather than by a version bump

A bump would have been the mechanism this ADR prescribes, and it would have been
wrong here. `READER_VERSION` means *every* score is suspect, and the corpus is
42,664 pages. But the reader did not change for the corpus at large:

- The one change to the locator was its span limit, four words to sixteen. A
  value of one word — every amount and every date, which is most of the corpus —
  provably examines the identical spans either way, because the search already
  breaks as soon as the joined run outgrows the value. Re-reading those pages
  would have spent hours to write down the same numbers.
- What actually changed was which pages have geometry, and that is a property of
  the page, not of the reader.

So `score_reader_version` was nulled on exactly the scores whose outcome could
differ: 81,707 deferred `unlocatable` on a page that now has word boxes, and
14,822 of the three multi-word kinds, whose location could change with the span.
Those pages then re-scored through the ordinary stale path.

Recalibration follows the re-score as this ADR already requires. Of the first
~17,500 values retaken, 27,934 carry a real second-read score against 2,733 that
still cannot be located.

### The hole this leaves open

Staleness has two causes and the schema records one. Anything that changes a
page after its values were scored — geometry arriving, a rotation applied, a
re-read replacing the text — leaves scores that describe pixels or boxes that
are no longer there, and no command reports it. This one was found by reading
locate rates on a sample, not by a tripwire.

A score should record what it was taken against, not only who took it. The
obvious candidate is the page's own revision, so that health can report *scores
older than the page they describe* alongside *scores older than the reader*.
That is not built. Until it is, any backfill that writes to `extraction_pages`
must be followed by invalidating the scores on the pages it touched, and that
obligation lives only in this paragraph.

---

## Addendum, 2026-09-03: a kind that defers by construction, and why no labels was not enough

Two kinds were added to the court corpus. `disposition` — "the Rule is made absolute.", "the
appeal is dismissed", "above suit is remanded" — is an ordinary certifiable kind: 2,972 candidates on
2,673 pages, three to seven words, and 1,419 of 1,430 located by the real locator on the pages that
carry word geometry. `finding_span` is the sentence in which the court states its holding, and ADR-024 puts it
on a different footing: it is carried as cited context, and it must never be certified. What follows
is only about the second, because keeping a value out of the accepted set is a claim about deferral
and that is this ADR's subject.

**"It has no labels" is not a mechanism.** The first answer to "how is a holding kept out of the
certified set" was that the stratum is publisher × script × kind, a new kind has no labels, and
deny-by-default refuses it. That is true today and is not a guarantee. Under ADR-018's ladder a
stratum with a handful of labels *borrows* a coarser threshold: one reviewer judging one finding
span would give the stratum a rung to stand on, and every scored finding span in the corpus would
then be accepted against a threshold fitted on one-word dates. The review queue draws by stratum
and nothing stops a finding span appearing in an ordinary draw, so this is a plausible Tuesday
rather than a hypothetical.

**The mechanism is that the value cannot be located, and that is measured rather than asserted.**
`ValueLocator` joins at most sixteen recognised words to make one value; a value it cannot locate
scores `null` and defers by the rule this ADR opened with. Running the locator's own search against
the stored word boxes of the 3,259 court pages that have them:

| candidate span | located |
| --- | --- |
| 16 words or fewer | 702 of 716 (98%) |
| 17 words or more | **0 of 2,912** |

So `ReviewCandidateGenerator::MIN_FINDING_SPAN_WORDS` is 17 — the exact cut, not a guess with a
margin added. The specific worry it answers is a recognizer that merges two words into one token and
so covers seventeen words of text in sixteen boxes; that happens zero times in 2,912 on this corpus.
With the rule in place, 0 of 3,230 finding spans located.

Two halves are needed and both are in the generator. The span is longer than the locator can reach,
so a scoring run writes `unlocatable`; and the candidate is *created* with `nonconformity_score`
null rather than with the page's confidence, which every other kind starts with, so it defers from
the moment it exists rather than from the moment a scoring job reaches it. Without the second half a
finding span would be accepted before anything had read it twice.

**The bound is untouched**, by the argument this ADR already makes for unlocatable values: a field
that is never accepted cannot contribute to `P(accepted AND wrong)`.

**`READER_VERSION` is not bumped.** Nothing here changes the locator's matching rules, the crop, the
nonconformity formula or the deferral rules that existed. Verified rather than assumed: the
generator's output was compared token by token against the previous version over all 31,995 pages of
this publisher, and every amount, date, reference, case number, statutory reference, law report
citation and organisation is identical — 0 changed of 103,811. No score on record describes a
different reader than the one that took it.

### What this does to the reports, which is the part that is not fixed

`unlocatable` now means two different things. `LimitationsReport` and `PaperReport` count
`score_basis = 'unlocatable'` as *values not locatable*, and this ADR's own "still outstanding"
paragraph reads that count as the direct route to certifying more. About 3,600 finding spans will
join it, and they are unlocatable **by design**: no amount of work on geometry, split tokens or
merged cells will ever locate one, because a sentence is not a token. A number that used to be a
backlog is now a backlog plus a constant, and nothing in those reports distinguishes them.

This is the same shape as everything else recorded in this file — a figure stays true while the
population it describes changes underneath it — and it is named here rather than repaired because
those files were not this change's to edit. The fix is one clause: exclude
`ReviewCandidateGenerator::CITED_CONTEXT_KIND` from the unlocatable counts, or report it as its own
line.

### One locator finding, recorded because it is this ADR's subject

Dispositions located 542 of 1,430 times before the value carried the stop the page prints after it,
and 1,419 of 1,430 after. The cause is exactly the one the first addendum above records for amounts
and `৩,৯৮,৭৪৮/-`: the recognizer's last word is `discharged.` with the sentence's full stop
attached, the extracted value stopped at the last letter, and `matches()` requires equality. For
amounts the fix went into the locator, which tolerates a trailing currency terminator. Here it went
into the pattern, which takes the punctuation when the page prints one, because the locator was not
this change's file to edit — but `CURRENCY_TERMINATORS` is the better home for it and a sentence
terminator belongs in that list.

The third time this has been the answer. **A value that never carried a character the page prints
after it is a value that cannot be found again**, and the visible symptom each time is not an error
but a kind that quietly certifies nothing.

### The coupling this leaves open

`MIN_FINDING_SPAN_WORDS` is 17 because `ValueLocator::MAX_WORDS_PER_VALUE` is 16. Raising the
locator's span without raising the generator's floor makes holdings certifiable, and — as with every
staleness in this file — nothing would say so, because a value that stops deferring simply starts
being accepted. `CourtHoldingsAndDispositionsTest` locates a generated finding span against a page
whose recognised words are exactly that span's words, which is the most favourable page there could
be, and fails if it is ever found. That test is the whole of the tripwire.
