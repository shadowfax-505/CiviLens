<?php

namespace App\Services\Ingestion;

/**
 * Decide whether a successful response actually carried what was expected.
 *
 * HTTP 200 is not success. The CAG audit category pages return 200 with 145KB
 * of markup whose body is "Lorem ipsum dolor sit amet" and zero document links:
 * an unfilled template that every status check calls healthy. A connector
 * pointed at one would report success forever while collecting nothing, which
 * is the worst failure for a transparency system because it is invisible.
 *
 * This does not reject anything on its own. It scores a response so a crawl run
 * can record why a page looked wrong, and an operator can see a source degrade
 * before the archive quietly fills with placeholders.
 */
class SourceContentValidator
{
    /** Phrases that only appear in unfilled templates. */
    private const PLACEHOLDER_MARKERS = [
        'lorem ipsum',
        'dolor sit amet',
        'consectetur adipiscing',
        'your text here',
        'sample text goes here',
    ];

    /**
     * @param  list<string>  $expectedTerms  words the page should mention if it is the right page
     * @return array<string, mixed>
     */
    public function validate(
        string $body,
        string $mediaType,
        ?string $expectedMediaType = null,
        array $expectedTerms = [],
        bool $documentsExpected = false,
    ): array {
        $normalized = mb_strtolower($body);
        $placeholderHits = $this->placeholderHits($normalized);
        $documentLinks = $this->documentLinks($body);
        $mismatch = $this->mediaTypeMismatch($body, $mediaType, $expectedMediaType);
        $missingTerms = $this->missingTerms($normalized, $expectedTerms);

        $reasons = [];

        if ($placeholderHits > 0) {
            $reasons[] = 'placeholder text present';
        }

        if ($mismatch !== null) {
            $reasons[] = $mismatch;
        }

        if ($documentsExpected && $documentLinks === 0) {
            $reasons[] = 'documents expected but none linked';
        }

        if ($missingTerms !== []) {
            $reasons[] = 'expected terms absent: '.implode(', ', $missingTerms);
        }

        if (trim($body) === '') {
            $reasons[] = 'empty body';
        }

        return [
            'usable' => $reasons === [],
            'placeholder_hits' => $placeholderHits,
            'document_link_count' => $documentLinks,
            'content_length' => strlen($body),
            'reasons' => $reasons,
        ];
    }

    private function placeholderHits(string $normalized): int
    {
        $hits = 0;

        foreach (self::PLACEHOLDER_MARKERS as $marker) {
            $hits += substr_count($normalized, $marker);
        }

        return $hits;
    }

    /**
     * An HTML error page saved with a .pdf extension is a common way for an
     * archive to fill with unreadable files, so the body is checked rather than
     * the declared type.
     */
    private function mediaTypeMismatch(string $body, string $mediaType, ?string $expected): ?string
    {
        if ($expected === null) {
            return null;
        }

        if ($expected === 'application/pdf') {
            return str_starts_with($body, '%PDF-')
                ? null
                : 'expected a PDF but the body is not one';
        }

        return $mediaType === $expected ? null : 'expected '.$expected.' but received '.$mediaType;
    }

    private function documentLinks(string $body): int
    {
        $matched = preg_match_all('/href\s*=\s*"[^"]*\.(pdf|docx?|xlsx?|csv|zip)("|\?)/i', $body);

        return $matched === false ? 0 : $matched;
    }

    /**
     * @param  list<string>  $expectedTerms
     * @return list<string>
     */
    private function missingTerms(string $normalized, array $expectedTerms): array
    {
        $missing = [];

        foreach ($expectedTerms as $term) {
            $needle = mb_strtolower(trim($term));

            if ($needle !== '' && ! str_contains($normalized, $needle)) {
                $missing[] = $term;
            }
        }

        return $missing;
    }
}
