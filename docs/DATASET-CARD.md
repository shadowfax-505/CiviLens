# Dataset card: CivicLens acquisition corpus

Counts regenerate with `php artisan civiclens:paper-report`; this document records provenance,
permission, and what the corpus is not.

## Sources

Which publishers are registered, what each yielded, and why any of them yielded
nothing:

```bash
php artisan civiclens:sources-report
```

Generated rather than transcribed, and it exits non-zero when a source yielded
nothing and recorded no reason — a zero beside a stated reason is a governance
decision, a zero beside silence is a breakage. A table of publishers written
into this file was wrong within a fortnight: it named three sources and said the
Comptroller and Auditor General's storage path held nothing, which stopped being
true once link harvesting reached it from another publisher's pages.

Three distinctions the report does not make for you:

- **Data is not documents.** A listing servlet that answers with tender rows
  yields structured observations and no file. Those support reporting on what
  was published while contributing nothing to the extraction guarantee, and
  counting them together overstates the corpus that guarantee applies to.
- **Archived pages are not documents.** Discovery follows every link a listing
  carries, so the archive holds each publisher's own menus and sector pages
  beside its reports. They are kept — they are the evidence of what a listing
  said on a date — and counted apart.
- **A file found twice is one document.** The same document is published at more
  than one address and discovery follows both. Counted once, from the checksum;
  see the method card for why that matters to the bound rather than only to the
  tally.

## Permission and governance

Each endpoint carries a recorded access decision and an authorisation string naming the authority that
granted it, provisioned through `civiclens:register-bangladesh-sources` rather than assumed in code.
**One source is fetched over plaintext.** `bdlaws.minlaw.gov.bd`, the
government's legal database holding the Constitution and every Act, answers over
HTTP and offers no HTTPS listener at all — not a broken certificate chain that
`civiclens:build-ca-bundle` repairs, no TLS at all. Fetched anyway, by a recorded
per-endpoint exception, because the statutes every judgment cites are otherwise
unreachable. The cost is real: bytes fetched over HTTP can be altered in transit,
so their checksum attests to what we received rather than to what the ministry
served. Statute text is corroborable against the printed gazette and nothing
certified rests on those bytes alone, which is why the trade is acceptable here
and would not be for an audit report carrying figures nothing else states.
`civiclens:limitations` names the endpoint and quotes the reason.

Fetching is allowlisted by host and path prefix, DNS-pinned, rate-limited per publisher, and
fail-closed: an endpoint that is not registered with a decision cannot be crawled.

Enumerating an unindexed storage path — guessing filenames — is not discovery and is not covered by
any authorisation recorded here. That is why the CAG endpoint stays registered and empty rather than
being made to produce results.

Republication follows attribution: the publisher's name travels with the record.

## How this corpus was crawled

**`robots.txt` is not consulted for any of the thirteen endpoints.** The operator's
recorded position, taken on legal advice for a non-commercial public-benefit
project, is that access to public-sector records for research of this kind does
not turn on a file the publisher can edit. A reader deciding whether to trust this
corpus is entitled to know that outright rather than infer it, so it is stated
here, in the method card, and by `civiclens:paper-report` under
`crawling_posture`.

Rate limits do not depend on that decision. They are set per endpoint and enforced
either way, so no source is fetched harder because robots is not consulted.

Two endpoints carry an earlier, narrower decision recorded before the policy was
made project-wide, and they are kept as a record of the reasoning rather than as
exceptions to it:

- `data.opensanctions.org`, which answers `User-agent: * / Disallow: /` for its
  whole bulk-data host. Its data is licensed for reuse under CC BY-NC 4.0, so
  attribution is required and commercial reuse is not permitted for anything
  derived from it.
- `query.wikidata.org`, which disallows `/sparql` to every agent. One query a day
  returning a few hundred rows, far below the load that directive exists to
  prevent. Wikidata is CC0, so nothing licence-wise travels with what is derived
  from it.

## Composition and its biases

- **Scripts.** Bengali dominates, with pages classed English and mixed behind it. Group-conditional
  calibration exists because a pooled threshold would let the majority script hide the others.
  **The class named English may contain no English.** See the next entry: a page of legacy-font
  Bengali is stored as Latin bytes and classified accordingly, and where every accepted value and
  every label in that group sits on such pages, the group is coherent and only its name is wrong.
- **Legacy fonts.** Many documents store Bengali numerals as the Latin bytes that render them, so a
  page reading ১০০.০০ arrives as `100.00`. Reviewers are told to judge digits rather than script.
  The router that reroutes unreadable pages finds nothing to reroute here: the text layer is dense
  and confident, and only its alphabet is wrong. Measured from the residue those fonts leave, and
  reported by `civiclens:limitations`.
- **Judgments name private individuals.** The High Court Division's judgments are published by the
  court and reproduced here as the court published them: an accused person, a petitioner, a witness,
  named as the judgment names them. This is the first source in this corpus where the people on a
  page are frequently *not* officials acting in an official capacity, and serving those pages where a
  search engine can reach them is a different reach from a file on the court's own site. The
  correction and withdrawal route at `/public/corrections` says so and is the remedy; it records a
  refusal as fully as a grant and will not accept a decision without a reason.
- **Word-processor documents.** A few sources publish `.doc`/`.docx`. Such a file has no pages until
  software lays it out, so it is rendered to PDF before anything reads it, and the page image a
  reader is shown is that rendering rather than the publisher's own. Flagged on the evidence page for
  those documents. See ADR-022.
- **Abstention.** Some pages are attempted and declined by the recognizer, and the share is reported
  by `civiclens:paper-report` under `abstained_share` rather than written here — this line read
  "roughly a quarter" while the measured figure was half that, having been true of a corpus a fifth
  of the current size. They are counted, not quietly dropped: a corpus reported without them
  overstates how much text exists. `civiclens:classify-abstentions` says why each page was declined,
  because a rate without a reason invites the reader to assume the worst or the best.
- **Selection.** Documents are whatever a publisher chose to put on a public page. Nothing here is a
  sample of procurement or audit activity; it is a sample of publication.

## What the labels are

Reviewer judgements of whether the characters were read correctly, made against the marked region of
the scanned page. They are not a gold transcription: a value judged incorrect records that the reading
was wrong, not what the page actually said.

## Licence

Source documents remain the publishers'. Attribution accompanies any republished body text. The
extraction records, labels and calibration artifacts are the project's own and are reproducible from
the commands in the method card.
