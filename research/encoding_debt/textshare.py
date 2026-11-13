"""Measure how much of each document's Bengali a machine can actually read.

    python textshare.py --corpus ../../storage/app/private/ingestion/approved \
        --out data/corpus_text.csv --pages-out data/corpus_pages.csv

Every character is attributed to the font it was set in (`pdftohtml -xml`),
and the font name decides wherever the PDF kept one:

- `bengali_unicode`: characters in the Bengali block — readable by search,
  screen readers and language models.
- `legacy_chars`: visible characters set in a named legacy family — the page
  shows Bengali, the text layer holds Latin bytes. Ground truth by font name.
- `legacy_inferred_chars`: characters in any other font whose bytes match a
  legacy scheme (`families.scheme_of`) — mostly fonts whose names a printer
  driver stripped. Inferred, so reported apart from `legacy_chars`, with the
  fonts it came from in `inferred_families`.
- `bengali_unassigned`: characters in the Bengali block that Unicode never
  assigned (category Cn), outside legacy runs. No font draws them; a text layer
  emits them when its Unicode map is broken or shifted, so the page shows one
  word and the text says another. A lower bound on that failure: a shifted map
  also yields assigned letters that are simply the wrong ones.

A legacy byte is not one Bengali character (Bijoy spells a conjunct with
several bytes and reorders vowel signs), so `legacy_chars` measures bytes of
unreadable text, not Bengali letters; `convert.py` gives the letter count for
families it can convert.
"""
from __future__ import annotations

import argparse
import unicodedata
from collections import defaultdict
from pathlib import Path

from families import BENGALI_BLOCK, SCHEMES, base_family, legacy_family, looks_legacy, scheme_of
from pdftools import ToolError, text_runs
from sources import Document, documents, parallel, write_csv

FIELDS = [
    "source", "group", "path", "url", "pages_with_text", "chars", "bengali_unicode", "bengali_unassigned",
    "legacy_chars", *[f"legacy_{s}" for s in SCHEMES],
    "legacy_inferred_chars", *[f"inferred_{s}" for s in SCHEMES], "inferred_families",
    "pages_with_legacy", "pages_with_unicode_bengali", "suspect_families", "error",
]
PAGE_FIELDS = ["source", "group", "path", "page", "chars", "bengali_unicode", "legacy_chars",
               "legacy_inferred_chars", "schemes"]


def visible(text: str) -> int:
    return sum(1 for c in text if not c.isspace())


def measure(doc: Document) -> tuple[dict[str, object], list[dict[str, object]]]:
    row: dict[str, object] = {"source": doc.source, "group": doc.group, "path": str(doc.path),
                              "url": doc.url, "error": ""}
    try:
        runs = text_runs(doc.path)
    except ToolError as exc:
        row["error"] = str(exc)
        return row, []
    pages: dict[int, dict[str, object]] = defaultdict(
        lambda: {"chars": 0, "bengali_unicode": 0, "legacy_chars": 0, "legacy_inferred_chars": 0, "schemes": set()})
    named: dict[str, int] = defaultdict(int)
    inferred: dict[str, int] = defaultdict(int)
    inferred_families: set[str] = set()
    suspects: set[str] = set()
    unassigned = 0
    for run in runs:
        page = pages[run.page]
        n = visible(run.text)
        page["chars"] += n
        page["bengali_unicode"] += len(BENGALI_BLOCK.findall(run.text))
        if scheme := legacy_family(run.family):
            page["legacy_chars"] += n
            named[scheme] += n
        elif scheme := scheme_of(run.text):
            page["legacy_inferred_chars"] += n
            inferred[scheme] += n
            inferred_families.add(base_family(run.family) or "[unnamed]")
        else:
            unassigned += sum(1 for c in BENGALI_BLOCK.findall(run.text) if unicodedata.category(c) == "Cn")
            if looks_legacy(run.text):
                suspects.add(base_family(run.family) or "[unnamed]")
        if scheme:
            page["schemes"].add(scheme)
    row.update(
        pages_with_text=len(pages),
        chars=sum(p["chars"] for p in pages.values()),
        bengali_unicode=sum(p["bengali_unicode"] for p in pages.values()),
        legacy_chars=sum(named.values()),
        legacy_inferred_chars=sum(inferred.values()),
        bengali_unassigned=unassigned,
        inferred_families=";".join(sorted(inferred_families)),
        pages_with_legacy=sum(1 for p in pages.values() if p["legacy_chars"] or p["legacy_inferred_chars"]),
        pages_with_unicode_bengali=sum(1 for p in pages.values() if p["bengali_unicode"]),
        suspect_families=";".join(sorted(suspects)),
        **{f"legacy_{s}": named.get(s, 0) for s in SCHEMES},
        **{f"inferred_{s}": inferred.get(s, 0) for s in SCHEMES},
    )
    page_rows = [
        {"source": doc.source, "group": doc.group, "path": str(doc.path), "page": number,
         "chars": p["chars"], "bengali_unicode": p["bengali_unicode"], "legacy_chars": p["legacy_chars"],
         "legacy_inferred_chars": p["legacy_inferred_chars"], "schemes": ";".join(sorted(p["schemes"]))}
        for number, p in sorted(pages.items())
    ]
    return row, page_rows


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--corpus", type=Path)
    parser.add_argument("--manifest", type=Path)
    parser.add_argument("--out", type=Path, required=True)
    parser.add_argument("--pages-out", type=Path)
    parser.add_argument("--workers", type=int)
    args = parser.parse_args()
    docs = documents(args.corpus, args.manifest)
    page_rows: list[dict[str, object]] = []

    def rows():
        for row, pages in parallel(docs, measure, args.workers):
            page_rows.extend(pages)
            yield row

    written = write_csv(args.out, rows(), FIELDS)
    print(f"{written} documents -> {args.out}")
    if args.pages_out:
        print(f"{write_csv(args.pages_out, page_rows, PAGE_FIELDS)} pages -> {args.pages_out}")


if __name__ == "__main__":
    main()
