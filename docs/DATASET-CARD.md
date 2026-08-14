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
