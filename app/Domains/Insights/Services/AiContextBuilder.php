<?php

declare(strict_types=1);

namespace App\Domains\Insights\Services;

use App\Enums\AnnouncementStatus;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * AI context builder — assembles a compact, authoritative snapshot of the
 * tenant for the School Assistant. Reuses the institution snapshot reader
 * for cross-capability KPIs and adds lightweight live facts (recent
 * announcements). Kept deliberately small so prompts stay bounded.
 */
final class AiContextBuilder
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly InstitutionSnapshotReader $snapshot,
    ) {}

    /**
     * @return array{
     *   school_name: string,
     *   as_of: string,
     *   period_label: string,
     *   headline: list<string>,
     *   top_cohorts: list<string>,
     *   enrollment_trend: list<string>,
     *   recent_announcements: array<int, string>,
     * }
     */
    public function facts(): array
    {
        $tenantId = $this->tenant->id();
        $report = $tenantId === null ? [] : $this->snapshot->read([]);

        $headline = [];
        /** @var list<array{label?: mixed, value?: mixed}> $headlineKpis */
        $headlineKpis = $report['headline'] ?? [];
        foreach ($headlineKpis as $kpi) {
            $label = $kpi['label'] ?? '';
            $value = $kpi['value'] ?? '';
            $headline[] = sprintf('%s: %s', is_string($label) ? $label : '', is_string($value) ? $value : '');
        }

        $topCohorts = [];
        foreach ($report['top_cohorts'] ?? [] as $cohort) {
            $topCohorts[] = sprintf(
                '%s: %.1f%% (%s)',
                $cohort['label'],
                $cohort['value'],
                $cohort['secondary'],
            );
        }

        $enrollmentTrend = [];
        foreach ($report['enrollment_trend'] ?? [] as $bucket) {
            $enrollmentTrend[] = sprintf('%s: %d', $bucket['bucket'], $bucket['value']);
        }

        $announcements = $tenantId === null
            ? []
            : DB::table('comm_announcements')
                ->where('tenant_id', $tenantId)
                ->where('status', AnnouncementStatus::Sent->value)
                ->whereNotNull('sent_at')
                ->latest('sent_at')
                ->limit(3)
                ->pluck('title')
                ->map(fn (mixed $title): string => is_string($title) ? $title : '')
                ->values()
                ->all();

        $tenantName = $tenantId === null
            ? 'Unknown school'
            : (string) Tenant::query()->findOrFail($tenantId)->name;

        return [
            'school_name' => $tenantName,
            'as_of' => (string) ($report['as_of'] ?? ''),
            'period_label' => (string) ($report['period']['label'] ?? ''),
            'headline' => $headline,
            'top_cohorts' => $topCohorts,
            'enrollment_trend' => $enrollmentTrend,
            'recent_announcements' => $announcements,
        ];
    }

    /**
     * @param  array{
     *   school_name: string,
     *   as_of: string,
     *   period_label: string,
     *   headline: list<string>,
     *   top_cohorts: list<string>,
     *   enrollment_trend: list<string>,
     *   recent_announcements: array<int, string>,
     * }  $facts
     */
    public function render(array $facts): string
    {
        $lines = [
            "School: {$facts['school_name']}",
            "Snapshot as of: {$facts['as_of']} (period: {$facts['period_label']})",
            'Headline: '.implode(' | ', $facts['headline']),
        ];

        if ($facts['top_cohorts'] !== []) {
            $lines[] = 'Top cohorts: '.implode(' | ', $facts['top_cohorts']);
        }

        if ($facts['enrollment_trend'] !== []) {
            $lines[] = 'Enrollment trend: '.implode(' -> ', $facts['enrollment_trend']);
        }

        if ($facts['recent_announcements'] !== []) {
            $lines[] = 'Recent announcements: '.implode('; ', $facts['recent_announcements']);
        }

        return implode("\n", $lines);
    }
}
