# ADR-021: The public site serves a frozen snapshot, and not the domain tables

**Status:** Accepted
**Date:** 2026-08-19

## Context

Acquisition, extraction, scoring and calibration all worked and all wrote to their own tables. Nothing
carried the results anywhere a reader could reach. `OcdsTenderNormaliser` transformed a notice and
returned it without persisting anything, no code wrote an extraction result into a domain table, and
the public portal never referenced extraction data at all. The site served six demonstration projects
and four agencies beside a pipeline holding 5,186 certified values.

The obvious bridge was to fill the domain tables. It is not available:

| `tenders` requires | an e-GP listing row has | an audit page has |
| --- | --- | --- |
| project, budget, agency | — | — |
| procurement method, category, status | nature only | — |
| title | — | — |
| tender number | reference number | — |

Writing either source into `tenders` means inventing the six columns that make a row a tender. A
project whose method is built on declining to guess cannot guess here.

## Decision

Publication is a **snapshot**: a frozen set of what CivicLens is prepared to stand behind, in the
shape the evidence actually supports — documents, the certified figures read off their pages, and
the notices a publisher listed.

**A snapshot copies the values it publishes.** The pipeline's tables answer "what do we believe now"
and are rewritten whenever a page is re-extracted or a threshold moves. A citation needs "what did
you publish, and on what basis", and it has to stay answerable afterwards. Ids are kept beside the
copies so any row traces back.

**Building and publishing are separate.** `civiclens:publish <label>` builds; `--publish` makes it
the one the site serves. A new snapshot supersedes the old without deleting it, so an earlier
citation still resolves. The site serves exactly one.

**Only accepted fields become figures.** A deferral is the system declining to stand behind a value,
and publishing one under a caveat is still publishing it.

**Every figure carries its level and its basis.** ADR-018 requires the certified α to travel with the
group everywhere it is reported, and a threshold borrowed from a wider pool is not a conditional
claim about the group inside it. Both are columns, both are on the page.

**Zeroes stay.** 494 artifacts are in the snapshot; 13 are documents and 9 of those certified
anything. The 481 archived web pages and records are listed with a figure count of zero. ADR-020
settled this for an endpoint that produced nothing, and dropping the zeroes here would make the
corpus look like it certifies everything it holds.

## What the guarantee does not cover

The bound is about the **reading**, not the meaning. A value certified as an amount is a number the
system is confident it read off the page correctly; whether that number is a sum of money, a
percentage or a section heading is not something it decides. `৩.১` beginning `৩.১ ভূমিকা:` is a
heading, and it is certified as read rather than as money. The document page says so in as many
words.

**A correction, recorded because the wrong figure was published.** This section first claimed that
1,131 of 5,186 accepted values were contents-page section numbers. That number came from the *shape*
of the value — one or two digits, a point, one or two digits — and was never checked against the
page. Read in context, most values of that shape are budget figures in crore or percentages, which
is how these reports write them: `GDP এর ১১.৫৩ শতাংশ`, or a row of `541.85 13.89 0.00 555.74`.

Classifying a sample of 658 by what surrounds them on the page — a heading number is followed by
words, a budget figure sits among other numbers — puts roughly three quarters in the budget-figure
bucket, and inspection of the remainder finds percentages there too. Heading numbers are a small
minority of accepted values, on the order of a few per cent, not a fifth. The classification is a
heuristic and the figure is an estimate; what is certain is that the original claim was wrong and
was arrived at by exactly the method this project's standing rules forbid.

The general point survives the correction and is why the caveat stays on the page: the guarantee
covers the reading, and a reader must not take a certified number as a certified *kind* of number.

## Consequences

**The demonstration portal is still there.** `/public/projects`, `/public/procurement` and the rest
read the domain tables and still show seeded rows. `/public/evidence` reads only the published
snapshot and cannot show a demonstration row by construction. Retiring the demonstration pages is a
separate decision from building the real one.

**One publisher, on the public site as everywhere else.** Every certified figure is from
dgcivil-bangladesh, because it is the only publisher with adjudicated labels. The certification table
on the index page names the publisher and script for each group, so the narrowness is visible rather
than inferred.

**A snapshot is only as current as its last build.** Nothing rebuilds it automatically. That is
deliberate — an unattended rebuild would move published figures under a citation — but it means a
stale snapshot is a real failure mode, and the label and publication date are shown on every page
that serves one.
