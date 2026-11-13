"""Draw converted lines for a person to judge, and build the page to judge them on.

    python audit_sample.py --text data/corpus_text.csv --tables data/table_adarshalipi.json \
        --n 300 --out data/audit

The certification in `certify.py` needs labels, and the only admissible label
is a person looking at the page (docs/METHOD-CARD.md). Each item is one line of
legacy text: its crop of the rendered page beside its conversion. The question
is objective — does the conversion say exactly what the image says? — so one
careful reader suffices for a first audit, and a second reader's agreement can
be measured on the same file later.

Sampling is uniform over legacy lines in `evaluate`-split documents, never by
score: queueing the lines the verifier liked would make the audit unlike the
lines the system accepts. Each item also carries its verification score (the
conversion's disagreement with an OCR reading of the same crop), which the
reviewer never sees.

Writes `items.csv`, `img/<n>.png` and `review.html` — a local page that keeps
answers in the browser and exports `judgements.csv`. Nothing is published.
"""
from __future__ import annotations

import argparse
import csv
import html
import json
import os
import random
import tempfile
from pathlib import Path

from PIL import Image

from convert import ConversionError, convert
from decipher import cer
from families import legacy_family, scheme_of
from pairs import DPI, PAD, SCALE, ocr_line, split_of
from pdftools import ToolError, run, text_runs
from sources import parallel, write_csv

FIELDS = ["item", "doc", "page", "scheme", "named", "legacy", "converted", "ocr", "verification", "image"]


def candidates(path: Path) -> list[dict[str, object]]:
    try:
        runs = text_runs(path)
    except ToolError:
        return []
    out = []
    for r in runs:
        visible = [c for c in r.text if not c.isspace()]
        if not (6 <= len(visible) <= 120 and 8 <= r.height <= 40):
            continue
        scheme = legacy_family(r.family)
        named = scheme is not None
        scheme = scheme or scheme_of(r.text)
        if scheme:
            out.append({"doc": path.name, "path": str(path), "page": r.page, "scheme": scheme, "named": int(named),
                        "legacy": r.text.strip(), "box": (r.left, r.top, r.width, r.height)})
    return out


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--text", type=Path, required=True)
    parser.add_argument("--tables", type=Path, nargs="*", default=[], help="learned tables, e.g. data/table_adarshalipi.json")
    parser.add_argument("--n", type=int, default=300)
    parser.add_argument("--seed", type=int, default=0)
    parser.add_argument("--out", type=Path, default=Path("data/audit"))
    args = parser.parse_args()
    os.environ.setdefault("OMP_THREAD_LIMIT", "1")
    tables = {json.loads(t.read_text(encoding="utf-8")).get("scheme", t.stem.removeprefix("table_")): t for t in args.tables}

    with args.text.open(newline="", encoding="utf-8") as handle:
        docs = {Path(r["path"]).name: Path(r["path"]) for r in csv.DictReader(handle)
                if not r["error"] and int(r["legacy_chars"] or 0) + int(r["legacy_inferred_chars"] or 0) > 0}
    evaluate = [p for name, p in sorted(docs.items()) if split_of(name) == "evaluate"]
    pool = [c for batch in parallel(evaluate, candidates) for c in batch
            if c["scheme"] == "bijoy" or c["scheme"] in tables]
    rng = random.Random(args.seed)
    chosen = rng.sample(pool, min(args.n, len(pool)))
    print(f"{len(pool)} legacy lines in {len(evaluate)} evaluate-split documents; drew {len(chosen)}")

    images = args.out / "img"
    images.mkdir(parents=True, exist_ok=True)

    def prepare(indexed: tuple[int, dict[str, object]]) -> dict[str, object] | None:
        n, c = indexed
        try:
            converted = convert(c["legacy"], c["scheme"], tables.get(c["scheme"]))
        except ConversionError:
            return None
        with tempfile.TemporaryDirectory() as tmp:
            prefix = Path(tmp) / "page"
            try:
                run(["pdftoppm", "-r", str(DPI), "-f", str(c["page"]), "-l", str(c["page"]), "-png", "-singlefile",
                     c["path"], str(prefix)], timeout=120)
                raster = Image.open(f"{prefix}.png").convert("L")
            except (ToolError, OSError):
                return None
            left, top, width, height = c["box"]
            box = (max(0, int(left * SCALE) - PAD), max(0, int(top * SCALE) - PAD),
                   min(raster.width, int((left + width) * SCALE) + PAD),
                   min(raster.height, int((top + height) * SCALE) + PAD))
            crop = raster.crop(box)
            crop.save(images / f"{n}.png")
            ocr, _, _ = ocr_line(crop)
        return {"item": n, "doc": c["doc"], "page": c["page"], "scheme": c["scheme"], "named": c["named"],
                "legacy": c["legacy"], "converted": converted, "ocr": ocr,
                "verification": round(cer([converted], [ocr]) if ocr else 1.0, 4), "image": f"img/{n}.png"}

    items = [i for i in parallel(list(enumerate(chosen)), prepare) if i]
    write_csv(args.out / "items.csv", items, FIELDS)
    (args.out / "review.html").write_text(review_page(items), encoding="utf-8")
    print(f"{len(items)} items -> {args.out / 'items.csv'}; open {args.out / 'review.html'} to judge them")


def review_page(items: list[dict[str, object]]) -> str:
    rows = "\n".join(
        f'<section data-item="{i["item"]}"><img src="{html.escape(str(i["image"]))}" alt="">'
        f'<p lang="bn">{html.escape(str(i["converted"]))}</p>'
        f'<div><button data-v="yes">Matches</button><button data-v="no">Differs</button>'
        f'<button data-v="unsure">Can\'t tell</button></div></section>'
        for i in items)
    return f"""<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>Conversion audit</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root {{ --bg:#fff; --fg:#111; --muted:#666; --line:#ddd; --yes:#1f7a3a; --no:#b3261e; --unsure:#8a6d00; }}
@media (prefers-color-scheme: dark) {{ :root {{ --bg:#111; --fg:#eee; --muted:#aaa; --line:#333; }} }}
body {{ background:var(--bg); color:var(--fg); font:16px/1.5 system-ui, sans-serif; margin:0 auto; max-width:960px; padding:16px; }}
header {{ position:sticky; top:0; background:var(--bg); padding:8px 0; border-bottom:1px solid var(--line); }}
section {{ border-bottom:1px solid var(--line); padding:16px 0; }}
section img {{ max-width:100%; background:#fff; border:1px solid var(--line); }}
section p {{ font-size:22px; margin:8px 0; }}
button {{ font:inherit; margin-right:8px; padding:6px 14px; border:1px solid var(--line); border-radius:6px; background:transparent; color:inherit; cursor:pointer; }}
section[data-answer="yes"] button[data-v="yes"] {{ background:var(--yes); color:#fff; }}
section[data-answer="no"] button[data-v="no"] {{ background:var(--no); color:#fff; }}
section[data-answer="unsure"] button[data-v="unsure"] {{ background:var(--unsure); color:#fff; }}
</style></head><body>
<header><strong>Does the Bengali text say exactly what the image shows?</strong> Ignore spacing and line breaks.
One wrong letter, mark or digit means <em>Differs</em>. <span id="count"></span>
<button id="export">Export judgements.csv</button></header>
{rows}
<script>
const KEY = "encoding-debt-audit";
let answers = {{}};
try {{ answers = JSON.parse(localStorage.getItem(KEY) || "{{}}"); }} catch (e) {{ answers = {{}}; }}
const sections = [...document.querySelectorAll("section")];
function paint() {{
  sections.forEach(s => {{ const a = answers[s.dataset.item]; if (a) s.dataset.answer = a; else delete s.dataset.answer; }});
  document.getElementById("count").textContent = Object.keys(answers).length + " of " + sections.length + " judged.";
}}
sections.forEach(s => s.addEventListener("click", e => {{
  const v = e.target.dataset && e.target.dataset.v; if (!v) return;
  answers[s.dataset.item] = v;
  try {{ localStorage.setItem(KEY, JSON.stringify(answers)); }} catch (err) {{}}
  paint();
}}));
document.getElementById("export").addEventListener("click", () => {{
  const lines = ["item,verdict"].concat(Object.entries(answers).map(([k, v]) => k + "," + v));
  const a = document.createElement("a");
  a.href = URL.createObjectURL(new Blob([lines.join("\\n") + "\\n"], {{ type: "text/csv" }}));
  a.download = "judgements.csv"; a.click();
}});
paint();
</script></body></html>
"""


if __name__ == "__main__":
    main()
