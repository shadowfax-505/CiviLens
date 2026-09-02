<?php

use App\Models\ExtractionField;
use App\Models\ExtractionPage;
use App\Services\Extraction\FieldDecisionService;
use App\Services\Extraction\ReviewCandidateGenerator;
use App\Services\Extraction\ValueLocator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Every value of one kind the generator reads out of this text.
 *
 * @return list<string>
 */
function courtTokensOf(string $text, string $kind): array
{
    return array_values(array_map(
        fn (array $token): string => $token['value'],
        array_filter(
            app(ReviewCandidateGenerator::class)->tokens($text),
            fn (array $token): bool => $token['kind'] === $kind,
        ),
    ));
}

/** A page whose recognised words are exactly the words of this value. */
function pageWhoseWordsAre(string $value): ExtractionPage
{
    $words = preg_split('/\s+/u', trim($value), -1, PREG_SPLIT_NO_EMPTY);
    $left = 0;
    $boxes = [];

    foreach (is_array($words) ? $words : [] as $word) {
        $boxes[] = ['t' => $word, 'c' => 96, 'l' => $left, 'y' => 100, 'w' => 40, 'h' => 20];
        $left += 50;
    }

    return new ExtractionPage(['recognized_words' => $boxes]);
}

it('reads the disposition as the court pronounces it', function (): void {
    // Real text off pages 11840, 11920, 10680 and 12087 of this corpus. The
    // stop or comma the page prints after the phrase is part of the value: the
    // recognizer's last word carries it, and the locator requires a run of
    // words to equal the value exactly.
    expect(courtTokensOf('Therefore, we find merit in the Rule. Accordingly, the Rule is made absolute. The Court concerned is directed', 'disposition'))
        ->toBe(['the Rule is made absolute.'])
        ->and(courtTokensOf("In the result, the Rule is made\nabsolute. However, there is no order as to cost.", 'disposition'))
        ->toBe(['the Rule is made absolute.'])
        ->and(courtTokensOf('we find no merit in the Rule. Accordingly, the Rule is discharged and ad-interim order of stay granted earlier is hereby re-called', 'disposition'))
        ->toBe(['the Rule is discharged'])
        ->and(courtTokensOf('The criminal appeal is, therefore, dismissed and the jail appeal is accordingly disposed of.', 'disposition'))
        ->toBe(['The criminal appeal is, therefore, dismissed', 'the jail appeal is accordingly disposed of.']);
});

it('does not read counsel as the court', function (): void {
    // All four are real, and in each the matter was not disposed of the way the
    // words say. Present tense with no auxiliary is what separates the court's
    // act from the submission, the reasoning and the history of another case.
    $prayer = 'On these grounds, the learned counsel for the petitioner prays for the Rule to be made absolute.';
    $reasoning = 'as there is no merit the Rule is liable to be discharged.';
    $history = 'Sebayet Ranjit Kumar Das filed Writ Petition No.424 of 1990 and above Rule was made absolute vide judgment';
    $procedure = 'those have been heard together and are being disposed of by this single judgment.';

    expect(courtTokensOf($prayer, 'disposition'))->toBe([])
        ->and(courtTokensOf($reasoning, 'disposition'))->toBe([])
        ->and(courtTokensOf($history, 'disposition'))->toBe([])
        ->and(courtTokensOf($procedure, 'disposition'))->toBe([]);
});

it('does not read a hypothetical disposition as one that happened', function (): void {
    // 101 of the 3,098 disposition-shaped spans in this corpus sit behind a
    // conjunction that makes them an option rather than an act. Both of these
    // are real, and the second is why one word of lookback is not enough — the
    // pattern's own determiner sits between the conjunction and the matter.
    expect(courtTokensOf('the complainant no longer intends to proceed and has no objection if the Rule is made absolute.', 'disposition'))
        ->toBe([])
        ->and(courtTokensOf('he submits that no prejudice is caused if the said appeal is disposed of today.', 'disposition'))
        ->toBe([]);
});

it('does not read a lower court order as the disposition of this matter', function (): void {
    // Real text off page 18353. The decree is what is upheld; the suit is what
    // the decree dismissed. Anchored on the matter noun the span reads as the
    // opposite of what the page says, and every character of it is on the page,
    // so nothing about how well it was read would ever reveal the error.
    $text = 'The judgment and decree passed by the learned Senior Assistant Judge, 1st Court, Naogaon in Title Suit No. 94 of 2004 dismissing the suit is hereby upheld.';

    expect(courtTokensOf($text, 'disposition'))->toBe([]);

    // A participle that qualifies the matter rather than governing it is left
    // alone: "the connecting Rule is disposed of" is a real disposition.
    expect(courtTokensOf('The appeal, therefore, is dismissed and consequently the connecting Rule is disposed of.', 'disposition'))
        ->toBe(['the connecting Rule is disposed of.']);
});

it('leaves the audit corpus alone', function (): void {
    // Zero matches across all 11,750 pages of the audit, gazette and evaluation
    // corpus when this was measured, so a court pattern cannot put court
    // furniture into another publisher's queue.
    $text = 'মোট ৩,৯৮,৭৪৮ টাকা ব্যয় হয়েছে, চালান নং-১২৩ তাং-১৩/১০/২০১১';

    expect(courtTokensOf($text, 'disposition'))->toBe([])
        ->and(courtTokensOf($text, 'finding_span'))->toBe([]);
});

it('carries the whole sentence in which the court states its holding', function (): void {
    // Real text off page 10680. The span runs from the sentence's own start,
    // not from the cue, because the lead-in is what the holding is grounded on.
    $text = "he attained\nthe age of superannuation. In view of the above observations as well as the facts and\ncircumstances of the case, we find no merit in the Rule. Accordingly, the Rule is discharged";

    expect(courtTokensOf($text, 'finding_span'))
        ->toBe(['In view of the above observations as well as the facts and circumstances of the case, we find no merit in the Rule.']);
});

it('does not cut a holding off at an abbreviated name', function (): void {
    // A full stop followed by a space and a capital letter is the shape of a
    // sentence boundary and also the shape of "Md.", and this holding ended at
    // the convict's name until the abbreviations were listed.
    $text = 'Considering the gravity of the offence and the facts and circumstances of the case, I am of the view that the ends of justice would be best served if the sentence passed by the trial court is modified as under; the accused Md. Sohrab Ali shall serve five years. Send down the record.';

    expect(courtTokensOf($text, 'finding_span'))
        ->toBe(['Considering the gravity of the offence and the facts and circumstances of the case, I am of the view that the ends of justice would be best served if the sentence passed by the trial court is modified as under; the accused Md. Sohrab Ali shall serve five years.']);
});

it('leaves a holding whose sentence it cannot delimit alone', function (): void {
    // Page 6 of one judgment stops mid-sentence, and a paragraph break is not
    // the court saying the sentence finished. Half a holding presented as a
    // whole one misrepresents the court as surely as a figure read out of the
    // middle of a longer number.
    $text = "On consideration of the facts and circumstances of the case, along\nwith the materials on record, we are of the view that the Appeal and\n\n";

    expect(courtTokensOf($text, 'finding_span'))->toBe([]);
});

it('leaves a holding short enough for the locator to find', function (): void {
    // "I find merit in the Rule." is a holding and is five words, so the
    // locator would find it, the second read would score it, and a stratum
    // borrowing a threshold under ADR-018's ladder would accept it. What
    // `disposition` carries in its place is the court's own operative words.
    $text = 'Accordingly, I find merit in the Rule. The Rule is made absolute.';

    expect(courtTokensOf($text, 'finding_span'))->toBe([])
        ->and(courtTokensOf($text, 'disposition'))->toBe(['The Rule is made absolute.']);
});

it('emits no finding span the locator could find', function (): void {
    // The tripwire. `MIN_FINDING_SPAN_WORDS` is 17 because the locator joins at
    // most `ValueLocator::MAX_WORDS_PER_VALUE` words to make one value, and
    // measured against the real locator over the 3,259 court pages carrying
    // word geometry, 0 of 3,230 spans located while 702 of 716 spans of sixteen
    // words or fewer did.
    //
    // The page here is the most favourable one there could be: its recognised
    // words are exactly the span's words, in order, with nothing else on it. If
    // the locator cannot find it there it cannot find it anywhere.
    $text = "In view of the above observations as well as the facts and\ncircumstances of the case, we find no merit in the Rule. Accordingly, the Rule is discharged.";

    $spans = courtTokensOf($text, 'finding_span');

    expect($spans)->toHaveCount(1);

    foreach ($spans as $span) {
        $words = preg_split('/\s+/u', $span, -1, PREG_SPLIT_NO_EMPTY);

        expect(is_array($words) ? count($words) : 0)
            ->toBeGreaterThanOrEqual(ReviewCandidateGenerator::MIN_FINDING_SPAN_WORDS)
            ->and(app(ValueLocator::class)->locate(
                new ExtractionField(['extracted_value' => $span]),
                pageWhoseWordsAre($span),
            ))->toBe([]);
    }

    // And the same page finds the disposition, so the assertion above is about
    // the length of a holding rather than about a locator that finds nothing.
    $disposition = courtTokensOf($text, 'disposition')[0];

    expect(app(ValueLocator::class)->locate(
        new ExtractionField(['extracted_value' => $disposition]),
        pageWhoseWordsAre($disposition),
    ))->not->toBe([]);
});

it('stores a finding span with no score at all', function (): void {
    // Every other kind starts with the page's own confidence, which is the
    // statistic ADR-019 replaced as a score and which a scoring run overwrites.
    // A finding span starts at null, so it is deferred by rule from the moment
    // it exists rather than from the moment a scoring job reaches it.
    ['page' => $page] = publishableDocument();

    $page->forceFill([
        'extracted_text' => 'The petitioner was appointed in 2011. In view of the above observations as well as the facts and circumstances of the case, we find no merit in the Rule. Accordingly, the Rule is discharged.',
        'character_count' => 400,
        'confidence' => 92.5,
        'script_class' => 'en',
    ])->save();

    app(ReviewCandidateGenerator::class)->generate(50);

    $span = ExtractionField::query()->where('field_key', 'finding_span')->firstOrFail();
    $disposition = ExtractionField::query()->where('field_key', 'disposition')->firstOrFail();

    expect($span->nonconformity_score)->toBeNull()
        ->and($span->normalized_value)->toBe($span->extracted_value)
        ->and($disposition->nonconformity_score)->not->toBeNull();
});

it('cannot certify a finding span in a stratum that certifies everything else', function (): void {
    // The stratum is publisher x script x kind and a new kind has no labels, so
    // a finding span defers today whatever this file does. That is not the
    // property being pinned. This gives the stratum forty labels of its own —
    // more than the nineteen a 5% claim needs — and the finding span still
    // defers, because a value with no score is deferred by rule and a finding
    // span has no score. A disposition in the same stratum is accepted, so the
    // test is about the kind and not about a decision pass that accepts nothing.
    ['page' => $page] = publishableDocument();

    foreach (['finding_span', 'disposition'] as $kind) {
        foreach (range(1, 40) as $ignored) {
            ExtractionField::factory()->create([
                'extraction_run_id' => $page->extraction_run_id,
                'extraction_page_id' => $page->id,
                'field_key' => $kind,
                'publisher_group' => 'supremecourt-bangladesh',
                'script_class' => 'en',
                'calibration_split' => 'calibration',
                'nonconformity_score' => 0.01,
                'is_correct' => true,
                'gold_value' => 'seen',
                'gold_source' => 'reviewer',
            ]);
        }
    }

    $undecided = [];

    foreach (['finding_span' => null, 'disposition' => 0.01] as $kind => $score) {
        $undecided[$kind] = ExtractionField::factory()->create([
            'extraction_run_id' => $page->extraction_run_id,
            'extraction_page_id' => $page->id,
            'field_key' => $kind,
            'publisher_group' => 'supremecourt-bangladesh',
            'script_class' => 'en',
            'calibration_split' => null,
            'nonconformity_score' => $score,
            'is_correct' => null,
            'gold_source' => null,
            'decision' => FieldDecisionService::DEFERRED,
        ]);
    }

    app(FieldDecisionService::class)->decide(0.05);

    expect($undecided['finding_span']->refresh()->decision)->toBe(FieldDecisionService::DEFERRED)
        ->and($undecided['finding_span']->refresh()->decision_basis)->toBeNull()
        ->and($undecided['disposition']->refresh()->decision)->toBe(FieldDecisionService::ACCEPTED);
});
