# Dataset card: CivicLens acquisition corpus

Counts regenerate with `php artisan civiclens:paper-report`; this document records provenance,
permission, and what the corpus is not.

## Sources

| publisher | what it publishes | what CivicLens holds |
| --- | --- | --- |
| Civil Audit Directorate (`dgcivil-bangladesh`) | audit reports as PDFs, indexed on one archive page | the documents behind every link on that page |
| BPPA e-GP (`bppa-egp`) | tender rows in a listing servlet | structured observations only — the detail servlet answers POST, so there is no file behind a row |
| Comptroller and Auditor General (`cag-bangladesh`) | audit documents in a storage path | nothing: the category pages return placeholder text and the storage path has no index |

The e-GP distinction matters. Those rows are a second publisher's **data** and not a second
publisher's **documents**, so they support reporting on what was published while contributing nothing
to the extraction guarantee.

## Permission and governance

Each endpoint carries a recorded access decision and an authorisation string naming the authority that
granted it, provisioned through `civiclens:register-bangladesh-sources` rather than assumed in code.
Fetching is allowlisted by host and path prefix, DNS-pinned, rate-limited per publisher, and
fail-closed: an endpoint that is not registered with a decision cannot be crawled.

Enumerating an unindexed storage path — guessing filenames — is not discovery and is not covered by
any authorisation recorded here. That is why the CAG endpoint stays registered and empty rather than
being made to produce results.

Republication follows attribution: the publisher's name travels with the record.

## Sources fetched against a publisher's robots directive

Two sources are fetched despite their `robots.txt` disallowing automated agents,
each on a decision the operator recorded against that endpoint alone, taken on
legal advice for a non-commercial public-benefit project:

- `data.opensanctions.org`, which answers `User-agent: * / Disallow: /` for its
  whole bulk-data host. Its data is licensed for reuse under CC BY-NC 4.0.
- `query.wikidata.org`, which disallows `/sparql` to every agent. One query a day
  returning a few hundred rows, far below the load that directive exists to
  prevent. Wikidata is CC0, so nothing licence-wise travels with what is derived
  from it.

That decision is stored against that endpoint alone, logged on every request, and
listed by `civiclens:paper-report` under `fetched_against_robots`. It is recorded
here because a reader deciding whether to trust this corpus is entitled to know
which parts of it were taken against a publisher's stated wishes, and because the
CC BY-NC term travels with anything derived from it: attribution is required and
commercial reuse is not permitted.

## Composition and its biases

- **Scripts.** Bengali dominates, with English and mixed pages behind it. Group-conditional
  calibration exists because a pooled threshold would let the majority script hide the others.
- **Legacy fonts.** Many documents store Bengali numerals as the Latin bytes that render them, so a
  page reading ১০০.০০ arrives as `100.00`. Reviewers are told to judge digits rather than script.
- **Abstention.** Roughly a quarter of pages are attempted and declined by the recognizer. They are
  counted, not quietly dropped: a corpus reported without them overstates how much text exists.
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
