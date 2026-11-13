"""Certify repaired text: accept a converted line only where the bound allows.

    python certify.py --audit data/audit --alphas 0.05 0.10 0.20

Reads `items.csv` (each line's verification score: its conversion's
disagreement with an OCR reading of the same crop) and `judgements.csv` (a
person's verdict from review.html). "Can't tell" is excluded, never coerced.

Guarantee, as in research/conformal.py: P(line auto-accepted AND wrong) <= alpha
for a line drawn exchangeably from the same population as the calibration
lines. Calibration and test are split by *document*, because lines from one
document share a font, a scan and a typist, and a line-level split would test
on documents the threshold was fitted to.

Also reported: the error rate with no selection (accept everything), and a
cluster bootstrap over documents for the realized test risk, since a binomial
interval over lines would overstate what a few dozen documents can show.
"""
from __future__ import annotations

import argparse
import csv
import hashlib
import random
import sys
from pathlib import Path

from scipy.stats import beta as beta_dist

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from conformal import calibrate, label_leakage, realized_risk  # noqa: E402

CALIBRATION_SHARE = 0.6


def split_of(doc: str) -> str:
    """Calibration or test, fixed by the document's hash under its own salt."""
    bucket = int(hashlib.sha256(f"certify:{doc}".encode()).hexdigest()[:8], 16) / 0xFFFFFFFF
    return "calibration" if bucket < CALIBRATION_SHARE else "test"


def clopper_pearson(k: int, n: int, level: float = 0.95) -> tuple[float, float]:
    if n == 0:
        return 0.0, 1.0
    a = (1 - level) / 2
    low = 0.0 if k == 0 else float(beta_dist.ppf(a, k, n - k + 1))
    high = 1.0 if k == n else float(beta_dist.ppf(1 - a, k + 1, n - k))
    return low, high


def cluster_bootstrap(rows: list[dict[str, object]], threshold: float | None, draws: int = 2000,
                      seed: int = 0) -> tuple[float, float]:
    """95% interval for P(accepted AND wrong), resampling whole documents."""
    if threshold is None or not rows:
        return 0.0, 0.0
    by_doc: dict[str, list[dict[str, object]]] = {}
    for row in rows:
        by_doc.setdefault(str(row["doc"]), []).append(row)
    docs = list(by_doc)
    rng = random.Random(seed)
    stats = []
    for _ in range(draws):
        sample = [r for d in (rng.choice(docs) for _ in docs) for r in by_doc[d]]
        wrong = sum(1 for r in sample if r["score"] <= threshold and not r["correct"])
        stats.append(wrong / len(sample))
    stats.sort()
    return stats[int(0.025 * draws)], stats[int(0.975 * draws) - 1]


def load(audit: Path, judgements: Path | None = None) -> tuple[dict[str, dict[str, str]], dict[str, str]]:
    with (audit / "items.csv").open(newline="", encoding="utf-8") as handle:
        items = {row["item"]: row for row in csv.DictReader(handle)}
    with (judgements or audit / "judgements.csv").open(newline="", encoding="utf-8") as handle:
        verdicts = {row["item"]: row["verdict"] for row in csv.DictReader(handle)}
    return items, verdicts


def summarise(items: dict[str, dict[str, str]], verdicts: dict[str, str], alphas: list[float]) -> dict[str, object]:
    rows = [{"doc": items[i]["doc"], "scheme": items[i]["scheme"], "score": float(items[i]["verification"]),
             "correct": verdict == "yes"}
            for i, verdict in verdicts.items() if i in items and verdict in ("yes", "no")]
    calibration = [r for r in rows if split_of(str(r["doc"])) == "calibration"]
    test = [r for r in rows if split_of(str(r["doc"])) == "test"]
    base_wrong = sum(1 for r in test if not r["correct"])
    out: dict[str, object] = {
        "items": len(items),
        "judged": len(verdicts),
        "cant_tell": sum(1 for v in verdicts.values() if v == "unsure"),
        "labelled": len(rows),
        "wrong": sum(1 for r in rows if not r["correct"]),
        "documents": len({r["doc"] for r in rows}),
        "calibration": {"lines": len(calibration), "documents": len({r["doc"] for r in calibration})},
        "test": {"lines": len(test), "documents": len({r["doc"] for r in test})},
        "label_leakage": label_leakage([(float(r["score"]), bool(r["correct"])) for r in rows]),
        "accept_everything": {"test_error": round(base_wrong / max(len(test), 1), 4),
                              "ci95": [round(x, 4) for x in clopper_pearson(base_wrong, len(test))]},
        "levels": [],
    }
    for alpha in alphas:
        fit = calibrate([(float(r["score"]), bool(r["correct"])) for r in calibration], alpha)
        level: dict[str, object] = {"alpha": alpha, "calibration_lines": fit.n, "minimum_lines": fit.minimum_n,
                                    "threshold": fit.threshold}
        if fit.threshold is not None:
            held = realized_risk([(float(r["score"]), bool(r["correct"])) for r in test], fit.threshold)
            k = round(held["false_acceptance_rate"] * held["n"])
            level.update(
                calibration_acceptance=fit.acceptance_rate,
                test_accepted=held["accepted"], test_lines=held["n"],
                false_acceptance=held["false_acceptance_rate"],
                false_acceptance_ci95_lines=[round(x, 4) for x in clopper_pearson(k, held["n"])],
                false_acceptance_ci95_documents=[round(x, 4) for x in cluster_bootstrap(test, fit.threshold)],
                error_among_accepted=held["error_among_accepted"],
            )
        out["levels"].append(level)  # type: ignore[union-attr]
    return out


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--audit", type=Path, default=Path("data/audit"))
    parser.add_argument("--judgements", type=Path, help="defaults to <audit>/judgements.csv")
    parser.add_argument("--alphas", type=float, nargs="+", default=[0.05, 0.10, 0.20])
    args = parser.parse_args()
    result = summarise(*load(args.audit, args.judgements), args.alphas)
    print(f"judged {result['judged']} of {result['items']} items; {result['cant_tell']} can't-tell excluded")
    print(f"labelled lines {result['labelled']}: {result['wrong']} wrong across {result['documents']} documents")
    print(f"calibration {result['calibration']}; test {result['test']}")
    print(f"label leakage check: {result['label_leakage']}")
    print(f"accept everything: {result['accept_everything']}")
    for level in result["levels"]:  # type: ignore[union-attr]
        print(level)

if __name__ == "__main__":
    main()
