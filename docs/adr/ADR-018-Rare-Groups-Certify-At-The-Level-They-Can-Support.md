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

---

## Addendum, 2026-09-02: the stratum is publisher × script × kind

The addendum above set out two routes and recorded the second as a decision not
taken. It has now been taken, deliberately: **the stratum is publisher × script
× kind of value.**

What changed:

- `ConformalCalibrator` keys on `publisher|script|kind`. So does the realized
  rate in `CalibrationReport` and the AUC in `ScoreDiscriminationReport` — a
  realized rate measured over one population and compared with a threshold
  fitted on another is not a check of anything.
- The ladder gained a rung. A stratum too small for the alpha asked for now
  borrows, in order, from every kind its publisher writes in that script
  (`group`), then that script across publishers (`script`), then everything
  (`marginal`). The new rung is the one most strata will land on while the kinds
  are young, which is the whole reason it exists.
- `basis = 'stratum'` is the conditional case. It was `'group'`, and everything
  that keyed on that string was moved: `PublicationFigure::isGroupConditional()`,
  the extraction overview, the limitations report.
- The public evidence page names the kind in the claim. Saying "conditional on
  dgcivil-bangladesh · bn" while the threshold was fitted on one kind of value
  would state a wider claim than the one that was certified.
- The explicit "is this kind covered?" check in `FieldDecisionService` is gone.
  A kind with no labels forms no stratum, so deny-by-default already refuses
  it — one mechanism instead of two saying the same thing.
- The review queue draws by stratum, kind included. **This is the move the
  previous addendum said must not be made, and what makes it sound now is that
  the threshold is fitted per kind.** A draw inside one kind is uniform inside
  the very population that kind's threshold is applied to. Nineteen judgements
  buy a kind directly, against roughly 340 through a group-wide draw for
  something at 5.6% of the pool.

What it costs, stated plainly:

- **A claim is now conditional on the kind of value.** "dgcivil-bangladesh · bn
  is certified at 0.05" is no longer a sentence this system can say; it says
  "dgcivil-bangladesh · bn · amount". That is a narrower claim and a truer one.
- **Every count is against a finer partition.** Strata multiply — a benchmark
  with three gold field names is three strata of one, not one of three — and
  more of them sit below nineteen labels, so more borrow, and borrowing is not
  a conditional claim. The limitations report counts exactly that.
- **Labels already collected are unevenly spread across the new partition.**
  `supremecourt|en` was 105 dates, 15 amounts and 3 references; those become
  three strata, of which one certifies and two do not. Nothing is lost, but
  what looked like 123 labels for one group is not 123 labels for any stratum.

The gain is that the guarantee is now about the thing it was measured on. The
previous arrangement would have certified 2,308 case numbers on the strength of
105 dates, and a bound that holds over a population nobody sampled is
arithmetic, not evidence.
