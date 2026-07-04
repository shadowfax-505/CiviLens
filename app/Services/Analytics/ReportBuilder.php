<?php

namespace App\Services\Analytics;

use App\Events\ReportGenerated;
use App\Models\AnalyticsReport;
use App\Models\User;
use App\Support\Analytics\AnalyticsFilters;
use DateTimeInterface;
use Illuminate\Support\Str;

class ReportBuilder
{
    public function __construct(private readonly DashboardService $dashboards) {}

    public function generate(string $dashboard, string $format, AnalyticsFilters $filters, ?User $user = null): AnalyticsReport
    {
        $payload = $this->dashboards->dashboard($dashboard, $filters, $user);
        $payload['branding'] = [
            'name' => 'CivicLens',
            'subtitle' => 'Enterprise Civic Intelligence Platform',
        ];
        $payload['evidence'] = [
            'generated_timestamp' => date(DATE_ATOM),
            'filters' => $filters->toArray(),
            'source' => 'Normalized CivicLens operational records',
        ];

        $report = AnalyticsReport::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user?->id,
            'name' => $payload['title'].' Report',
            'dashboard' => $dashboard,
            'format' => $format,
            'status' => 'generated',
            'filters' => $filters->toArray(),
            'payload' => $payload,
            'generated_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);

        ReportGenerated::dispatch($report);

        return $report;
    }

    public function csv(AnalyticsReport $report): string
    {
        $rows = [
            '"CivicLens","'.$this->escape((string) $report->name).'","'.date(DATE_ATOM).'"',
            'Metric,Value,Unit',
        ];
        $metrics = data_get($report->getAttribute('payload'), 'metrics', []);

        if (! is_array($metrics)) {
            return implode("\n", $rows)."\n";
        }

        foreach ($metrics as $metric) {
            if (! is_array($metric)) {
                continue;
            }

            $rows[] = sprintf('"%s","%s","%s"', $this->escape((string) $metric['label']), $this->escape((string) $metric['value']), $this->escape((string) ($metric['unit'] ?? '')));
        }

        return implode("\n", $rows)."\n";
    }

    public function spreadsheet(AnalyticsReport $report): string
    {
        $rows = [
            ['CivicLens', (string) $report->name, $this->dateValue($report->generated_at)],
            ['Metric', 'Value', 'Unit'],
        ];
        $metrics = data_get($report->getAttribute('payload'), 'metrics', []);

        if (is_array($metrics)) {
            foreach ($metrics as $metric) {
                if (! is_array($metric)) {
                    continue;
                }

                $rows[] = [(string) $metric['label'], (string) $metric['value'], (string) ($metric['unit'] ?? '')];
            }
        }

        $lines = [];
        foreach ($rows as $row) {
            $lines[] = implode("\t", array_map(fn (string $cell): string => str_replace(["\t", "\n", "\r"], ' ', $cell), $row));
        }

        return $this->minimalPdf($lines);
    }

    public function pdfCompatible(AnalyticsReport $report): string
    {
        $metrics = data_get($report->getAttribute('payload'), 'metrics', []);
        $lines = [
            'CivicLens',
            (string) $report->name,
            'Generated: '.$this->dateValue($report->generated_at),
            'Filters: '.json_encode($report->filters ?? []),
            '',
            'KPIs',
        ];

        if (is_array($metrics)) {
            foreach ($metrics as $metric) {
                if (! is_array($metric)) {
                    continue;
                }

                $lines[] = sprintf('%s: %s %s', $metric['label'], $metric['value'], $metric['unit'] ?? '');
            }
        }

        $lines[] = '';
        $lines[] = 'Evidence: Generated from normalized CivicLens operational records.';

        return implode("\n", $lines)."\n";
    }

    public function content(AnalyticsReport $report): string
    {
        return match ($report->format) {
            'xlsx' => $this->spreadsheet($report),
            'pdf' => $this->pdfCompatible($report),
            default => $this->csv($report),
        };
    }

    public function mimeType(AnalyticsReport $report): string
    {
        return match ($report->format) {
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pdf' => 'application/pdf',
            default => 'text/csv',
        };
    }

    public function extension(AnalyticsReport $report): string
    {
        return match ($report->format) {
            'xlsx' => 'xlsx',
            'pdf' => 'pdf',
            default => 'csv',
        };
    }

    private function escape(string $value): string
    {
        return str_replace('"', '""', $value);
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function minimalPdf(array $lines): string
    {
        $content = "BT\n/F1 11 Tf\n72 760 Td\n";

        foreach (array_slice($lines, 0, 42) as $index => $line) {
            if ($index > 0) {
                $content .= "0 -16 Td\n";
            }

            $content .= '('.$this->escapePdfText($line).") Tj\n";
        }

        $content .= "ET\n";

        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n",
            "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
            "5 0 obj\n<< /Length ".strlen($content)." >>\nstream\n".$content."endstream\nendobj\n",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\n";

        return $pdf.("startxref\n".$xrefOffset."\n%%EOF\n");
    }

    private function escapePdfText(string $value): string
    {
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $value);
    }

    private function dateValue(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        return $value ? (string) $value : '';
    }
}
