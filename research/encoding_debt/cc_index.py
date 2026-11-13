"""List government PDFs Common Crawl already holds, and draw a sample by host.

    python cc_index.py --domain '*.gov.bd' --years 2024 2025 2026 --per-host 40 --out data/cc

Why Common Crawl: it widens the sample from one project's corpus to the
government's own web without this project fetching anything from a
government server. Every byte comes from Common Crawl's public archive.

Writes, under --out:
- `index/<crawl>-<page>.jsonl`: raw index responses, cached so a rerun resumes
  instead of asking again (the index server throttles, and did in the probe);
- `records.csv`: every PDF capture, deduplicated by content digest;
- `sample.csv`: at most --per-host documents per host, drawn uniformly with a
  fixed seed, so one prolific site cannot stand in for the government.

Selection is by `mime-detected`, which Common Crawl sets from the bytes, so a
PDF a server mislabels is still found.
"""
from __future__ import annotations

import argparse
import csv
import http.client
import json
import random
import re
import time
import urllib.error
import urllib.parse
import urllib.request
from collections import defaultdict
from pathlib import Path

INDEX = "https://index.commoncrawl.org"
AGENT = "CivicLens-research/1.0 (encoding-debt measurement; Common Crawl index reader)"
PAUSE = 1.5
FIELDS = ["crawl", "timestamp", "host", "url", "digest", "filename", "offset", "length", "truncated"]


def fetch(url: str, attempts: int = 6) -> str:
    """GET with backoff; the index answers 503 when asked too quickly."""
    delay = PAUSE
    for attempt in range(attempts):
        try:
            request = urllib.request.Request(url, headers={"User-Agent": AGENT})
            with urllib.request.urlopen(request, timeout=120) as response:
                return response.read().decode("utf-8", "replace")
        except urllib.error.HTTPError as exc:
            if exc.code == 404:
                return ""
            if attempt == attempts - 1:
                raise
        except (urllib.error.URLError, TimeoutError, http.client.HTTPException, ConnectionError):
            # A connection dropped mid-body raises IncompleteRead, an HTTPException.
            if attempt == attempts - 1:
                raise
        time.sleep(delay)
        delay *= 2
    return ""


def crawls(years: list[str]) -> list[str]:
    ids = [c["id"] for c in json.loads(fetch(f"{INDEX}/collinfo.json"))]
    return [i for i in ids if (m := re.match(r"CC-MAIN-(\d{4})-", i)) and m.group(1) in years]


def query(crawl: str, domain: str, extra: str) -> str:
    params = urllib.parse.urlencode({"url": domain, "filter": "mime-detected:application/pdf", "output": "json"})
    return f"{INDEX}/{crawl}-index?{params}{extra}"


def pages_of(crawl: str, domain: str) -> int:
    body = fetch(query(crawl, domain, "&showNumPages=true"))
    return int(json.loads(body)["pages"]) if body.strip().startswith("{") else 0


def parse(body: str) -> list[dict[str, str]] | None:
    """Every line of an index page, or None if any line is cut short.

    A connection dropped mid-response leaves a truncated last line; caching
    that would silently lose the rest of the page on every rerun.
    """
    rows = []
    for line in body.splitlines():
        if not line.strip():
            continue
        try:
            rows.append(json.loads(line))
        except json.JSONDecodeError:
            return None
    return rows


def page_rows(crawl: str, domain: str, page: int, cache: Path, attempts: int = 4) -> list[dict[str, str]]:
    path = cache / f"{crawl}-{page}.jsonl"
    if path.exists() and (rows := parse(path.read_text(encoding="utf-8"))) is not None:
        return rows
    for _ in range(attempts):
        body = fetch(query(crawl, domain, f"&page={page}"))
        time.sleep(PAUSE)
        if (rows := parse(body)) is not None:
            path.write_text(body, encoding="utf-8")
            return rows
    raise RuntimeError(f"{crawl} page {page}: no complete response after {attempts} attempts")


def records(crawl: str, domain: str, cache: Path) -> list[dict[str, str]]:
    found = []
    for page in range(pages_of(crawl, domain)):
        for row in page_rows(crawl, domain, page, cache):
            if "url" not in row:
                continue
            if row.get("status") != "200":
                continue
            found.append({
                "crawl": crawl,
                "timestamp": row["timestamp"],
                "host": urllib.parse.urlsplit(row["url"]).hostname or "",
                "url": row["url"],
                "digest": row["digest"],
                "filename": row["filename"],
                "offset": row["offset"],
                "length": row["length"],
                "truncated": "1" if row.get("truncated") else "0",
            })
    return found


def preference(row: dict[str, str]) -> tuple[bool, str]:
    return row["truncated"] == "0", row["timestamp"]


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--domain", default="*.gov.bd")
    parser.add_argument("--years", nargs="+", default=["2024", "2025", "2026"])
    parser.add_argument("--per-host", type=int, default=40)
    parser.add_argument("--exclude-truncated", action="store_true",
                        help="draw the sample from whole captures only; truncated ones cannot be measured")
    parser.add_argument("--uniform", type=int,
                        help="draw this many documents uniformly from all eligible captures instead of per host; "
                             "every document then has the same inclusion probability (self-weighting)")
    parser.add_argument("--cache", type=Path, help="index cache directory to read (default <out>/index)")
    parser.add_argument("--seed", type=int, default=0)
    parser.add_argument("--out", type=Path, default=Path("data/cc"))
    args = parser.parse_args()
    cache = args.cache or args.out / "index"
    cache.mkdir(parents=True, exist_ok=True)
    args.out.mkdir(parents=True, exist_ok=True)

    by_digest: dict[str, dict[str, str]] = {}
    for crawl in crawls(args.years):
        rows = records(crawl, args.domain, cache)
        print(f"{crawl}: {len(rows)} PDF captures")
        for row in rows:
            # One row per distinct file: a whole capture over a truncated one,
            # then the newest.
            kept = by_digest.get(row["digest"])
            if kept is None or preference(row) > preference(kept):
                by_digest[row["digest"]] = row

    unique = sorted(by_digest.values(), key=lambda r: (r["host"], r["url"], r["timestamp"]))
    with (args.out / "records.csv").open("w", newline="", encoding="utf-8") as handle:
        writer = csv.DictWriter(handle, fieldnames=FIELDS)
        writer.writeheader()
        writer.writerows(unique)

    hosts: dict[str, list[dict[str, str]]] = defaultdict(list)
    for row in unique:
        if args.exclude_truncated and row["truncated"] == "1":
            continue
        hosts[row["host"]].append(row)
    rng = random.Random(args.seed)
    sample = []
    if args.uniform:
        eligible_rows = sorted((r for rows in hosts.values() for r in rows), key=lambda r: (r["host"], r["url"], r["digest"]))
        sample = rng.sample(eligible_rows, min(args.uniform, len(eligible_rows)))
    else:
        for host in sorted(hosts):
            rows = hosts[host]
            sample.extend(rows if len(rows) <= args.per_host else rng.sample(rows, args.per_host))
    with (args.out / "sample.csv").open("w", newline="", encoding="utf-8") as handle:
        writer = csv.DictWriter(handle, fieldnames=FIELDS)
        writer.writeheader()
        writer.writerows(sample)
    eligible = sum(len(rows) for rows in hosts.values())
    print(f"{len(unique)} distinct PDFs on {len({r['host'] for r in unique})} hosts; "
          f"{eligible} eligible on {len(hosts)} hosts; sample of {len(sample)} -> {args.out / 'sample.csv'}")


if __name__ == "__main__":
    main()
