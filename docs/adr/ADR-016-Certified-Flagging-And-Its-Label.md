# ADR-016: Certified Flagging And The Label It Depends On

## Status

Accepted, and blocked on one input that cannot be produced by code.

## Context

CivicLens flags procurement records for review. The research contribution is not the flagging —
procurement red flags are a mature field, and single-bid indicators plus gradient boosting
replicate published work. The contribution is a **bound on how often the system is wrong when it
flags**, calibrated per procuring agency, distribution-free and finite-sample.

A literature scan found conformal risk control applied to surface-defect detection, railway
signalling, radiotherapy QA, and generic anomaly detection, and nothing for procurement,
corruption, or audit selection. The gap is also the product requirement: a system whose ethos
forbids accusation needs a way to say "at most α of what we surface is wrong, and here is the
proof" rather than "our model scored this 0.87".

## Decision

### What the guarantee says

Split conformal prediction over indicator flags, calibrated group-conditionally by procuring
agency. For a chosen risk level α, the finite-sample bound is

```
P(accepted AND wrong) ≤ (errors + 1) / (n + 1) ≤ α
```

which requires at least `⌈1/α⌉ − 1` calibration items per group — **19 at α = 0.05**. An agency
below that threshold flags nothing. Denying by default is the correct behaviour, not a
limitation: a guarantee computed from four examples is arithmetic, not evidence.

### What the guarantee does not say

It bounds `P(accepted AND wrong)`, **not** `P(wrong | accepted)`. Those differ, and the second is
what a reader intuitively assumes. `ConformalCalibrator` is explicit about which one it computes,
and any public wording must be too.

### The label is reviewer adjudication, and the claim is scoped to it

This is the load-bearing decision, and ST2 settled it.

Corruption labels do not exist. Audit findings are not corruption labels — a procedural
violation, an unresolved objection, a financial loss, a suspected fraud and a conviction are five
different things. Audited entities are also selected rather than random, so audit findings are not
exchangeable with the general population and break the conformal assumption outright.

That leaves **a trained reviewer judging whether a flag was correct**. So the claim is:

> At most α of the records this system flags for review are flagged in error, per agency, where
> "in error" means a trained reviewer judged the flag unwarranted.

It is a claim about **reviewer-adjudicated flag correctness**, never about corruption. Drift
toward "certified corruption detection" is the single most likely way this work becomes
dishonest, and reviewing for that drift is a standing obligation on anyone writing about it.

### Exchangeability is a stated limitation, not an assumption to hide

Conformal bounds assume exchangeability. Procurement data is temporal, agencies change
procedures, and rules shift. Realized coverage is therefore reported against nominal, per agency
per period, and a divergence is treated as a finding to publish rather than noise to smooth. The
literature on false confidence in selective classification is directly about bounds that look
valid and are not, and it is required reading before any bound is claimed.

## Consequences

### What is built

- `ConformalCalibrator` — group-conditional split conformal, honest about which probability it
  bounds.
- `CalibrationReport` — realized versus nominal risk, with a label-leakage detector that reports
  `decidable: false` when only one outcome is present rather than reporting "clear".
- `FieldExtractionSignals` and `StructuralOrderings` — ordering built from structural evidence,
  because born-digital words carry uniform confidence and a constant ranks nothing.
- `research/conformal.py` — an independent reimplementation that reproduces the PHP thresholds
  exactly, so the arithmetic is checked by something that shares no code with it.

### What blocks the empirical claim

**Adjudicated labels. Roughly 50 reviews gets the first agency past n ≥ 19.**

As of this ADR the corpus holds 562 tender observations collected from the live e-GP crawl and
**zero calibratable fields**, because nothing has been adjudicated. Every other input is now
self-feeding: acquisition runs continuously, observations accumulate, and the listing re-sweeps so
revisions become visible.

This is person-work and cannot be generated. Synthetic labels would make the guarantee
arithmetically valid and practically meaningless, which is the failure this ADR exists to
prevent.

### Why the guarantee is worth having even with a weak detector

A guarantee that holds under a bad detector is stronger evidence than one riding a good model. On
a 0%-accurate extractor the calibrator accepted about one field in forty — exactly the error
budget. If the rule engine is weak, the certified system flags almost nothing, which is the
correct and safe outcome and is itself a reportable result.

## Related

ADR-013 (heterogeneous public sources), ADR-015 (recognizer ceiling). The impossibility result for
non-decomposable sequence metrics (CER, edit distance) is why the guarantee is stated over
per-field decisions rather than over whole-document accuracy.
