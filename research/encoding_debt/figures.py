"""Draw the paper's figures from data/results.json and the per-word search CSV.

    python figures.py          # after make_figures.py; writes data/figures/*.png

Static figures for print, so light mode only. Colours are the first three
categorical slots of the dataviz reference palette, validated all-pairs on the
light surface (worst CVD separation 9.2, normal-vision 24.0); the third sits
below 3:1 contrast, so every segment it colours carries a direct label, and
results.md is the table view. One series per chart gets one colour and no
legend; the title names it.
"""
from __future__ import annotations

import csv
import json
import math
from pathlib import Path

import matplotlib

matplotlib.use("Agg")
import matplotlib.pyplot as plt  # noqa: E402

DATA = Path(__file__).parent / "data"
OUT = DATA / "figures"
SURFACE, INK, INK_2, GRID = "#fcfcfb", "#0b0b0b", "#52514e", "#e4e3df"
SERIES = ["#2a78d6", "#eb6834", "#1baf7a"]   # blue, orange, aqua — fixed order
NEUTRAL = "#8f8e89"                          # a baseline, not a series

plt.rcParams.update({
    "figure.facecolor": SURFACE, "axes.facecolor": SURFACE, "savefig.facecolor": SURFACE,
    "axes.edgecolor": GRID, "axes.labelcolor": INK_2, "xtick.color": INK_2, "ytick.color": INK_2,
    "text.color": INK, "font.size": 10, "axes.titlesize": 11, "axes.titleweight": "bold",
    "axes.titlelocation": "left", "axes.spines.top": False, "axes.spines.right": False,
    "axes.grid": True, "grid.color": GRID, "grid.linewidth": 0.8, "axes.axisbelow": True,
})


def title(fig, text: str) -> None:
    """Title on the figure's left edge, so long category labels cannot push it off the canvas."""
    fig.suptitle(text, x=0.0, ha="left", fontsize=11, fontweight="bold", color=INK)


def categorical_axis(ax) -> None:
    ax.tick_params(axis="y", length=0)
    ax.grid(axis="y", visible=False)


def wilson(k: int, n: int, z: float = 1.96) -> tuple[float, float]:
    if n == 0:
        return 0.0, 0.0
    p = k / n
    centre = (p + z * z / (2 * n)) / (1 + z * z / n)
    half = z * math.sqrt(p * (1 - p) / n + z * z / (4 * n * n)) / (1 + z * z / n)
    return max(0.0, centre - half), min(1.0, centre + half)


def composition(results: dict, path: Path) -> None:
    """Of each population's Bengali PDFs: legacy only, mixed, Unicode only."""
    rows = [("Supreme Court judgments\n(CivicLens corpus)", results["corpus_prevalence"]["supremecourt-bangladesh"])]
    if "cc_prevalence" in results:
        rows.append(("Government web,\n2 per site (2024–26)", results["cc_prevalence"]["ALL"]))
    if "ccu_prevalence" in results:
        rows.append(("Government web,\nuniform over documents", results["ccu_prevalence"]["ALL"]))
        gazette = results["ccu_prevalence"].get("www.dpp.gov.bd")
        if gazette and gazette["with_bengali"]:
            rows.insert(1, ("Bangladesh Gazette\n(dpp.gov.bd, uniform sample)", gazette))
    parts = [("legacy only", "bengali_legacy_only"), ("mixed", "bengali_mixed"), ("Unicode only", "bengali_unicode_only")]
    fig, ax = plt.subplots(figsize=(7.2, 1.3 + 0.75 * len(rows)), layout="constrained")
    for y, (label, c) in enumerate(reversed(rows)):
        total = c["with_bengali"]
        left = 0.0
        for colour, (name, key) in zip(SERIES, parts):
            share = c[key] / total
            ax.barh(y, share, left=left, height=0.55, color=colour, edgecolor=SURFACE, linewidth=2)
            if share >= 0.06:
                ax.text(left + share / 2, y, f"{share:.0%}", ha="center", va="center", fontsize=9,
                        color="white" if colour != SERIES[2] else INK)
            left += share
        ax.text(1.01, y, f"n = {total}", va="center", fontsize=8.5, color=INK_2)
    ax.set_yticks(range(len(rows)), [r[0] for r in reversed(rows)])
    ax.set_xlim(0, 1)
    ax.xaxis.set_major_formatter(matplotlib.ticker.PercentFormatter(1.0))
    categorical_axis(ax)
    handles = [matplotlib.patches.Patch(color=c, label=n) for c, (n, _) in zip(SERIES, parts)]
    fig.legend(handles=handles, ncol=3, frameon=False, loc="outside lower left", fontsize=9)
    ax.set_xlabel("share of the PDFs that contain Bengali")
    title(fig, "How the Bengali in government PDFs is encoded")
    fig.savefig(path, dpi=200)
    plt.close(fig)


def persistence(results: dict, path: Path, minimum: int = 10) -> None:
    """Share of Bengali PDFs carrying legacy text, by the year the PDF was created."""
    fig, ax = plt.subplots(figsize=(7.2, 3.8), layout="constrained")
    series = []
    if "cc_by_year" in results:
        series.append(("Government web (2 per site)", results["cc_by_year"], SERIES[0]))
    series.append(("Supreme Court judgments", results["corpus_by_year"], SERIES[1]))
    for name, years, colour in series:
        points = [(int(y), v["with_legacy_bengali"], v["with_bengali"]) for y, v in years.items()
                  if y.isdigit() and v["with_bengali"] >= minimum]
        if not points:
            continue
        xs = [p[0] for p in points]
        ys = [p[1] / p[2] for p in points]
        lows, highs = zip(*(wilson(p[1], p[2]) for p in points))
        ax.fill_between(xs, lows, highs, color=colour, alpha=0.15, linewidth=0)
        ax.plot(xs, ys, color=colour, linewidth=2, marker="o", markersize=5,
                markeredgecolor=SURFACE, markeredgewidth=1.5)
        ax.annotate(name, (xs[-1], ys[-1]), xytext=(6, 0), textcoords="offset points", va="center",
                    fontsize=9, color=INK)
    ax.set_ylim(0, 1)
    ax.yaxis.set_major_formatter(matplotlib.ticker.PercentFormatter(1.0))
    ax.set_xlabel(f"creation year from PDF metadata · shaded: 95% interval · years with ≥ {minimum} Bengali PDFs")
    ax.set_ylabel("Bengali PDFs with legacy text")
    title(fig, "Legacy encoding by the year a PDF was created")
    ax.margins(x=0.12)
    fig.savefig(path, dpi=200)
    plt.close(fig)


def repair(results: dict, path: Path) -> None:
    """Character error against what the page shows, documented converter vs learned tables."""
    bijoy = results["repair_bijoy"]["evaluation"]
    adarsha = results["repair_adarshalipi"]["evaluation"]
    bars = [("Bijoy: bijoy2unicode (documented, open)", bijoy["cer_reference_vs_page_ocr"], NEUTRAL),
            ("Bijoy: table learned from the pages", bijoy["cer_vs_page_ocr"], SERIES[0]),
            ("AdarshaLipi: table learned from the pages\n(no public table exists)", adarsha["cer_vs_page_ocr"], SERIES[0])]
    fig, ax = plt.subplots(figsize=(7.2, 2.9), layout="constrained")
    for y, (label, value, colour) in enumerate(reversed(bars)):
        ax.barh(y, value, height=0.5, color=colour)
        ax.text(value + 0.001, y, f"{value:.1%}", va="center", fontsize=9, color=INK)
    ax.set_yticks(range(len(bars)), [b[0] for b in reversed(bars)])
    ax.xaxis.set_major_formatter(matplotlib.ticker.PercentFormatter(1.0, decimals=0))
    ax.set_xlim(0, max(b[1] for b in bars) * 1.25)
    categorical_axis(ax)
    ax.set_xlabel("character error rate vs. OCR of the page (held-out)")
    title(fig, "Learned conversion tables against the only open converter")
    fig.savefig(path, dpi=200)
    plt.close(fig)


def llm(results: dict, path: Path) -> None:
    """Balanced accuracy of a local model asked whether a passage contains a word."""
    labels = {"legacy_raw": "legacy text, as extracted today",
              "legacy_repaired": "the same passages, repaired",
              "unicode_control": "text that was Unicode already"}
    data = results["llm_reading"]
    rows = [(labels[k], data[k]) for k in labels if k in data]
    fig, ax = plt.subplots(figsize=(7.2, 2.7), layout="constrained")
    for y, (label, d) in enumerate(reversed(rows)):
        low, high = d["document_bootstrap_ci95"]
        ax.plot([low, high], [y, y], color=SERIES[0], linewidth=2, solid_capstyle="round")
        ax.plot(d["balanced_accuracy"], y, "o", color=SERIES[0], markersize=8, markeredgecolor=SURFACE, markeredgewidth=1.5)
        ax.text(high + 0.012, y, f"{d['balanced_accuracy']:.2f}", va="center", fontsize=9, color=INK)
    ax.axvline(0.5, color=INK_2, linewidth=1, linestyle=(0, (3, 3)))
    ax.text(0.505, len(rows) - 0.45, "chance", fontsize=8.5, color=INK_2)
    ax.set_yticks(range(len(rows)), [r[0] for r in reversed(rows)])
    ax.set_xlim(0.4, 1.0)
    ax.set_ylim(-0.6, len(rows) - 0.3)
    categorical_axis(ax)
    ax.set_xlabel("balanced accuracy, with 95% intervals")
    title(fig, "Can a language model find a given Bengali word in the text layer?")
    fig.savefig(path, dpi=200)
    plt.close(fig)


def search(path: Path, words: Path) -> None:
    """Per query word: share of documents containing it that a Unicode search finds today."""
    with words.open(newline="", encoding="utf-8") as handle:
        recall = [float(r["recall_now"]) for r in csv.DictReader(handle)]
    fig, ax = plt.subplots(figsize=(7.2, 3.2), layout="constrained")
    ax.hist(recall, bins=[i / 20 for i in range(21)], color=SERIES[0], edgecolor=SURFACE, linewidth=2)
    mean = sum(recall) / len(recall)
    ax.axvline(mean, color=INK_2, linewidth=1, linestyle=(0, (3, 3)))
    ax.text(mean + 0.01, ax.get_ylim()[1] * 0.92, f"mean {mean:.2f}", fontsize=8.5, color=INK_2)
    ax.xaxis.set_major_formatter(matplotlib.ticker.PercentFormatter(1.0))
    ax.set_xlabel("share of the documents containing a word that a Unicode search finds today")
    ax.set_ylabel("query words")
    title(fig, f"Keyword search recall for {len(recall):,} common Bengali words")
    fig.savefig(path, dpi=200)
    plt.close(fig)


def main() -> None:
    results = json.loads((DATA / "results.json").read_text(encoding="utf-8"))
    OUT.mkdir(parents=True, exist_ok=True)
    made = []
    for name, draw in (("composition", lambda p: composition(results, p)),
                       ("persistence", lambda p: persistence(results, p)),
                       ("repair", lambda p: repair(results, p)),
                       ("llm_reading", lambda p: llm(results, p)),
                       ("search_recall", lambda p: search(p, DATA / "search_recall.csv"))):
        try:
            draw(OUT / f"{name}.png")
            made.append(name)
        except (KeyError, FileNotFoundError) as exc:
            print(f"skipped {name}: missing {exc}")
    print(f"{len(made)} figures -> {OUT}")


if __name__ == "__main__":
    main()
