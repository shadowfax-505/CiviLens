"""Regenerate every headline number from the stored outputs, in one place.

    python make_figures.py            # writes data/results.json and data/results.md

Reads whatever the pipeline has produced so far and says what is missing
rather than failing, so it can be run at any stage. No number in the paper or
README should come from anywhere but this file's output.
"""
from __future__ import annotations

import csv
import json
import random
from collections import Counter, defaultdict
from pathlib import Path

DATA = Path(__file__).parent / "data"
# A document "contains Bengali" when it holds at least this many characters of
# it, legacy or Unicode: a stray glyph in a letterhead is not Bengali content.
BENGALI_MIN = 50
TABLE_ROWS = 25  # largest groups shown in results.md; results.json keeps every group
COLUMNS = ["pdfs", "with_text_layer", "legacy_font_declared", "legacy_bytes_named", "legacy_bytes_inferred",
           "unicode_bengali_chars", "with_bengali", "bengali_legacy_only", "bengali_mixed", "bengali_unicode_only",
           "found_only_by_bytes", "bengali_unassigned_chars", "broken_unicode_map"]
# A document's Unicode Bengali counts as coming from a broken map when at least
# this many characters, and this share of it, are code points Unicode never
# assigned. A lower bound: a shifted map also emits assigned, wrong letters.
BROKEN_MIN, BROKEN_SHARE = 20, 0.02


def rows(path: Path) -> list[dict[str, str]] | None:
    if not path.exists():
        return None
    with path.open(newline="", encoding="utf-8") as handle:
        return list(csv.DictReader(handle))


def n(row: dict[str, str], key: str) -> int:
    return int(row.get(key) or 0)


def prevalence(fonts: list[dict[str, str]], text: list[dict[str, str]]) -> dict[str, dict[str, object]]:
    """Per group and overall: denominators, document-level split, volume share."""
    by_path = {r["path"]: r for r in text}
    groups: dict[str, Counter] = defaultdict(Counter)
    for f in fonts:
        t = by_path.get(f["path"], {})
        for g in (f["group"], "ALL"):
            c = groups[g]
            c["pdfs"] += 1
            c["with_text_layer"] += n(f, "fonts") > 0
            c["legacy_font_declared"] += n(f, "legacy")
            named, inferred, uni = n(t, "legacy_chars"), n(t, "legacy_inferred_chars"), n(t, "bengali_unicode")
            c["legacy_bytes_named"] += named
            c["legacy_bytes_inferred"] += inferred
            c["unicode_bengali_chars"] += uni
            unassigned = n(t, "bengali_unassigned")
            c["bengali_unassigned_chars"] += unassigned
            c["broken_unicode_map"] += unassigned >= BROKEN_MIN and unassigned >= BROKEN_SHARE * max(uni, 1)
            legacy = named + inferred
            if legacy >= BENGALI_MIN or uni >= BENGALI_MIN:
                c["with_bengali"] += 1
                if uni < BENGALI_MIN:
                    c["bengali_legacy_only"] += 1
                elif legacy >= BENGALI_MIN:
                    c["bengali_mixed"] += 1
                else:
                    c["bengali_unicode_only"] += 1
                if named < BENGALI_MIN <= legacy:
                    c["found_only_by_bytes"] += 1
    out = {}
    for g, c in groups.items():
        legacy = c["legacy_bytes_named"] + c["legacy_bytes_inferred"]
        total = legacy + c["unicode_bengali_chars"]
        named_total = c["legacy_bytes_named"] + c["unicode_bengali_chars"]
        out[g] = {**dict.fromkeys(COLUMNS, 0), **c,
                  "legacy_share_of_bengali_text": round(legacy / total, 4) if total else None,
                  "legacy_share_named_only": round(c["legacy_bytes_named"] / named_total, 4) if named_total else None}
    return out


def hosts(prevalence_by_group: dict[str, dict[str, object]]) -> dict[str, int]:
    """How many groups (publishers or hosts) serve Bengali, and how many any legacy Bengali."""
    groups = {g: c for g, c in prevalence_by_group.items() if g != "ALL"}
    bengali = [c for c in groups.values() if c["with_bengali"]]
    return {"groups": len(groups), "serving_bengali": len(bengali),
            "serving_legacy_bengali": sum(1 for c in bengali if c["bengali_legacy_only"] or c["bengali_mixed"])}


def by_year(fonts: list[dict[str, str]], text: list[dict[str, str]], group: str) -> dict[str, dict[str, int]]:
    """Per PDF creation year: PDFs, those with Bengali, and those whose Bengali includes legacy text.

    Persistence is judged among PDFs that contain Bengali: a year of English
    PDFs is not evidence that the debt has shrunk.
    """
    by_path = {r["path"]: r for r in text}
    years: dict[str, Counter] = defaultdict(Counter)
    for f in fonts:
        if group != "ALL" and f["group"] != group:
            continue
        t = by_path.get(f["path"], {})
        legacy = n(t, "legacy_chars") + n(t, "legacy_inferred_chars")
        uni = n(t, "bengali_unicode")
        y = years[f["created_year"] or "unknown"]
        y["pdfs"] += 1
        if legacy >= BENGALI_MIN or uni >= BENGALI_MIN:
            y["with_bengali"] += 1
            y["with_legacy_bengali"] += legacy >= BENGALI_MIN
    return {year: {**dict.fromkeys(["pdfs", "with_bengali", "with_legacy_bengali"], 0), **c}
            for year, c in sorted(years.items())}


def weighted_population(directory: Path, text: list[dict[str, str]], uniform: bool = False,
                        draws: int = 1000, seed: int = 0) -> dict[str, object] | None:
    """Document-level estimates for the whole-capture population, not just the sample.

    A per-host sample draws a few documents per host, so every host counts
    equally; a document on a host with N whole captures, of which n were
    fetched, stands for N/n documents (Horvitz-Thompson). Large hosts then
    carry most of the weight, which makes the interval wide. A uniform sample
    (`cc_index.py --uniform`) gives every document the same inclusion
    probability, so every weight is 1. Either way the interval resamples
    hosts, because documents on one host share a publisher's habits.
    """
    records, sample, manifest = (rows(directory / f) for f in ("records.csv", "sample.csv", "manifest.csv"))
    if not (records and sample and manifest):
        return None
    whole = Counter(r["host"] for r in records if r["truncated"] == "0")
    held = {r["digest"]: r for r in manifest if r["status"] == "ok"}
    fetched = Counter(held[r["digest"]]["host"] for r in sample if r["digest"] in held)
    by_name = {Path(r["path"]).name: r for r in text}
    units: dict[str, list[tuple[float, bool, bool, int, int]]] = defaultdict(list)
    for digest, m in held.items():
        t = by_name.get(Path(m["path"]).name)
        if t is None or not fetched[m["host"]]:
            continue
        legacy = n(t, "legacy_chars") + n(t, "legacy_inferred_chars")
        uni = n(t, "bengali_unicode")
        weight = 1.0 if uniform else whole[m["host"]] / fetched[m["host"]]
        units[m["host"]].append((weight, legacy >= BENGALI_MIN or uni >= BENGALI_MIN,
                                 legacy >= BENGALI_MIN, legacy, uni))

    def estimate(groups: list[list[tuple[float, bool, bool, int, int]]]) -> tuple[float, float, float]:
        flat = [u for g in groups for u in g]
        bengali = sum(w for w, b, _, _, _ in flat if b)
        with_legacy = sum(w for w, b, l, _, _ in flat if b and l)
        volume = sum(w * (legacy + uni) for w, _, _, legacy, uni in flat)
        return (with_legacy / bengali if bengali else float("nan"),
                sum(w * legacy for w, _, _, legacy, _ in flat) / volume if volume else float("nan"), bengali)

    hosts = sorted(units)
    share, volume, bengali = estimate([units[h] for h in hosts])
    rng = random.Random(seed)
    boots = [estimate([units[rng.choice(hosts)] for _ in hosts]) for _ in range(draws)]
    shares = sorted(b[0] for b in boots)
    volumes = sorted(b[1] for b in boots)
    population = sum(whole.values())
    documents = sum(len(g) for g in units.values())
    return {"design": "uniform over documents" if uniform else "per host, Horvitz-Thompson weighted",
            "hosts": len(hosts), "documents": documents,
            "estimated_bengali_pdfs_in_population": round(bengali * population / documents) if uniform else round(bengali),
            "share_of_bengali_pdfs_with_legacy": round(share, 4),
            "share_ci95_host_bootstrap": [round(shares[int(0.025 * draws)], 4), round(shares[int(0.975 * draws) - 1], 4)],
            "legacy_share_of_bengali_text": round(volume, 4),
            "volume_ci95_host_bootstrap": [round(volumes[int(0.025 * draws)], 4), round(volumes[int(0.975 * draws) - 1], 4)]}


def concentration(fonts: list[dict[str, str]], text: list[dict[str, str]], top: int = 10) -> dict[str, object]:
    """Which groups (hosts) hold the legacy Bengali, and each one's record by creation year.

    A handful of publishers can drive a government-wide figure; naming them is
    both the explanation for a wide interval and the finding a reader can act on.
    """
    year_of = {f["path"]: f["created_year"] or "unknown" for f in fonts}
    groups: dict[str, dict[str, object]] = defaultdict(lambda: {"bengali": 0, "legacy": 0, "by_year": defaultdict(lambda: [0, 0])})
    for t in text:
        legacy = n(t, "legacy_chars") + n(t, "legacy_inferred_chars")
        uni = n(t, "bengali_unicode")
        if legacy >= BENGALI_MIN or uni >= BENGALI_MIN:
            g = groups[t["group"]]
            g["bengali"] += 1  # type: ignore[operator]
            g["legacy"] += legacy >= BENGALI_MIN  # type: ignore[operator]
            cell = g["by_year"][year_of.get(t["path"], "unknown")]  # type: ignore[index]
            cell[0] += 1
            cell[1] += legacy >= BENGALI_MIN
    ranked = sorted(groups.items(), key=lambda kv: -kv[1]["legacy"])  # type: ignore[operator]
    total_legacy = sum(g["legacy"] for g in groups.values())  # type: ignore[misc]
    total_bengali = sum(g["bengali"] for g in groups.values())  # type: ignore[misc]
    head = ranked[:5]
    head_legacy = sum(g["legacy"] for _, g in head)  # type: ignore[misc]
    head_bengali = sum(g["bengali"] for _, g in head)  # type: ignore[misc]
    return {
        "top": [{"group": name, "bengali": g["bengali"], "legacy": g["legacy"],
                 "by_year": {y: {"bengali": c[0], "legacy": c[1]} for y, c in sorted(g["by_year"].items())}}  # type: ignore[union-attr]
                for name, g in ranked[:top]],
        "top5_share_of_legacy": round(head_legacy / total_legacy, 4) if total_legacy else None,
        "legacy_share_outside_top5": round((total_legacy - head_legacy) / (total_bengali - head_bengali), 4)
        if total_bengali > head_bengali else None,
    }


def detector(path: Path) -> dict[str, object] | None:
    table = rows(path)
    if table is None:
        return None
    cell = {(r["truth"], r["predicted"]): int(r["chars"]) for r in table}
    labels = sorted({t for t, _ in cell})
    schemes = [s for s in labels if s != "none"]
    out: dict[str, object] = {}
    for s in schemes:
        tp = cell.get((s, s), 0)
        predicted = sum(cell.get((t, s), 0) for t in labels)
        actual = sum(cell.get((s, p), 0) for p in labels)
        out[s] = {"precision": round(tp / predicted, 4) if predicted else None,
                  "recall": round(tp / actual, 4) if actual else None, "chars": actual}
    none = sum(cell.get(("none", p), 0) for p in labels)
    out["false_alarm_rate"] = round(sum(cell.get(("none", s), 0) for s in schemes) / none, 6) if none else None
    return out


def search(path: Path) -> dict[str, object] | None:
    table = rows(path)
    if not table:
        return None
    now = sum(n(r, "found_now") for r in table)
    after = sum(n(r, "found_after_repair") for r in table)
    return {"query_words": len(table),
            "recall_mean_over_words": round(sum(float(r["recall_now"]) for r in table) / len(table), 4),
            "recall_over_matches": round(now / after, 4) if after else None,
            "words_found_in_under_half": sum(1 for r in table if float(r["recall_now"]) < 0.5)}


def main() -> None:
    results: dict[str, object] = {"bengali_min_chars": BENGALI_MIN, "missing": []}
    missing: list[str] = results["missing"]  # type: ignore[assignment]

    for name in ("corpus", "cc2026", "cc", "ccu"):
        fonts, text = rows(DATA / f"{name}_fonts.csv"), rows(DATA / f"{name}_text.csv")
        if fonts is None or text is None:
            missing.append(f"{name}: run fonts.py and textshare.py")
            continue
        results[f"{name}_prevalence"] = prevalence(fonts, text)
        results[f"{name}_groups"] = hosts(results[f"{name}_prevalence"])  # type: ignore[arg-type]
        if name != "corpus":
            results[f"{name}_concentration"] = concentration(fonts, text)
        if name != "corpus" and (weighted := weighted_population(DATA / name, text, uniform=name == "ccu")):
            results[f"{name}_population_weighted"] = weighted
        results[f"{name}_by_year"] = by_year(fonts, text, "supremecourt-bangladesh" if name == "corpus" else "ALL")

    results["detector_held_out"] = detector(DATA / "detector_validation.csv") or missing.append("detector validation")
    for scheme in ("bijoy", "adarshalipi"):
        path = DATA / f"table_{scheme}.json"
        if path.exists():
            table = json.loads(path.read_text(encoding="utf-8"))
            results[f"repair_{scheme}"] = {"conventions": {"reph": table["reph"], "two_part_glyphs_in_parts": table["visual_parts"]},
                                           "learned_from": table["learned_from"], "byte_pairs": len(table["segments"]),
                                           "words": len(table["words"]), "evaluation": table.get("evaluation")}
        else:
            missing.append(f"repair {scheme}: run pairs.py and decipher.py")
    results["search_recall"] = search(DATA / "search_recall.csv") or missing.append("search recall")
    llm = rows(DATA / "llm_reading.csv")
    if llm:
        import sys
        sys.path.insert(0, str(Path(__file__).parent / "consequences"))
        from llm_reading import balanced, bootstrap
        results["llm_reading"] = {}
        for condition in ("legacy_raw", "legacy_repaired", "unicode_control"):
            part = [r for r in llm if r["condition"] == condition]
            if part and (score := balanced(part)) is not None:
                results["llm_reading"][condition] = {  # type: ignore[index]
                    "questions": len(part), "documents": len({r["doc"] for r in part}),
                    "balanced_accuracy": round(score, 4),
                    "document_bootstrap_ci95": [round(x, 4) for x in bootstrap(part)],
                    "says_yes": round(sum(r["answer"] == "yes" for r in part) / len(part), 4),
                    "unparseable": sum(r["answer"] == "other" for r in part)}
    else:
        missing.append("llm reading: run consequences/llm_reading.py (needs ollama)")
    pages = rows(DATA / "page_validation.csv")
    if pages:
        judged = [r for r in pages if r["confirms"] != ""]
        results["page_validation"] = {
            "pages_checked": len(judged),
            "confirm_page_shows_bengali_text_layer_does_not": sum(int(r["confirms"]) for r in judged),
            "text_layer_share_of_ocr_bengali_where_not_confirmed": [
                round(int(r["text_layer_bengali"]) / max(int(r["ocr_bengali"]), 1), 3) for r in judged if r["confirms"] == "0"],
        }
    else:
        missing.append("page validation: run validate_pages.py")

    judgements = DATA / "audit" / "judgements.csv"
    if judgements.exists():
        from certify import load, summarise
        results["certification"] = summarise(*load(DATA / "audit"), [0.05, 0.10, 0.20])
    else:
        missing.append("certification: waiting for data/audit/judgements.csv (a person judges review.html)")

    (DATA / "results.json").write_text(json.dumps(results, ensure_ascii=False, indent=1), encoding="utf-8")
    (DATA / "results.md").write_text(markdown(results), encoding="utf-8")
    print(f"-> {DATA / 'results.json'} and {DATA / 'results.md'}")
    try:
        import figures
    except ImportError:
        print("figures skipped: matplotlib is not installed")
    else:
        figures.main()
    for item in missing:
        print(f"missing: {item}")


def markdown(results: dict[str, object]) -> str:
    lines = ["# Encoding debt: results", "", "Generated by `make_figures.py`; do not edit by hand.", ""]
    for key, value in results.items():
        if key.endswith("_prevalence"):
            lines += [f"## {key}", "", "| group | PDFs | with text layer | legacy font declared | with Bengali | "
                      "legacy only | mixed | Unicode only | found only by bytes | legacy share of Bengali text |",
                      "|---|---|---|---|---|---|---|---|---|---|"]
            ordered = sorted(value.items(), key=lambda kv: (kv[0] != "ALL", -kv[1]["pdfs"]))  # type: ignore[union-attr]
            shown = ordered[:TABLE_ROWS + 1]
            for group, c in shown:
                lines.append(f"| {group} | {c['pdfs']} | {c['with_text_layer']} | {c['legacy_font_declared']} | "
                             f"{c['with_bengali']} | {c['bengali_legacy_only']} | {c['bengali_mixed']} | "
                             f"{c['bengali_unicode_only']} | {c['found_only_by_bytes']} | {c['legacy_share_of_bengali_text']} |")
            if len(ordered) > len(shown):
                lines.append(f"\n{len(ordered) - len(shown)} smaller groups omitted here; all are in results.json.")
            lines.append("")
        elif key != "missing":
            lines += [f"## {key}", "", "```json", json.dumps(value, ensure_ascii=False, indent=1), "```", ""]
    if results["missing"]:
        lines += ["## Not yet produced", ""] + [f"- {m}" for m in results["missing"]]  # type: ignore[union-attr]
    return "\n".join(lines) + "\n"


if __name__ == "__main__":
    main()
