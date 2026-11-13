"""Which fonts store Bengali at Latin code points, and in which byte scheme.

A legacy Bengali font draws Bengali glyphs over Latin byte values, so a PDF
set in one stores `Avgvi` where the page shows আমার. Every PDF names the fonts
it uses, so for a named font the family is ground truth for the encoding, and
measuring the debt needs no human labels.

Two schemes occur in this corpus, each shared by several faces:

- `bijoy`: SutonnyMJ and the other Bijoy "…MJ" faces, and SulekhaT.
- `adarshalipi`: AdarshaLipi and LipiEkushey, which assign the same bytes.

A PDF printed through a driver that strips font names ("CIDFont+F4") still
carries these schemes, so text in an unnamed or unlisted font is classified by
its bytes (`scheme_of`) and always reported apart from the name-confirmed
count, never merged into it.
"""
from __future__ import annotations

import re

# Each confirmed by extracting text set in the family and finding Latin bytes
# where the rendered page shows Bengali. The OpenType "OMJ" Bijoy faces are a
# different product and are left out until one is seen and checked.
LEGACY_FAMILIES: dict[str, list[re.Pattern[str]]] = {
    "bijoy": [re.compile(r"(?<!O)MJ(Bold|Italic|BoldItalic|Regular)?$"), re.compile(r"^SulekhaT")],
    "adarshalipi": [re.compile(r"^AdarshaLipi"), re.compile(r"^LipiEkushey")],
}
SCHEMES = list(LEGACY_FAMILIES)

# Code points each scheme uses at least 4 per 1,000 characters and the other
# scheme and ordinary text almost never do, learned from the documents listed
# in signature_learning_draw.txt. Soft hyphen, £ and ® are frequent in
# AdarshaLipi text too but were dropped on the documents in
# detector_tuning_draw.txt: English hyphenation and currency set them off.
# The detector is scored only on documents in neither list (README.md).
SIGNATURES: dict[str, frozenset[str]] = {
    "bijoy": frozenset("\u2021\u2020\u00A9\u00D6\u00AF"),  # ‡ † © Ö ¯
    "adarshalipi": frozenset("\u00A2\u2212\u00D1\u00C9\u00D0\u00B2"),  # ¢ − Ñ É Ð ²
}

_SUBSET = re.compile(r"^[A-Z]{6}\+")
_STYLE = re.compile(r"[,-](Bold|Italic|BoldItalic|Regular|Normal|Oblique|BoldOblique)$", re.I)

BENGALI_BLOCK = re.compile(r"[\u0980-\u09FF]")

# Broader than SIGNATURES: anything a legacy scheme might use. Nominates a
# family for inspection when neither scheme claims its text.
_LEGACY_LOOKING = re.compile(r"[\u00A1-\u00BF\u00C0-\u00FF\u02C6\u02DC\u2013\u2020-\u2022\u2030\u2039\u203A\u2212]")


def base_family(name: str) -> str:
    """`QHOSXU+AdarshaLipiNormal,Bold` -> `AdarshaLipiNormal`."""
    name = _SUBSET.sub("", name.strip())
    previous = None
    while previous != name:
        previous = name
        name = _STYLE.sub("", name)
    return name


def is_unnamed(name: str) -> bool:
    base = base_family(name)
    return not base or base.startswith("CIDFont+") or base == "[none]"


def legacy_family(name: str) -> str | None:
    """The scheme a named font stores text in, or None if not a listed legacy face."""
    base = base_family(name)
    for scheme, patterns in LEGACY_FAMILIES.items():
        if any(pattern.search(base) for pattern in patterns):
            return scheme
    return None


def _visible(text: str) -> list[str]:
    return [c for c in text if not c.isspace()]


def scheme_of(text: str, minimum: int = 20, density: float = 0.03, letters: float = 0.5) -> str | None:
    """The legacy scheme a run's bytes are in, judged from the bytes alone.

    Needs at least `minimum` visible characters, `density` of them from one
    scheme's signature with the other scheme under a third of that, and at
    least `letters` of them letters or signature bytes — legacy text spells
    words, while a broken Unicode map yields punctuation and digits, which is
    unreadable too but is not this scheme. Anything less decisive returns None.
    """
    chars = _visible(text)
    if len(chars) < minimum:
        return None
    rates = {s: sum(1 for c in chars if c in sig) / len(chars) for s, sig in SIGNATURES.items()}
    best = max(rates, key=rates.__getitem__)
    others = [rate for scheme, rate in rates.items() if scheme != best]
    if rates[best] < density or any(rate >= rates[best] / 3 for rate in others):
        return None
    wordlike = sum(1 for c in chars if c.isalpha() or c in SIGNATURES[best])
    return best if wordlike / len(chars) >= letters else None


def looks_legacy(text: str, minimum: int = 20, density: float = 0.15) -> bool:
    """Whether text reads like legacy bytes of some scheme, known or not."""
    chars = _visible(text)
    if len(chars) < minimum:
        return False
    return sum(1 for c in chars if _LEGACY_LOOKING.match(c)) / len(chars) >= density
