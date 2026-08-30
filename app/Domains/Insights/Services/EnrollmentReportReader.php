<?php

declare(strict_types=1);

namespace App\Domains\Insights\Services;

use App\Domains\Insights\Support\PeriodResolver;
use App\Enums\OfferStatus;
use App\Enums\PipelineStage;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Enrollment funnel report — projects the Admissions pipeline into
 * KPIs, stage counts, grade-band mix, and outstanding offers.
 */
final class EnrollmentReportReader
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly PeriodResolver $periods,
    ) {}

    /** @param array{period?:?string,from?:?string,to?:?string} $input */
    public function read(array $input): array
    {
        $tenantId = $this->tenant->id();
        $win = $this->periods->resolve($input['period'] ?? null, $input['from'] ?? null, $input['to'] ?? null);

        $submittedIn = fn (CarbonImmutable $f, CarbonImmutable $t) => DB::table('applications')
            ->where('tenant_id', $tenantId)
            ->whereBetween('submitted_at', [$f->toDateTimeString(), $t->toDateTimeString()]);

        $applications = (int) $submittedIn($win['from'], $win['to'])->count();
        $prevApplications = (int) $submittedIn($win['prev_from'], $win['prev_to'])->count();

        $offersIssued = (int) DB::table('application_offers as o')
            ->join('applications as a', 'a.id', '=', 'o.application_id')
            ->where('o.tenant_id', $tenantId)
            ->whereIn('o.status', [OfferStatus::Sent->value, OfferStatus::Accepted->value, OfferStatus::Declined->value, OfferStatus::Expired->value])
            ->whereBetween('o.sent_at', [$win['from']->toDateTimeString(), $win['to']->toDateTimeString()])
            ->count();

        $prevOffers = (int) DB::table('application_offers')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [OfferStatus::Sent->value, OfferStatus::Accepted->value, OfferStatus::Declined->value, OfferStatus::Expired->value])
            ->whereBetween('sent_at', [$win['prev_from']->toDateTimeString(), $win['prev_to']->toDateTimeString()])
            ->count();

        $acceptedInWindow = (int) DB::table('application_offers')
            ->where('tenant_id', $tenantId)
            ->where('status', OfferStatus::Accepted->value)
            ->whereBetween('responded_at', [$win['from']->toDateTimeString(), $win['to']->toDateTimeString()])
            ->count();

        $conversion = $offersIssued > 0 ? round(($acceptedInWindow / $offersIssued) * 100, 1) : 0.0;

        $withdrawals = (int) DB::table('applications')
            ->where('tenant_id', $tenantId)
            ->where('stage', PipelineStage::Withdrawn->value)
            ->whereBetween('updated_at', [$win['from']->toDateTimeString(), $win['to']->toDateTimeString()])
            ->count();

        $prevWithdrawals = (int) DB::table('applications')
            ->where('tenant_id', $tenantId)
            ->where('stage', PipelineStage::Withdrawn->value)
            ->whereBetween('updated_at', [$win['prev_from']->toDateTimeString(), $win['prev_to']->toDateTimeString()])
            ->count();

        $stageCounts = DB::table('applications')
            ->where('tenant_id', $tenantId)
            ->whereBetween('updated_at', [$win['from']->toDateTimeString(), $win['to']->toDateTimeString()])
            ->groupBy('stage')
            ->selectRaw('stage, COUNT(*) as total')
            ->pluck('total', 'stage')
            ->toArray();

        $byStage = [];
        foreach (PipelineStage::cases() as $s) {
            if ($s->isTerminal() && $s !== PipelineStage::Enrolled) {
                continue;
            }
            $byStage[] = ['label' => $s->label(), 'value' => (int) ($stageCounts[$s->value] ?? 0)];
        }

        $byGradeBand = DB::table('applications')
            ->where('tenant_id', $tenantId)
            ->whereBetween('submitted_at', [$win['from']->toDateTimeString(), $win['to']->toDateTimeString()])
            ->groupBy('intended_stage')
            ->selectRaw('intended_stage, COUNT(*) as total')
            ->get()
            ->map(fn ($r) => ['label' => ucfirst((string) $r->intended_stage), 'value' => (int) $r->total])
            ->values()
            ->all();

        $offersOutstanding = DB::table('application_offers as o')
            ->join('applications as a', 'a.id', '=', 'o.application_id')
            ->where('o.tenant_id', $tenantId)
            ->where('o.status', OfferStatus::Sent->value)
            ->orderBy('o.expires_on')
            ->limit(8)
            ->get(['a.applicant_full_name', 'a.intended_grade_label', 'o.expires_on'])
            ->map(function ($r) {
                $expires = $r->expires_on ? CarbonImmutable::parse($r->expires_on) : null;
                $sec = $expires ? 'expires '.$expires->diffForHumans() : 'no expiry set';

                return [
                    'label' => sprintf('%s — %s', $r->applicant_full_name, $r->intended_grade_label),
                    'value' => 1,
                    'secondary' => $sec,
                ];
            })
            ->values()
            ->all();

        return [
            'as_of' => CarbonImmutable::now()->toIso8601String(),
            'period' => [
                'key' => $win['period']->value,
                'label' => $win['period']->label(),
                'from' => $win['from']->toDateString(),
                'to' => $win['to']->toDateString(),
            ],
            'headline' => [
                $this->kpi('Applications', $applications, $prevApplications, 'count', 'positive'),
                $this->kpi('Offers issued', $offersIssued, $prevOffers, 'count', 'positive'),
                $this->kpi('Conversion rate', $conversion, $conversion, 'pct', 'positive'),
                $this->kpi('Withdrawals', $withdrawals, $prevWithdrawals, 'count', 'warning'),
            ],
            'by_stage' => $byStage,
            'by_grade' => $byGradeBand,
            'offers_outstanding' => $offersOutstanding,
        ];
    }

    private function kpi(string $label, int|float $value, int|float $prev, string $unit, string $tone): array
    {
        return [
            'label' => $label,
            'value' => $value,
            'delta_pct' => $this->periods->deltaPct($value, $prev),
            'unit' => $unit,
            'tone' => $tone,
        ];
    }
}
