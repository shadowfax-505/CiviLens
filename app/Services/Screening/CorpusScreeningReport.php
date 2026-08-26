<?php

namespace App\Services\Screening;

use App\Models\ExtractionField;
use App\Models\ScreeningEntity;

/**
 * Every organisation named in the corpus, checked against the published lists.
 *
 * The screener has held 1,401 debarment entities and nothing from this corpus
 * to compare them against, so it has never actually been run on anything. It
 * now has names, and what it returns is a negative: none of the contractors
 * named in these audit findings appears on the list.
 *
 * That is a result worth publishing and worth stating carefully. It does not
 * mean the contractors are clean; it means they are absent from one register
 * assembled for a different purpose, in which very few Bangladeshi firms appear
 * at all. A reader who takes absence for exoneration has been misled by the
 * report rather than by the data, so the wording here does the work.
 */
class CorpusScreeningReport
{
    public function __construct(private readonly EntityScreener $screener) {}

    /**
     * @return array<string, mixed>
     */
    public function build(int $perName = 3): array
    {
        $names = ExtractionField::query()
            ->where('field_key', 'organisation')
            ->distinct()
            ->pluck('extracted_value')
            ->map(fn ($value): string => trim((string) $value))
            ->filter()
            ->values();

        $matched = [];

        foreach ($names as $name) {
            // A Collection, not an array, so an identity check against [] is
            // always false and every name looked like a match.
            $candidates = $this->screener->candidates($name, $perName);

            if ($candidates->isEmpty()) {
                continue;
            }

            $matched[] = [
                'name_in_the_corpus' => $name,
                // Candidates, never verdicts. The claim a match supports is
                // that two strings resemble each other, and a person decides
                // whether they are the same firm.
                'resembles' => $candidates->all(),
            ];
        }

        return [
            'names_found_in_the_corpus' => $names->count(),
            'entities_on_the_lists' => ScreeningEntity::query()->count(),
            'names_resembling_a_listed_entity' => count($matched),
            'matches' => $matched,
            // Said in the report rather than left to a reader's assumption.
            'what_a_zero_means' => 'No name found in this corpus resembles an entry on the lists held. '
                .'That is absence from one register assembled for a different purpose, in which very few '
                .'Bangladeshi firms appear at all. It is not a finding about any contractor.',
            'what_a_match_means' => 'Two names resemble each other. Whether they are the same organisation '
                .'is a judgement this system does not make.',
        ];
    }
}
