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
- **Labels.** Reviewer adjudication is the only admissible label (ADR-016). "Can't tell" is recorded
  and excluded rather than coerced into a verdict.
- **Sampling.** Uniform at random, never by confidence. Queueing the most confident items first would
  make the calibration set unrepresentative of what the system accepts.

## What is not certified

- **Row, column and heading.** Table structure is context that makes a figure usable. It is shown to
  reviewers as read, not as verified, and takes no part in the judgement.
- **Values that cannot be located.** A field whose characters cannot be found again on its page
  carries no score, is always deferred, and is excluded from calibration. It is never accepted, so it
  cannot contribute to the bound — but it is also never covered by it. The share is reported.
- **Pages the recognizer abstained on.** Attempted and declined; no text is stored and nothing from
  them is certified.
- **Anything about a publisher's conduct.** The system reports counts. An amendment is routine; a
  screen that implied otherwise would be making an accusation out of a count.

## Known limits

- **A second read is a second opinion**, not ground truth — the same engine family can misread the
  same glyph identically twice and agree with itself.
- **Document extraction rests on one publisher.** Of the registered sources, one publishes an
  indexable document archive; one publishes records rather than files; one has no index to discover
  from. Any group-conditional claim is about that single publisher until a second document source is
  authorised.
- **Errors are rare in absolute terms**, so the realized-risk estimate is coarse and the reported
  interval should be read accordingly.
