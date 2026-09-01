# Method card: what CivicLens certifies, and what it does not

Regenerate every figure below with:

```bash
php artisan civiclens:paper-report --alpha=0.05
```

Numbers are deliberately absent from this document. A figure copied into prose cannot be checked and
goes stale silently, and the unflattering ones go stale first.

## The claim

For a field drawn exchangeably from the same group as the calibration set:

```
P(field is auto-accepted AND wrong) <= alpha
```

An unconditional joint probability. It is **not** `P(wrong | accepted)`, which is a ratio of two
random quantities and is not what this construction bounds. That conditional rate is computed and
reported as an empirical diagnostic, never as a guarantee.

**Wrong** means one thing only: a reviewer, shown the value beside the marked characters on the
scanned page, judged that the characters were not read correctly. Not that the number is implausible,
not that it was filed under the wrong heading, not that it sits in the wrong column.

## Construction

- **Corpus.** One row per distinct file. The same document is published at more than one address and
  discovery follows both, so a share of stored artifacts hold a file already held. A duplicated
  document duplicates its candidates, and a calibration set drawn from that pool would not be a
  sample of distinct documents — which is what exchangeability requires. Counted from the checksum.
- **Unit (court records).** Two further kinds, measured over 563 read judgments: the identifier a
  judgment gives itself — `WRIT PETITION NO. 8951 OF 2025` — which 401 of them carry, and the
  provision it invokes — `Section 410 of the Code of Criminal Procedure` — which 396 carry, and the
  precedents it relies on — `54 DLR (AD) 111` — which 293 carry. All three are short enough to find
  again on the page, which is what makes them certifiable. The law reports are named rather than
  matched as any capitalised abbreviation, so what is recognised is stated rather than guessed. What a court *held*
  is a sentence, not a token: it is carried as cited context and is not certified, on the same
  footing as table structure.
- **Unit.** A numeric token — amount, date, reference number. Audit reports carry no labelled fields:
  sixty pages were sampled and not one held three label-value pairs. A token is decomposable, so a
  bound over tokens stays valid where a bound over page-level character accuracy would not.
- **Groups.** Publisher × script. A threshold fitted across publishers and scripts at once reports a
  healthy average while a low-resource subset fails, because the majority group dominates it.
- **Level.** Each group is certified at the tightest alpha its own label count supports,
  `alpha >= 1/(n+1)`. A group too small even for a ceiling borrows a threshold fitted on every
  publisher writing its script — valid for that script, **not** a conditional claim about the rare
  publisher inside it, and recorded as `basis` so the two cannot be reported as one. See ADR-018.
- **Score.** Each value is re-read from its own marked region and scored by whether the two readings
  agree. The score it replaced was page-level OCR confidence, identical for every field on a page.
  See ADR-019.
- **Rules the score cannot express.** A metric over characters ranks how confidently two readings
  agreed; it cannot weigh what a character *means*. Three disagreements are therefore deferred by
  rule rather than scored, because each changes the meaning of the number while differing in one
  character: the two readings disagree about a **sign**; the value is a numeral spelled in **two
  alphabets**, which a document does not do; the two readings disagree about the **leading digit**,
  and so about which order of magnitude the number is. Each was found in published figures. Deferred,
  never corrected — which reading is right is exactly what neither pass can tell us.
- **Labels.** Reviewer adjudication is the only admissible label (ADR-016). "Can't tell" is recorded
  and excluded rather than coerced into a verdict.
- **Orientation.** A landscape page stored portrait is read sideways and comes back as gibberish. The
  enhanced pass detects the turn and straightens the image first; the rotation applied is recorded,
  because the word geometry is then in the rotated frame and any crop drawn later has to agree.
- **Sampling.** Uniform at random, never by confidence. Queueing the most confident items first would
  make the calibration set unrepresentative of what the system accepts.

## Why a value carries no score

Every reason recorded against a field, as it appears in the data:

| basis | meaning |
| --- | --- |
| `second-read` | the two readings were compared and scored |
| `unlocatable` | the characters could not be found again on the page |
| `unreadable` | the crop could not be recognised at all |
| `sign-disagreement` | the readings disagree about a sign |
| `mixed-script-numeral` | one numeral spelled in two alphabets |
| `leading-digit-disputed` | the readings disagree about the leading digit |

Everything but the first is a deferral, and every deferral shrinks the set the
guarantee covers. A test fails if a basis exists in the code and not in this
table, because adding one silently would narrow the claim without narrowing the
claim's description.

## What is not certified

- **Row, column and heading.** Table structure is context that makes a figure usable. It is shown to
  reviewers as read, not as verified, and takes no part in the judgement.
- **Values that cannot be located.** A field whose characters cannot be found again on its page
  carries no score, is always deferred, and is excluded from calibration. It is never accepted, so it
  cannot contribute to the bound — but it is also never covered by it. The share is reported.
- **Pages the recognizer abstained on.** A page with nothing legible on it: attempted, declined, no
  text stored, nothing certified.
- **Pages read but not vouched for.** A page whose mean word confidence never reached the threshold
  keeps its text, marked, and offers only those values whose *own* token confidence clears it. The
  page-level mean is a poor judge of an individual value — ADR-019 measured it at AUC 0.511 for
  ranking errors — and it was deciding whether a page was read at all. On one measured page the mean
  was 74.1 while its 56 numeric tokens averaged 83.5. The token threshold is fitted, not chosen: over
  719 labelled values located in the geometry, tokens read at 80 or above were wrong 3.6% of the
  time, matching the corpus reviewer error rate, and tokens below 80 were wrong 28% of the time.
  **Values recovered this way are a different population from the one the current thresholds were
  fitted on, so they are candidates until their group is labelled afresh.**
- **Values the two readings dispute in a way that changes the number.** See the deferral rules
  above. Like an unlocatable value, such a field is never accepted, so it cannot contribute to the
  bound — and is equally not covered by it. The counts are reported.
- **Anything about a publisher's conduct.** The system reports counts. An amendment is routine; a
  screen that implied otherwise would be making an accusation out of a count.

## Known limits

- **A second read is a second opinion**, not ground truth — the same engine family can misread the
  same glyph identically twice and agree with itself. The three deferral rules above catch
  disagreements by their shape, not by understanding them; where both passes make the same mistake
  they agree, the score is low, and the value certifies. A rule line read as a digit by both passes
  on a single-script page is exactly that. Only a person looking at the page finds it, and that is
  how each of these was found.
- **Every certified value comes from one publisher.** The corpus now holds documents from several,
  but labels exist for only one, and a group with no labels certifies nothing. Any
  group-conditional claim is about that single publisher until a second one is adjudicated — which
  is a matter of reviewer hours, not of code.
- **A script class is named for a language and one of them is not one.** Bengali set in a
  pre-Unicode font stores its characters as Latin bytes, so the classifier reads such a page as
  English. Where every accepted value and every label in a group sit on those pages the group is
  homogeneous and its bound is sound — the name is simply wrong, and a reader who sees a certified
  rate for English will believe this corpus contains certified English documents. Where the shares
  differ between the labels and the values they calibrate, the group blends two populations that
  fail differently, and exchangeability is what is at risk rather than the arithmetic.
- **For word-processor documents the page image is our rendering.** Such a file has no pages until
  software lays it out. The words and figures are the publisher's; the pagination and spacing are
  produced by our conversion, and the evidence page says so for those documents. See ADR-022.
- **Errors are rare in absolute terms**, so the realized-risk estimate is coarse and the reported
  interval should be read accordingly.
- **The corpus was crawled without consulting `robots.txt`**, on a recorded operator decision taken
  on legal advice for non-commercial public-benefit research. It bears on how the corpus was
  obtained rather than on the guarantee, and is stated because a reader is entitled to weigh it.
  Per-endpoint rate limits are enforced regardless. `civiclens:paper-report` reports the posture
  under `crawling_posture`; the dataset card gives the reasoning.
