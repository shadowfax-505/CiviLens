# ADR-018: A rare group certifies at the level it can support, not at nothing

**Status:** Accepted
**Date:** 2026-08-14

## Context

Group-conditional split conformal needs, for a group of size *n* at risk level α,

```
n >= 1/α - 1
```

which is 19 at α = 0.05. The system reported that number as a single requirement for every group and
described anything below it as "certified for nothing".

That is the right arithmetic attached to the wrong claim. Some publishers are simply rare — a source
that publishes four notices a year cannot reach nineteen adjudications by anyone working harder, and
the reviewer holding the queue said exactly that. Treating 19 as a property of the group rather than
of α means a rare publisher is permanently deferred, and the low-resource sources are the ones this
project exists to cover.

## Decision

Invert the same inequality. For a group of size *n*, the tightest attainable level is

```
α_min = 1/(n + 1)
```

so nine labels certify at α = 0.10, four at α = 0.20, and nineteen at 0.05. Each group is calibrated
at `max(requested α, α_min)` and the level it was certified at is reported beside it. Nothing about
the finite-sample argument changes; only the level being claimed does.

A ladder handles groups too small even for that:

1. **publisher × script** — the guarantee is conditional on the group.
2. **script across publishers** — used when α_min exceeds a ceiling (default 0.25), past which the
   claim stops being worth making. Valid for that script; **not** a conditional statement about the
   rare publisher inside it.
3. **nothing** — when the wider pool cannot certify either. Borrowing from a pool that lacks a
   guarantee would invent one out of two populations that each lack it.

Every group records `attainable_alpha`, `certified_alpha`, and `basis`, and the report lists
`relaxed_groups` and `borrowed_groups` separately from groups certified conditionally.

## What this changes on the current corpus

| group | n | before | after |
| --- | --- | --- | --- |
| dgcivil-bangladesh \| bn | 85 | certified at 0.05 | certified at 0.05 |
| dgcivil-bangladesh \| en | 17 | **certified for nothing** | certified at 0.056 |
| dgcivil-bangladesh \| mixed | 9 | **certified for nothing** | certified at 0.10 |

## Consequences

**A weaker claim is still a claim, and must be reported as one.** A group certified at α = 0.20 is
not comparable to one at 0.05, and any table that lists them in one column without the level is
misleading. The level travels with the group everywhere it is reported.

**A borrowed threshold is not conditional coverage.** This is the one place the scheme can be
misread into an overclaim, so `basis` is carried through the data structure rather than reconstructed,
and `conditional()` is what the write-up should key on.

**The ceiling is a judgement, not a theorem.** 0.25 is where a claim was judged to stop being worth
making; it is configuration, and the report states it.

**Clustered conformal remains the better answer at scale.** With many rare publishers, pooling by
script is crude — grouping by similarity of score distributions would keep more conditionality. That
is worth doing when there are enough publishers for the clustering to mean anything, and is not worth
doing for three groups.
