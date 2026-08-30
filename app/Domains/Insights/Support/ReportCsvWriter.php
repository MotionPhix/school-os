<?php

declare(strict_types=1);

namespace App\Domains\Insights\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Flattens any insights read-model array into the same `Section, Label,
 * Value, Delta %, Note` shape the workspace exports client-side, so a
 * CSV pulled from the API is byte-comparable with one pulled from the UI.
 *
 * Recognised keys:
 *  - `headline`  => list<KpiPoint{label,value,delta_pct,unit}>
 *  - `*_trend` / `*_by_month` => list<TrendPoint{bucket,value}>
 *  - every other list of {label,value,secondary}
 */
final class ReportCsvWriter
{
    private const HEADER = ['Section', 'Label', 'Value', 'Delta %', 'Note'];

    /** @param array<string, mixed> $report */
    public function stream(array $report, string $filename): StreamedResponse
    {
        $rows = $this->rows($report);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'wb');
            fputcsv($out, self::HEADER);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename.'-'.now()->toDateString().'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<list<string|int|float>>
     */
    public function rows(array $report): array
    {
        $rows = [];

        foreach ($report['headline'] ?? [] as $kpi) {
            $rows[] = [
                'Headline',
                (string) ($kpi['label'] ?? ''),
                $kpi['value'] ?? '',
                $kpi['delta_pct'] ?? '',
                (string) ($kpi['unit'] ?? ''),
            ];
        }

        foreach ($report as $key => $value) {
            if ($key === 'headline' || ! is_array($value) || ! array_is_list($value)) {
                continue;
            }

            $section = ucfirst(str_replace('_', ' ', (string) $key));
            foreach ($value as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $rows[] = [
                    $section,
                    (string) ($row['label'] ?? $row['bucket'] ?? ''),
                    $row['value'] ?? '',
                    '',
                    (string) ($row['secondary'] ?? ''),
                ];
            }
        }

        return $rows;
    }
}
