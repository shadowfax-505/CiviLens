"""How much of the corpus a Unicode Bengali keyword search can find, before and after repair.

    python consequences/search_recall.py --text data/corpus_text.csv \
        --tables data/table_bijoy.json data/table_adarshalipi.json --out data/search_recall.csv

Queries are Bengali words that occur in Unicode somewhere in the corpus at
least --min-docs times: real words, spelled as a searcher would type them. For
each, the documents containing it after repair are compared with those a
search finds today, in their Unicode text alone.

Conservative by construction: a conversion error can only stop a legacy
occurrence from matching, which makes today's search look better than it is,
never worse. A document counts once per query however often the word occurs.
"""
from __future__ import annotations

import argparse
import csv
import re
import sys
from collections import defaultdict
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from convert import ConversionError, convert  # noqa: E402
from families import legacy_family, scheme_of  # noqa: E402
from pdftools import ToolError, text_runs  # noqa: E402
from sources import parallel, write_csv  # noqa: E402

WORD = re.compile(r"[ঀ-৿]{2,}")


def words_of(item: tuple[Path, str, dict[str, Path]]) -> tuple[str, str, set[str], set[str]]:
    """(document, group, words in its Unicode text, words only its repaired legacy text adds)."""
    path, group, tables = item
    unicode_words: set[str] = set()
    repaired_words: set[str] = set()
    try:
        runs = text_runs(path)
    except ToolError:
        return path.name, group, unicode_words, repaired_words
    for run in runs:
        scheme = legacy_family(run.family) or scheme_of(run.text)
        if scheme is None:
            unicode_words.update(WORD.findall(run.text))
        elif scheme in tables:
            try:
                repaired_words.update(WORD.findall(convert(run.text, scheme, tables[scheme])))
            except ConversionError:
                continue
    return path.name, group, unicode_words, repaired_words


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--text", type=Path, required=True)
    parser.add_argument("--tables", type=Path, nargs="+", required=True)
    parser.add_argument("--min-docs", type=int, default=5)
    parser.add_argument("--out", type=Path, required=True)
    args = parser.parse_args()
    tables = {t.stem.removeprefix("table_"): t for t in args.tables}

    with args.text.open(newline="", encoding="utf-8") as handle:
        docs = {Path(r["path"]).name: (Path(r["path"]), r["group"]) for r in csv.DictReader(handle)
                if not r["error"] and (int(r["bengali_unicode"] or 0) + int(r["legacy_chars"] or 0)
                                       + int(r["legacy_inferred_chars"] or 0)) > 0}
    found_now: dict[str, set[str]] = defaultdict(set)
    found_after: dict[str, set[str]] = defaultdict(set)
    for name, group, now, added in parallel([(p, g, tables) for p, g in docs.values()], words_of):
        for word in now:
            found_now[word].add(name)
            found_after[word].add(name)
        for word in added:
            found_after[word].add(name)

    queries = [w for w, d in found_now.items() if len(d) >= args.min_docs]
    rows = [{"word": w, "found_now": len(found_now[w]), "found_after_repair": len(found_after[w]),
             "recall_now": round(len(found_now[w]) / len(found_after[w]), 4)} for w in sorted(queries)]
    write_csv(args.out, rows, ["word", "found_now", "found_after_repair", "recall_now"])
    macro = sum(r["recall_now"] for r in rows) / max(len(rows), 1)
    micro = sum(r["found_now"] for r in rows) / max(sum(r["found_after_repair"] for r in rows), 1)
    hidden = sum(1 for r in rows if r["recall_now"] < 0.5)
    print(f"{len(docs)} documents with Bengali text; {len(rows)} query words (in Unicode in >= {args.min_docs} documents)")
    print(f"recall of a Unicode keyword search today: mean over words {macro:.3f}, over all word-document matches {micro:.3f}")
    print(f"words for which today's search finds under half the documents that contain them: {hidden} ({hidden / max(len(rows), 1):.3f})")


if __name__ == "__main__":
    main()
