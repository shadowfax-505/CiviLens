# ADR-015: Recognizer Ceiling On Filled Forms

## Status

Accepted

## Context

The conformal calibration layer needs a corpus with real predictions and real outcomes before any empirical claim can be made. BaFCo KIE was the candidate: 156 annotated Bangladeshi government forms with key/value pairs, of which 60 pages and 633 gold fields were usable locally.

The geometric key-to-value extractor scored 0 of 633 on that corpus. The obvious explanations were that the layout assumption was wrong or the extraction heuristics were badly tuned. Both were measured, and both were wrong.

Against BaFCo's own bounding boxes, values sit to the right of their label on the same visual line in 85.6% of cases, and a column-gap allowance of three times the key height reaches 93.4% of them. The layout model and its tuning are sound.

The actual constraint is recognition. Tesseract with `ben+eng` recognizes the printed labels at roughly 0.35-0.47 and the filled-in values at 0.012-0.069 depending on the sample. These are completed forms: the labels are printed, the values are handwritten, and Tesseract ships printed-text models only.

A tuning sweep confirmed the ceiling is not a configuration artefact. Six engine configurations (`--psm` 3, 4, 6, 11, 12 and `--oem` 1) and four input scales (1x, 1.5x, 2x, 3x) were measured on the same sample. Label recognition moved within 44.6-51.8% and value recognition never exceeded 2.4%. The variation is sample noise, not signal.

## Decision

Filled-form corpora are out of scope for CivicLens extraction evaluation while Tesseract is the recognizer. No further engine tuning, preprocessing, or extraction-heuristic work will be spent against them, because the value-recognition rate is a hard ceiling that extraction logic cannot exceed.

`CorpusLegibilityProbe` runs before extraction work on any new corpus and reports that ceiling explicitly, so this determination is made from measurement rather than from effort already sunk.

Evaluation targets published government documents instead: budget documents, tender notices, and audit reports, where values are printed rather than handwritten and most pages carry a native text layer. That population is also what CivicLens actually ingests, so the evaluation matches the deployment rather than a related-looking dataset.

## Consequences

The empirical calibration claim is deferred until a legible corpus exists. That is the correct trade: a guarantee calibrated on a corpus the recognizer cannot read would be arithmetically valid and practically meaningless.

Adopting a handwriting-capable recognizer such as TrOCR, PaddleOCR, or a hosted vision-language model remains open, but it is a new model dependency with its own governance, cost, and data-handling questions and is not undertaken to rescue one dataset.

The negative result stands on its own: on real Bangladeshi government forms, the gap between raw OCR and the 0.848 F1 that BaFCo reports for a frontier vision-language model is dominated by recognition of handwritten content, not by layout understanding.
