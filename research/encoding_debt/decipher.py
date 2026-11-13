"""Learn a legacy font's byte-to-Unicode table from the page images alone.

    python decipher.py --pairs data/pairs_bijoy.csv --out data/table_bijoy.json --reference bijoy
    python decipher.py --pairs data/pairs_adarshalipi.csv --out data/table_adarshalipi.json

A legacy Bengali font is a substitution cipher with a known twist: bytes are
stored in the order glyphs are drawn, not the order Unicode spells them. The
key is on the page — the renderer draws each byte's glyph and OCR reads it —
so `pairs.py` yields (legacy bytes, what the page shows) with no human labels,
and this recovers the table in two steps:

1. Which Unicode each byte stands for: IBM Model 1 in the direction
   P(Unicode character | legacy byte). It ignores order, which is what lets
   it learn a vowel sign stored before the consonant it follows in Unicode,
   and lets one byte emit several characters (a conjunct). A mild position
   prior breaks ties between repeated bytes in one word.
2. Where the output goes: the reordering Bengali's legacy schemes share —
   the pre-base vowel signs ি ে ৈ move after the consonant cluster they are
   drawn before, and reph (র্) moves to the front of its cluster. Whether a
   scheme stores reph before or after the cluster is chosen by which
   convention reproduces the learn split better, not assumed.

Scored on documents in the `evaluate` split only. With `--reference bijoy`, the
learned table is also scored against the documented Bijoy converter — the
check that the method recovers a table that is actually known.
"""
from __future__ import annotations

import argparse
import csv
import json
import math
import unicodedata
from collections import Counter, defaultdict
from pathlib import Path

HASANTA = "\u09CD"
NUKTA = "\u09BC"
REPH = "\u09B0\u09CD"
PRE_BASE = {"\u09BF", "\u09C7", "\u09C8"}                       # ি ে ৈ
CONSONANTS = {chr(c) for c in range(0x0995, 0x09BA)} | {"\u09CE", "\u09DC", "\u09DD", "\u09DF"}
MARKS = {chr(c) for c in range(0x09BE, 0x09CD)} | {"\u0981", "\u0982", "\u0983", "\u09D7", NUKTA}
NULL = "\u0000"


# Glyphs a legacy font draws in two parts that Unicode spells as one
# character: অ + া is drawn for আ. Learning sees the parts; output rejoins them.
VISUAL_PARTS = {"\u0986": "\u0985\u09BE"}
# Characters a reader cannot tell apart on the page, compared as one: curly
# and straight quotes, and Assamese ra, which Tesseract's Bengali model emits.
SAME_ON_PAGE = str.maketrans({"\u201C": '"', "\u201D": '"', "\u2018": "'", "\u2019": "'", "\u09F0": "\u09B0"})


def comparable(text: str) -> str:
    """Text as compared for error rates: NFC, no spaces, look-alikes merged."""
    text = unicodedata.normalize("NFC", text).translate(SAME_ON_PAGE)
    for whole, parts in VISUAL_PARTS.items():
        text = text.replace(parts, whole)
    return "".join(c for c in text if not c.isspace())


def edits(a: str, b: str) -> int:
    previous = list(range(len(b) + 1))
    for i, ca in enumerate(a, 1):
        current = [i]
        for j, cb in enumerate(b, 1):
            current.append(min(previous[j] + 1, current[j - 1] + 1, previous[j - 1] + (ca != cb)))
        previous = current
    return previous[-1]


def cer(outputs: list[str], references: list[str]) -> float:
    total = sum(len(comparable(r)) for r in references)
    return sum(edits(comparable(o), comparable(r)) for o, r in zip(outputs, references)) / max(total, 1)


def load(path: Path, split: str, min_confidence: float) -> list[tuple[str, str]]:
    with path.open(newline="", encoding="utf-8") as handle:
        return [(r["legacy"], unicodedata.normalize("NFC", r["ocr"])) for r in csv.DictReader(handle)
                if r["split"] == split and r["ocr"].strip() and float(r["ocr_confidence"] or 0) >= min_confidence]


def word_pairs(lines: list[tuple[str, str]]) -> list[tuple[str, str]]:
    """Word-aligned pairs where both sides split into the same number of words."""
    out = []
    for legacy, ocr in lines:
        a, b = legacy.split(), ocr.split()
        if len(a) == len(b):
            out.extend(zip(a, b))
    return out


def drawn_form(unicode_word: str, visual_parts: bool = True) -> str:
    """Unicode spelled the way the glyphs are drawn: canonical parts, visual parts.

    NFD splits ো into ে + া, the two glyphs a legacy font stores separately;
    VISUAL_PARTS does the same for pairs Unicode has no decomposition for.
    Whether a scheme draws those in parts differs (Bijoy stores আ as অ + া,
    AdarshaLipi as one byte), so `learn` tries both.
    """
    text = unicodedata.normalize("NFD", unicode_word)
    if visual_parts:
        for whole, parts in VISUAL_PARTS.items():
            text = text.replace(whole, parts)
    return text


def model1(pairs: list[tuple[str, str]], iterations: int = 15, visual_parts: bool = True) -> dict[str, dict[str, float]]:
    """t[b][u] = P(Unicode char u | legacy byte b), by EM.

    Trained without hasanta: it occurs in nearly every conjunct, so the model
    would explain it by the empty source rather than by any byte. Hasanta is
    placed afterwards from where it sits in the drawn form.
    """
    stripped = [(legacy, drawn_form(uni, visual_parts).replace(HASANTA, "")) for legacy, uni in pairs]
    sources = {b for legacy, _ in stripped for b in legacy} | {NULL}
    targets = {u for _, uni in stripped for u in uni}
    t = {b: {u: 1.0 / len(targets) for u in targets} for b in sources}
    for _ in range(iterations):
        counts: dict[str, Counter] = defaultdict(Counter)
        for legacy, uni in stripped:
            bs = [NULL, *legacy]
            for u in uni:
                z = sum(t[b][u] for b in bs)
                for b in bs:
                    counts[b][u] += t[b][u] / z
        t = {b: {u: c / total for u, c in row.items()}
             for b, row in counts.items() if (total := sum(row.values())) > 0}
    return t


def _spans(legacy: str, form: str, t: dict[str, dict[str, float]], spread: float) -> dict[int, tuple[int, int]]:
    """For each byte position, the span of the drawn form its characters came from."""
    positions = [k for k, c in enumerate(form) if c != HASANTA]
    letters = [form[k] for k in positions]
    spans: dict[int, list[int]] = defaultdict(list)
    for j, u in enumerate(letters):
        def score(i: int) -> float:
            return t.get(legacy[i], {}).get(u, 1e-12) * math.exp(
                -spread * abs(i / max(len(legacy), 1) - j / max(len(letters), 1)))
        best = max(range(len(legacy)), key=score)
        if t.get(legacy[best], {}).get(u, 0) > t.get(NULL, {}).get(u, 0):
            spans[best].append(positions[j])
    return {i: (min(ks), max(ks)) for i, ks in spans.items()}


def outputs_per_byte(pairs: list[tuple[str, str]], t: dict[str, dict[str, float]], spread: float = 2.0,
                     visual_parts: bool = True) -> dict[str, str]:
    """For each byte, the Unicode string most often drawn from it.

    A hasanta inside one byte's span is part of that byte (a conjunct glyph). A
    hasanta between two bytes belongs to one of them: a half-form (স্) carries
    it after, a phala (্য) carries it before. Which is measured, per byte, as how
    often a hasanta sits on that side of it, and the boundary one goes to the
    side that carries it more.
    """
    occurrences = []
    after, before, seen_count = Counter(), Counter(), Counter()
    for legacy, uni in pairs:
        form = drawn_form(uni, visual_parts)
        spans = _spans(legacy, form, t, spread)
        starts = {a: i for i, (a, _) in spans.items()}
        ends = {b: i for i, (_, b) in spans.items()}
        boundary = []
        for k, c in enumerate(form):
            if c == HASANTA and not any(a < k < b for a, b in spans.values()):
                left, right = ends.get(k - 1), starts.get(k + 1)
                boundary.append((k, left, right))
                if left is not None:
                    after[legacy[left]] += 1
                if right is not None:
                    before[legacy[right]] += 1
        for b in legacy:
            seen_count[b] += 1
        occurrences.append((legacy, form, spans, boundary))

    rate_after = {b: after[b] / seen_count[b] for b in seen_count}
    rate_before = {b: before[b] / seen_count[b] for b in seen_count}
    seen: dict[str, Counter] = defaultdict(Counter)
    for legacy, form, spans, boundary in occurrences:
        prefix, suffix = Counter(), Counter()
        for _, left, right in boundary:
            left_claim = rate_after.get(legacy[left], 0.0) if left is not None else -1.0
            right_claim = rate_before.get(legacy[right], 0.0) if right is not None else -1.0
            if left is not None and left_claim >= right_claim:
                suffix[left] += 1
            elif right is not None:
                prefix[right] += 1
        for i, b in enumerate(legacy):
            core = form[spans[i][0]:spans[i][1] + 1] if i in spans else ""
            seen[b][HASANTA * prefix[i] + core + HASANTA * suffix[i]] += 1
    return {b: counter.most_common(1)[0][0] for b, counter in seen.items()}


def _cluster_start(out: list[str]) -> int:
    """Index where the consonant cluster ending `out` starts (after trailing marks)."""
    k = len(out)
    while k > 0 and out[k - 1] in MARKS:
        k -= 1
    end = k
    while k > 0 and out[k - 1] in CONSONANTS:
        k -= 1
        if k > 1 and out[k - 1] == HASANTA and out[k - 2] in CONSONANTS:
            k -= 1
            continue
        break
    return k if k < end else end


def apply_table(text: str, table: dict[str, object]) -> str:
    """Convert legacy bytes with a learned table and its reordering convention."""
    mapping: dict[str, str] = table["map"]  # type: ignore[assignment]
    memory: dict[str, str] = table.get("words", {})  # type: ignore[assignment]
    segments: dict[str, str] = table.get("segments", {})  # type: ignore[assignment]
    reph_before_cluster = table.get("reph") == "before"
    words = []
    for word in text.split(" "):
        if word in memory:
            words.append(memory[word])
            continue
        pieces = []
        k = 0
        while k < len(word):
            if word[k:k + 2] in segments:
                pieces.append(unicodedata.normalize("NFC", segments[word[k:k + 2]]))
                k += 2
            else:
                pieces.append(mapping.get(word[k], word[k]))
                k += 1
        out: list[str] = []
        pending = ""
        pending_reph = False
        i = 0
        while i < len(pieces):
            piece = pieces[i]
            if piece in PRE_BASE:
                pending += piece
                i += 1
                continue
            if piece == REPH and reph_before_cluster:
                pending_reph = True
                i += 1
                continue
            if piece == REPH:
                k = _cluster_start(out)
                out[k:k] = list(REPH)
                i += 1
                continue
            if piece and piece[0] in CONSONANTS:
                start = len(out)
                out.extend(piece)
                i += 1
                # Swallow the rest of the cluster: hasanta-joined consonants,
                # phala forms (্য ্র ্ব) and nukta.
                while i < len(pieces) and pieces[i] and (
                        pieces[i][0] in (HASANTA, NUKTA) or (out and out[-1] == HASANTA and pieces[i][0] in CONSONANTS)):
                    out.extend(pieces[i])
                    i += 1
                if pending_reph:
                    out[start:start] = list(REPH)
                    pending_reph = False
                if pending:
                    out.extend(pending)
                    pending = ""
                continue
            out.extend(piece)
            i += 1
        out.extend(pending)
        words.append("".join(out))
    text = unicodedata.normalize("NFC", " ".join(words))
    for whole, parts in VISUAL_PARTS.items():
        text = text.replace(parts, whole)
    return text


def segment_memory(pairs: list[tuple[str, str]], t: dict[str, dict[str, float]], mapping: dict[str, str],
                   visual_parts: bool, spread: float = 2.0, minimum: int = 4, agreement: float = 0.6) -> dict[str, str]:
    """Byte pairs that draw one glyph the byte table cannot spell by concatenation.

    Some conjuncts are two bytes whose Unicode is neither byte's output followed
    by the other's: AdarshaLipi stores ক্ত as a pair whose single-byte readings
    come out swapped, and স্থ as a pair whose hasanta belongs to neither byte
    alone. Such a pair is kept when its page reading is consistent and differs
    from what the byte table would produce. Pairs touching a pre-base vowel or
    reph are left to the reordering rules, which move those across clusters.
    """
    movable = PRE_BASE | {REPH}
    seen: dict[str, Counter] = defaultdict(Counter)
    for legacy, uni in pairs:
        form = drawn_form(uni, visual_parts)
        spans = _spans(legacy, form, t, spread)
        for i in range(len(legacy) - 1):
            a, b = legacy[i], legacy[i + 1]
            if mapping.get(a, a) in movable or mapping.get(b, b) in movable or i not in spans or i + 1 not in spans:
                continue
            first, second = sorted((spans[i], spans[i + 1]))
            gap = form[first[1] + 1:second[0]]
            if gap not in ("", HASANTA):
                continue  # the two spans are not one glyph's worth of text
            seen[a + b][form[first[0]:second[1] + 1]] += 1
    proposed = {}
    for pair, counter in seen.items():
        reading, count = counter.most_common(1)[0]
        concatenated = unicodedata.normalize("NFD", mapping.get(pair[0], pair[0]) + mapping.get(pair[1], pair[1]))
        if count >= minimum and count / sum(counter.values()) >= agreement and reading != concatenated:
            proposed[pair] = reading
    # Keep a pair only if it helps the learn-split words it touches: an
    # alignment can be consistent and still wrong.
    base = {"map": mapping, "reph": "after", "visual_parts": visual_parts}
    memory: dict[str, str] = {}
    for pair, reading in sorted(proposed.items(), key=lambda item: -seen[item[0]][item[1]]):
        touched = [(legacy, uni) for legacy, uni in pairs if pair in legacy]
        without = sum(edits(comparable(apply_table(l, {**base, "segments": memory})), comparable(u)) for l, u in touched)
        trial = {**memory, pair: reading}
        with_pair = sum(edits(comparable(apply_table(l, {**base, "segments": trial})), comparable(u)) for l, u in touched)
        if with_pair < without:
            memory = trial
    return memory


def word_memory(pairs: list[tuple[str, str]], minimum: int = 3, agreement: float = 0.6) -> dict[str, str]:
    """Whole legacy words whose page readings agree often enough to trust.

    A frequent word is read by OCR many times on many pages; its majority
    reading is more reliable than any one reading, and than a byte-by-byte
    conversion of a conjunct the byte table has not seen enough of.
    """
    readings: dict[str, Counter] = defaultdict(Counter)
    for legacy, uni in pairs:
        readings[legacy][unicodedata.normalize("NFC", uni)] += 1
    memory = {}
    for legacy, counter in readings.items():
        reading, count = counter.most_common(1)[0]
        if count >= minimum and count / sum(counter.values()) >= agreement:
            memory[legacy] = reading
    return memory


def learn(lines: list[tuple[str, str]]) -> dict[str, object]:
    """Fit the byte table, then choose the scheme's conventions on the learn split.

    Two conventions differ between schemes and are not assumed: whether reph
    is stored before or after its cluster, and whether two-part glyphs such as
    আ are stored as their parts. Each combination is scored on the learn split.
    """
    pairs = word_pairs(lines)
    candidates = []
    for visual_parts in (True, False):
        t = model1(pairs, visual_parts=visual_parts)
        mapping = outputs_per_byte(pairs, t, visual_parts=visual_parts)
        segments = segment_memory(pairs, t, mapping, visual_parts)
        candidates += [{"map": mapping, "segments": segments, "reph": reph, "visual_parts": visual_parts}
                       for reph in ("after", "before")]
    best = min(candidates, key=lambda table: cer([apply_table(l, table) for l, _ in lines], [o for _, o in lines]))
    best["word_pairs"] = len(pairs)
    best["words"] = word_memory(pairs)
    return best


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--pairs", type=Path, required=True)
    parser.add_argument("--out", type=Path, required=True)
    parser.add_argument("--min-confidence", type=float, default=70.0)
    parser.add_argument("--reference", choices=["bijoy"], help="score against a documented converter too")
    args = parser.parse_args()

    learn_lines = load(args.pairs, "learn", args.min_confidence)
    evaluate_lines = load(args.pairs, "evaluate", 0.0)
    table = learn(learn_lines)
    with args.pairs.open(newline="", encoding="utf-8") as handle:
        table["scheme"] = next(csv.DictReader(handle))["scheme"]
    table["learned_from"] = {"pairs_file": args.pairs.name, "lines": len(learn_lines),
                             "min_ocr_confidence": args.min_confidence}
    legacy = [l for l, _ in evaluate_lines]
    ocr = [o for _, o in evaluate_lines]
    converted = [apply_table(l, table) for l in legacy]
    bytes_only = [apply_table(l, {**table, "words": {}, "segments": {}}) for l in legacy]
    no_words = [apply_table(l, {**table, "words": {}}) for l in legacy]
    evaluation: dict[str, object] = {
        "lines": len(evaluate_lines),
        "cer_vs_page_ocr": round(cer(converted, ocr), 4),
        "cer_vs_page_ocr_bytes_only": round(cer(bytes_only, ocr), 4),
        "cer_vs_page_ocr_bytes_and_pairs": round(cer(no_words, ocr), 4),
    }
    if args.reference == "bijoy":
        from convert import ConversionError, bijoy
        kept = []
        for l, c, o in zip(legacy, converted, ocr):
            try:
                kept.append((bijoy(l), c, o))
            except ConversionError:
                continue
        truth = [k[0] for k in kept]
        evaluation.update({
            "reference": "bijoy2unicode 0.1.1",
            "reference_lines": len(kept),
            "cer_learned_vs_reference": round(cer([k[1] for k in kept], truth), 4),
            "cer_reference_vs_page_ocr": round(cer(truth, [k[2] for k in kept]), 4),
        })
    table["evaluation"] = evaluation
    args.out.parent.mkdir(parents=True, exist_ok=True)
    args.out.write_text(json.dumps(table, ensure_ascii=False, indent=1, sort_keys=True), encoding="utf-8")

    print(f"learned from {len(learn_lines)} lines ({table['word_pairs']} word pairs); reph stored {table['reph']} "
          f"the cluster; two-part glyphs {'in parts' if table['visual_parts'] else 'whole'}")
    print(f"  {len(table['segments'])} byte pairs and {len(table['words'])} whole words remembered")
    for key, value in evaluation.items():
        print(f"  {key}: {value}")

if __name__ == "__main__":
    main()
