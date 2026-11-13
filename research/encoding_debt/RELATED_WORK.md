# Related work (found 2026-09-26; re-check on Google Scholar, ACL Anthology and SSRN before drafting)

What each covers, and where this work differs. Links were reachable when found;
two pages (ResearchGate, Medium) refused automated access and are described
from their abstracts and search snippets only.

## Legacy Indic fonts in (government) documents

- **Information Loss in Digital Documents: Gurmukhi Fonts Perspective** (IEEE conference, c. 2014;
  [IEEE Xplore](https://ieeexplore.ieee.org/document/7048182/),
  [ResearchGate](https://www.researchgate.net/publication/280495796_Information_Loss_in_Digital_Documents_Gurmukhi_Fonts_Perspective)).
  Legacy ASCII-mapped Gurmukhi fonts lose information under font substitution during migration;
  includes a figure of font usage in (Punjab) government documents. *Closest precedent on
  prevalence.* Differs: Gurmukhi/India, preservation framing, no large-scale automated measurement,
  nothing on search or language models, no repair.
- **Wrecked Indian Fonts: A Problem for Digitalization of Indic Documents** (same group;
  [ResearchGate](https://www.researchgate.net/publication/280496031_Wrecked_Indian_Fonts_A_Problem_for_Digitalization_of_Indic_Documents)).
- **Born Broken: Fonts and Information Loss in Legacy Digital Documents** (International Journal of Digital
  Curation, 2011; [IJDC](https://ijdc.net/index.php/ijdc/article/view/168)). Script-independent font
  loss in legacy document collections.
- **A Survey of Language-, Font-Detection and Font-Conversion Systems for Indian Languages**
  ([ResearchGate](https://www.researchgate.net/publication/281381833_A_Survey_of_Language_-_Detection_Font_-_Detection_and_Font_-_Conversion_Systems_for_Indian_Languages)).
- Practitioner sources only, no measurement: Bangla PDF tooling notes asserting that Bangladeshi
  gazettes and circulars are mostly SutonnyMJ ([bangla-pdf-processor](https://github.com/ibrahimhasan0012/bangla-pdf-processor));
  Hindi PDF extraction ([Digital Orientalist, 2025](https://digitalorientalist.com/2025/12/02/why-extracting-hindi-text-from-pdfs-is-so-much-harder-than-english-and-how-you-can-do-it/)).

## Identifying and converting legacy encodings

- **Raj & Prahallad, Identification and Conversion of Font-Data in Indian Languages** (ICUDL 2007;
  [Semantic Scholar](https://www.semanticscholar.org/paper/IDENTIFICATION-AND-CONVERSION-ON-FONT-DATA-IN-Name-Raj-Prahallad/1e3cdee1fc97236d479a376b0a39873ccf3180d1)).
  TF-IDF font-type identification; converters from manually built glyph-map tables plus
  assimilation rules. Differs: our tables are learned from the page image, with no manual table.
- **Knight, Nair, Rathod & Yamada, Unsupervised Analysis for Decipherment Problems** (COLING/ACL 2006;
  [ResearchGate](https://www.researchgate.net/publication/220874226_Unsupervised_Analysis_for_Decipherment_Problems)).
  Treats unknown text encodings (including Hindi font encodings) as substitution ciphers, solved by
  EM with a language model, from text alone. Differs: we use the rendered glyphs as the teacher,
  which also resolves the drawing-order reordering from data.
- Open converters: `bijoy2unicode` (MIT) — measured here as mis-mapping several SutonnyMJ bytes;
  AdarshaLipi converters exist only as closed web tools. Hand-built Kruti Dev tables are common
  ([example write-up](https://medium.com/@sibiakash/indic-font-to-unicode-mapping-solving-ocr-problem-in-legacy-indian-docs-83889b305a23)).

## Downstream effects: search, retrieval, language models

- **When Good OCR Is Not Enough: Benchmarking OCR Robustness for Retrieval-Augmented Generation**
  ([arXiv 2605.00911](https://arxiv.org/pdf/2605.00911)). OCR noise, not legacy encodings.
- **Semantic Integrity Failures in Document-to-LLM Supply Chains** ([arXiv 2606.15020](https://arxiv.org/pdf/2606.15020)).
  Adversarial render/extract divergence on synthetic PDFs; one family is font decoding. Differs:
  naturally occurring legacy encodings in real government records.
- **GlotOCR Bench: OCR Models Still Struggle Beyond a Handful of Unicode Scripts**
  ([arXiv 2604.12978](https://arxiv.org/pdf/2604.12978)). Background on OCR for low-resource scripts.

## Policy context (Bangladesh)

- Bengali Language Introduction Act 1987, s. 3: court proceedings and records in Bengali; Constitution
  Art. 3 ([CommonLII](https://www.commonlii.org/bd/legis/num_act/blia1987274/),
  [Wikipedia](https://en.wikipedia.org/wiki/Bengali_Language_Introduction_Act,_1987)).
- Supreme Court "Amar Vasha" (February 2021), AI translation of judgments from English to Bangla,
  built with EkStep ([The Business Standard](https://www.tbsnews.net/bangladesh/court/supreme-court-launches-translation-software-amar-vasha-204100),
  [The Daily Star](https://www.thedailystar.net/bangladesh/news/india-bangladesh-collaboration-software-translate-sc-judgments-english-bangla-unveiled-2047193)).

## What appears new here (to verify before claiming)

1. Label-free, automated measurement of legacy encodings across a national government's PDFs at
   scale (font names + byte-scheme detector validated on held-out documents), with persistence by year.
2. Consequences measured on modern pipelines: keyword-search recall, and a language model's ability
   to read the text layer, with a Unicode control.
3. Legacy-font tables learned from rendered pages with OCR as teacher, validated against a documented
   table and better than the only open converter on real documents.
4. A certified repair: conversions accepted under a finite-sample error bound, fitted by document.
