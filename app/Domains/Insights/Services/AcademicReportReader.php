<?php

declare(strict_types=1);

namespace App\Domains\Insights\Services;

use App\Domains\Insights\Support\PeriodResolver;
use App\Enums\AttendanceStatus;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Academic report — attendance and assessment outcomes rolled up by
 * cohort, section, subject, and student.
 */
final class AcademicReportReader
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

        $attRate = $this->rateBetween($tenantId, $win['from'], $win['to']);
        $prevAtt = $this->rateBetween($tenantId, $win['prev_from'], $win['prev_to']);

        $avgScoreRow = DB::table('exam_results as r')
            ->join('exams as e', 'e.id', '=', 'r.exam_id')
            ->where('r.tenant_id', $tenantId)
            ->whereBetween('e.updated_at', [$win['from']->toDateTimeString(), $win['to']->toDateTimeString()])
            ->whereNotNull('r.score')
            ->selectRaw('AVG(r.score / NULLIF(e.max_score,0) * 100) as pct')
            ->first();

        $avgScore = $avgScoreRow && $avgScoreRow->pct !== null ? round((float) $avgScoreRow->pct, 1) : 0.0;

        $reportsPublished = (int) DB::table('exam_periods')
            ->where('tenant_id', $tenantId)
            ->where('status', 'published')
            ->whereBetween('updated_at', [$win['from']->toDateTimeString(), $win['to']->toDateTimeString()])
            ->count();

        $atRisk = $this->atRiskStudents($tenantId, $win);

        $bySection = DB::table('attendance_marks as m')
            ->join('attendance_sessions as s', 's.id', '=', 'm.session_id')
            ->join('course_sections as c', 'c.id', '=', 's.course_section_id')
            ->where('m.tenant_id', $tenantId)
            ->whereBetween('s.date', [$win['from']->toDateString(), $win['to']->toDateString()])
            ->groupBy('c.id', 'c.grade_label', 'c.section_label')
            ->selectRaw('c.grade_label, c.section_label,
                COUNT(*) as total,
                SUM(CASE WHEN m.status IN (?, ?) THEN 1 ELSE 0 END) as present',
                [AttendanceStatus::Present->value, AttendanceStatus::Late->value])
            ->get()
            ->map(fn ($r) => [
                'label' => sprintf('%s — %s', $r->grade_label, $r->section_label),
                'value' => (int) $r->total > 0 ? round(((int) $r->present / (int) $r->total) * 100, 1) : 0.0,
            ])
            ->sortByDesc('value')
            ->take(6)
            ->values()
            ->all();

        $topPerformers = DB::table('exam_results as r')
            ->join('exams as e', 'e.id', '=', 'r.exam_id')
            ->join('students as st', 'st.id', '=', 'r.student_id')
            ->where('r.tenant_id', $tenantId)
            ->whereBetween('e.updated_at', [$win['from']->toDateTimeString(), $win['to']->toDateTimeString()])
            ->whereNotNull('r.score')
            ->groupBy('st.id', 'st.full_name', 'st.grade_label')
            ->selectRaw('st.full_name, st.grade_label,
                AVG(r.score / NULLIF(e.max_score,0) * 100) as pct')
            ->orderByDesc('pct')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'label' => sprintf('%s — %s', $r->full_name, $r->grade_label),
                'value' => round((float) $r->pct, 1),
                'secondary' => 'term avg',
            ])
            ->values()
            ->all();

        $underperforming = DB::table('exam_results as r')
            ->join('exams as e', 'e.id', '=', 'r.exam_id')
            ->join('course_sections as c', 'c.id', '=', 'e.course_section_id')
            ->join('subjects as s', 's.id', '=', 'c.subject_id')
            ->where('r.tenant_id', $tenantId)
            ->whereBetween('e.updated_at', [$win['from']->toDateTimeString(), $win['to']->toDateTimeString()])
            ->whereNotNull('r.score')
            ->groupBy('s.id', 's.name', 'c.grade_label')
            ->selectRaw('s.name, c.grade_label,
                AVG(r.score / NULLIF(e.max_score,0) * 100) as pct')
            // Postgres does not allow select aliases in HAVING — repeat the expression.
            ->havingRaw('AVG(r.score / NULLIF(e.max_score,0) * 100) < 65')
            ->orderByRaw('AVG(r.score / NULLIF(e.max_score,0) * 100)')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'label' => sprintf('%s — %s', $r->name, $r->grade_label),
                'value' => round((float) $r->pct, 1),
                'secondary' => 'avg score',
            ])
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
                $this->kpi('Attendance rate', $attRate, $prevAtt, 'pct', $attRate >= 90 ? 'positive' : 'warning'),
                $this->kpi('Average score', $avgScore, $avgScore, 'pct', $avgScore >= 70 ? 'positive' : 'warning'),
                $this->kpi('Reports published', $reportsPublished, $reportsPublished, 'count', 'positive'),
                $this->kpi('At-risk students', count($atRisk), count($atRisk), 'count', 'warning'),
            ],
            'attendance_by_section' => $bySection,
            'at_risk_students' => $atRisk,
            'top_performers' => $topPerformers,
            'underperforming_subjects' => $underperforming,
        ];
    }

    private function rateBetween(string $tenantId, CarbonImmutable $from, CarbonImmutable $to): float
    {
        $row = DB::table('attendance_marks as m')
            ->join('attendance_sessions as s', 's.id', '=', 'm.session_id')
            ->where('m.tenant_id', $tenantId)
            ->whereBetween('s.date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('COUNT(*) as total,
                SUM(CASE WHEN m.status IN (?, ?) THEN 1 ELSE 0 END) as present',
                [AttendanceStatus::Present->value, AttendanceStatus::Late->value])
            ->first();
        $t = (int) ($row->total ?? 0);
        $p = (int) ($row->present ?? 0);

        return $t > 0 ? round(($p / $t) * 100, 1) : 0.0;
    }

    /** @return list<array{label:string,value:float,secondary:string}> */
    private function atRiskStudents(string $tenantId, array $win): array
    {
        return DB::table('attendance_marks as m')
            ->join('attendance_sessions as s', 's.id', '=', 'm.session_id')
            ->join('students as st', 'st.id', '=', 'm.student_id')
            ->where('m.tenant_id', $tenantId)
            ->whereBetween('s.date', [$win['from']->toDateString(), $win['to']->toDateString()])
            ->groupBy('st.id', 'st.full_name', 'st.grade_label')
            ->selectRaw('st.full_name, st.grade_label,
                COUNT(*) as total,
                SUM(CASE WHEN m.status IN (?, ?) THEN 1 ELSE 0 END) as present',
                [AttendanceStatus::Present->value, AttendanceStatus::Late->value])
            ->havingRaw('COUNT(*) >= 3')
            ->get()
            ->map(fn ($r) => [
                'name' => (string) $r->full_name,
                'grade' => (string) $r->grade_label,
                'rate' => (int) $r->total > 0 ? round(((int) $r->present / (int) $r->total) * 100, 1) : 0.0,
            ])
            ->filter(fn ($r) => $r['rate'] < 80)
            ->sortBy('rate')
            ->take(6)
            ->map(fn ($r) => [
                'label' => sprintf('%s — %s', $r['name'], $r['grade']),
                'value' => $r['rate'],
                'secondary' => 'attendance %',
            ])
            ->values()
            ->all();
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
