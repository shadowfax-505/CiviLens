"""How well the byte-scheme detector agrees with font names it never saw.

    python validate_detector.py --text data/corpus_text.csv --out data/detector_validation.csv

`families.SIGNATURES` was learned from the documents in
signature_learning_draw.txt and its guards chosen on those in
detector_tuning_draw.txt. This scores `families.scheme_of` on every run in
every document in *neither* list where the font name gives the answer:

- a run in a named legacy family is labelled with that family's scheme;
- a run in any other named, non-legacy font is labelled "none" — which
  includes Unicode Bengali and English, the text the detector must leave alone.

Unnamed fonts are excluded: they are what the detector is for, and have no
label. Counts are weighted by visible characters and also given per run.
"""
from __future__ import annotations

import argparse
import csv
import random
from collections import Counter
from pathlib import Path

from families import SCHEMES, is_unnamed, legacy_family, scheme_of
from pdftools import ToolError, text_runs
from sources import parallel, write_csv

LEARNING_SEED = 0
LEARNING_DRAW = 500


DRAW_FILE = Path(__file__).parent / "signature_learning_draw.txt"


def learning_draw(rows: list[dict[str, str]]) -> set[str]:
    """The documents the signatures were learned from.

    Read from the file frozen when they were learned, because re-running
    `textshare.py` after a family is added changes which documents are
    eligible, and a re-drawn sample would quietly overlap the held-out set.
    The file lists stored names, which are content hashes, so it identifies
    the same documents on any machine. Re-drawn only if that file is missing.
    """
    if DRAW_FILE.exists():
        names = {line for line in DRAW_FILE.read_text(encoding="utf-8").splitlines() if line}
        return {r["path"] for r in rows if Path(r["path"]).name in names}
    eligible = [r for r in rows if int(r["legacy_chars"] or 0) >= 50 or int(r["chars"] or 0) >= 500]
    random.seed(LEARNING_SEED)
    return {r["path"] for r in random.sample(eligible, min(LEARNING_DRAW, len(eligible)))}


def score(path: str) -> Counter:
    tally: Counter = Counter()
    try:
        runs = text_runs(Path(path))
    except ToolError:
        return tally
    for run in runs:
        if is_unnamed(run.family):
            continue
        truth = legacy_family(run.family) or "none"
        guess = scheme_of(run.text) or "none"
        weight = sum(1 for c in run.text if not c.isspace())
        tally[(truth, guess, "chars")] += weight
        tally[(truth, guess, "runs")] += 1
    return tally


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--text", type=Path, required=True, help="textshare.py output for the corpus")
    parser.add_argument("--out", type=Path, required=True)
    args = parser.parse_args()
    with args.text.open(newline="", encoding="utf-8") as handle:
        rows = list(csv.DictReader(handle))
    learned = learning_draw(rows)
    tuning_file = Path(__file__).parent / "detector_tuning_draw.txt"
    tuned = set(tuning_file.read_text(encoding="utf-8").split()) if tuning_file.exists() else set()
    held_out = [r["path"] for r in rows
                if r["path"] not in learned and Path(r["path"]).name not in tuned and not r["error"]]
    total: Counter = Counter()
    for tally in parallel(held_out, score):
        total.update(tally)
    labels = SCHEMES + ["none"]
    out = [{"truth": t, "predicted": p, "chars": total[(t, p, "chars")], "runs": total[(t, p, "runs")]}
           for t in labels for p in labels]
    write_csv(args.out, out, ["truth", "predicted", "chars", "runs"])
    print(f"held-out documents: {len(held_out)}")
    for unit in ("chars", "runs"):
        print(f"-- weighted by {unit}")
        for scheme in SCHEMES:
            tp = total[(scheme, scheme, unit)]
            predicted = sum(total[(t, scheme, unit)] for t in labels)
            actual = sum(total[(scheme, p, unit)] for p in labels)
            precision = tp / predicted if predicted else float("nan")
            recall = tp / actual if actual else float("nan")
            print(f"{scheme:12s} precision {precision:.4f}  recall {recall:.4f}  (n={actual})")
        none_total = sum(total[("none", p, unit)] for p in labels)
        false_alarm = sum(total[("none", s, unit)] for s in SCHEMES)
        print(f"{'none':12s} false-alarm rate {false_alarm / none_total:.6f}  (n={none_total})")


if __name__ == "__main__":
    main()
