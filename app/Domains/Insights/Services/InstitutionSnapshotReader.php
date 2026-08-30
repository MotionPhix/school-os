<?php

declare(strict_types=1);

namespace App\Domains\Insights\Services;

use App\Domains\Insights\Support\PeriodResolver;
use App\Enums\AttendanceStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\PipelineStage;
use App\Enums\StudentStatus;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Institution snapshot — the cross-capability KPI block shown on the
 * insights overview. Every figure is a live aggregate over the
 * capability tables the handbook allows insights to project from.
 */
final class InstitutionSnapshotReader
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly PeriodResolver $periods,
    ) {}

    /**
     * @param  array{period?:?string,from?:?string,to?:?string,currency?:?string}  $input
     * @return array{
     *   as_of:string,
     *   period:array{key:string,label:string,from:string,to:string},
     *   headline:list<array<string,mixed>>,
     *   enrollment_trend:list<array{bucket:string,value:int}>,
     *   attendance_trend:list<array{bucket:string,value:float}>,
     *   collections_trend:list<array{bucket:string,value:int}>,
     *   top_cohorts:list<array{label:string,value:float,secondary:string}>,
     * }
     */
    public function read(array $input): array
    {
        $tenantId = $this->tenant->id();
        $win = $this->periods->resolve($input['period'] ?? null, $input['from'] ?? null, $input['to'] ?? null);
        $currency = $input['currency'] ?? (string) config('insights.defaults.currency');

        // Headline KPIs
        $activeStudents = (int) DB::table('students')
            ->where('tenant_id', $tenantId)
            ->where('status', StudentStatus::Enrolled->value)
            ->count();

        $prevActive = (int) DB::table('students')
            ->where('tenant_id', $tenantId)
            ->where('status', StudentStatus::Enrolled->value)
            ->where('enrolled_on', '<=', $win['prev_to']->toDateString())
            ->count();

        [$attRate, $prevAttRate] = $this->attendanceRates($tenantId, $win);
        [$collected, $prevCollected] = $this->collectionsTotals($tenantId, $win, $currency);
        $arrears = (int) DB::table('finance_invoices')
            ->where('tenant_id', $tenantId)
            ->where('currency', $currency)
            ->whereIn('status', [InvoiceStatus::Issued->value, InvoiceStatus::PartiallyPaid->value, InvoiceStatus::Overdue->value])
            ->sum('balance_minor');

        $applicationsOpen = (int) DB::table('applications')
            ->where('tenant_id', $tenantId)
            ->whereNotIn('stage', [PipelineStage::Enrolled->value, PipelineStage::Rejected->value, PipelineStage::Withdrawn->value])
            ->count();

        $atRisk = $this->atRiskCount($tenantId, $win);

        $headline = [
            $this->kpi('Active students', $activeStudents, $prevActive, 'count', tone: 'positive'),
            $this->kpi('Attendance rate', $attRate, $prevAttRate, 'pct', tone: $attRate >= 90 ? 'positive' : 'warning'),
            $this->kpi('Collections', $collected, $prevCollected, 'money', currency: $currency, tone: 'positive'),
            $this->kpi('Arrears outstanding', $arrears, $arrears, 'money', currency: $currency, tone: 'warning'),
            $this->kpi('Applications open', $applicationsOpen, $applicationsOpen, 'count', tone: 'neutral'),
            $this->kpi('At-risk students', $atRisk, $atRisk, 'count', tone: 'warning'),
        ];

        return [
            'as_of' => CarbonImmutable::now()->toIso8601String(),
            'period' => [
                'key' => $win['period']->value,
                'label' => $win['period']->label(),
                'from' => $win['from']->toDateString(),
                'to' => $win['to']->toDateString(),
            ],
            'headline' => $headline,
            'enrollment_trend' => $this->enrollmentTrend($tenantId, $win),
            'attendance_trend' => $this->attendanceTrend($tenantId, $win),
            'collections_trend' => $this->collectionsTrend($tenantId, $win, $currency),
            'top_cohorts' => $this->topCohorts($tenantId, $win),
        ];
    }

    /** @param array{from:CarbonImmutable,to:CarbonImmutable,prev_from:CarbonImmutable,prev_to:CarbonImmutable} $win
     *  @return array{0:float,1:float} */
    private function attendanceRates(string $tenantId, array $win): array
    {
        return [
            $this->attendanceRateBetween($tenantId, $win['from'], $win['to']),
            $this->attendanceRateBetween($tenantId, $win['prev_from'], $win['prev_to']),
        ];
    }

    private function attendanceRateBetween(string $tenantId, CarbonImmutable $from, CarbonImmutable $to): float
    {
        $row = DB::table('attendance_marks as m')
            ->join('attendance_sessions as s', 's.id', '=', 'm.session_id')
            ->where('m.tenant_id', $tenantId)
            ->whereBetween('s.date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('COUNT(*) as total,
                SUM(CASE WHEN m.status IN (?, ?) THEN 1 ELSE 0 END) as present',
                [AttendanceStatus::Present->value, AttendanceStatus::Late->value])
            ->first();

        $total = (int) ($row->total ?? 0);
        $present = (int) ($row->present ?? 0);

        return $total > 0 ? round(($present / $total) * 100, 1) : 0.0;
    }

    /** @param array{from:CarbonImmutable,to:CarbonImmutable,prev_from:CarbonImmutable,prev_to:CarbonImmutable} $win
     *  @return array{0:int,1:int} */
    private function collectionsTotals(string $tenantId, array $win, string $currency): array
    {
        $sum = fn (CarbonImmutable $f, CarbonImmutable $t) => (int) DB::table('finance_payments')
            ->where('tenant_id', $tenantId)
            ->where('currency', $currency)
            ->where('status', PaymentStatus::Succeeded->value)
            ->whereBetween('received_at', [$f->toDateTimeString(), $t->toDateTimeString()])
            ->sum('amount_minor');

        return [$sum($win['from'], $win['to']), $sum($win['prev_from'], $win['prev_to'])];
    }

    private function atRiskCount(string $tenantId, array $win): int
    {
        $rows = DB::table('attendance_marks as m')
            ->join('attendance_sessions as s', 's.id', '=', 'm.session_id')
            ->where('m.tenant_id', $tenantId)
            ->whereBetween('s.date', [$win['from']->toDateString(), $win['to']->toDateString()])
            ->groupBy('m.student_id')
            ->selectRaw('m.student_id,
                COUNT(*) as total,
                SUM(CASE WHEN m.status IN (?, ?) THEN 1 ELSE 0 END) as present',
                [AttendanceStatus::Present->value, AttendanceStatus::Late->value])
            ->get();

        return $rows->filter(function ($r) {
            $t = (int) $r->total;
            $p = (int) $r->present;

            return $t >= 3 && ($p / $t) < 0.80;
        })->count();
    }

    /** @return list<array{bucket:string,value:int}> */
    private function enrollmentTrend(string $tenantId, array $win): array
    {
        $months = $this->monthBuckets($win['from'], $win['to']);
        $out = [];
        foreach ($months as $m) {
            $out[] = [
                'bucket' => $m->format('Y-m'),
                'value' => (int) DB::table('students')
                    ->where('tenant_id', $tenantId)
                    ->where('status', StudentStatus::Enrolled->value)
                    ->where('enrolled_on', '<=', $m->endOfMonth()->toDateString())
                    ->count(),
            ];
        }

        return $out;
    }

    /** @return list<array{bucket:string,value:float}> */
    private function attendanceTrend(string $tenantId, array $win): array
    {
        $weeks = $this->weekBuckets($win['from'], $win['to']);
        $out = [];
        foreach ($weeks as $w) {
            $out[] = [
                'bucket' => 'W'.$w['start']->format('W'),
                'value' => $this->attendanceRateBetween($tenantId, $w['start'], $w['end']),
            ];
        }

        return $out;
    }

    /** @return list<array{bucket:string,value:int}> */
    private function collectionsTrend(string $tenantId, array $win, string $currency): array
    {
        $months = $this->monthBuckets($win['from'], $win['to']);
        $out = [];
        foreach ($months as $m) {
            $out[] = [
                'bucket' => $m->format('Y-m'),
                'value' => (int) DB::table('finance_payments')
                    ->where('tenant_id', $tenantId)
                    ->where('currency', $currency)
                    ->where('status', PaymentStatus::Succeeded->value)
                    ->whereBetween('received_at', [$m->startOfMonth()->toDateTimeString(), $m->endOfMonth()->toDateTimeString()])
                    ->sum('amount_minor'),
            ];
        }

        return $out;
    }

    /** @return list<array{label:string,value:float,secondary:string}> */
    private function topCohorts(string $tenantId, array $win): array
    {
        $rows = DB::table('attendance_marks as m')
            ->join('attendance_sessions as s', 's.id', '=', 'm.session_id')
            ->join('course_sections as c', 'c.id', '=', 's.course_section_id')
            ->where('m.tenant_id', $tenantId)
            ->whereBetween('s.date', [$win['from']->toDateString(), $win['to']->toDateString()])
            ->groupBy('c.id', 'c.grade_label', 'c.section_label')
            ->selectRaw('c.id, c.grade_label, c.section_label,
                COUNT(*) as total,
                SUM(CASE WHEN m.status IN (?, ?) THEN 1 ELSE 0 END) as present',
                [AttendanceStatus::Present->value, AttendanceStatus::Late->value])
            ->get()
            ->map(fn ($r) => [
                'label' => sprintf('%s — %s', $r->grade_label, $r->section_label),
                'value' => (int) $r->total > 0 ? round(((int) $r->present / (int) $r->total) * 100, 1) : 0.0,
                'secondary' => 'attendance %',
            ])
            ->sortByDesc('value')
            ->take(5)
            ->values()
            ->all();

        return $rows;
    }

    /** @return list<CarbonImmutable> */
    private function monthBuckets(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $out = [];
        $cur = $from->startOfMonth();
        while ($cur->lessThanOrEqualTo($to)) {
            $out[] = $cur;
            $cur = $cur->addMonth();
        }

        return $out;
    }

    /** @return list<array{start:CarbonImmutable,end:CarbonImmutable}> */
    private function weekBuckets(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $out = [];
        $cur = $from->startOfWeek();
        while ($cur->lessThanOrEqualTo($to)) {
            $out[] = ['start' => $cur, 'end' => $cur->endOfWeek()];
            $cur = $cur->addWeek();
        }

        return array_slice($out, -8); // last 8 weeks max
    }

    /** @return array<string,mixed> */
    private function kpi(string $label, int|float $value, int|float $prev, string $unit, ?string $currency = null, string $tone = 'neutral'): array
    {
        return [
            'label' => $label,
            'value' => $value,
            'delta_pct' => $this->periods->deltaPct($value, $prev),
            'unit' => $unit,
            'currency' => $currency,
            'tone' => $tone,
        ];
    }
}
