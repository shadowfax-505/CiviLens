<?php

namespace App\Services\Normalisation;

/**
 * Map fields read off a tender notice onto the Open Contracting Data Standard.
 *
 * Not compliance theatre. Every indicator would otherwise be written against
 * one publisher's label wording, and comparing a Bangladeshi tender with
 * anything published elsewhere would mean writing the comparison twice. OCDS is
 * the shape the rest of the world already publishes in, so it is what makes
 * cross-checking possible at all.
 *
 * Two rules govern everything here.
 *
 * The raw value is always kept beside the normalised one. Normalisation is
 * lossy and sometimes wrong, and a reviewer has to be able to see through it to
 * what the document actually said.
 *
 * A value that is not recognised is reported as unmapped, never guessed.
 * "Open Tendering Method" is open tendering; a method this mapper has not seen
 * before is not quietly filed as the nearest match, because a wrong
 * procurement method makes a tender look like an exception when it is routine,
 * or routine when it is an exception.
 */
class OcdsTenderNormaliser
{
    /**
     * Label wordings seen on e-GP notices, mapped to the OCDS field they carry.
     *
     * Matched on a normalised substring, so "Procurement Nature" and
     * "Procurement Nature :" are the same label.
     *
     * @var array<string, string>
     */
    private const FIELDS = [
        'tender/proposal id' => 'tender.id',
        'invitation reference' => 'tender.title',
        'tender/proposal package no' => 'tender.description',
        'procurement method' => 'tender.procurementMethod',
        'procurement nature' => 'tender.mainProcurementCategory',
        'procurement type' => 'tender.procurementMethodDetails',
        'ministry' => 'buyer.parent.ministry',
        'division' => 'buyer.parent.division',
        'organization' => 'buyer.name',
        'procuring entity name' => 'buyer.details.entityName',
        'district' => 'buyer.address.region',
        'project code' => 'planning.budget.projectID',
        'app id' => 'planning.budget.id',
        'source of funds' => 'planning.budget.source',
    ];

    /**
     * OCDS procurementMethod is a closed codelist. Anything not here is
     * reported unmapped rather than forced into the nearest code.
     *
     * @var array<string, string>
     */
    private const METHODS = [
        'open tendering method' => 'open',
        'open tendering method (otm)' => 'open',
        'otm' => 'open',
        'request for quotation' => 'limited',
        'request for quotation method' => 'limited',
        'rfq' => 'limited',
        'limited tendering method' => 'limited',
        'ltm' => 'limited',
        'direct procurement method' => 'direct',
        'direct procurement' => 'direct',
        'dpm' => 'direct',
        'two stage tendering method' => 'selective',
        'restricted tendering method' => 'selective',
    ];

    /** @var array<string, string> */
    private const CATEGORIES = [
        'works' => 'works',
        'goods' => 'goods',
        'services' => 'services',
        'service' => 'services',
        'consultancy' => 'services',
        'consultancy services' => 'services',
        'non consultancy services' => 'services',
    ];

    /**
     * @param  array<string, string>  $fields  label => value as read from the notice
     * @return array{
     *     ocid: string|null,
     *     release: array<string, mixed>,
     *     raw: array<string, string>,
     *     unmapped_labels: list<string>,
     *     unmapped_values: list<array{label: string, field: string, value: string}>
     * }
     */
    public function normalise(array $fields, ?string $publisherPrefix = null): array
    {
        $release = [];
        $raw = [];
        $unmappedLabels = [];
        $unmappedValues = [];

        foreach ($fields as $label => $value) {
            $value = trim($value);
            $field = $this->fieldFor($label);

            if ($field === null) {
                $unmappedLabels[] = $label;

                continue;
            }

            // Keyed by the OCDS path rather than by the publisher's wording, so
            // the raw value can be found from the normalised one.
            $raw[$field] = $value;
            $coded = $this->code($field, $value);

            if ($coded === null) {
                $unmappedValues[] = ['label' => $label, 'field' => $field, 'value' => $value];

                continue;
            }

            $this->set($release, $field, $coded);
        }

        $prefix = $publisherPrefix ?? (string) config('civiclens.normalisation.ocid_prefix', 'bd-egp');
        $tenderId = $raw['tender.id'] ?? null;

        return [
            // An ocid identifies a contracting process across its whole life.
            // Without a tender identifier there is nothing stable to build one
            // from, and inventing one would create a second process rather than
            // identify the existing one.
            'ocid' => $tenderId === null || $tenderId === '' ? null : $prefix.'-'.$tenderId,
            'release' => $release,
            'raw' => $raw,
            'unmapped_labels' => $unmappedLabels,
            'unmapped_values' => $unmappedValues,
        ];
    }

    private function fieldFor(string $label): ?string
    {
        $normalised = $this->normaliseText($label);

        foreach (self::FIELDS as $wording => $field) {
            if (str_contains($normalised, $wording)) {
                return $field;
            }
        }

        return null;
    }

    /**
     * Closed codelists are translated; free text is passed through as read.
     */
    private function code(string $field, string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        return match ($field) {
            'tender.procurementMethod' => self::METHODS[$this->normaliseText($value)] ?? null,
            'tender.mainProcurementCategory' => self::CATEGORIES[$this->normaliseText($value)] ?? null,
            default => $value,
        };
    }

    /**
     * @param  array<string, mixed>  $release
     */
    private function set(array &$release, string $field, string $value): void
    {
        $cursor = &$release;

        foreach (explode('.', $field) as $segment) {
            if (! isset($cursor[$segment]) || ! is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }

            $cursor = &$cursor[$segment];
        }

        $cursor = $value;
    }

    private function normaliseText(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = (string) preg_replace('/[^\p{L}\p{N}\/() ]+/u', ' ', $text);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
