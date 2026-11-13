"""Inventory the fonts every PDF declares, one row per document.

    python fonts.py --corpus ../../storage/app/private/ingestion/approved --out data/corpus_fonts.csv
    python fonts.py --manifest data/cc/manifest.csv --out data/cc_fonts.csv

A document is counted as carrying encoding debt when it declares a font from
`families.LEGACY_FAMILIES`. That says the document contains legacy-encoded
text, not how much; `textshare.py` measures the volume.
"""
from __future__ import annotations

import argparse
from pathlib import Path

from families import legacy_family
from pdftools import ToolError, pdffonts, pdfinfo, year_of
from sources import Document, documents, parallel, write_csv

FIELDS = [
    "source", "group", "path", "url", "crawl", "pages", "created_year", "producer", "creator",
    "fonts", "legacy", "legacy_families", "fonts_without_tounicode", "error",
]


def inventory(doc: Document) -> dict[str, object]:
    row: dict[str, object] = {"source": doc.source, "group": doc.group, "path": str(doc.path),
                              "url": doc.url, "crawl": doc.crawl, "error": ""}
    try:
        fonts = pdffonts(doc.path)
        info = pdfinfo(doc.path)
    except ToolError as exc:
        row["error"] = str(exc)
        return row
    families = sorted({family for font in fonts if (family := legacy_family(font.name))})
    row.update(
        pages=info.get("Pages", ""),
        created_year=year_of(info.get("CreationDate", "")) or "",
        producer=info.get("Producer", ""),
        creator=info.get("Creator", ""),
        fonts=len(fonts),
        legacy=int(bool(families)),
        legacy_families=";".join(families),
        fonts_without_tounicode=sum(1 for font in fonts if not font.to_unicode),
    )
    return row


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--corpus", type=Path)
    parser.add_argument("--manifest", type=Path)
    parser.add_argument("--out", type=Path, required=True)
    parser.add_argument("--workers", type=int)
    args = parser.parse_args()
    docs = documents(args.corpus, args.manifest)
    written = write_csv(args.out, parallel(docs, inventory, args.workers), FIELDS)
    print(f"{written} documents -> {args.out}")


if __name__ == "__main__":
    main()
