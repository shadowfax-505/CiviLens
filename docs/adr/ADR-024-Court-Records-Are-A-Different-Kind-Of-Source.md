# ADR-024: Court records are a different kind of source

**Status:** Accepted
**Date:** 2026-09-02

## Context

Until this week the corpus was audit reports, gazettes and evaluation reports. Every document in it
had been written by a public body reporting on itself or on another executive body, and every value
read off one was a figure — an amount, a date, a reference number. The calibration unit was
publisher × script because that was sufficient for exactly that reason: reading a Bengali amount and
a Bengali date are close to the same task.

The High Court Division's judgments are now the largest single source in the corpus. 2,525 stored
artifacts hold 2,507 distinct files, 2,506 of which have been read, across 31,775 pages and 101,845
extracted values. They are also the first source that is not a report, and almost everything
difficult about today came from that rather than from their size.

## What a judgment adds that no other source here does

ADR-016 settled what this project's guarantee is a claim about, and it did so by elimination.
Corruption labels do not exist. Audit findings are not corruption labels, because a procedural
violation, an unresolved objection, a financial loss, a suspected fraud and a conviction are five
different things. Audited entities are selected rather than drawn at random, so audit findings are
not exchangeable with the population anyway. What was left was a trained reviewer judging whether a
flag was warranted, and the claim was scoped to that.

A judgment is the fifth of those five things. It does not report a suspicion or record an
unresolved objection; it states that a court **held** something, in a case identified by name and
number, under a provision it names, on the authority of decisions it cites. Measured over the first
563 judgments read, before anything was built for them: 477 (85%) carry holding language, 401 (71%)
a case number, 396 (70%) a statutory reference, 350 (62%) a disposition. The Supreme Court Online
Bulletin goes further and states the holding itself, as a short summary written by the court rather
than inferred by us — which is the reason that endpoint was registered alongside the judgment
listing.

This is the only material in the corpus where a conclusion has already been reached by someone
entitled to reach it. Everywhere else the project's position is that it publishes counts and
declines to conclude anything from them: an amendment is routine, and a screen implying otherwise
would be making an accusation out of a count. A judgment is not a count. Its conclusion is not
ours, it is not a prediction, and it does not need certifying in order to be citable, because it is
already attributed to an authority a reader can go and check.

What follows from that is narrower than it sounds, and the narrowness is the point. A holding is a
sentence rather than a token, so it is carried as cited context and is not certified — the same
footing table structure has. The certification machinery has nothing to say about whether a court
was right. What it is for is the strings: whether `WRIT PETITION NO. 8951 OF 2025` is what the page
actually says.

## What is certified, and what is only shown

Measured against the published snapshot `2026-09-02-e`, over the current runs of this publisher's
canonical documents:

| stratum | certified | α | basis | not certified |
| --- | --- | --- | --- | --- |
| `supremecourt-bangladesh \| en \| date` | **32,148** | 0.05 | stratum | 26,865 |
| `supremecourt-bangladesh \| en \| amount` | **9,103** | 0.10 | stratum | 13,450 |
| `supremecourt-bangladesh \| en \| reference` | 203 | 0.05 | *group* | 661 |
| `supremecourt-bangladesh \| en \| statutory_reference` | **0** | — | — | 7,780 |
| `supremecourt-bangladesh \| en \| case_number` | **0** | — | — | 5,867 |
| `supremecourt-bangladesh \| en \| law_report_citation` | **0** | — | — | 5,231 |
| `supremecourt-bangladesh \| en \| organisation` | 0 | — | — | 537 |

Three of the five kinds this corpus was read for certify nothing at all, and the three zeroes are
the whole of what a case number, a statutory reference or a law report citation in this corpus is
worth to a consumer who needs a bound. **That is the correct answer and not a shortfall.**

The stratum is publisher × script × kind, and a threshold is fitted on labelled values of that
stratum and applied to unlabelled ones. `supremecourt-bangladesh | en` has been judged 105 times on
dates, 15 times on amounts and 3 times on bare references, and never once on a case number, a
statutory reference or a law report citation. So there is no threshold to apply to one. A kind with
no labels forms no stratum, and deny-by-default refuses it — which is the same rule the system has
always applied to a group with no labels at all, one level down.

The alternative is what the arithmetic would have permitted this morning: apply the date threshold.
It would have been a valid finite-sample bound over a population nobody sampled. A threshold fitted
on 32,148 one-word dates is not a claim about a seven-word case number that runs across a line
break, carries its own punctuation, and has its own ways of going wrong. The bound would have held
and meant nothing, which is the failure this project keeps finding in gentler forms and the one
place it can be committed on purpose.

The two levels in that table are worth reading closely, because they are the ladder of ADR-018
doing its work on real data. Dates certify at α = 0.05 conditionally: 35 of the 105 labels sit in
the calibration split and carry a usable score, comfortably past the nineteen a 5% claim needs.
Amounts certify at α = 0.10 rather than 0.05 for one reason — nine scorable calibration labels, and
α_min = 1/(9 + 1). That number was true of the snapshot and is already false of the pipeline: a
re-score running while this was written took the same stratum to twenty-one scorable labels, so
α_min is 1/22 and the next decision pass will certify amounts at 0.05. The figure above is not
corrected, because it is what `2026-09-02-e` published and a snapshot records what was claimed
rather than what is claimable now — which is the distinction ADR-021 exists to keep. Bare references certify at all only through the first borrowed rung: one usable
label, `basis = group`, a threshold fitted across every kind this publisher writes in this script.
Those 203 values are the one part of the court corpus carrying a level that is not a conditional
statement about the population it was applied to, and they are labelled as such in the snapshot
because ADR-018 requires the basis to travel with the figure.

**Waiting does not close the three zeroes.** The uniform draw inside a stratum is what makes the
calibration set exchangeable with what the stratum certifies, and case numbers are 5.80% of this
publisher's English values, statutory references 7.65%, law report citations 5.16%. Meanwhile the
population grows and the label count does not: ADR-018's first addendum was written this morning
against 2,308 case numbers and 972 statutory references, and by this evening those are 5,867 and
7,780. The route that works is to draw by stratum with the kind included, which is now sound
because the threshold is now fitted per kind.

## Why the calibration stratum had to gain the kind, and what it cost the claims

ADR-018's second addendum records the mechanics of that change, taken today: `ConformalCalibrator`,
the realized rate in `CalibrationReport` and the AUC in `ScoreDiscriminationReport` all key on
`publisher|script|kind`, the ladder gained a rung, `basis = 'stratum'` became the conditional case,
and the review queue draws by stratum. This ADR records what it cost the project's claims, because
that cost is not a refactor and is not visible from the mechanics.

**A sentence the project used to be able to say is gone.** "dgcivil-bangladesh · bn is certified at
0.05" is no longer a claim this system makes. What it says now is "dgcivil-bangladesh · bn · amount
is certified at 0.05", and the difference is not pedantry: the older sentence asserted a level over
every kind of value in that group, including kinds nobody had judged, and the guarantee was never
measured over them. Every artifact that states a level now states the kind alongside the publisher
and the script — the public evidence page, the published snapshot, the paper report, the corpus
export.

**The guarantee is conditional on the kind as well as on the publisher and the script.** Of the
52,630 figures in the current snapshot, 52,208 carry `basis = stratum` and are conditional claims
about publisher × script × kind; 422 carry `basis = group`, a threshold borrowed from a wider pool,
and are valid for that pool rather than conditional statements about the stratum inside it. Both
numbers will move in the same direction as the partition gets finer: strata multiply, more of them
fall below nineteen labels, and more of them borrow. That is the trade ADR-018 accepted and it is
made twice over here.

**A string that used to mean "conditional" now names the first borrowed rung**, and anything written
before today that reads `decision_basis = group` as evidence of conditional coverage now says the
opposite of what its author meant. Two such statements are known to exist and are not corrected
here, because this ADR changes no file but itself: the method card still describes the calibration
groups as publisher × script, and the limitations finding recorded on 2026-09-01 cites "all 21,108
accepted values carry decision_basis = group, none a borrowed threshold" as reassurance. The second
of those was true when written and is now a description of exactly the borrowed case it was denying.

## These documents name private individuals, and there is a line

The judgments are published by the Supreme Court and reproduced here as the court published them,
without alteration. They name petitioners, respondents, accused persons, witnesses, counsel and
judges. This is the first source in the corpus where the people on a page are frequently *not*
officials acting in an official capacity: an accused person in a criminal appeal is neither an audit
report nor an official, and 2,491 judgments were one scoring run away from the published snapshot
when this was noticed. The correction page and the dataset card were changed the same day to
describe that situation to somebody who arrives at them because they are named in one, and
`/public/corrections` remains the remedy — it records a refusal as fully as a grant and will not
accept a decision without a reason.

Reproduction is the easy half. The harder half is that this corpus exists to train a model, and a
model trained on adjudicated findings can emit a per-person score. **The line this project draws is
between citing a finding and making one.**

In scope: training a model on the court's own text, names included, because the names are what makes
the text interpretable and removing them would leave a corpus about nobody. In scope: aggregating
adjudicated findings against **organisations** — a ministry, a directorate, a contractor — because
an organisation named in a count can answer it in public, and because the finding being aggregated
was made by a court rather than by us.

Not in scope, and not built: any output asserting that a **named natural person** is corrupt,
criminal or negligent on the strength of a model's prediction. The reason is not squeamishness about
the subject. It is that a classifier output is a guess, and a guess printed beside a real person's
name does not read as a guess — it reads as a finding, at the precision of the name and none of the
confidence of the model. Nothing about a conformal bound repairs this, and it is worth being exact
about why: the bound this project computes is over whether a *string was read correctly*. It is not
a bound on what the string means. A certified case number is a claim that seven words were
transcribed accurately, and there is no arithmetic that turns it into a claim about the conduct of
anyone the judgment names.

So the rule for output is stated as a rule about outputs rather than as an intention:

**Where a court has found something, cite the court** — the case, the number, the provision, the
page the words are on. That is not the system's conclusion and does not need the system's
guarantee. **Where a court has not, the honest output is "flagged for review"**, which is the
project's oldest position stated in the one place it was newly at risk: the system surfaces, a
person adjudicates, and the bound is over how often what was surfaced was surfaced in error.

**This amends the constitution rather than restating it.** The constitution's non-goals say
CivicLens does not make legal accusations and does not replace human review, and both are written as
statements about what the project *does not do*. That framing was adequate while the corpus was
audit reports, because nothing in the pipeline could have produced a per-person assertion to begin
with. A corpus of judgments plus a model trained on it is a capability the non-goal did not
contemplate, and a non-goal that describes an absent capability stops being a constraint the moment
the capability arrives. The rule above is therefore a constraint on outputs, binding on anything
built on this corpus, and it distinguishes two things the constitution treats as one: reproducing
and citing an accusation somebody else was entitled to make is permitted, and originating one is
not. The corpus export already carries the narrower half of this in
`CourtCorpusExport::PERSONAL_NAMES_NOTE`, which the manifest is required to carry and a test pins,
and the export builds no machinery that could score, flag, rank or label a person.

## Cause lists would be records, not documents

The court also publishes cause lists — the schedule of matters listed before each bench. None is
registered and none is acquired. If one is, it enters as a **record** and not as a document, on the
resource-type route that already exists for e-GP tender rows.

A cause list says that a matter is scheduled. It contains no adjudicated finding and no figure worth
certifying; what it holds is the fact of a listing, which is the information in the row itself
rather than something to be read off a page. ADR-020 set the discipline for exactly this shape when
3,226 e-GP tender rows were captured as observations and their resource type marked record-only, so
that nothing dispatches a fetch for a file that does not exist and `civiclens:paper-report` reports
`documents_by_publisher` and `tender_observations` as two figures rather than one.

The reason that discipline matters here is the guarantee. The bound is over values read off pages of
documents, and every count of documents is a statement about the population the bound covers.
Adding several thousand cause lists to the document count would enlarge the apparent corpus without
adding a single certifiable value to it, so the same guarantee would appear to cover a much larger
body of material than it was ever measured on. That is the silent-narrowing failure of ADR-019 and
ADR-023 run in reverse, and it is worse: those made the guarantee true of less than it claimed,
where this would make it look true of more.

## The subscription and login-gated databases stay out

Bangladeshi case law is also published, in more usable form, by commercial services — BDLex,
Chancery Law Chronicles, Law Messenger, BD Law Service. Reported case law with headnotes,
cross-references and a working search is exactly what would make the citations in this corpus
resolvable. None of them is registered and none will be without a decision recorded against it
separately from this one.

The reason is not that their material is less useful. It is that **crossing a login is a different
act from fetching a public page.** Everything in the acquisition posture rests on a distinction that
holds only for public documents: an operator records an authorisation naming a publisher and what
may be fetched, the host allowlist and path prefixes bound it, the rate limit is set from what the
publisher tolerates, and the record of that decision is what makes the crawl accountable. An
account, a subscription or a set of terms accepted on somebody's behalf changes the nature of the
authorisation. It is no longer "these bytes are published and we fetched them at a pace the
publisher tolerates" but "we hold credentials to a service whose terms govern what we may do with
what we take out of it", and those terms bear directly on the one thing this project does with
everything it holds — republish it beside a page image.

The registry already draws that distinction rather than avoiding it. The IATI Datastore is fetched
under a subscription key, recorded as `operator-authorised-subscription-api`, with the header name
and the *name* of the config key stored on the endpoint and never the key itself. That is a
programmatic access key issued for programmatic access, and it was still a decision recorded by a
person. A case-law site behind an account login is a larger version of the same question and has not
been asked, let alone answered. Until it is, the exclusion is a recorded position and not an
oversight, which is the discipline ADR-020 set when it kept an endpoint registered, active and empty
rather than deleting an inconvenient zero.

## Consequences

**The court corpus is mostly candidates, and every artifact must keep saying so.** 41,454 of this
publisher's 101,845 values are certified and about 58,000 are not. `civiclens:export-court-corpus`
writes `certified_values` and `candidate_values` under separate keys for that reason, and a consumer
that concatenated them would hold roughly 45,000 uncertified strings under a guarantee nobody made
about them.

**Certification is no longer single-publisher and the write-ups have not caught up.** Four
publishers now carry certified values — supremecourt-bangladesh 41,454, dgcivil-bangladesh 6,095,
cbad-bangladesh 2,987, imed-bangladesh 2,094. ADR-020's addendum, ADR-021's consequences and the
method card's known limits all still state that every certified value comes from one publisher.
That was true a week ago and is now the most visible stale claim in the documentation. Correcting it
is somebody's next commit, not this file's.

**Three kinds of value now exist that no reviewer has ever judged**, and judging them is the only
thing that changes the three zeroes. Nineteen judgements buy a kind directly now that the queue
draws by stratum, against the roughly 340 a group-wide draw would have cost for something at 5.8% of
the pool.

**Everything built on this corpus inherits the output rule, including work not yet imagined.** A
model, a ranking, a screen, an export, a paper. The test is the same each time: name the authority
that reached the conclusion. If the answer is a classifier, the output is "flagged for review".

## Related

ADR-016 (what the guarantee is a claim about, and the five things audit findings are not), ADR-018
(the ladder, and the stratum that now includes the kind), ADR-019 (what the score can and cannot
see), ADR-020 (record-only sources, and stating what a corpus holds nothing of), ADR-021 (a level
and a basis travel with every published figure), ADR-023 (a population widened by recovery is a
population the old labels do not cover). The constitution's non-goals on accusation and human
review are amended by the output rule above rather than restated by it.
