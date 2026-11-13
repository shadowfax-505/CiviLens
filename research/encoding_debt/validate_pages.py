"""Check the premise on rendered pages: the page shows Bengali, the text layer does not.

    python validate_pages.py --pages data/corpus_pages.csv --n 50 --out data/page_validation.csv

Font names say a run is legacy-encoded; this checks what that means on the
page, independently of any font name. For a uniform sample of documents with
legacy text, the page holding the most of it is rendered and read with
Tesseract's Bengali model, and the Bengali letters OCR finds on the page are
compared with the Bengali letters the text layer offers on the same page.

A page confirms the premise when OCR finds Bengali (at least --min-bengali
letters) and the text layer offers under --max-share of what OCR found.
"""
from __future__ import annotations

import argparse
import csv
import os
import random
import tempfile
from pathlib import Path

from families import BENGALI_BLOCK
from pdftools import ToolError, run
from sources import parallel, write_csv

FIELDS = ["path", "page", "legacy_chars", "text_layer_bengali", "ocr_bengali", "confirms", "error"]


def check(item: tuple[str, int, int, int], min_bengali: int, max_share: float) -> dict[str, object]:
    path, page, legacy, text_bengali = item
    row: dict[str, object] = {"path": path, "page": page, "legacy_chars": legacy,
                              "text_layer_bengali": text_bengali, "error": ""}
    with tempfile.TemporaryDirectory() as tmp:
        prefix = Path(tmp) / "p"
        try:
            run(["pdftoppm", "-r", "200", "-f", str(page), "-l", str(page), "-png", "-singlefile", path, str(prefix)])
            text = run(["tesseract", f"{prefix}.png", "stdout", "-l", "ben"], timeout=300)
        except ToolError as exc:
            row.update(ocr_bengali=0, confirms="", error=str(exc)[:200])
            return row
    ocr_bengali = len(BENGALI_BLOCK.findall(text))
    row.update(ocr_bengali=ocr_bengali,
               confirms=int(ocr_bengali >= min_bengali and text_bengali < max_share * ocr_bengali))
    return row


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--pages", type=Path, required=True, help="textshare.py --pages-out output")
    parser.add_argument("--n", type=int, default=50)
    parser.add_argument("--min-legacy", type=int, default=200, help="legacy characters a page needs to be drawn")
    parser.add_argument("--min-bengali", type=int, default=100)
    parser.add_argument("--max-share", type=float, default=0.2)
    parser.add_argument("--seed", type=int, default=0)
    parser.add_argument("--out", type=Path, required=True)
    args = parser.parse_args()
    os.environ.setdefault("OMP_THREAD_LIMIT", "1")

    best: dict[str, tuple[str, int, int, int]] = {}
    with args.pages.open(newline="", encoding="utf-8") as handle:
        for r in csv.DictReader(handle):
            legacy = int(r["legacy_chars"] or 0) + int(r["legacy_inferred_chars"] or 0)
            if legacy >= args.min_legacy and (Path(r["path"]).name not in best or legacy > best[Path(r["path"]).name][2]):
                best[Path(r["path"]).name] = (r["path"], int(r["page"]), legacy, int(r["bengali_unicode"] or 0))
    rng = random.Random(args.seed)
    chosen = rng.sample(sorted(best.values()), min(args.n, len(best)))
    results = list(parallel(chosen, lambda item: check(item, args.min_bengali, args.max_share)))
    write_csv(args.out, results, FIELDS)
    judged = [r for r in results if r["confirms"] != ""]
    confirmed = sum(int(r["confirms"]) for r in judged)
    print(f"{len(best)} documents eligible; {len(judged)} pages checked; {confirmed} confirm the premise "
          f"({confirmed / max(len(judged), 1):.3f}); {len(results) - len(judged)} failed to render or read")
    for r in results:
        if r["confirms"] == 0:
            print(f"  does not confirm: {Path(str(r['path'])).name[:16]} p{r['page']} "
                  f"legacy={r['legacy_chars']} text-layer Bengali={r['text_layer_bengali']} OCR Bengali={r['ocr_bengali']}")


if __name__ == "__main__":
    main()
