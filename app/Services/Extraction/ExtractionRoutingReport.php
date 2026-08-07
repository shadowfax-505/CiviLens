<?php

namespace App\Services\Extraction;

use App\Models\ExtractionPage;
use App\Models\ExtractionRun;

class ExtractionRoutingReport
{
    /**
     * Summarize how much of the corpus carried a usable text layer.
     *
     * The born-digital share is the denominator for every later OCR claim: it is
     * both the share of pages that never need OCR and, inverted, the share of
     * compute an OCR-always pipeline spends for nothing.
     *
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $pages = ExtractionPage::query()
            ->selectRaw('extraction_path, script_class, count(*) as total')
            ->groupBy('extraction_path', 'script_class')
            ->get();

        $totalPages = (int) $pages->sum('total');
        $nativePages = (int) $pages->where('extraction_path', PageRoutingPolicy::NATIVE)->sum('total');

        return [
            'threshold_chars_per_square_inch' => (float) config('civiclens.extraction.native_density_threshold', 1.5),
            'runs' => ExtractionRun::query()
                ->selectRaw('routing_decision, count(*) as total')
                ->groupBy('routing_decision')
                ->pluck('total', 'routing_decision')
                ->all(),
            'failed_runs' => ExtractionRun::query()->where('status', 'failed')->count(),
            'total_pages' => $totalPages,
            'native_pages' => $nativePages,
            'ocr_required_pages' => $totalPages - $nativePages,
            'born_digital_share' => $totalPages > 0 ? round($nativePages / $totalPages, 4) : null,
            'accept_confidence' => (float) config('civiclens.extraction.ocr.accept_confidence', 80.0),
            'pages_by_path' => $pages
                ->groupBy('extraction_path')
                ->map(fn ($rows): int => (int) $rows->sum('total'))
                ->all(),
            'abstention_rate' => $totalPages > 0
                ? round((int) $pages->where('extraction_path', SelectiveOcrService::ABSTAINED)->sum('total') / $totalPages, 4)
                : null,
            'enhanced_pass_recovery' => $this->enhancedRecovery(),
            'ocr_confidence_by_script' => $this->confidenceByScript(),
            'pages_by_script' => $pages
                ->groupBy('script_class')
                ->map(fn ($rows): int => (int) $rows->sum('total'))
                ->all(),
            'native_share_by_script' => $pages
                ->groupBy('script_class')
                ->map(function ($rows): ?float {
                    $total = (int) $rows->sum('total');
                    $native = (int) $rows->where('extraction_path', PageRoutingPolicy::NATIVE)->sum('total');

                    return $total > 0 ? round($native / $total, 4) : null;
                })
                ->all(),
        ];
    }

    /**
     * How often the one permitted enhanced pass rescued a page that the primary
     * pass could not accept. A low value argues the second pass is not paying for
     * its compute; a high value argues the primary operating point is set wrong.
     *
     * @return array<string, mixed>
     */
    private function enhancedRecovery(): array
    {
        $enhanced = ExtractionPage::query()->where('extraction_path', SelectiveOcrService::ENHANCED)->count();
        $abstained = ExtractionPage::query()->where('extraction_path', SelectiveOcrService::ABSTAINED)->count();
        $attempted = $enhanced + $abstained;

        return [
            'pages_entering_enhanced_pass' => $attempted,
            'recovered' => $enhanced,
            'recovery_rate' => $attempted > 0 ? round($enhanced / $attempted, 4) : null,
        ];
    }

    /**
     * Confidence distribution per script class. Reported per script rather than
     * pooled because a pooled figure can look healthy while the low-resource
     * subset fails — the same reason calibration has to be group-conditional.
     *
     * @return array<string, array<string, float|int|null>>
     */
    private function confidenceByScript(): array
    {
        return ExtractionPage::query()
            ->whereNotNull('confidence')
            ->whereIn('extraction_path', [SelectiveOcrService::PRIMARY, SelectiveOcrService::ENHANCED, SelectiveOcrService::ABSTAINED])
            ->get(['script_class', 'confidence'])
            ->groupBy('script_class')
            ->map(function ($rows): array {
                $values = $rows->pluck('confidence')->map(fn ($value): float => (float) $value)->sort()->values()->all();
                $count = count($values);

                return [
                    'pages' => $count,
                    'mean' => round(array_sum($values) / $count, 2),
                    'median' => round($values[intdiv($count, 2)], 2),
                    'min' => round($values[0], 2),
                    'max' => round($values[$count - 1], 2),
                ];
            })
            ->all();
    }
}
