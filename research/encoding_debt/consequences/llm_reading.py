"""Can a language model read what a PDF's text layer gives it? Before and after repair.

    python consequences/llm_reading.py --text data/corpus_text.csv \
        --tables data/table_bijoy.json data/table_adarshalipi.json --model qwen3:1.7b \
        --passages 150 --out data/llm_reading.csv

A document pipeline hands a model the text layer, not the page. Each passage
is asked two questions whose answers are known without a human label: does it
contain a given Bengali word (one that is there, one that is not)?

Three conditions:
- `legacy_raw`: legacy-encoded text exactly as extracted — what a pipeline sends today;
- `legacy_repaired`: the same passages after conversion with the learned tables;
- `unicode_control`: passages that were Unicode to begin with, which show whether
  the model can do the task in Bengali at all. Without this, a failure on legacy
  text could just be a model that cannot read Bengali.

Target words are letters only, every code point assigned, and occur in some
valid Unicode passage: real words a person would search for. The present word
is drawn from the reference text (the repaired or Unicode passage); the absent
word from other passages and checked absent. Scored by balanced accuracy,
because a small model leans towards one answer. The model runs locally
through ollama; nothing leaves the machine.
"""
from __future__ import annotations

import argparse
import csv
import json
import random
import re
import sys
import unicodedata
import urllib.request
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from convert import ConversionError, convert  # noqa: E402
from families import legacy_family, scheme_of  # noqa: E402
from pairs import split_of  # noqa: E402
from pdftools import ToolError, text_runs  # noqa: E402
from sources import parallel, write_csv  # noqa: E402

LETTERS = re.compile(r"[\u0980-\u09E3\u09F0\u09F1]{3,}")
PROMPT = ('Passage extracted from a PDF:\n<<<{passage}>>>\n\n'
          'Does the passage contain the Bengali word "{word}"? Answer only yes or no.')
FIELDS = ["condition", "doc", "scheme", "word", "truth", "answer", "correct"]


def valid_word(word: str) -> bool:
    """Every code point assigned: a text layer with a broken map emits unassigned ones."""
    try:
        return all(unicodedata.name(c) for c in word)
    except ValueError:
        return False


def passages_of(item: tuple[Path, dict[str, Path], int, int]) -> list[dict[str, str]]:
    """Legacy and Unicode passages of roughly `size` visible characters from one document."""
    path, tables, size, per_doc = item
    try:
        runs = text_runs(path)
    except ToolError:
        return []
    out: list[dict[str, str]] = []
    current: dict[str, object] | None = None
    for run in runs:
        scheme = legacy_family(run.family) or scheme_of(run.text)
        kind = "legacy" if scheme else ("unicode" if LETTERS.search(run.text) else None)
        if kind is None:
            continue
        if current is None or current["kind"] != kind or current["scheme"] != (scheme or ""):
            current = {"kind": kind, "scheme": scheme or "", "runs": []}
        current["runs"].append(run.text)  # type: ignore[union-attr]
        text = " ".join(current["runs"])  # type: ignore[arg-type]
        if len(text.replace(" ", "")) >= size:
            if sum(1 for o in out if o["kind"] == kind) >= per_doc:
                current = None
                continue  # enough of this kind from one document; keep the sample spread
            item_out = {"doc": path.name, "kind": kind, "scheme": scheme or "", "raw": text}
            if kind == "legacy":
                if scheme not in tables:
                    current = None
                    continue
                try:
                    item_out["repaired"] = convert(text, scheme, tables[scheme])
                except ConversionError:
                    current = None
                    continue
            out.append(item_out)
            current = None
    return out


def ask(model: str, prompt: str, host: str) -> str:
    body = json.dumps({"model": model, "prompt": prompt, "stream": False, "think": False,
                       "options": {"temperature": 0, "num_predict": 8}}).encode()
    request = urllib.request.Request(f"{host}/api/generate", data=body, headers={"Content-Type": "application/json"})
    with urllib.request.urlopen(request, timeout=600) as response:
        return json.loads(response.read())["response"].strip()


ANSWERS = {"yes": "yes", "no": "no", "\u09B9\u09CD\u09AF\u09BE\u0981": "yes", "\u09A8\u09BE": "no"}  # and হ্যাঁ, না


def parse(answer: str) -> str:
    """The first word, stripped of markdown and punctuation, as yes / no / other."""
    for token in answer.lower().split():
        word = re.sub(r"[^a-z\u0980-\u09FF]", "", token)
        if word:
            return ANSWERS.get(word, "other")
    return "other"


def balanced(rows: list[dict[str, object]]) -> float | None:
    pos = [r for r in rows if r["truth"] == "yes"]
    neg = [r for r in rows if r["truth"] == "no"]
    if not pos or not neg:
        return None
    return (sum(r["answer"] == "yes" for r in pos) / len(pos) + sum(r["answer"] == "no" for r in neg) / len(neg)) / 2


def bootstrap(rows: list[dict[str, object]], draws: int = 2000, seed: int = 0) -> tuple[float, float]:
    by_doc: dict[str, list[dict[str, object]]] = {}
    for r in rows:
        by_doc.setdefault(str(r["doc"]), []).append(r)
    docs = list(by_doc)
    rng = random.Random(seed)
    stats = sorted(s for s in (balanced([r for d in (rng.choice(docs) for _ in docs) for r in by_doc[d]])
                               for _ in range(draws)) if s is not None)
    return stats[int(0.025 * len(stats))], stats[int(0.975 * len(stats)) - 1]


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--text", type=Path, required=True)
    parser.add_argument("--tables", type=Path, nargs="+", required=True)
    parser.add_argument("--model", default="qwen3:1.7b")
    parser.add_argument("--host", default="http://127.0.0.1:11434")
    parser.add_argument("--passages", type=int, default=150, help="per kind (legacy, unicode)")
    parser.add_argument("--size", type=int, default=250, help="visible characters per passage")
    parser.add_argument("--seed", type=int, default=0)
    parser.add_argument("--out", type=Path, required=True)
    args = parser.parse_args()
    tables = {t.stem.removeprefix("table_"): t for t in args.tables}
    rng = random.Random(args.seed)

    with args.text.open(newline="", encoding="utf-8") as handle:
        docs = [Path(r["path"]) for r in csv.DictReader(handle) if not r["error"]
                and (int(r["bengali_unicode"] or 0) >= 500 or int(r["legacy_chars"] or 0) + int(r["legacy_inferred_chars"] or 0) >= 500)]
    # Documents the tables were not learned from, one copy of each file.
    docs = sorted({p.name: p for p in docs if split_of(p.name) == "evaluate"}.values())
    found = [p for batch in parallel([(d, tables, args.size, 3) for d in docs], passages_of) for p in batch]
    legacy = [p for p in found if p["kind"] == "legacy"]
    unicode = [p for p in found if p["kind"] == "unicode"]
    legacy = rng.sample(legacy, min(args.passages, len(legacy)))
    unicode = rng.sample(unicode, min(args.passages, len(unicode)))

    vocabulary = {w for p in unicode for w in LETTERS.findall(p["raw"]) if valid_word(w)}
    pool = sorted(vocabulary | {w for p in legacy for w in LETTERS.findall(p["repaired"]) if valid_word(w)})

    def questions(reference: str) -> list[tuple[str, str]]:
        words = [w for w in LETTERS.findall(reference) if valid_word(w)]
        real = [w for w in words if w in vocabulary] or words
        if not real:
            return []
        absent = [w for w in rng.sample(pool, min(200, len(pool))) if w not in reference]
        return [(rng.choice(real), "yes")] + ([(absent[0], "no")] if absent else [])

    plan = []
    for p in legacy:
        for word, truth in questions(p["repaired"]):
            plan.append(("legacy_raw", p, p["raw"], word, truth))
            plan.append(("legacy_repaired", p, p["repaired"], word, truth))
    for p in unicode:
        for word, truth in questions(p["raw"]):
            plan.append(("unicode_control", p, p["raw"], word, truth))
    print(f"{len(legacy)} legacy and {len(unicode)} Unicode passages from {len(docs)} evaluate-split documents; "
          f"{len(plan)} questions to {args.model}")

    rows = []
    for n, (condition, p, passage, word, truth) in enumerate(plan, start=1):
        answer = parse(ask(args.model, PROMPT.format(passage=passage, word=word), args.host))
        rows.append({"condition": condition, "doc": p["doc"], "scheme": p["scheme"], "word": word,
                     "truth": truth, "answer": answer, "correct": int(answer == truth)})
        if n % 100 == 0:
            print(f"  {n}/{len(plan)}")
    write_csv(args.out, rows, FIELDS)
    for condition in ("legacy_raw", "legacy_repaired", "unicode_control"):
        part = [r for r in rows if r["condition"] == condition]
        score = balanced(part)
        if score is None:
            continue
        low, high = bootstrap(part)
        yes = sum(r["answer"] == "yes" for r in part) / len(part)
        other = sum(r["answer"] == "other" for r in part)
        print(f"{condition:16s} n={len(part):4d} balanced accuracy {score:.3f} (document bootstrap {low:.3f}-{high:.3f}); "
              f"says yes {yes:.2f}; unparseable {other}")


if __name__ == "__main__":
    main()
