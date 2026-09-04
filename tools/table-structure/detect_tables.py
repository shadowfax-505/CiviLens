"""Report where a page's tables are, and where each cell sits.

Structure only. This deliberately returns no text: PaddleOCR's recognizer has no
established quality on Bengali, which is most of this corpus, and adopting it for
text would silently change how every page is read. Tesseract already reads these
pages with ben+eng, and its word boxes are placed into these cells by the caller.

Usage:  detect_tables.py <image-path>
        CIVICLENS_TABLE_BUDGET_SECONDS bounds the run from inside (default 840).
Output: JSON on stdout — {"tables": [{"index": 0, "cells": [...]}]}
        Each cell carries row, col, spans and a pixel box.
Exit:   0 on success including "no tables found", 1 on failure with a message.
"""

from __future__ import annotations

import json
import logging
import os
import re
import signal
import sys
import warnings

warnings.filterwarnings("ignore")
logging.disable(logging.WARNING)


def enforce_own_deadline() -> None:
    """Stop after the budget, whatever the caller manages to do about it.

    The caller sets a timeout on this process and cannot always act on it. PHP
    enforces its timeouts with a signal handler, and a worker blocked in the
    syscall that reads this process's output cannot run one — so on a machine
    that is paging, none of the three timeouts around this script fires. One
    page ran for 3h 14m against an 840-second budget, past the job's 900 and the
    worker's 1800, and the worker did not even recycle at its own --max-time.
    Detection fell to 0.3 pages an hour because a single page ate the afternoon.

    SIGALRM is delivered by the kernel to this process, so it does not depend on
    anyone else being able to act. A budget kept here turns an unbounded stall
    into a bounded failure, which is the difference between an estimate and a
    guess.
    """
    try:
        budget = int(os.environ.get("CIVICLENS_TABLE_BUDGET_SECONDS", "840"))
    except ValueError:
        budget = 840

    if budget <= 0:
        return

    def give_up(_signum: int, _frame: object) -> None:
        print(
            json.dumps({"error": f"TimeBudgetExceeded: no result within {budget}s"}),
            file=sys.stderr,
        )
        # Hard exit rather than an exception: the stack may be inside native
        # inference code that will not unwind, which is how this became a
        # three-hour job rather than a fourteen-minute one.
        os._exit(1)

    signal.signal(signal.SIGALRM, give_up)
    signal.alarm(budget)


def cells_from_html(html: str) -> list[dict]:
    """Recover row and column indices from the predicted table HTML.

    PP-Structure reports the structure as HTML rather than as coordinates per
    cell, so the grid is walked to assign indices, honouring spans. An occupancy
    map is kept because a rowspan pushes later cells rightwards on the rows it
    covers, and ignoring that misaligns every column beneath a merged heading.
    """
    cells: list[dict] = []
    occupied: set[tuple[int, int]] = set()

    for row_index, row_html in enumerate(re.findall(r"<tr>(.*?)</tr>", html, re.S)):
        col_index = 0

        for cell_html in re.findall(r"<t[dh](.*?)>(.*?)</t[dh]>", row_html, re.S):
            attributes, inner = cell_html

            while (row_index, col_index) in occupied:
                col_index += 1

            row_span = int((re.search(r'rowspan="(\d+)"', attributes) or [0, 1])[1])
            col_span = int((re.search(r'colspan="(\d+)"', attributes) or [0, 1])[1])

            for r in range(row_index, row_index + row_span):
                for c in range(col_index, col_index + col_span):
                    occupied.add((r, c))

            cells.append(
                {
                    "row": row_index,
                    "col": col_index,
                    "row_span": row_span,
                    "col_span": col_span,
                    # Kept only so a caller can see what the model itself read;
                    # cell text is assembled from Tesseract words, not from this.
                    "model_text": re.sub(r"<.*?>", "", inner).strip(),
                }
            )

            col_index += col_span

    return cells


def main() -> int:
    enforce_own_deadline()

    if len(sys.argv) != 2:
        print(json.dumps({"error": "expected exactly one image path"}), file=sys.stderr)
        return 1

    try:
        from paddleocr import TableRecognitionPipelineV2
    except Exception as exc:  # pragma: no cover - environment dependent
        print(json.dumps({"error": f"paddleocr unavailable: {exc}"}), file=sys.stderr)
        return 1

    try:
        pipeline = TableRecognitionPipelineV2()
        tables = []

        for result in pipeline.predict(sys.argv[1]):
            for table in result.get("table_res_list", []):
                box = table.get("cell_box_list") or []
                cells = cells_from_html(table.get("pred_html", "") or "")

                # The model reports cell boxes separately from the structure
                # HTML, in reading order, so they are zipped back together.
                for cell, cell_box in zip(cells, box):
                    coordinates = [int(v) for v in list(cell_box)[:4]]
                    cell["box"] = coordinates

                tables.append({"index": len(tables), "cells": cells})

        print(json.dumps({"tables": tables}, ensure_ascii=False))

        return 0
    except Exception as exc:
        print(json.dumps({"error": f"{type(exc).__name__}: {exc}"}), file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
