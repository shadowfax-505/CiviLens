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

---

## Addendum, 2026-09-02: certifying per kind of value needs calibrating per kind

A threshold is now refused for a kind of value the group has never labelled.
That closes an overclaim — 4,135 court values facing a threshold fitted on 105
dates, and 389 already-accepted values elsewhere in the same position — but it
does not open the other side. Refusing a kind is not the same as certifying it.

The group in this ADR is publisher × script, and the threshold is still fitted
across every kind inside it. So the way to make a kind certifiable is to
calibrate it, and there are only two routes:

1. **Wait for the group's own draw to reach it.** The queue draws uniformly
   inside the group, which is what makes the calibration set exchangeable with
   what the group certifies. Case numbers are 5.6% of `supremecourt|en`, so
   nineteen of them costs on the order of 340 judgements — and statutory
   references, at 4.7%, about the same again.

2. **Make the stratum publisher × script × kind.** Then a kind-filtered draw is
   uniform inside its own stratum and nineteen judgements buy that kind
   directly.

**Filtering the review queue by kind without doing (2) is the one move that must
not be made.** It looks like the obvious shortcut and it is the exact error this
ADR's group filter was careful to avoid: judging case numbers on purpose
enriches the calibration set in case numbers, the set stops being a uniform
sample of the group, and the threshold it fits is arithmetic about a population
that does not exist. It would buy the court kinds a certification by
invalidating what certification means. The review screen therefore *reports*
which kinds a group has never judged, and offers no way to draw by kind.

Route 2 is the real answer and it is not free. Splitting each group by kind
multiplies the strata — `supremecourt|en` alone holds seven kinds — and most of
the pieces land below the nineteen labels a 5% claim needs, which is precisely
the situation this ADR was written for: adaptive α, so each stratum certifies at
the level its own count supports, and a borrowed threshold where it cannot. The
cost is that the group-conditional claim becomes a kind-conditional claim, and
every count on the review screen and in the paper is recomputed against a
finer partition.

That is a decision about what the paper claims, not a refactor, so it is
recorded here and not taken. Until it is taken, court values stay withheld and
the corpus certifies figures.
