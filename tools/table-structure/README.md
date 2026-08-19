# Table structure sidecar

Reports where a scanned page's tables are and where each cell sits. **Structure
only — it returns no text.**

That split is deliberate. PaddleOCR's recognizer has no established quality on
Bengali, which is most of this corpus. Tesseract already reads these pages with
`ben+eng`, and its word boxes are placed into these cells by the caller, so a
model with unknown Bengali quality never decides what a figure says.

## Why it is a sidecar

PaddlePaddle has no wheels for Python 3.14, the system interpreter here, and
pulls roughly a gigabyte of models. Keeping it in its own interpreter means the
application, its tests and CI do not depend on any of it: a machine without this
installed keeps working, with pages simply carrying no table structure.

## Install

```bash
brew install python@3.12
/opt/homebrew/bin/python3.12 -m venv .venv
.venv/bin/pip install -r requirements.txt
```

Then point `EXTRACTION_TABLE_PYTHON` at `.venv/bin/python3`.

## Run

```bash
.venv/bin/python3 detect_tables.py /path/to/page.png
```

Prints `{"tables": [{"index": 0, "cells": [{"row":0,"col":0,"row_span":1,"col_span":1,"box":[...]}]}]}`.
A page with no table prints `{"tables": []}` and exits 0 — that is a result, not
a failure.

## Cost

About 90 seconds per page on CPU. Far too slow for the synchronous extraction
path, which is why it runs as a queued pass over pages that carry review
candidates rather than over everything.

## Where to install it

Not in `/tmp`. The first environment lived at `/tmp/civiclens-pp`, and macOS
purges `/tmp` of anything untouched for a few days: PaddleOCR vanished, and every
table job afterwards failed with `No module named 'paddleocr'` — days later, with
nothing to connect the two.

```bash
python3.12 -m venv storage/app/python/paddle
storage/app/python/paddle/bin/python -m pip install -r tools/table-structure/requirements.txt
```

Then point `EXTRACTION_TABLE_PYTHON` at
`storage/app/python/paddle/bin/python`. It is 1.2GB, gitignored, and rebuilt from
the pinned requirements rather than committed. Python 3.12 specifically: the
pinned PaddlePaddle has no wheels for 3.14.

`TableStructureDetector::isAvailable()` reports whether it is there, so a machine
without it keeps working and its pages simply carry no table structure.
