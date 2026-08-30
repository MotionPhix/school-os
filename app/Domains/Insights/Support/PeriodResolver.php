<?php

declare(strict_types=1);

namespace App\Domains\Insights\Support;

use App\Enums\InsightPeriod;
use Carbon\CarbonImmutable;

/**
 * Resolves the `period` + optional `from`/`to` inputs shared by every
 * insights endpoint into a concrete, inclusive date window plus the
 * previous-period window used for delta % calculations.
 */
final class PeriodResolver
{
    /**
     * @return array{
     *   period:InsightPeriod,
     *   from:CarbonImmutable, to:CarbonImmutable,
     *   prev_from:CarbonImmutable, prev_to:CarbonImmutable,
     * }
     */
    public function resolve(?string $period, ?string $from, ?string $to): array
    {
        $enum = InsightPeriod::tryFrom((string) $period) ?? InsightPeriod::Last30d;

        if ($enum === InsightPeriod::Custom && $from && $to) {
            $fromDt = CarbonImmutable::parse($from)->startOfDay();
            $toDt = CarbonImmutable::parse($to)->endOfDay();
        } else {
            $win = $enum->window();
            $fromDt = $win['from'];
            $toDt = $win['to'];
        }

        $span = max(1, $fromDt->diffInDays($toDt) + 1);
        $prevTo = $fromDt->subDay()->endOfDay();
        $prevFrom = $prevTo->subDays($span - 1)->startOfDay();

        return [
            'period' => $enum,
            'from' => $fromDt,
            'to' => $toDt,
            'prev_from' => $prevFrom,
            'prev_to' => $prevTo,
        ];
    }

    public function deltaPct(int|float $current, int|float $previous): ?float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
