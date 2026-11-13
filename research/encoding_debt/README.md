# Encoding debt

How much of the Bengali in Bangladesh's government documents a machine cannot
read, why, and whether it can be repaired with a stated error rate.

A legacy Bengali font (Bijoy's SutonnyMJ, Proshika's AdarshaLipi) draws Bengali
glyphs over Latin byte values. The page looks right; the text layer holds
`Avgvi` where the page shows আমার. Search, screen readers, language models and
this project's own extraction all read the text layer.

Numbers are deliberately absent from this file, as in `docs/METHOD-CARD.md`.
Every figure is regenerated from stored files by the scripts below.

## Ground truth without labels

Every PDF names each font it uses, and `pdftohtml -xml` attributes every run of
text to its font. For a named font, the family is ground truth for the encoding
(`families.LEGACY_FAMILIES`, each family confirmed by extracting its text). No
human judgement enters the prevalence measurement.

A printer driver can strip font names (`CIDFont+F4`). Text in such fonts is
classified by its bytes (`families.scheme_of`) and always reported in separate
`*_inferred_*` columns, never merged into the name-confirmed counts.

The byte signatures were learned on the documents in
`signature_learning_draw.txt`, their guards chosen on
`detector_tuning_draw.txt`, and the detector is scored only on documents in
neither list (`validate_detector.py`), against font names it never saw. Both
lists hold stored names, which are content hashes, so they identify the same
documents on any machine.

## Pipeline

```bash
python3 -m venv ../.venv && ../.venv/bin/pip install -r ../requirements.txt
CORPUS=../../storage/app/private/ingestion/approved          # a CivicLens storage root

# Prevalence in the corpus
../.venv/bin/python fonts.py --corpus $CORPUS --out data/corpus_fonts.csv
../.venv/bin/python textshare.py --corpus $CORPUS --out data/corpus_text.csv --pages-out data/corpus_pages.csv
../.venv/bin/python validate_detector.py --text data/corpus_text.csv --out data/detector_validation.csv

# Prevalence on the government's own web, from Common Crawl's archive only
../.venv/bin/python cc_index.py --domain '*.gov.bd' --years 2024 2025 2026 --per-host 40 --out data/cc
../.venv/bin/python cc_fetch.py --sample data/cc/sample.csv --out data/cc --max-bytes 1500000000
../.venv/bin/python fonts.py --manifest data/cc/manifest.csv --out data/cc_fonts.csv
../.venv/bin/python textshare.py --manifest data/cc/manifest.csv --out data/cc_text.csv

# Repair: learn each scheme's table from the page images, check it on Bijoy's documented one
../.venv/bin/python pairs.py --text data/corpus_text.csv --scheme bijoy --docs 150 --out data/pairs_bijoy.csv
../.venv/bin/python decipher.py --pairs data/pairs_bijoy.csv --out data/table_bijoy.json --reference bijoy
../.venv/bin/python pairs.py --text data/corpus_text.csv --scheme adarshalipi --docs 150 --out data/pairs_adarshalipi.csv
../.venv/bin/python decipher.py --pairs data/pairs_adarshalipi.csv --out data/table_adarshalipi.json

# The premise on rendered pages: the page shows Bengali, the text layer does not
../.venv/bin/python validate_pages.py --pages data/corpus_pages.csv --n 50 --out data/page_validation.csv

# Consequence: what a Unicode keyword search finds today, and after repair
../.venv/bin/python consequences/search_recall.py --text data/corpus_text.csv \
    --tables data/table_bijoy.json data/table_adarshalipi.json --out data/search_recall.csv

# Consequence: whether a language model can read the text layer (local model via ollama, nothing leaves the machine)
ollama serve &                     # session only; ollama pull qwen3:1.7b first
../.venv/bin/python consequences/llm_reading.py --text data/corpus_text.csv \
    --tables data/table_bijoy.json data/table_adarshalipi.json --model qwen3:1.7b --out data/llm_reading.csv

# Certified repair: a person judges a uniform sample, then the bound is fitted by document
../.venv/bin/python audit_sample.py --text data/corpus_text.csv \
    --tables data/table_bijoy.json data/table_adarshalipi.json --n 300 --out data/audit
#   open data/audit/review.html, judge every line, export judgements.csv into data/audit/
../.venv/bin/python certify.py --audit data/audit

# Every headline number, from the outputs above
../.venv/bin/python make_figures.py          # -> data/results.json, data/results.md, data/figures/*.png

../.venv/bin/python test_encoding_debt.py    # needs poppler and tesseract (ben)
```

`data/` is ignored by git: it holds fetched PDFs and derived CSVs, all
reproducible from the commands above.

## Sources and what was not done

- **Corpus**: the PDFs CivicLens holds, grouped by publisher from the storage path.
- **Common Crawl**: `*.gov.bd` PDFs Common Crawl captured in the chosen years,
  deduplicated by content digest, at most `--per-host` per host so one prolific
  site cannot stand in for the government. Nothing is requested from a
  government server. Captures Common Crawl truncated are listed but not measured.
- A PDF's creation date is what its producer wrote, which may be the date it
  was exported rather than written.
- A third failure is counted but not repaired: a Unicode font whose map is
  broken emits Bengali-block code points Unicode never assigned
  (`bengali_unassigned`). That count is a lower bound, since a shifted map also
  emits assigned letters that are simply wrong.
- Font presence says a document contains legacy text, not how much;
  `textshare.py` measures the volume, in bytes of legacy text, which is not a
  count of Bengali letters.

## Repair

No openly licensed AdarshaLipi-to-Unicode table was found, so `decipher.py`
learns one: `pairs.py` renders each legacy run, crops it, and reads it with
Tesseract's Bengali model, producing (legacy bytes, what the page shows) with
no human labels. IBM Model 1 learns what each byte stands for; Bengali's
drawing-order rules put the output in Unicode order. The method is first scored
against Bijoy's documented converter, where the right answer is known, and only
then applied to AdarshaLipi. Documents are split into `learn` and `evaluate`
by content hash (`pairs.split_of`).
