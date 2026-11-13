"""Convert legacy-encoded Bengali to Unicode.

Bijoy has a documented table, used here through `bijoy2unicode` (MIT). No
openly licensed AdarshaLipi table was found, so that scheme is converted with
the table `decipher.py` learns from the page images, loaded from a JSON file
and never presented as documented.
"""
from __future__ import annotations

import json
import unicodedata
from functools import lru_cache
from pathlib import Path

from bijoy2unicode import converter as _bijoy

_BIJOY = _bijoy.Unicode()


class ConversionError(ValueError):
    pass


def bijoy(text: str) -> str:
    """Bijoy bytes to NFC Unicode.

    bijoy2unicode 0.1.1 reads past the end of some strings while reordering;
    a trailing space avoids it, and word-by-word conversion isolates any word
    that still fails, which is raised rather than passed through unconverted.
    """
    try:
        return unicodedata.normalize("NFC", _BIJOY.convertBijoyToUnicode(text + " ").rstrip())
    except IndexError:
        words = []
        for word in text.split(" "):
            try:
                words.append(_BIJOY.convertBijoyToUnicode(word + " ").rstrip())
            except IndexError as exc:
                raise ConversionError(f"bijoy2unicode failed on {word!r}") from exc
        return unicodedata.normalize("NFC", " ".join(words))


@lru_cache(maxsize=None)
def learned_table(path: str) -> dict[str, object]:
    return json.loads(Path(path).read_text(encoding="utf-8"))


def convert(text: str, scheme: str, table: Path | None = None) -> str:
    """With a learned table, use it; without one, only Bijoy has a documented fallback.

    A learned table is preferred even for Bijoy: on this corpus's pages it is
    closer to what the page shows than bijoy2unicode, which mis-maps several
    bytes SutonnyMJ uses (decipher.py --reference bijoy reports both).
    """
    if table is not None:
        from decipher import apply_table  # a learned table is applied by the code that learned it
        return apply_table(text, learned_table(str(table)))
    if scheme == "bijoy":
        return bijoy(text)
    raise ConversionError(f"no documented table for {scheme}; pass the table decipher.py learned")
