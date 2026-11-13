"""Run with: python3 test_encoding_debt.py  (no test framework required; needs poppler).

Fixture PDFs are generated here rather than committed, so no government
document is copied into the repository to test against.
"""
from __future__ import annotations

import tempfile
from pathlib import Path

from families import base_family, legacy_family, looks_legacy, scheme_of
from fonts import inventory
from pdftools import parse_pdffonts, parse_pdftohtml, year_of
from sources import Document
from textshare import measure


def check(name, cond):
    print(("  PASS  " if cond else "  FAIL  ") + name)
    return cond


def pdf_string(text: str) -> bytes:
    """A PDF literal string holding text's Windows-1252 bytes."""
    out = bytearray(b"(")
    for byte in text.encode("cp1252"):
        if byte in b"()\\" or byte >= 0x80:
            out += b"\\%03o" % byte
        else:
            out.append(byte)
    return bytes(out + b")")


def hex_string(cids: list[int]) -> bytes:
    return b"<" + b"".join(b"%04X" % cid for cid in cids) + b">"


def write_pdf(path: Path, fonts: dict[str, bytes], content: bytes) -> None:
    """A one-page PDF from font dictionaries and a content stream."""
    objects: list[bytes] = [b"<< /Type /Catalog /Pages 2 0 R >>", b"<< /Type /Pages /Kids [3 0 R] /Count 1 >>"]
    font_refs = " ".join(f"/{name} {4 + i} 0 R" for i, name in enumerate(fonts)).encode()
    objects.append(b"<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << "
                   + font_refs + b" >> >> /Contents %d 0 R >>" % (4 + len(fonts)))
    objects.extend(fonts.values())
    objects.append(b"<< /Length %d >>\nstream\n" % len(content) + content + b"\nendstream")
    body, offsets = bytearray(b"%PDF-1.4\n"), []
    for number, obj in enumerate(objects, start=1):
        offsets.append(len(body))
        body += b"%d 0 obj\n" % number + obj + b"\nendobj\n"
    xref = len(body)
    body += b"xref\n0 %d\n0000000000 65535 f \n" % (len(objects) + 1)
    body += b"".join(b"%010d 00000 n \n" % offset for offset in offsets)
    body += b"trailer << /Size %d /Root 1 0 R >>\nstartxref\n%d\n%%%%EOF\n" % (len(objects) + 1, xref)
    path.write_bytes(bytes(body))


def simple_font(name: str) -> bytes:
    return b"<< /Type /Font /Subtype /Type1 /BaseFont /" + name.encode() + b" /Encoding /WinAnsiEncoding >>"


def unicode_font(name: str, mapping: dict[int, str]) -> bytes:
    """A Type0 font whose ToUnicode map sends CIDs to the given characters."""
    cmap = ("/CIDInit /ProcSet findresource begin 12 dict begin begincmap "
            "/CMapName /Test def 1 begincodespacerange <0000> <FFFF> endcodespacerange "
            f"{len(mapping)} beginbfchar "
            + " ".join(f"<{cid:04X}> <{ord(ch):04X}>" for cid, ch in mapping.items())
            + " endbfchar endcmap CMapName currentdict /CMap defineresource pop end end").encode()
    stream = b"<< /Length %d >>\nstream\n" % len(cmap) + cmap + b"\nendstream"
    descendant = (b"<< /Type /Font /Subtype /CIDFontType2 /BaseFont /" + name.encode()
                  + b" /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >>"
                  b" /FontDescriptor << /Type /FontDescriptor /FontName /" + name.encode()
                  + b" /Flags 4 /FontBBox [0 0 1000 1000] /ItalicAngle 0 /Ascent 800 /Descent -200"
                  b" /CapHeight 700 /StemV 80 >> /DW 500 >>")
    # Inline the descendant and the ToUnicode stream would need indirect
    # objects; a direct dictionary is enough for poppler to read the map.
    return (b"<< /Type /Font /Subtype /Type0 /BaseFont /" + name.encode()
            + b" /Encoding /Identity-H /DescendantFonts [" + descendant + b"] /ToUnicode " + stream + b" >>")


BIJOY = "Avgvi \u2020mvbvi evsjv, Avwg \u2020Zvgvq fvjevwm| XvKv \u2020Rjv RR Av`vjZ \u00A9\u00D6\u00AF"
ADARSHALIPI = "A\u00A1j\u00A1l Aha\u00D1j\u00A1 \u00A2L\u00A1 L\u00A2l\u2212m A\u00A1j\u00A1l f\u00A4 j\u00A1qg\u00A5Sm qL \u00C9 \u00B2"
ENGLISH = "The Government of the People's Republic of Bangladesh, represented by the Secretary"

ok = True

# Font names: the family decides the scheme, the style suffix and subset tag do not.
ok &= check("subset tag and style suffix are stripped", base_family("QHOSXU+AdarshaLipiNormal,Bold") == "AdarshaLipiNormal")
ok &= check("SutonnyMJ is bijoy", legacy_family("SutonnyMJ") == "bijoy" and legacy_family("ABCDEF+SutonnyMJ,Bold") == "bijoy")
ok &= check("a style glued to the name is still bijoy", legacy_family("SutonnyMJBold") == "bijoy")
ok &= check("SulekhaT is bijoy", legacy_family("SulekhaT") == "bijoy")
ok &= check("AdarshaLipi and LipiEkushey share a scheme",
            legacy_family("AdarshaLipiExp") == legacy_family("LipiEkushey") == "adarshalipi")
ok &= check("the OpenType OMJ faces are not assumed legacy", legacy_family("SutonnyOMJ") is None)
ok &= check("Unicode and Latin fonts are not legacy", legacy_family("Nikosh") is None and legacy_family("TimesNewRoman") is None)

# Bytes: the detector names a scheme only when the bytes are decisive.
ok &= check("Bijoy bytes are recognised", scheme_of(BIJOY) == "bijoy")
ok &= check("AdarshaLipi bytes are recognised", scheme_of(ADARSHALIPI) == "adarshalipi")
ok &= check("English is left alone", scheme_of(ENGLISH) is None and not looks_legacy(ENGLISH))
ok &= check("hyphenated English with a pound sign is left alone",
            scheme_of("the residence of the Gover\u00ADnor cost \u00A365000 in 1998 and more after") is None)
ok &= check("a short run is not evidence", scheme_of("\u2020\u2021\u00A9") is None)
ok &= check("punctuation from a broken map is not a scheme",
            scheme_of("-8\u0192.)8,RRRR,R8\u201E.\u2026).\u2026S\u2026(\u2020))\u0192.S\u2020]; 1 2 3 4") is None)

# Tool output parsing.
FONTS_OUT = """name                                 type              encoding         emb sub uni object ID
------------------------------------ ----------------- ---------------- --- --- --- ---------
QHOSXU+AdarshaLipiNormal             TrueType          WinAnsi          yes yes no      12  0
ABCDEF+AVeryLongSubsetFontNameThatOverflowsItsColumn,BoldItalic CID TrueType      Identity-H       yes yes yes     14  0
[none]                               Type 3            Custom           yes no  no      20  0
"""
fonts = parse_pdffonts(FONTS_OUT)
ok &= check("pdffonts: every row parsed", len(fonts) == 3)
ok &= check("pdffonts: an overflowing name is kept whole",
            fonts[1].name == "ABCDEF+AVeryLongSubsetFontNameThatOverflowsItsColumn,BoldItalic" and fonts[1].to_unicode)
ok &= check("pdffonts: ToUnicode flag read", not fonts[0].to_unicode and fonts[0].type == "TrueType")

XML = ('<page number="1"><fontspec id="0" size="21" family="QHOSXU+AdarshaLipiNormal" color="#000"/>'
       '<text top="1" left="1" width="1" height="1" font="0">Lj\u00D1L\u00A1m\x07 N\u2212Z\u00C9</text></page>'
       '<page number="2"><fontspec id="1" size="21" family="Vrinda" color="#000"/>'
       '<text top="1" left="1" width="1" height="1" font="1"><b>\u0986\u09AE\u09BE\u09B0 &amp; \u09B8\u09CB\u09A8\u09BE\u09B0</b></text></page>')
runs = parse_pdftohtml(XML)
ok &= check("pdftohtml: a control character does not stop parsing", len(runs) == 2)
ok &= check("pdftohtml: family, page and unescaped text kept",
            runs[0].family.endswith("AdarshaLipiNormal") and runs[1].page == 2 and runs[1].text == "\u0986\u09AE\u09BE\u09B0 & \u09B8\u09CB\u09A8\u09BE\u09B0")
ok &= check("year from a pdfinfo date", year_of("Mon Jan  6 10:04:11 2025 +06") == 2025 and year_of("") is None)

# End to end on generated PDFs.
with tempfile.TemporaryDirectory() as tmp:
    root = Path(tmp) / "approved" / "test-publisher" / "1"
    root.mkdir(parents=True)
    legacy_pdf = root / "legacy.pdf"
    write_pdf(legacy_pdf, {"F1": simple_font("SutonnyMJ"), "F2": simple_font("Helvetica")},
              b"BT /F1 12 Tf 72 700 Td " + pdf_string(BIJOY) + b" Tj ET\n"
              b"BT /F2 12 Tf 72 650 Td " + pdf_string(ENGLISH) + b" Tj ET")
    bengali = "\u0986\u09AE\u09BE\u09B0 \u09B8\u09CB\u09A8\u09BE\u09B0 \u09AC\u09BE\u0982\u09B2\u09BE"
    mapping = {i + 1: ch for i, ch in enumerate(dict.fromkeys(bengali))}
    reverse = {ch: cid for cid, ch in mapping.items()}
    unicode_pdf = root / "unicode.pdf"
    write_pdf(unicode_pdf, {"F1": unicode_font("NotoSansBengali", mapping)},
              b"BT /F1 12 Tf 72 700 Td " + hex_string([reverse[c] for c in bengali]) + b" Tj ET")
    # A name-stripped font maps its CIDs to AdarshaLipi's Latin bytes, which is
    # how the real ones carry characters such as U+2212 that WinAnsi lacks.
    legacy_map = {i + 1: ch for i, ch in enumerate(dict.fromkeys(ADARSHALIPI))}
    legacy_reverse = {ch: cid for cid, ch in legacy_map.items()}
    unnamed_pdf = root / "unnamed.pdf"
    write_pdf(unnamed_pdf, {"F1": unicode_font("CIDFont+F4", legacy_map)},
              b"BT /F1 12 Tf 72 700 Td " + hex_string([legacy_reverse[c] for c in ADARSHALIPI]) + b" Tj ET")

    row = inventory(Document("corpus", "test-publisher", legacy_pdf))
    ok &= check("fonts: a SutonnyMJ document is legacy", row["legacy"] == 1 and row["legacy_families"] == "bijoy")
    row = inventory(Document("corpus", "test-publisher", unicode_pdf))
    ok &= check("fonts: a Unicode document is not", row["legacy"] == 0 and not row["error"])

    row, pages = measure(Document("corpus", "test-publisher", legacy_pdf))
    ok &= check("textshare: legacy text counted by name, English not",
                row["legacy_bijoy"] == sum(1 for c in BIJOY if not c.isspace()) and row["bengali_unicode"] == 0)
    row, _ = measure(Document("corpus", "test-publisher", unicode_pdf))
    ok &= check("textshare: Unicode Bengali counted by code point",
                row["bengali_unicode"] == sum(1 for c in bengali if not c.isspace()) and row["legacy_chars"] == 0)
    row, _ = measure(Document("corpus", "test-publisher", unnamed_pdf))
    ok &= check("textshare: an unnamed font's legacy bytes are inferred, not counted as named",
                row["legacy_chars"] == 0 and row["inferred_adarshalipi"] > 0)

    # A broken map: the page draws প, the text layer says U+09A9, which Unicode never assigned.
    broken = "঩রিদর্শন"
    broken_map = {i + 1: ch for i, ch in enumerate(dict.fromkeys(broken))}
    broken_reverse = {ch: cid for cid, ch in broken_map.items()}
    broken_pdf = root / "broken.pdf"
    write_pdf(broken_pdf, {"F1": unicode_font("Nikosh", broken_map)},
              b"BT /F1 12 Tf 72 700 Td " + hex_string([broken_reverse[c] for c in broken]) + b" Tj ET")
    row, _ = measure(Document("corpus", "test-publisher", broken_pdf))
    ok &= check("textshare: unassigned Bengali code points from a broken map are counted",
                row["bengali_unassigned"] == 1 and row["legacy_chars"] == 0)

    # Short Unicode lines must add up to one passage; a comparison of "" with
    # None once reset the passage on every line, so no Unicode control existed.
    import sys as _sys
    _sys.path.insert(0, str(Path(__file__).resolve().parent / "consequences"))
    from llm_reading import passages_of
    lines_pdf = root / "lines.pdf"
    write_pdf(lines_pdf, {"F1": unicode_font("NotoSansBengali", mapping)},
              b"".join(b"BT /F1 12 Tf 72 %d Td " % (700 - 20 * k) + hex_string([reverse[c] for c in bengali]) + b" Tj ET\n"
                       for k in range(12)))
    found = passages_of((lines_pdf, {}, 60, 3))
    ok &= check("llm_reading: consecutive short Unicode lines form a passage",
                any(p["kind"] == "unicode" for p in found))

# Reordering: a legacy scheme stores glyphs in drawing order; Unicode does not.
from decipher import apply_table, model1, outputs_per_byte, word_pairs

TOY = {"map": {"K": "ক", "v": "া", "‡": "ে", "w": "ি", "Z": "ত", "©": "র্",
               "¯": "স্", "’": "থ", "m": "স", "Ö": "্র", "c": "প"},
       "reph": "after"}
ok &= check("a pre-base vowel sign moves after its consonant", apply_table("wK", TOY) == "কি")
ok &= check("e-kar and aa-kar around a consonant compose to o-kar", apply_table("‡Kv", TOY) == "কো")
ok &= check("reph stored after its cluster moves to the front", apply_table("Z©", TOY) == "র্ত")
ok &= check("a hasanta-final byte continues the cluster", apply_table("¯’v", TOY) == "স্থা")
ok &= check("a pre-base vowel waits for the whole cluster, phala included",
            apply_table("‡cÖ", TOY) == "প্রে")
ok &= check("reph stored before its cluster, when the scheme does that",
            apply_table("©Z", {**TOY, "reph": "before"}) == "র্ত")

# Learning: from clean pairs the model recovers a substitution with a moved vowel.
truth = {"K": "ক", "L": "খ", "M": "গ", "v": "া", "w": "ি", "b": "ন"}
words = ["Kv", "wK", "Lv", "wL", "Mv", "wM", "bv", "wb", "KL", "Mb", "wKv", "bM", "vK", "wbv"]
clean = [(w, apply_table(w, {"map": truth, "reph": "after"})) for w in words * 3]
learned = outputs_per_byte(word_pairs(clean), model1(word_pairs(clean)))
ok &= check("IBM Model 1 recovers a toy table, the moved i-kar included",
            all(learned.get(b) == u for b, u in truth.items()))

print("\nALL PASS" if ok else "\nSOME FAILED")
raise SystemExit(0 if ok else 1)
