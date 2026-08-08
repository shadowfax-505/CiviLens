# CivicLens research sidecar

Statistical evaluation for the calibration work. Deliberately separate from the
Laravel application.

**Why separate.** The application owns acquisition, provenance, review workflow,
publication gates, and audit — web-application concerns, and they stay in
Laravel. This directory owns conformal calibration analysis, ordering
comparison, and the figures behind any claim. A reviewer checking
reproducibility should not have to install PHP to re-run an evaluation.

**Contract.** The database is the interface. The application writes
`extraction_fields` (publisher group, script class, calibration split,
nonconformity score, outcome); this reads them as CSV and computes. Nothing here
writes to the application's tables, and nothing here is on the request path.

## Use

```bash
python3 -m venv .venv && .venv/bin/pip install -r requirements.txt
php artisan tinker --execute='...' > fields.csv   # export from the app
.venv/bin/python compare_orderings.py fields.csv --alpha 0.05
```

## Why ordering-only

Born-digital extraction has no model and no confidence: every word is exact, so
a confidence-derived score is constant across every field and ranks nothing.

Split conformal's threshold is a quantile of calibration scores — a rank
statistic — so it is invariant under any strictly monotone transformation of the
score. Only the *ordering* matters. A structural ordering therefore suffices
where no probability exists.

That invariance is elementary and self-verifiable for a **total** order, which
is what `compare_orderings.py` requires. Extending to a partial or inconsistent
comparator needs the treatment in "Conformal Prediction without Nonconformity
Scores" (OpenReview ENJd3vujta), which is not implemented here.
