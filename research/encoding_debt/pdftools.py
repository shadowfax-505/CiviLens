"""Thin wrappers over poppler's command-line tools.

Poppler is called as a program rather than linked, so this directory takes on
no copyleft dependency, and the measurements are whatever `pdffonts`,
`pdfinfo` and `pdftohtml` report, which anyone can re-run by hand.
"""
from __future__ import annotations

import html
import re
import subprocess
from dataclasses import dataclass
from pathlib import Path

TIMEOUT = 120


class ToolError(RuntimeError):
    pass


def run(args: list[str], timeout: int = TIMEOUT) -> str:
    try:
        done = subprocess.run(args, capture_output=True, timeout=timeout)
    except subprocess.TimeoutExpired as exc:
        raise ToolError(f"{args[0]} timed out") from exc
    if done.returncode != 0 and not done.stdout:
        raise ToolError(f"{args[0]} exited {done.returncode}: {done.stderr.decode('utf-8', 'replace').strip()[:200]}")
    return done.stdout.decode("utf-8", "replace")


@dataclass(frozen=True)
class Font:
    name: str
    type: str
    encoding: str
    embedded: bool
    subset: bool
    to_unicode: bool


_FONT_TYPES = r"Type 1C|Type 1|Type 3|TrueType \(CID\)|TrueType|CID TrueType|CID Type 0C|CID Type 0|CID Type 2|Type 0C|Type 0|OpenType|unknown"
_FONT_LINE = re.compile(
    rf"^(?P<name>.*?)\s+(?P<type>{_FONT_TYPES})\s+(?P<encoding>\S+)\s+"
    r"(?P<emb>yes|no)\s+(?P<sub>yes|no)\s+(?P<uni>yes|no)\s+\d+\s+\d+\s*$"
)


def parse_pdffonts(output: str) -> list[Font]:
    """Parse `pdffonts`, anchoring on the type column rather than on widths.

    The name column is padded to a fixed width, but a longer name overflows it
    and pushes every later column right, so cutting at the declared width
    would truncate exactly the long subset names this reads.
    """
    lines = output.splitlines()
    if len(lines) < 2 or not lines[1].startswith("-"):
        return []
    fonts = []
    for line in lines[2:]:
        match = _FONT_LINE.match(line)
        if not match:
            continue
        fonts.append(Font(
            name=match["name"].strip(),
            type=match["type"],
            encoding=match["encoding"],
            embedded=match["emb"] == "yes",
            subset=match["sub"] == "yes",
            to_unicode=match["uni"] == "yes",
        ))
    return fonts


def pdffonts(path: Path) -> list[Font]:
    return parse_pdffonts(run(["pdffonts", str(path)]))


def parse_pdfinfo(output: str) -> dict[str, str]:
    info = {}
    for line in output.splitlines():
        key, sep, value = line.partition(":")
        if sep:
            info[key.strip()] = value.strip()
    return info


def pdfinfo(path: Path) -> dict[str, str]:
    return parse_pdfinfo(run(["pdfinfo", str(path)]))


def year_of(date: str) -> int | None:
    """First plausible four-digit year in a pdfinfo date, or None."""
    match = re.search(r"\b(19[89]\d|20\d\d)\b", date or "")
    return int(match.group(1)) if match else None


_PAGE = re.compile(r'<page number="(\d+)"([^>]*)>')
_SIZE = re.compile(r'(height|width)="([\d.]+)"')
_FONTSPEC = re.compile(r'<fontspec id="(\d+)"[^>]*family="([^"]*)"')
_TEXT = re.compile(r'<text top="(-?[\d.]+)" left="(-?[\d.]+)" width="([\d.]+)" height="([\d.]+)" font="(\d+)"[^>]*>(.*?)</text>', re.S)
_TAG = re.compile(r"<[^>]+>")


@dataclass(frozen=True)
class Run:
    """One run of text, with its box in pdftohtml's units.

    pdftohtml lays pages out at 1.5 times PDF points, so a box converts to a
    raster at `dpi` by `dpi / 72 / 1.5`; `page_width`/`page_height` are in the
    same units, for callers that want fractions of the page instead.
    """
    page: int
    family: str
    text: str
    top: float = 0.0
    left: float = 0.0
    width: float = 0.0
    height: float = 0.0
    page_width: float = 0.0
    page_height: float = 0.0


def parse_pdftohtml(xml: str) -> list[Run]:
    """Text runs with the font family each was set in, page by page.

    Parsed with patterns rather than an XML parser: pdftohtml passes control
    characters from broken text layers straight through, which a conforming
    parser rejects, and those documents are exactly the ones being measured.
    """
    families: dict[str, str] = {}
    runs: list[Run] = []
    page, page_height, page_width = 0, 0.0, 0.0
    events = sorted(
        [(m.start(), "page", m) for m in _PAGE.finditer(xml)]
        + [(m.start(), "font", m) for m in _FONTSPEC.finditer(xml)]
        + [(m.start(), "text", m) for m in _TEXT.finditer(xml)],
        key=lambda event: event[0],
    )
    for _, kind, match in events:
        if kind == "page":
            size = dict(_SIZE.findall(match.group(2)))
            page = int(match.group(1))
            page_height, page_width = float(size.get("height", 0)), float(size.get("width", 0))
        elif kind == "font":
            families[match.group(1)] = html.unescape(match.group(2))
        else:
            top, left, width, height, font, body = match.groups()
            text = html.unescape(_TAG.sub("", body))
            if text.strip():
                runs.append(Run(page, families.get(font, ""), text, float(top), float(left),
                                float(width), float(height), page_width, page_height))
    return runs


def text_runs(path: Path, first: int | None = None, last: int | None = None) -> list[Run]:
    args = ["pdftohtml", "-xml", "-i", "-q", "-stdout", "-nodrm"]
    if first:
        args += ["-f", str(first)]
    if last:
        args += ["-l", str(last)]
    return parse_pdftohtml(run(args + [str(path)], timeout=300))
