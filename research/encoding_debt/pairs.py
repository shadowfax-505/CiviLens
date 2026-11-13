"""Pair legacy text with what the page actually shows, by reading the glyphs.

    python pairs.py --text data/corpus_text.csv --scheme adarshalipi --docs 120 --out data/pairs_adarshalipi.csv

For each run set in a named legacy font, the page is rendered, the run's box
cropped, and the crop read by Tesseract's Bengali model. The result is a
parallel corpus — legacy bytes beside the Unicode a reader sees — built with no
human labels: the renderer draws the glyphs the font maps each byte to, and
OCR reads them back. OCR errs, so the pairs are noisy; `decipher.py` learns from
many of them and is scored against a known table (Bijoy) before being trusted
on an undocumented one (AdarshaLipi).

Documents are assigned to `learn` or `evaluate` by their content hash, so the
split is the same on every run and every machine, and no document contributes
to both.
"""
from __future__ import annotations

import argparse
import csv
import hashlib
import os
import random
import subprocess
import tempfile
import unicodedata
from pathlib import Path

from PIL import Image

from families import legacy_family
from pdftools import Run, ToolError, run, text_runs
from sources import parallel, write_csv

DPI = 300
SCALE = DPI / 72 / 1.5          # pdftohtml units -> raster pixels
PAD = 6
FIELDS = ["split", "doc", "page", "scheme", "legacy", "ocr", "ocr_confidence", "psm", "left", "top", "width", "height"]


def split_of(doc_name: str, evaluate_share: float = 0.3) -> str:
    """`learn` or `evaluate`, fixed by the document's content hash."""
    bucket = int(hashlib.sha256(doc_name.encode()).hexdigest()[:8], 16) / 0xFFFFFFFF
    return "evaluate" if bucket < evaluate_share else "learn"


def usable(r: Run, scheme: str) -> bool:
    visible = [c for c in r.text if not c.isspace()]
    # One line of running text: long enough to carry letters, short enough
    # that Tesseract's single-line mode applies, and not a bare number.
    return (legacy_family(r.family) == scheme and 6 <= len(visible) <= 120
            and 8 <= r.height <= 40 and any(c.isalpha() for c in visible))


def ocr_line(image: Image.Image) -> tuple[str, float, int]:
    """Read one line with Tesseract's Bengali model: text, mean word confidence, mode.

    Single-line mode (7) first. Its layout pass rejects some tight crops of
    large clean type outright and returns nothing, so raw-line mode (13), which
    skips layout analysis, is tried when 7 finds no words.
    """
    with tempfile.NamedTemporaryFile(suffix=".png") as handle:
        image.save(handle.name)
        for psm in (7, 13):
            tsv = run(["tesseract", handle.name, "stdout", "-l", "ben", "--psm", str(psm), "tsv"], timeout=60)
            words, confidences = [], []
            for line in tsv.splitlines()[1:]:
                cells = line.split("\t")
                if len(cells) == 12 and cells[11].strip() and float(cells[10]) >= 0:
                    words.append(cells[11])
                    confidences.append(float(cells[10]))
            if words:
                return unicodedata.normalize("NFC", " ".join(words)), sum(confidences) / len(confidences), psm
    return "", 0.0, 0


def pairs_for(path: Path, scheme: str, per_doc: int, seed: int) -> list[dict[str, object]]:
    try:
        runs = [r for r in text_runs(path) if usable(r, scheme)]
    except ToolError:
        return []
    if not runs:
        return []
    rng = random.Random(f"{seed}:{path.name}")
    runs = rng.sample(runs, min(per_doc, len(runs)))
    out: list[dict[str, object]] = []
    split = split_of(path.name)
    with tempfile.TemporaryDirectory() as tmp:
        for page in sorted({r.page for r in runs}):
            prefix = Path(tmp) / f"p{page}"
            try:
                run(["pdftoppm", "-r", str(DPI), "-f", str(page), "-l", str(page), "-png", "-singlefile",
                     str(path), str(prefix)], timeout=120)
                raster = Image.open(f"{prefix}.png").convert("L")
            except (ToolError, OSError):
                continue
            for r in (x for x in runs if x.page == page):
                box = (max(0, int(r.left * SCALE) - PAD), max(0, int(r.top * SCALE) - PAD),
                       min(raster.width, int((r.left + r.width) * SCALE) + PAD),
                       min(raster.height, int((r.top + r.height) * SCALE) + PAD))
                if box[2] - box[0] < 10 or box[3] - box[1] < 10:
                    continue
                try:
                    text, confidence, psm = ocr_line(raster.crop(box))
                except ToolError:
                    continue
                out.append({"split": split, "doc": path.name, "page": page, "scheme": scheme,
                            "legacy": r.text.strip(), "ocr": text, "ocr_confidence": round(confidence, 1), "psm": psm,
                            "left": r.left, "top": r.top, "width": r.width, "height": r.height})
    return out


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--text", type=Path, required=True, help="textshare.py output")
    parser.add_argument("--scheme", required=True)
    parser.add_argument("--docs", type=int, default=120, help="documents to draw")
    parser.add_argument("--per-doc", type=int, default=40, help="runs to read per document")
    parser.add_argument("--seed", type=int, default=0)
    parser.add_argument("--out", type=Path, required=True)
    parser.add_argument("--workers", type=int)
    args = parser.parse_args()
    os.environ.setdefault("OMP_THREAD_LIMIT", "1")  # one Tesseract thread each; parallelism is per document

    with args.text.open(newline="", encoding="utf-8") as handle:
        candidates = [Path(r["path"]) for r in csv.DictReader(handle)
                      if int(r.get(f"legacy_{args.scheme}") or 0) >= 200 and not r["error"]]
    # Stored names are content hashes, so a file held twice is drawn once.
    unique = list({p.name: p for p in candidates}.values())
    rng = random.Random(args.seed)
    chosen = rng.sample(unique, min(args.docs, len(unique)))
    rows = [row for batch in parallel(chosen, lambda p: pairs_for(p, args.scheme, args.per_doc, args.seed),
                                      args.workers) for row in batch]
    write_csv(args.out, rows, FIELDS)
    learn = sum(1 for r in rows if r["split"] == "learn")
    print(f"{len(rows)} pairs from {len(chosen)} documents ({learn} learn, {len(rows) - learn} evaluate) -> {args.out}")


if __name__ == "__main__":
    main()
