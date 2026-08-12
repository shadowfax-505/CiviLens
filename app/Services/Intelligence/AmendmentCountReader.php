<?php

namespace App\Services\Intelligence;

use App\Models\TenderObservation;

/**
 * Report how many amendments a publisher says a notice has had.
 *
 * The e-GP listing states this itself, in the status column: "Amendment /
 * Corrigendum issued : 2". Reading a number the publisher printed is not
 * inference, and it needs neither a peer cohort nor calibration, so it produces
 * findings from the first crawl.
 *
 * An amendment is routine and often good practice. A tender corrected before
 * bidding closes is a tender that was fixed. What matters for transparency is
 * that the correction is visible and countable, not that it happened.
 */
class AmendmentCountReader
{
    /**
     * Amendments declared on a single observation, or null when the publisher
     * declared none.
     *
     * Absence of the marker means no amendment was declared. It does not mean
     * none occurred, and the difference is why this returns null rather than
     * zero: zero would assert something the listing did not say.
     */
    public function declaredOn(TenderObservation $observation): ?int
    {
        $status = (string) $observation->status;

        if (preg_match('/amendment|corrigendum/i', $status) !== 1) {
            return null;
        }

        // The count follows a colon. A marker with no number still means one
        // amendment was declared, so it reads as 1 rather than as nothing.
        if (preg_match('/(\d+)\s*$/', $status, $matches) === 1) {
            return max(1, (int) $matches[1]);
        }

        return 1;
    }

    /**
     * @return array{
     *     notices: int,
     *     amended_notices: int,
     *     amendments: int,
     *     amended_share: float|null,
     *     statement: string
     * }
     */
    public function summarise(int $publisherId): array
    {
        $latest = TenderObservation::query()
            ->where('source_publisher_id', $publisherId)
            ->orderByDesc('observed_at')
            ->orderByDesc('id')
            ->get()
            ->unique('external_id');

        $notices = $latest->count();
        $amendedNotices = 0;
        $amendments = 0;

        foreach ($latest as $observation) {
            $declared = $this->declaredOn($observation);

            if ($declared === null) {
                continue;
            }

            $amendedNotices++;
            $amendments += $declared;
        }

        return [
            'notices' => $notices,
            'amended_notices' => $amendedNotices,
            'amendments' => $amendments,
            'amended_share' => $notices === 0 ? null : round($amendedNotices / $notices, 4),
            'statement' => $this->statement($notices, $amendedNotices, $amendments),
        ];
    }

    /**
     * Counting, never characterising.
     *
     * The wording states what the publisher declared and says in terms that an
     * amendment is not evidence of anything improper. A reader who takes a
     * count as an accusation has been misled by the writing, not by the data.
     */
    private function statement(int $notices, int $amendedNotices, int $amendments): string
    {
        if ($notices === 0) {
            return 'No notices have been observed for this publisher yet.';
        }

        if ($amendedNotices === 0) {
            return "None of the {$notices} notices observed declared an amendment or corrigendum.";
        }

        return "{$amendedNotices} of {$notices} observed notices declared an amendment or corrigendum, "
            ."{$amendments} in total, as stated by the publisher. Notices are amended for many ordinary "
            .'reasons and a correction issued before bidding closes is a tender that was fixed; this counts '
            .'what the publisher declared, not that anything was wrong.';
    }
}
