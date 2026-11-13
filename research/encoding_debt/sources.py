"""Where the PDFs being measured come from, and what group each belongs to.

Two layouts, one interface. The CivicLens corpus stores a file under
`…/approved/<publisher>/<id>/<sha256>.pdf`, so the publisher is read off the
path. Common Crawl records are fetched by `cc_fetch.py`, which writes a
manifest naming each file's host, URL and crawl.
"""
from __future__ import annotations

import csv
import os
import re
from concurrent.futures import ThreadPoolExecutor
from dataclasses import dataclass
from pathlib import Path
from typing import Callable, Iterable, Iterator, TypeVar

_PUBLISHER = re.compile(r"/approved/([^/]+)/")


@dataclass(frozen=True)
class Document:
    source: str
    group: str
    path: Path
    url: str = ""
    crawl: str = ""


def from_corpus(root: Path) -> Iterator[Document]:
    """Every PDF under a CivicLens storage root, grouped by publisher."""
    for path in sorted(root.rglob("*.pdf")):
        match = _PUBLISHER.search(path.as_posix())
        yield Document("corpus", match.group(1) if match else "", path)


def from_manifest(manifest: Path) -> Iterator[Document]:
    """Every fetched, untruncated PDF a `cc_fetch.py` manifest lists."""
    base = manifest.parent
    with manifest.open(newline="", encoding="utf-8") as handle:
        for row in csv.DictReader(handle):
            if row.get("status") != "ok" or row.get("truncated") == "1":
                continue
            yield Document("commoncrawl", row["host"], base / row["path"], row["url"], row["crawl"])


def documents(corpus: Path | None, manifest: Path | None) -> list[Document]:
    found: list[Document] = []
    if corpus:
        found.extend(from_corpus(corpus))
    if manifest:
        found.extend(from_manifest(manifest))
    return found


T = TypeVar("T")
R = TypeVar("R")


def parallel(items: Iterable[T], work: Callable[[T], R], workers: int | None = None) -> Iterator[R]:
    """Map over subprocess-bound work, yielding results in input order."""
    with ThreadPoolExecutor(max_workers=workers or os.cpu_count() or 4) as pool:
        yield from pool.map(work, items)


def write_csv(path: Path, rows: Iterable[dict[str, object]], fields: list[str]) -> int:
    path.parent.mkdir(parents=True, exist_ok=True)
    count = 0
    with path.open("w", newline="", encoding="utf-8") as handle:
        writer = csv.DictWriter(handle, fieldnames=fields)
        writer.writeheader()
        for row in rows:
            writer.writerow(row)
            count += 1
    return count
