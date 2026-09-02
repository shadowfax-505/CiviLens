<?php

namespace App\Services\Extraction;

use App\Models\DiscoveredResource;
use App\Models\ExtractionField;
use App\Models\ExtractionPage;
use App\Models\ExtractionRun;
use App\Models\ExtractionTableCell;
use App\Models\ScreeningEntity;
use App\Models\SourceArtifactVersion;
use App\Models\SourceEndpoint;
use App\Models\SourcePublisher;
use Illuminate\Support\Str;

/**
 * Turn extracted pages into items a person can judge.
 *
 * Nothing created extraction fields from live documents. BenchmarkEvaluationService
 * only creates them where a gold value already exists, so the calibration set
 * could never grow beyond a fixture, and the conformal bound had nothing to be
 * computed from.
 *
 * The unit is a numeric token — an amount, a date, a reference number — because
 * that is what audit reports actually contain. They carry no labelled fields at
 * all: sixty pages were sampled and not one held three label-value pairs. A
 * token is also decomposable, so a bound computed over tokens stays valid, which
 * a bound over whole-page character accuracy would not.
 *
 * Every candidate is created with no gold value and no outcome. A generator that
 * decided correctness would be marking its own homework, and the guarantee
 * computed from it would be vacuous.
 */
class ReviewCandidateGenerator
{
    /**
     * Most a cell may hold and still be treated as holding a value.
     *
     * Two rather than one so a figure carrying a unit or a bracketed sign stays
     * eligible, and well below the three-to-seven words seen in cells that were
     * prose rather than table.
     */
    /**
     * Pages held in memory at once.
     *
     * Each carries its whole extracted text, so this is the only thing standing
     * between a corpus that has grown five-fold and the 128 MB this command is
     * given. Loading them all exhausted it at 8,900 pages.
     */
    private const PAGES_PER_BATCH = 200;

    private const MAX_VALUE_CELL_WORDS = 2;

    /**
     * Acquired, archived, and not a document anybody publishes as one.
     *
     * HTML is the site around a document; JSON is a record feed archived for
     * provenance. Both belong in the corpus and neither belongs in a queue that
     * asks whether characters were read correctly.
     */
    private const NON_DOCUMENT_MEDIA_TYPES = ['text/html', 'application/json', 'text/plain'];

    /**
     * The kind that is carried as context and never certified.
     *
     * ADR-024 settles why a holding is on this footing: a judgment states that
     * a court **held** something, and that conclusion is the court's rather
     * than this project's. The certification machinery has nothing to say about
     * whether a court was right — what it bounds is whether a string was read
     * off the page correctly. So the sentence is stored, shown and exported,
     * and it carries no score and no level.
     */
    public const CITED_CONTEXT_KIND = 'finding_span';

    /**
     * Fewest words a finding span may hold.
     *
     * The rule that keeps a holding out of the certified set has to be
     * mechanical, because "never certified" cannot rest on nobody having
     * judged one yet: under ADR-018's ladder a stratum with a handful of
     * labels borrows a coarser threshold, so one label on one finding span
     * would let every scored finding span be accepted against a threshold
     * fitted on one-word dates.
     *
     * What makes it mechanical is length. `ValueLocator` joins at most sixteen
     * recognised words to make one value, and a value it cannot locate scores
     * `null` and defers by rule (ADR-019). Measured over the 3,628 candidate
     * spans on the 3,259 court pages that carry word geometry, by running the
     * locator's own search against each page's stored word boxes:
     *
     *   16 words or fewer   702 of 716 located   (98%)
     *   17 words or more    0 of 2,912 located
     *
     * So seventeen is a measured cut and not a guess with a margin bolted on.
     * The concern it answers — that a recognizer which merges two words into
     * one token covers seventeen words of text in sixteen boxes — happens zero
     * times in 2,912 on this corpus.
     *
     * The 716 spans this discards are the short ones, "I find merit in the
     * Rule.", and `disposition` already carries what they say in the form the
     * court wrote it.
     *
     * **Raising `ValueLocator::MAX_WORDS_PER_VALUE` without raising this makes
     * finding spans certifiable.** Nothing in the schema would say so, for the
     * reason ADR-019's last addendum records: a value that stops deferring
     * simply starts being accepted, and the bound stays true of a population
     * that quietly grew. `CourtHoldingsAndDispositionsTest` is the tripwire.
     */
    public const MIN_FINDING_SPAN_WORDS = 17;

    /**
     * How far either side of a holding cue its sentence is looked for.
     *
     * Bytes, and bounded on purpose. A page whose recognizer dropped every
     * full stop has no sentence to delimit, and a span that runs to the edge of
     * the window is a page without punctuation rather than a holding — so it is
     * discarded rather than stored at whatever length the window allows.
     */
    private const FINDING_SPAN_LOOKBACK = 900;

    private const FINDING_SPAN_LOOKAHEAD = 1500;

    /**
     * Words whose full stop does not end a sentence.
     *
     * Lower case, matched against the ASCII letters immediately before the
     * stop. Without them a holding was cut off at the judge's name: "the
     * sentence passed by the courts below is modified as under; the
     * convict-petitioner Md." ended a span, because `Md.` is a full stop
     * followed by a space and a capital letter, which is exactly the shape of a
     * sentence boundary.
     */
    private const SENTENCE_ABBREVIATIONS = [
        'no', 'nos', 'md', 'mr', 'mrs', 'ms', 'dr', 'vs', 'ltd', 'co', 'govt',
        'hon', 'smt', 'prof', 'sl', 'art', 'viz', 'etc', 'rs', 'tk', 'ed', 'jr', 'st',
    ];

    /**
     * Conjunctions that make a disposition a hypothesis rather than an act.
     *
     * 101 of the 3,098 disposition-shaped spans in the court corpus sit behind
     * one of these, and every one read is counsel's submission or the court
     * weighing an option: "the complainant has no objection if the Rule is made
     * absolute", "they would produce above additional evidence if the suit is
     * remanded". The characters are on the page and the matter was not disposed
     * of that way, which is the same class of error as a figure read out of the
     * middle of a longer number.
     */
    private const HYPOTHETICAL_CONJUNCTIONS = [
        'if', 'unless', 'whether', 'until', 'till', 'lest', 'when', 'where', 'once',
        'though', 'although',
    ];

    /**
     * Participles that make the matter somebody else's object.
     *
     * "the judgment and decree passed by the learned Senior Assistant Judge in
     * Title Suit No. 94 of 2004 **dismissing the suit is hereby upheld**" is
     * the trial court's decree being upheld, not the suit. Anchored on the
     * matter noun the span reads as the opposite of what the page says, and
     * unlike a misreading there is nothing wrong with the characters — the span
     * is a fragment of a longer noun phrase.
     *
     * A closed list of adjudicative verbs rather than any `-ing` word, because
     * `connecting` and `impugned` qualify the matter instead of governing it:
     * "the connecting Rule is disposed of" is a real disposition and a blanket
     * participle rule would drop it. 25 spans in the corpus are dropped here.
     *
     * The two guards together take 126 of 3,098, and nothing else does: the
     * 2,972 that survive them are exactly what this kind contributes, so no
     * disposition is lost to an overlap with another kind.
     */
    private const ADJUDICATIVE_PARTICIPLES = [
        'dismissing', 'decreeing', 'allowing', 'rejecting', 'discharging', 'disposing',
        'remanding', 'upholding', 'affirming', 'setting', 'granting', 'refusing',
        'quashing', 'staying', 'condoning', 'directing', 'modifying', 'confirming',
        'maintaining',
    ];

    /** Determiners the disposition pattern takes as part of the span itself. */
    private const DISPOSITION_DETERMINERS = ['a', 'an', 'the', 'this', 'that', 'these', 'those'];

    /**
     * The amount pattern, so a caller can ask what this class would produce
     * without duplicating the expression and drifting from it.
     */
    public static function amountPattern(): string
    {
        return self::PATTERNS['amount'];
    }

    public function __construct(
        private readonly BengaliTextPlausibility $plausibility,
        private readonly AmountGrouping $grouping,
    ) {}

    /**
     * Amounts, dates and reference numbers as these documents write them,
     * including Bengali digits, which appear throughout the audit corpus.
     */
    private const PATTERNS = [
        // Day and month bounded, or a reference like 44.07.9000 reads as a date.
        'date' => '/\b(0?[1-9]|[12]\d|3[01])[.\-\/](0?[1-9]|1[0-2])[.\-\/](\d{4}|\d{2})\b/u',
        // A grouping comma or a decimal, or at least five digits. Without that
        // every bare year matched: "2016" was queued as an Amount, and a
        // reviewer then had to decide whether a correctly read year is a
        // correctly read amount, which is not the question being asked.
        // The sign is part of the figure. Read without it, a reappropriation of
        // -100.00 crore is recorded as +100.00 and the reviewer is shown a value
        // the page does not contain. Accounting parentheses mean the same thing
        // and are captured the same way, as written rather than interpreted.
        // A minus directly after a digit is a range, not a sign: 2013-2017 must
        // not yield -2017, so the sign is only taken where no digit precedes it.
        // A match may not begin immediately after a comma, or end immediately
        // before a decimal digit. Without those two guards the pattern read
        // fragments of numbers it could not match whole: ৬.০২,৪৫,৩৯৫ — a
        // thousands separator the recognizer read as a point — yielded
        // ৪৫,৩৯৫, and the section number ৩.৬.৭ yielded ৩.৬. Both are values
        // the page does not contain, and a reviewer shown one is being asked
        // about a figure that is not there.
        //
        // The fractional part takes the same digits as the integer part.
        // Accepting Bengali digits before the point and only Latin ones after
        // it is what sent ৬.০২ down this path to begin with.
        //
        // A minus after a closing bracket is not a sign. These reports write a
        // difference as (১,৫৯৪.৫২-১,১১৬.৮২)-৪৭৭.৭০, where the last minus
        // introduces the result; reading it as negative reverses the figure.
        'amount' => '/(?<![\w.,])(?:\((?:[-\x{2212}]\x{0020}?)?(?:[\d\x{09E6}-\x{09EF}]{1,3}(?:[,][\d\x{09E6}-\x{09EF}]{2,3})+(?:\.[\d\x{09E6}-\x{09EF}]{1,2})?|[\d\x{09E6}-\x{09EF}]+\.[\d\x{09E6}-\x{09EF}]{1,2}|[\d\x{09E6}-\x{09EF}]{5,})\)|(?<![\d\x{09E6}-\x{09EF}\)])[-\x{2212}]?(?:[\d\x{09E6}-\x{09EF}]{1,3}(?:[,][\d\x{09E6}-\x{09EF}]{2,3})+(?:\.[\d\x{09E6}-\x{09EF}]{1,2})?|[\d\x{09E6}-\x{09EF}]+\.[\d\x{09E6}-\x{09EF}]{1,2}|[\d\x{09E6}-\x{09EF}]{5,}))(?![\w])(?!\.[\d\x{09E6}-\x{09EF}])/u',
        'reference' => '/\b[\d\x{09E6}-\x{09EF}]{2,}(?:\.[\d\x{09E6}-\x{09EF}]{2,}){2,}\b/u',
        // Organisations, which is what gives the screener Bangladeshi names to
        // screen at all — it holds 1,401 debarment entities and, until now,
        // nothing from this corpus to compare them against.
        //
        // Only the Messrs form. `মেসার্স` and `M/S` mean "the firm of", so what
        // follows is a firm name and not a description of one. Anchoring on
        // `ব্যাংক` or `কোম্পানি` instead was tried over the whole corpus and
        // produced "বিভিন্ন বাণিজ্যিক ব্যাংক" — "various commercial banks" —
        // and "এবং কর্পোরেশন"; a regex does not find names in Bengali prose,
        // and asking a reviewer to judge those wastes the scarcest thing this
        // project has. This form yields 43 distinct names across the corpus,
        // among them M/S SKYNET SEA FOODS and M/S Argon Denims Ltd., which are
        // the contractors screening is for.
        //
        // The span ends at a particle. `এর` and `কে` mark the noun before them,
        // so what follows is the sentence resuming rather than more of the name.
        // A judgment names itself in its first lines: "WRIT PETITION NO. 8951 OF
        // 2025", "Criminal Revision No.204 of 2007". Measured over 563 read
        // judgments, 401 of them carry one in this form — the identifier by
        // which a case is cited, looked up and tracked, and short enough for
        // the locator to find again on the page.
        'case_number' => '/\b(?:Writ Petition|Criminal Appeal|Criminal Revision|Civil Revision|Civil Appeal|Criminal Miscellaneous(?: Case)?|Contempt Petition|First Appeal|Second Appeal)\s+(?:No\.?|Nos\.?)\s*\d[\d,\s]{0,12}\s+of\s+(?:19|20)\d{2}/iu',

        // A precedent, as lawyers cite one: "54 DLR (AD) 111", "15 BLC (AD)
        // 222". Volume, reporter, division, page — the address of a decision
        // in a printed law report, and how a judgment points at the authority
        // it is relying on.
        //
        // The reporters are named rather than matched as any capitalised
        // abbreviation. A generic pattern found "OF" 163 times, out of "No.
        // 8951 OF 2025", and "PW" 16 times, out of "prosecution witness 3".
        // Naming them says plainly which reports this recognises, and the ones
        // listed are those the corpus actually cites: DLR leads with 446
        // appearances, then BLC, BLD, BLT, MLR.
        'law_report_citation' => '/\b\d{1,4}\s+(?:DLR|BLC|BLD|BLT|MLR|ALR|ADC|CLR|SCOB|BCR|BSCR|SCC)\s*(?:\((?:AD|HCD|HD|HC|\d{4})\)\s*)*\d{1,4}\b/u',

        // "Article 102(2)(a)(ii) of the Constitution", "Section 410 of the Code
        // of Criminal Procedure". 396 of 563 judgments cite a provision this
        // way, and this is what makes registering BD Laws worth the plaintext
        // exception: a citation stops being a string and becomes resolvable.
        'statutory_reference' => '/\b(?:Article|Section|Rule|Order)\s+\d+[A-Za-z]?(?:\([^)\n]{1,12}\))*\s+of\s+the\s+[A-Z][A-Za-z0-9\s,\.\-]{3,60}?(?=[,;\.]|\s+(?:and|which|as|is|are|was|were)\b)/u',

        // What the court did with the matter, as the court writes it: "the Rule
        // is made absolute", "the appeal is dismissed", "the Rule is
        // discharged", "the Rule is disposed of", "above suit is remanded".
        // 2,972 of them on 2,673 of this publisher's 31,995 pages, three to
        // seven words each, which is inside the sixteen the locator can span —
        // so unlike a holding this is an ordinary certifiable kind.
        //
        // Present tense and no auxiliary, which is what separates the court's
        // own act from everything else on the page that has the same words in
        // it. "should be made absolute" and "may be made absolute" are
        // counsel's prayer; "was made absolute" and "the Rule issued in the
        // writ petition was made absolute" are the history of another case;
        // "is liable to be discharged" is the court reasoning towards a
        // disposition it has not yet pronounced; "are being disposed of by this
        // single judgment" is the procedural fact that matters were heard
        // together. Requiring `is` or `are` immediately before the verb, with
        // only an adverb between, excludes all five without naming any of them.
        //
        // Three optional slots before the matter noun — determiner, then
        // adjective, then which kind of matter — because "the connecting Rule
        // is disposed of" needs all three and a single alternation captured
        // only the last word before the noun, leaving the value starting at
        // `connecting`. It reads as a fragment of a phrase, which is the shape
        // of error this file guards against everywhere else.
        //
        // The comma after `is` is real: "The criminal appeal is, therefore,
        // dismissed".
        //
        // The trailing stop or comma is taken when the page prints one, and
        // this is not cosmetic. The locator matches a run of recognised words
        // against the value and requires them to be equal; the recognizer's
        // last word is `discharged.` with the sentence's full stop attached,
        // so a value that stopped at the last letter could never equal it.
        // Measured against the real locator over the pages that carry word
        // geometry, dispositions located 542 of 1,430 times (37.9%) without the
        // punctuation and 1,419 of 1,430 (99.2%) with it — and an unlocatable
        // value scores null and defers by rule (ADR-019), so the difference is
        // between a kind that can be certified once somebody judges it and one
        // that defers forever while looking fine.
        //
        // This is the same defect ADR-019's first addendum records for
        // amounts, where the figure never carried the `/-` the recognized word
        // always did. That one was fixed in the locator, which tolerates a
        // trailing currency terminator; a sentence terminator is not in that
        // list, and adding it there would be the better fix and is not this
        // file's to make.
        //
        // Zero matches across all 11,750 pages of the audit, gazette and
        // evaluation corpus, so this cannot put court furniture into another
        // publisher's queue.
        'disposition' => '/\b(?:the|this|these|those|both|all)?\s*(?:instant|present|connected|connecting|impugned|aforesaid|said|above|two)?\s*(?:writ|criminal|civil|jail|death|first|second|contempt|miscellaneous|review|leave)?\s*(?:Rules?|appeals?|petitions?|applications?|revisions?|references?|judgments?|orders?|decrees?|convictions?|sentences?|suits?)\s+(?:is|are),?\s+(?:hereby\s+|therefore,?\s+|thus\s+|accordingly,?\s+|also\s+|however,?\s+)*(?:made\s+absolute|discharged|dismissed|disposed\s+of|allowed|rejected|set\s+aside|remanded|upheld)\b[.,;:]?/iu',

        // The cue a holding is stated with, not the holding itself.
        //
        // The stored value is the whole sentence the cue sits in, and the
        // sentence is found by `findingSpans()` rather than by this expression.
        // A regex that matched from one sentence boundary to the next needed an
        // unbounded run of "any character that is not a sentence break", and on
        // pages whose recognizer left no full stops at all — Bengali audit
        // tables — PCRE returned false with "JIT stack limit exhausted". A
        // pattern that fails on some pages and not others narrows the corpus
        // silently, which is the failure mode this project keeps finding, so
        // the expression stays small enough to be safe on any page and the
        // sentence is delimited by a scan.
        //
        // First person, because that is how this court states a holding: "we
        // hold that", "we find no merit in the Rule", "I find substance in this
        // civil revisional application", "we are of the view that", "I am of
        // the opinion that". 3,608 spans on 2,903 pages. `it is held that` is
        // deliberately absent — in this corpus it almost always introduces a
        // quotation from another judgment, so the holding it names is somebody
        // else's.
        'finding_span' => '/\b(?:[Ww]e|I)\s+(?:hold|find|do\s+not\s+find|am\s+of\s+the\s+(?:view|opinion)|are\s+of\s+the\s+(?:view|opinion))\b/u',

        'organisation' => '/(?:মেসার্স|মেসাস|M\/S\.?|M\/s\.?)\s+(?:(?!(?:এর|কে|হতে|থেকে|এবং|নং|নামে|নামীয়|at|DT|কর্তৃক|প্রকৃত|বরাবর)\s)[\x{0980}-\x{09FF}A-Za-z][\x{0980}-\x{09FF}A-Za-z0-9.\x{0027}&-]*\s+){0,3}(?!(?:এর|কে|হতে|থেকে|এবং|নং|at|DT)\s)[\x{0980}-\x{09FF}A-Za-z][\x{0980}-\x{09FF}A-Za-z0-9.\x{0027}&-]*/u',
    ];

    /**
     * Candidates taken from table cells, which carry their row and column.
     *
     * Generated from the cell rather than matched to it afterwards. A figure
     * inside a cell knows which row and column it sat in by construction, so the
     * ambiguity that defeats text matching — 81 of 300 sampled values appear
     * more than once on their own page — never arises.
     *
     * @return array{created: int, cells: int}
     */
    public function generateFromCells(int $limit = 500): array
    {
        $created = 0;
        $cells = 0;

        $candidates = ExtractionTableCell::query()
            ->with('page')
            ->whereNotNull('text')
            ->where('word_count', '>', 0)
            // A cell holding a figure holds one word. On the page measured, every
            // value cell held exactly one and the cells holding three to seven
            // were running prose beneath the table that the detector's box had
            // swept in; a token taken from those would carry a row and column it
            // never sat in, which is worse than having no provenance at all.
            ->where('word_count', '<=', self::MAX_VALUE_CELL_WORDS)
            ->inRandomOrder()
            ->limit(max(1, $limit))
            ->get();

        foreach ($candidates as $cell) {
            $cells++;
            $page = $cell->page;

            if (! $page instanceof ExtractionPage) {
                continue;
            }

            foreach ($this->tokens((string) $cell->text) as $token) {
                if (ExtractionField::query()
                    ->where('extraction_table_cell_id', $cell->getKey())
                    ->where('extracted_value', $token['value'])
                    ->exists()) {
                    continue;
                }

                ExtractionField::query()->create([
                    'extraction_run_id' => $page->extraction_run_id,
                    'extraction_page_id' => $page->getKey(),
                    'extraction_table_cell_id' => $cell->getKey(),
                    'field_key' => $token['kind'],
                    'field_type' => 'string',
                    'extracted_value' => $token['value'],
                    'normalized_value' => $this->normalisedValue($token['kind'], $token['value']),
                    'script_class' => $page->script_class ?? 'unknown',
                    'publisher_group' => $this->publisherGroup($page),
                    'confidence' => $page->confidence,
                    'nonconformity_score' => $this->initialScore($page, $token['kind']),
                    'decision' => 'pending',
                    'evidence_page_number' => $page->page_number,
                    // Offsets are within the cell, not the page: the cell is the
                    // context a reviewer is shown.
                    'evidence_offset_start' => $token['start'],
                    'evidence_offset_end' => $token['start'] + mb_strlen($token['value']),
                    'gold_source' => null,
                ]);

                $created++;
            }
        }

        return ['created' => $created, 'cells' => $cells];
    }

    /**
     * @return array{created: int, pages: int, unreadable_pages: int, pages_without_new_candidates: int}
     */
    public function generate(int $limit = 200): array
    {
        $created = 0;
        $pages = 0;

        // Counted apart. A page rejected as unreviewable and a page already
        // harvested down to its last figure are opposite states, and reporting
        // both as "skipped" once made a corpus that had nothing left to give
        // look like a corpus the reader could not read.
        $unreadable = 0;
        $exhausted = 0;

        $candidates = ExtractionPage::query()
            ->with('run')
            ->whereNotNull('extracted_text')
            // Documents, not the websites they were found on. Discovery follows
            // every link a listing carries, so acquisition holds navigation
            // pages too — 58 of one publisher's 59 acquisitions are its own
            // menus and sector pages. Their text extracts perfectly well and
            // says "HOME | LINK | CONTACT US", and a reviewer asked to adjudicate
            // that is being asked to certify website furniture.
            ->whereHas('run.artifactVersion', fn ($query) => $query->whereNotIn('media_type', self::NON_DOCUMENT_MEDIA_TYPES))
            // One copy of each file. A duplicated document duplicates its
            // candidates, and a calibration set drawn from a pool where the
            // same document appears twice is no longer a sample of distinct
            // documents — which is the assumption the bound rests on.
            ->whereIn('extraction_run_id', ExtractionRun::query()
                ->whereIn('source_artifact_version_id', SourceArtifactVersion::query()->canonical()->select('id'))
                ->select('id'))
            // Reference lists are inputs, not corpus. The debarment CSV is
            // archived as a document and read as one, and it produced 901 review
            // candidates asking whether a World Bank record number was read
            // correctly — a question about somebody else's published register,
            // not about a Bangladeshi document this project certifies.
            ->whereNotIn(
                'extraction_run_id',
                ExtractionRun::query()
                    ->whereIn('source_artifact_version_id', ScreeningEntity::query()->select('source_artifact_version_id'))
                    ->select('id'),
            )
            // A page the recognizer would not vouch for is not worth a
            // reviewer's attention: they would be adjudicating text the system
            // has already declined to stand behind.
            ->where('extraction_path', '!=', SelectiveOcrService::ABSTAINED)
            ->where('character_count', '>', 50)
            // Identifiers first, then the pages themselves in batches. Loading
            // every candidate page at once meant holding every page's text in
            // memory: fine at 1,800 pages and fatal at 8,900, where it exhausted
            // 128 MB and killed the command outright.
            //
            // Sampling stays random. Taking the first N by id instead would
            // quietly bias a small sample toward the pages acquired earliest,
            // and the whole reason this draw is random is that a calibration set
            // must look like what the system actually reads.
            ->inRandomOrder()
            ->limit(max(1, $limit))
            ->pluck('id');

        foreach ($candidates->chunk(self::PAGES_PER_BATCH) as $batch) {
            foreach (ExtractionPage::query()->with('run')->findMany($batch->all()) as $page) {
                $pages++;
                $before = $created;

                // A page whose text layer is legacy-font mojibake is unreviewable.
                // Routing sends such pages to OCR now, but pages extracted before
                // that fix are still stored, and 88 percent of the first batch of
                // candidates were drawn from them. A reviewer would have marked
                // nine in ten incorrect and the bound would have described an
                // encoding bug rather than how well the system reads.
                if ($this->plausibility->isImplausible((string) $page->extracted_text)) {
                    $unreadable++;

                    continue;
                }

                foreach ($this->tokens((string) $page->extracted_text) as $token) {
                    // On a page the recognizer would not vouch for as a whole,
                    // each value has to earn its own place.
                    //
                    // The page-level mean is a coin flip at ranking errors —
                    // ADR-019 measured AUC 0.511 — but the *token's own*
                    // confidence is strongly predictive. Across 719 labelled
                    // values located in the geometry, tokens read at 80 or above
                    // were wrong 3.6% of the time, which is the corpus reviewer
                    // rate; below 80 they were wrong 28% of the time. So the
                    // threshold of 80 was never the wrong number. It was applied
                    // to the page instead of to the value.
                    if ($page->extraction_path === SelectiveOcrService::LOW_CONFIDENCE
                        && ! $this->tokenReadWell($page, $token['value'])) {
                        continue;
                    }

                    if (ExtractionField::query()
                        ->where('extraction_page_id', $page->getKey())
                        ->where('evidence_offset_start', $token['start'])
                        ->exists()) {
                        continue;
                    }

                    ExtractionField::query()->create([
                        'extraction_run_id' => $page->extraction_run_id,
                        'extraction_page_id' => $page->getKey(),
                        'field_key' => $token['kind'],
                        'field_type' => 'string',
                        'extracted_value' => $token['value'],
                        'normalized_value' => $this->normalisedValue($token['kind'], $token['value']),
                        'script_class' => $page->script_class ?? 'unknown',
                        'publisher_group' => $this->publisherGroup($page),
                        'confidence' => $page->confidence,
                        'nonconformity_score' => $this->initialScore($page, $token['kind']),
                        'decision' => 'pending',
                        'evidence_page_number' => $page->page_number,
                        'evidence_offset_start' => $token['start'],
                        'evidence_offset_end' => $token['start'] + mb_strlen($token['value']),
                        'gold_source' => null,
                    ]);

                    $created++;
                }

                if ($created === $before) {
                    $exhausted++;
                }
            }
        }

        return [
            'created' => $created,
            'pages' => $pages,
            'unreadable_pages' => $unreadable,
            'pages_without_new_candidates' => $exhausted,
        ];
    }

    /**
     * @return list<array{kind: string, value: string, start: int}>
     */
    public function tokens(string $text): array
    {
        $found = [];
        $claimed = [];

        // Dates first, then references, then amounts. A date claimed as a
        // reference is a mislabelled item a reviewer then has to argue with,
        // and an amount would otherwise swallow half of either.
        // Case number before the generic reference pattern: "No. 8951 of 2025"
        // would otherwise be harvested as a bare reference and the case would
        // be identified by a fragment of itself.
        // Disposition before the digit kinds and after the identifier ones: it
        // holds no digits, so nothing it claims was ever going to be a figure,
        // and putting it ahead of them keeps the claim order a statement about
        // which kind wins a contested span rather than an accident of listing.
        foreach (['case_number', 'law_report_citation', 'statutory_reference', 'disposition', 'date', 'reference', 'organisation', 'amount'] as $kind) {
            if (preg_match_all(self::PATTERNS[$kind], $text, $matches, PREG_OFFSET_CAPTURE) === false) {
                continue;
            }

            foreach ($matches[0] as $match) {
                $start = mb_strlen(substr($text, 0, $match[1]));
                $end = $start + mb_strlen($match[0]);

                foreach ($claimed as [$claimedStart, $claimedEnd]) {
                    if ($start < $claimedEnd && $end > $claimedStart) {
                        continue 2;
                    }
                }

                // A token ending in a separator is a fragment of a figure the
                // recognizer mangled: "US$23,¢8,80b" yielded "23,". Asking
                // whether that matches the page has no useful answer — the
                // characters are there, but the value is not a value.
                if ($kind === 'amount' && ! $this->wellFormedAmount($match[0])) {
                    continue;
                }

                // The same words appear on these pages as counsel's prayer and
                // as the record of another court's order, and only one of the
                // three is the disposition of this matter.
                if ($kind === 'disposition' && ! $this->isCourtDisposing($text, $match[1])) {
                    continue;
                }

                $value = $match[0];

                // A court citation runs across a line break more often than
                // not — "Writ Petition No. 9477 of\n2016" — and the locator
                // matches a run of recognised words, which carry no newlines.
                // Left as captured, every one of these would be unlocatable by
                // construction and defer by rule without anyone learning why.
                //
                // Only the two long kinds. The short ones do not span lines,
                // and normalising them would change candidates the existing
                // labels were drawn from.
                //
                // A disposition joins them: "the Rule is / made absolute" is
                // how a five-word span lands when it straddles a line, and it
                // is a new kind, so there are no labels for the change to move
                // underneath.
                if (in_array($kind, ['case_number', 'statutory_reference', 'law_report_citation', 'disposition'], true)) {
                    $value = trim((string) preg_replace('/\s+/u', ' ', $value));
                }

                if ($kind === 'amount') {
                    $value = $this->withoutResultMarker($value, $text, $match[1]);

                    // Dropping the marker moves the token one character along,
                    // and the offset is what the review screen locates the
                    // value by.
                    $start += mb_strlen($match[0]) - mb_strlen($value);
                }

                $claimed[] = [$start, $end];
                $found[] = ['kind' => $kind, 'value' => $value, 'start' => $start];
            }
        }

        // Finding spans last, and outside the claim entirely.
        //
        // Every other kind competes for characters, because two kinds reading
        // the same characters two ways is a mislabelled item a reviewer has to
        // argue with. A finding span is not a competitor: it is the sentence
        // the values sit in, and it contains dates, amounts, case numbers and
        // citations by construction. Made to respect the claim it would be
        // dropped by the first date inside it; allowed to claim first it would
        // suppress every value in the sentence. Either way the corpus loses
        // what it already reads, so the span neither consults nor extends the
        // claim.
        foreach ($this->findingSpans($text) as $span) {
            $found[] = $span;
        }

        return $found;
    }

    /**
     * The sentences in which this page states a holding.
     *
     * Cue first, then the sentence around it, because the sentence cannot be
     * delimited by a regex that is safe to run on every page in the corpus —
     * see the note on the `finding_span` pattern.
     *
     * @return list<array{kind: string, value: string, start: int}>
     */
    private function findingSpans(string $text): array
    {
        $count = preg_match_all(self::PATTERNS[self::CITED_CONTEXT_KIND], $text, $matches, PREG_OFFSET_CAPTURE);

        if ($count === false || $count === 0) {
            return [];
        }

        // The last byte that is not trailing whitespace, so the full stop that
        // closes the last sentence on a page can be recognised as one. It is
        // followed by nothing rather than by a capital letter, which is the
        // only shape of boundary the forward scan would otherwise see.
        $lastMeaningful = strlen(rtrim($text)) - 1;

        $found = [];
        $consumedTo = 0;

        foreach ($matches[0] as $match) {
            [$cue, $offset] = [(string) $match[0], (int) $match[1]];

            // A sentence stating its holding twice — "we find merit in the
            // Rule and we hold that the memo suffers from illegality" — is one
            // holding, not two.
            if ($offset < $consumedTo) {
                continue;
            }

            $sentence = $this->sentenceAround($text, $offset, strlen($cue), $lastMeaningful);

            if ($sentence === null) {
                continue;
            }

            $value = trim((string) preg_replace('/\s+/u', ' ', substr($text, $sentence['start'], $sentence['length'])));
            $words = preg_split('/\s+/u', $value);

            if ($value === '' || ! is_array($words) || count($words) < self::MIN_FINDING_SPAN_WORDS) {
                continue;
            }

            $consumedTo = $sentence['start'] + $sentence['length'];

            $found[] = [
                'kind' => self::CITED_CONTEXT_KIND,
                'value' => $value,
                'start' => mb_strlen(substr($text, 0, $sentence['start'])),
            ];
        }

        return $found;
    }

    /**
     * Where the sentence holding this cue begins and ends, in bytes.
     *
     * Null when either end is not found inside the window. A holding whose
     * sentence cannot be delimited is not stored: the alternative is a value
     * that begins or ends wherever the window happened to fall, and a citation
     * to a fragment of a sentence misrepresents the court as surely as a figure
     * read out of the middle of a longer number.
     *
     * @return array{start: int, length: int}|null
     */
    private function sentenceAround(string $text, int $cueOffset, int $cueLength, int $lastMeaningful): ?array
    {
        $windowStart = max(0, $cueOffset - self::FINDING_SPAN_LOOKBACK);
        $start = null;

        for ($position = $cueOffset - 1; $position >= $windowStart; $position--) {
            if ($this->endsSentence($text, $position, $lastMeaningful) || $this->endsParagraph($text, $position)) {
                $start = $position + 1;

                break;
            }
        }

        // A page begins mid-document but a sentence can still begin with it,
        // and the first holding on a page is often in the first sentence.
        if ($start === null && $windowStart === 0) {
            $start = 0;
        }

        if ($start === null) {
            return null;
        }

        // The boundary is the punctuation, so the sentence starts after
        // whatever whitespace follows it.
        while ($start < $cueOffset && ($text[$start] === ' ' || $text[$start] === "\n" || $text[$start] === "\r" || $text[$start] === "\t")) {
            $start++;
        }

        $limit = min(strlen($text), $cueOffset + $cueLength + self::FINDING_SPAN_LOOKAHEAD);

        // Punctuation only on the way forward, where a paragraph break will not
        // do. A paragraph break certainly begins a sentence, which is why the
        // backward scan accepts one; it does not certainly end one. Judgments
        // break a line and leave a page mid-sentence — "we are of the view that
        // the Appeal and" is where page 6 of one of them stops — and a span
        // closed at that break is half a holding presented as a whole one. A
        // terminal stop is the page's own statement that the sentence finished.
        for ($position = $cueOffset + $cueLength; $position < $limit; $position++) {
            if ($this->endsSentence($text, $position, $lastMeaningful)) {
                return ['start' => $start, 'length' => $position + 1 - $start];
            }
        }

        return null;
    }

    /**
     * Whether the byte at this position is the last character of a sentence.
     *
     * A full stop, question mark or exclamation followed by whitespace and then
     * something a sentence starts with — a capital, an opening quote, a bracket
     * — or the same punctuation at the end of the page, where nothing follows
     * it to say so.
     *
     * Byte positions rather than characters throughout. Every character this
     * examines is ASCII, and an ASCII byte cannot occur inside a UTF-8
     * multi-byte sequence, so scanning bytes cannot land in the middle of a
     * Bengali character and cannot be fooled by one.
     */
    private function endsSentence(string $text, int $position, int $lastMeaningful): bool
    {
        $character = $text[$position];

        if ($character !== '.' && $character !== '?' && $character !== '!') {
            return false;
        }

        if ($position !== $lastMeaningful
            && preg_match('/^\s+[\p{Lu}\x{201C}\x{201D}"(]/u', substr($text, $position + 1, 8)) !== 1) {
            return false;
        }

        // The letters immediately before the stop, at most as many as the
        // longest abbreviation, so `Md.` and `No.` do not end a sentence.
        $letters = '';

        for ($back = $position - 1; $back >= 0 && strlen($letters) < 4; $back--) {
            $byte = $text[$back];

            if (($byte >= 'a' && $byte <= 'z') || ($byte >= 'A' && $byte <= 'Z')) {
                $letters = $byte.$letters;

                continue;
            }

            break;
        }

        return ! in_array(strtolower($letters), self::SENTENCE_ABBREVIATIONS, true);
    }

    /**
     * Whether the byte at this position closes a blank line.
     *
     * Used only to find where a sentence starts. The recognizer drops a full
     * stop often enough that a paragraph break is the more reliable mark of
     * where one begins, and a span that began at the previous full stop would
     * otherwise swallow the paragraph before it.
     */
    private function endsParagraph(string $text, int $position): bool
    {
        if ($text[$position] !== "\n") {
            return false;
        }

        $back = $position - 1;

        while ($back >= 0 && ($text[$back] === ' ' || $text[$back] === "\t" || $text[$back] === "\r")) {
            $back--;
        }

        return $back >= 0 && $text[$back] === "\n";
    }

    /**
     * Whether a disposition-shaped span is this court disposing of this matter.
     *
     * Read off the two words before the span, which is as far as the two
     * failures found in the corpus reach. `if the said appeal is disposed of`
     * puts the pattern's own determiner between the conjunction and the matter,
     * so one word back is not far enough; nothing observed needs three.
     *
     * ASCII splitting and ASCII lowercasing on purpose, without the `u`
     * modifier. The window is taken by byte offset and may begin inside a
     * Bengali character, which makes a `/u` pattern fail outright — and a guard
     * that silently stops guarding is worse than one that is not there, because
     * the tokens it should have dropped are then indistinguishable from the
     * ones it approved. Whitespace and the words being matched are all ASCII,
     * and an ASCII byte cannot occur inside a multi-byte sequence.
     */
    private function isCourtDisposing(string $text, int $byteOffset): bool
    {
        $before = substr($text, max(0, $byteOffset - 80), min(80, $byteOffset));
        $words = preg_split('/\s+/', $before, -1, PREG_SPLIT_NO_EMPTY);

        if (! is_array($words)) {
            return true;
        }

        $words = array_values(array_filter(array_map(
            static fn (string $word): string => strtolower(trim($word, ",;:.()[]'\"")),
            $words,
        ), static fn (string $word): bool => $word !== ''));

        $last = $words === [] ? '' : $words[count($words) - 1];
        $previous = count($words) >= 2 ? $words[count($words) - 2] : '';

        if (in_array($last, self::HYPOTHETICAL_CONJUNCTIONS, true)
            || in_array($last, self::ADJUDICATIVE_PARTICIPLES, true)) {
            return false;
        }

        return ! (in_array($previous, self::HYPOTHETICAL_CONJUNCTIONS, true)
            && in_array($last, self::DISPOSITION_DETERMINERS, true));
    }

    /**
     * A figure a reviewer can judge: digits, optional grouping, optional
     * decimals, and nothing dangling at either end.
     */
    /**
     * A bracketed calculation, whose result these reports write after it.
     *
     * `(৩,১৩,৫৪২-২,৮১,৪১৭) -৩২,১২৫` is a subtraction and its answer — the
     * arithmetic is exact — and the mark before the answer is an equals sign
     * the recognizer read as a hyphen.
     */
    private const RESULT_OF_CALCULATION = '/\((?:\s*[\d\x{09E6}-\x{09EF}][\d\x{09E6}-\x{09EF},.\x{0020}]*[-+\x{2212}])+\s*[\d\x{09E6}-\x{09EF}][\d\x{09E6}-\x{09EF},.\x{0020}]*\)\s*$/u';

    /**
     * Strip a leading minus that introduces a result rather than a sign.
     *
     * The bracket has to hold a calculation, and that condition is the whole
     * substance of this method. A first attempt tested only for a closing
     * bracket, which in a budget table is the shape of every ordinary row: a
     * percentage in parentheses sits before the next column's figure, so
     * `(১৭.৩৮) -১৯৩৭৮` is a genuine negative and stripping it invented money in
     * eight places before a reviewer's screenshot caught it.
     *
     * A minus anywhere else is left exactly as read. Reappropriation columns run
     * negative throughout this corpus — 385 of the 425 values that begin with
     * one — and the second read cannot arbitrate: it re-reads the same glyph
     * with the same engine family and agrees with itself, which is what ADR-019
     * means by a correlated failure.
     */
    private function withoutResultMarker(string $value, string $text, int $byteOffset): string
    {
        if ($value === '' || ! in_array(mb_substr($value, 0, 1), ['-', "\u{2212}"], true)) {
            return $value;
        }

        $before = substr($text, 0, $byteOffset);

        return preg_match(self::RESULT_OF_CALCULATION, $before) === 1
            ? mb_substr($value, 1)
            : $value;
    }

    private function wellFormedAmount(string $value): bool
    {
        return preg_match(
            '/^[\d\x{09E6}-\x{09EF}]{1,3}(?:,[\d\x{09E6}-\x{09EF}]{2,3})+(?:\.\d{1,2})?$|^[\d\x{09E6}-\x{09EF}]+\.\d{1,2}$|^[\d\x{09E6}-\x{09EF}]{5,}$/u',
            // Checked without its sign, so a well-formed figure is not rejected
            // for carrying one. The sign is kept on the value itself; only the
            // shape of the digits is in question here.
            $this->unsigned($value),
        ) === 1;
    }

    /**
     * The figure without whatever marks it as negative.
     */
    private function unsigned(string $value): string
    {
        $value = trim($value);

        if (preg_match('/^\((.+)\)$/u', $value, $inner) === 1) {
            $value = $inner[1];
        }

        return ltrim($value, "-\u{2212} ");
    }

    /**
     * The figure as a number, with an accounting negative made explicit.
     *
     * The value keeps what the page shows; this is what any later analysis adds
     * up, and a bracketed figure summed as positive is a sign error in the
     * arithmetic rather than in the reading.
     */
    /**
     * Whether some word carrying this value was read confidently.
     *
     * Applied only to pages on the low-confidence path. Pages that cleared the
     * page-level gate keep the candidates they always had: changing what those
     * pages yield would change the population the existing labels were drawn
     * from, and the bound would then be fitted on one corpus and applied to
     * another.
     */
    private function tokenReadWell(ExtractionPage $page, string $value): bool
    {
        $words = $page->recognized_words;

        if (! is_array($words) || $words === []) {
            // No geometry to judge by. A page read before word boxes were
            // stored should not be silently promoted on the strength of a
            // measurement nobody took.
            return false;
        }

        $minimum = (float) config('civiclens.extraction.ocr.accept_token_confidence', 80.0);
        $value = trim($value);

        foreach ($words as $word) {
            $text = trim((string) ($word['t'] ?? ''));

            if ($text !== '' && mb_strpos($text, $value) !== false && (float) ($word['c'] ?? 0) >= $minimum) {
                return true;
            }
        }

        return false;
    }

    /**
     * The score a candidate carries before anything has read it twice.
     *
     * The page's own confidence, which is the statistic ADR-019 replaced as a
     * score and which `civiclens:score-fields` overwrites with a second read.
     * Lower is more conforming, matching the benchmark path so a
     * reviewer-labelled field and a benchmark one are comparable.
     *
     * A finding span starts at `null` and is the one kind that does. A null
     * score is deferred by rule in `FieldDecisionService::decisionFor`, which
     * is how a holding stays out of the certified set from the moment it is
     * created rather than from the moment somebody notices. It stays null
     * because the span cannot be located — see `MIN_FINDING_SPAN_WORDS` — so
     * the second read has nothing to score and writes `unlocatable`.
     *
     * Both halves are needed. Without the null it would carry a page-confidence
     * score the instant it was created, and a stratum that borrows a threshold
     * under ADR-018's ladder would accept it before any scoring job ran.
     */
    private function initialScore(ExtractionPage $page, string $kind): ?float
    {
        if ($kind === self::CITED_CONTEXT_KIND) {
            return null;
        }

        return $page->confidence === null
            ? 1.0
            : round(max(0.0, min(1.0, 1.0 - ((float) $page->confidence / 100))), 8);
    }

    /**
     * The value as later analysis would add it up or search for it.
     *
     * Commas are stripped from a figure because there they group thousands.
     * They are left alone in prose, where they are punctuation: stripping them
     * stored "the Rule is therefore discharged" for a page that says "the Rule
     * is, therefore, discharged", and a normalised value that differs from the
     * page in a way the page did not is the thing this column is least allowed
     * to be — it is what the public evidence search matches against.
     */
    private function normalisedValue(string $kind, string $value): ?string
    {
        if ($kind === 'amount') {
            return $this->normalisedAmount($value);
        }

        if ($kind === self::CITED_CONTEXT_KIND || $kind === 'disposition') {
            return trim((string) preg_replace('/\s+/u', ' ', $value));
        }

        return Str::of($value)->replace(',', '')->trim()->value();
    }

    private function normalisedAmount(string $value): ?string
    {
        // A comma that does not group thousands is most likely a decimal point
        // the recognizer misread, and stripping it turns 400.00 into 80,000. No
        // number is better than one that is wrong by two orders of magnitude,
        // so the figure is queued for judgement carrying no normalised form.
        if (! $this->grouping->groupsCorrectly($value)) {
            return null;
        }

        $digits = str_replace(',', '', $this->unsigned($value));
        $negative = preg_match('/^\(|^[-\x{2212}]/u', trim($value)) === 1;

        return ($negative ? '-' : '').$digits;
    }

    /**
     * Which publisher a page came from, since the bound is calibrated per group.
     *
     * A run with no source artifact is a benchmark run, and grouping it with a
     * live publisher would mix two populations under one guarantee.
     */
    private function publisherGroup(ExtractionPage $page): string
    {
        $artifact = $page->run?->artifactVersion;
        $resource = $artifact instanceof SourceArtifactVersion ? $artifact->discoveredResource : null;
        $endpoint = $resource instanceof DiscoveredResource ? $resource->endpoint : null;
        $publisher = $endpoint instanceof SourceEndpoint ? $endpoint->publisher : null;
        $slug = $publisher instanceof SourcePublisher ? $publisher->slug : null;

        return is_string($slug) && $slug !== '' ? $slug : 'unattributed';
    }
}
