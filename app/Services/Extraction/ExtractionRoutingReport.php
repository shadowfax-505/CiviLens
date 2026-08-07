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
}
