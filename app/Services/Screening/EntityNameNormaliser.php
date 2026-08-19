<?php

namespace App\Services\Screening;

/**
 * Reduce an organisation name to the part that identifies it.
 *
 * The same firm is written a dozen ways across a procurement notice, an audit
 * report and a debarment list: "M/S Rahman Construction Ltd.", "Rahman
 * Construction Limited", "RAHMAN CONSTRUCTION". Matching those needs a form that
 * survives the differences, and the differences are conventional rather than
 * arbitrary — legal suffixes, honorifics, punctuation and spacing.
 *
 * The normalised form is for comparison only. It is never shown to a reviewer in
 * place of the name as published, because the differences it discards are
 * sometimes the thing that distinguishes two firms.
 */
class EntityNameNormaliser
{
    /**
     * Legal forms and trading prefixes that identify nothing on their own.
     *
     * "M/S" and "Md." are worth naming: the first prefixes a great many
     * Bangladeshi firm names and the second a great many personal ones, so
     * leaving them in makes every name look alike at the start.
     */
    private const NOISE = [
        'ltd', 'limited', 'pvt', 'private', 'plc', 'inc', 'incorporated',
        'co', 'company', 'corporation', 'corp', 'llc', 'llp', 'gmbh', 'bv', 'sa', 'srl',
        'enterprise', 'enterprises', 'trading', 'traders', 'agency', 'agencies',
        'ms', 'messrs', 'md', 'mr', 'mrs', 'miss', 'dr', 'eng',
        'and', 'the', 'of', 'for',
    ];

    public function normalise(string $name): string
    {
        $value = mb_strtolower(trim($name));

        // "M/S" arrives with the slash attached to the next word.
        $value = (string) preg_replace('#\bm/s\.?#u', ' ', $value);

        // Punctuation to spaces rather than nothing: "A.B.C." is three tokens,
        // and deleting the dots would fuse them into one that matches nothing.
        $value = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value);

        $tokens = array_filter(
            explode(' ', (string) preg_replace('/\s+/u', ' ', trim($value))),
            fn (string $token): bool => $token !== '' && ! in_array($token, self::NOISE, true),
        );

        // Everything was noise: keep the name as it stood rather than returning
        // an empty string, which would match every other emptied name.
        if ($tokens === []) {
            return (string) preg_replace('/\s+/u', ' ', trim(mb_strtolower($name)));
        }

        return implode(' ', $tokens);
    }

    /**
     * The distinguishing tokens, for comparing names that share only some words.
     *
     * @return list<string>
     */
    public function tokens(string $name): array
    {
        $tokens = array_values(array_unique(array_filter(explode(' ', $this->normalise($name)))));

        sort($tokens);

        return $tokens;
    }
}
