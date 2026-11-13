"""Fetch the sampled PDFs out of Common Crawl's WARC archive.

    python cc_fetch.py --sample data/cc/sample.csv --out data/cc --max-bytes 1500000000

Each document is one byte range of one archive file on data.commoncrawl.org,
so nothing is requested from the publisher. Files are stored by the SHA-256 of
their content under `pdf/`, and `manifest.csv` records every attempt, so a rerun
skips what it already holds and the manifest is what `fonts.py --manifest`
reads. A capture Common Crawl truncated is kept in the manifest but marked, and
the measurements leave it out: a PDF cut short can hide fonts at its end.

--max-bytes stops the run before the disk fills; the stopping point is printed.
Files hard-linked from an earlier fetch (--reuse) cost no disk and do not count.
"""
from __future__ import annotations

import argparse
import csv
import gzip
import hashlib
import os
import random
import time
import urllib.error
import urllib.request
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path

DATA = "https://data.commoncrawl.org/"
AGENT = "CivicLens-research/1.0 (encoding-debt measurement; WARC range reader)"
FIELDS = ["crawl", "host", "url", "digest", "truncated", "status", "path", "sha256", "bytes", "error"]


def warc_payload(record: bytes) -> bytes:
    """The HTTP body inside one gzipped WARC response record."""
    raw = gzip.decompress(record)
    _, _, rest = raw.partition(b"\r\n\r\n")          # WARC headers
    _, _, body = rest.partition(b"\r\n\r\n")         # HTTP headers
    return body


def fetch_record(row: dict[str, str], attempts: int = 5) -> bytes:
    start = int(row["offset"])
    end = start + int(row["length"]) - 1
    request = urllib.request.Request(DATA + row["filename"],
                                     headers={"User-Agent": AGENT, "Range": f"bytes={start}-{end}"})
    delay = 2.0
    for attempt in range(attempts):
        try:
            with urllib.request.urlopen(request, timeout=120) as response:
                return warc_payload(response.read())
        except (urllib.error.URLError, TimeoutError, OSError):
            if attempt == attempts - 1:
                raise
            time.sleep(delay)
            delay *= 2
    raise RuntimeError("unreachable")


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--sample", type=Path, required=True)
    parser.add_argument("--out", type=Path, default=Path("data/cc"))
    parser.add_argument("--max-bytes", type=int, default=1_500_000_000)
    parser.add_argument("--workers", type=int, default=4)
    parser.add_argument("--reuse", type=Path, nargs="*", default=[],
                        help="earlier fetch directories whose PDFs are hard-linked by digest instead of refetched")
    args = parser.parse_args()
    store = args.out / "pdf"
    store.mkdir(parents=True, exist_ok=True)
    manifest = args.out / "manifest.csv"

    done: dict[str, dict[str, str]] = {}
    if manifest.exists():
        with manifest.open(newline="", encoding="utf-8") as handle:
            done = {row["digest"]: row for row in csv.DictReader(handle) if row["status"] == "ok"}
    with args.sample.open(newline="", encoding="utf-8") as handle:
        todo = [row for row in csv.DictReader(handle) if row["digest"] not in done]
    # The sample is sorted by host; fetched in that order, a --max-bytes stop
    # would drop whole hosts from the end of the alphabet. Shuffled with a fixed
    # seed, a stop drops documents uniformly instead.
    random.Random(0).shuffle(todo)
    used = sum(int(row["bytes"] or 0) for row in done.values())
    print(f"{len(done)} already held ({used / 1e6:.0f} MB); {len(todo)} to fetch")

    # A file an earlier fetch holds is the same content (same Common Crawl
    # digest): link it rather than download it again. It costs no disk, and
    # it does not count against --max-bytes, which caps new downloads.
    earlier: dict[str, tuple[Path, dict[str, str]]] = {}
    for directory in args.reuse:
        with (directory / "manifest.csv").open(newline="", encoding="utf-8") as handle:
            for row in csv.DictReader(handle):
                if row["status"] == "ok":
                    earlier[row["digest"]] = (directory / row["path"], row)
    reused = []
    for row in [r for r in todo if r["digest"] in earlier]:
        source, old = earlier[row["digest"]]
        target = store / source.name
        if not target.exists():
            os.link(source, target)
        reused.append({**{k: row[k] for k in ("crawl", "host", "url", "digest", "truncated")}, "status": "ok",
                       "path": str(target.relative_to(args.out)), "sha256": old["sha256"], "bytes": old["bytes"],
                       "error": ""})
    todo = [r for r in todo if r["digest"] not in earlier]
    if reused:
        print(f"{len(reused)} linked from earlier fetches; {len(todo)} left to fetch")

    def work(row: dict[str, str]) -> dict[str, str]:
        out = {k: row[k] for k in ("crawl", "host", "url", "digest", "truncated")}
        try:
            body = fetch_record(row)
        except Exception as exc:  # recorded, not fatal: one bad record should not stop a sample
            return {**out, "status": "error", "path": "", "sha256": "", "bytes": "0", "error": str(exc)[:200]}
        if not body.startswith(b"%PDF"):
            return {**out, "status": "error", "path": "", "sha256": "", "bytes": str(len(body)), "error": "not a PDF"}
        sha = hashlib.sha256(body).hexdigest()
        path = store / f"{sha}.pdf"
        if not path.exists():
            path.write_bytes(body)
        return {**out, "status": "ok", "path": str(path.relative_to(args.out)), "sha256": sha,
                "bytes": str(len(body)), "error": ""}

    rows = list(done.values()) + reused
    # Counts what earlier runs of this manifest already hold, so a resumed run
    # cannot download another full --max-bytes; files linked this run are free.
    new_bytes = used

    def save() -> None:
        with manifest.open("w", newline="", encoding="utf-8") as handle:
            writer = csv.DictWriter(handle, fieldnames=FIELDS)
            writer.writeheader()
            writer.writerows(rows)

    with ThreadPoolExecutor(max_workers=args.workers) as pool:
        for index, result in enumerate(pool.map(work, todo), start=1):
            rows.append(result)
            new_bytes += int(result["bytes"] or 0) if result["status"] == "ok" else 0
            if index % 200 == 0:
                save()  # an interrupted run resumes from here instead of refetching
                print(f"  {index}/{len(todo)} fetched, {new_bytes / 1e6:.0f} MB new")
            if new_bytes >= args.max_bytes:
                print(f"stopped at the --max-bytes cap after {index} of {len(todo)}")
                pool.shutdown(cancel_futures=True)
                break
    save()
    ok = sum(1 for r in rows if r["status"] == "ok")
    print(f"{ok} PDFs held, {len(rows) - ok} failed, {new_bytes / 1e6:.0f} MB downloaded this run -> {manifest}")


if __name__ == "__main__":
    main()
